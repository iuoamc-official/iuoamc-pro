<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

final class ProCertificateImage
{
    /**
     * @return array{bytes: string, mime: string, extension: string, width: int, height: int}
     */
    public function render(string $pdfPath, string $variant): array
    {
        $configuration = $this->configuration($variant);

        if (! is_file($pdfPath) || is_link($pdfPath)) {
            throw new RuntimeException('CERTIFICATE_IMAGE_SOURCE_UNAVAILABLE');
        }

        $bytes = class_exists(\Imagick::class)
            ? $this->renderWithImagick($pdfPath, $configuration)
            : $this->renderWithPoppler($pdfPath, $configuration);

        $dimensions = @getimagesizefromstring($bytes);
        if (! is_array($dimensions)
            || $dimensions[0] < 1000
            || $dimensions[1] < 700
            || $dimensions[0] * $dimensions[1] > 15_000_000
            || strlen($bytes) > 40_000_000) {
            throw new RuntimeException('CERTIFICATE_IMAGE_RENDER_FAILED');
        }

        return [
            'bytes' => $bytes,
            'mime' => $configuration['mime'],
            'extension' => $configuration['extension'],
            'width' => $dimensions[0],
            'height' => $dimensions[1],
        ];
    }

    /** @return array{dpi: int, format: string, mime: string, extension: string, quality: int, max_width: int|null} */
    private function configuration(string $variant): array
    {
        return match ($variant) {
            'print' => [
                'dpi' => 300,
                'format' => 'png',
                'mime' => 'image/png',
                'extension' => 'png',
                'quality' => 95,
                'max_width' => null,
            ],
            'share' => [
                'dpi' => 160,
                'format' => 'jpeg',
                'mime' => 'image/jpeg',
                'extension' => 'jpg',
                'quality' => 88,
                'max_width' => 2000,
            ],
            default => throw new RuntimeException('CERTIFICATE_IMAGE_VARIANT_UNSUPPORTED'),
        };
    }

    /** @param array{dpi: int, format: string, mime: string, extension: string, quality: int, max_width: int|null} $configuration */
    private function renderWithImagick(string $pdfPath, array $configuration): string
    {
        try {
            $document = new \Imagick();
            $document->setResolution($configuration['dpi'], $configuration['dpi']);
            $document->readImage($pdfPath.'[0]');
            $document->setIteratorIndex(0);
            $document->setImageBackgroundColor('white');
            $image = $document->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);

            if ($configuration['max_width'] !== null && $image->getImageWidth() > $configuration['max_width']) {
                $image->thumbnailImage($configuration['max_width'], 0);
            }

            $image->setImageFormat($configuration['format']);
            $image->setImageCompressionQuality($configuration['quality']);
            $image->stripImage();
            $bytes = $image->getImagesBlob();
            $image->clear();
            $document->clear();
        } catch (Throwable $error) {
            report($error);

            return $this->renderWithPoppler($pdfPath, $configuration);
        }

        if (! is_string($bytes) || $bytes === '') {
            throw new RuntimeException('CERTIFICATE_IMAGE_RENDER_FAILED');
        }

        return $bytes;
    }

    /** @param array{dpi: int, format: string, mime: string, extension: string, quality: int, max_width: int|null} $configuration */
    private function renderWithPoppler(string $pdfPath, array $configuration): string
    {
        $binary = collect(['/usr/bin/pdftocairo', '/usr/local/bin/pdftocairo'])
            ->first(fn (string $candidate): bool => is_file($candidate) && is_executable($candidate));
        if (! is_string($binary)) {
            throw new RuntimeException('CERTIFICATE_IMAGE_CONVERTER_UNAVAILABLE');
        }

        $directory = storage_path('app/private/pro-certificates/image-temp');
        $this->ensurePrivateDirectory($directory);
        $basePath = $directory.'/'.Str::uuid();
        $outputPath = $basePath.'.'.$configuration['extension'];
        $command = [
            $binary,
            '-f',
            '1',
            '-l',
            '1',
            '-singlefile',
            '-r',
            (string) $configuration['dpi'],
        ];
        if ($configuration['format'] === 'png') {
            $command[] = '-png';
        } else {
            array_push($command, '-jpeg', '-jpegopt', 'quality='.$configuration['quality']);
        }
        array_push($command, $pdfPath, $basePath);

        try {
            $process = new Process($command, null, null, null, 45);
            $process->setIdleTimeout(20);
            $process->mustRun();
            $bytes = file_get_contents($outputPath);
        } catch (Throwable $error) {
            report($error);
            throw new RuntimeException('CERTIFICATE_IMAGE_RENDER_FAILED', 0, $error);
        } finally {
            if (is_file($outputPath)) {
                @unlink($outputPath);
            }
        }

        if (! is_string($bytes) || $bytes === '') {
            throw new RuntimeException('CERTIFICATE_IMAGE_RENDER_FAILED');
        }

        return $bytes;
    }

    private function ensurePrivateDirectory(string $directory): void
    {
        if (is_link($directory)
            || (! is_dir($directory) && ! @mkdir($directory, 0700, true) && ! is_dir($directory))
            || realpath($directory) !== $directory
            || (fileperms($directory) & 0077) !== 0) {
            throw new RuntimeException('CERTIFICATE_IMAGE_TEMP_UNAVAILABLE');
        }
    }
}
