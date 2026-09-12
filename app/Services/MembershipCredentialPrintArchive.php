<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MembershipCredential;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;
use ZipArchive;

final class MembershipCredentialPrintArchive
{
    public function __construct(
        private readonly MembershipCredentialRegistry $credentials,
    ) {}

    public function create(MembershipCredential $credential): string
    {
        if (! class_exists(ZipArchive::class) || ! $this->credentials->verifyCredential($credential)) {
            throw new RuntimeException('MEMBERSHIP_CARD_IMAGE_ARCHIVE_UNAVAILABLE');
        }

        $pdfPath = $this->credentials->downloadPath($credential, 'card');
        $temporary = storage_path('app/private/memberships/image-temp');
        $archives = storage_path('app/private/memberships/print-archives');
        $this->ensurePrivateDirectory($temporary);
        $this->ensurePrivateDirectory($archives);

        $identifier = (string) Str::uuid();
        $basePath = $temporary.'/'.$identifier;
        $frontPath = $basePath.'-1.png';
        $backPath = $basePath.'-2.png';
        $archivePath = $archives.'/'.$this->artifactKey($credential, 'card').'.zip';
        $number = $this->safeName((string) $credential->membership_number, 'membership-card');
        $version = max(1, (int) $credential->version);

        if (is_file($archivePath) && ! is_link($archivePath) && filesize($archivePath) > 0) {
            return $archivePath;
        }

        try {
            $this->renderPages($pdfPath, $basePath, $frontPath, $backPath);

            $this->validateCardPng($frontPath);
            $this->validateCardPng($backPath);
            if (is_file($basePath.'-3.png')) {
                throw new RuntimeException('MEMBERSHIP_CREDENTIAL_IMAGE_PAGE_COUNT_INVALID');
            }

            $archive = new ZipArchive();
            if ($archive->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('MEMBERSHIP_CARD_IMAGE_ARCHIVE_UNAVAILABLE');
            }

            $open = true;
            try {
                $prefix = $number.'-v'.$version;
                if (! $archive->addFile($frontPath, $prefix.'-front-300dpi.png')
                    || ! $archive->addFile($backPath, $prefix.'-back-300dpi.png')
                    || ! $archive->close()) {
                    throw new RuntimeException('MEMBERSHIP_CARD_IMAGE_ARCHIVE_FAILED');
                }
                $open = false;
            } finally {
                if ($open) {
                    $archive->close();
                }
            }

            if (! is_file($archivePath) || filesize($archivePath) < 1) {
                throw new RuntimeException('MEMBERSHIP_CARD_IMAGE_ARCHIVE_FAILED');
            }
            @chmod($archivePath, 0600);

            return $archivePath;
        } catch (Throwable $error) {
            if (is_file($archivePath)) {
                @unlink($archivePath);
            }
            throw $error;
        } finally {
            foreach ([$frontPath, $backPath, $basePath.'-3.png'] as $temporaryFile) {
                if (is_file($temporaryFile)) {
                    @unlink($temporaryFile);
                }
            }
        }
    }

    public function createCertificateImage(MembershipCredential $credential): string
    {
        if (! $this->credentials->verifyCredential($credential)) {
            throw new RuntimeException('MEMBERSHIP_CERTIFICATE_IMAGE_UNAVAILABLE');
        }

        $pdfPath = $this->credentials->downloadPath($credential, 'certificate');
        $temporary = storage_path('app/private/memberships/image-temp');
        $images = storage_path('app/private/memberships/print-images');
        $this->ensurePrivateDirectory($temporary);
        $this->ensurePrivateDirectory($images);

        $identifier = (string) Str::uuid();
        $basePath = $temporary.'/'.$identifier;
        $temporaryPath = $basePath.'-1.png';
        $outputPath = $images.'/'.$this->artifactKey($credential, 'certificate').'.png';

        if (is_file($outputPath) && ! is_link($outputPath)) {
            $this->validateCertificatePng($outputPath);

            return $outputPath;
        }

        try {
            $this->renderDocument($pdfPath, $basePath, [$temporaryPath]);
            $this->validateCertificatePng($temporaryPath);
            if (! @rename($temporaryPath, $outputPath)) {
                throw new RuntimeException('MEMBERSHIP_CERTIFICATE_IMAGE_WRITE_FAILED');
            }
            @chmod($outputPath, 0600);

            return $outputPath;
        } catch (Throwable $error) {
            foreach ([$temporaryPath, $outputPath] as $path) {
                if (is_file($path)) {
                    @unlink($path);
                }
            }
            throw $error;
        }
    }

    private function renderPages(string $pdfPath, string $basePath, string $frontPath, string $backPath): void
    {
        $this->renderDocument($pdfPath, $basePath, [$frontPath, $backPath]);
    }

    /** @param array<int, string> $outputPaths */
    private function renderDocument(string $pdfPath, string $basePath, array $outputPaths): void
    {
        if (class_exists(\Imagick::class)) {
            try {
                $this->renderWithImagick($pdfPath, $outputPaths);

                return;
            } catch (Throwable $error) {
                report($error);

                if (! function_exists('proc_open')) {
                    throw new RuntimeException('MEMBERSHIP_CARD_IMAGE_RENDER_FAILED', 0, $error);
                }
            }
        }

        $this->renderWithPoppler($pdfPath, $basePath, count($outputPaths));
    }

    /** @param array<int, string> $outputPaths */
    private function renderWithImagick(string $pdfPath, array $outputPaths): void
    {
        $document = new \Imagick();

        try {
            $document->setResolution(300, 300);
            $document->readImage($pdfPath);
            if ($document->getNumberImages() !== count($outputPaths)) {
                throw new RuntimeException('MEMBERSHIP_CREDENTIAL_IMAGE_PAGE_COUNT_INVALID');
            }

            foreach ($outputPaths as $pageNumber => $outputPath) {
                $document->setIteratorIndex($pageNumber);
                $page = $document->getImage();

                try {
                    $page->setImageBackgroundColor('white');
                    $page->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
                    $page->setImageFormat('png');
                    $page->stripImage();
                    if (! $page->writeImage($outputPath)) {
                        throw new RuntimeException('MEMBERSHIP_CARD_IMAGE_RENDER_FAILED');
                    }
                } finally {
                    $page->clear();
                    $page->destroy();
                }
            }
        } finally {
            $document->clear();
            $document->destroy();
        }
    }

    private function renderWithPoppler(string $pdfPath, string $basePath, int $lastPage): void
    {
        if (! function_exists('proc_open')) {
            throw new RuntimeException('MEMBERSHIP_CARD_IMAGE_CONVERTER_UNAVAILABLE');
        }

        $converter = collect(['/usr/bin/pdftocairo', '/usr/local/bin/pdftocairo'])
            ->first(fn (string $candidate): bool => is_file($candidate) && is_executable($candidate));
        if (! is_string($converter)) {
            throw new RuntimeException('MEMBERSHIP_CARD_IMAGE_CONVERTER_UNAVAILABLE');
        }

        $process = new Process([
            $converter,
            '-png',
            '-r',
            '300',
            '-f',
            '1',
            '-l',
            (string) $lastPage,
            $pdfPath,
            $basePath,
        ], null, null, null, 60);
        $process->setIdleTimeout(25);
        $process->mustRun();
    }

    private function validateCardPng(string $path): void
    {
        if (! is_file($path) || is_link($path) || filesize($path) > 20_000_000) {
            throw new RuntimeException('MEMBERSHIP_CARD_IMAGE_RENDER_FAILED');
        }

        $dimensions = @getimagesize($path);
        if (! is_array($dimensions)
            || ($dimensions['mime'] ?? null) !== 'image/png'
            || $dimensions[0] < 1008
            || $dimensions[0] > 1013
            || $dimensions[1] < 635
            || $dimensions[1] > 640) {
            throw new RuntimeException('MEMBERSHIP_CARD_IMAGE_DIMENSIONS_INVALID');
        }
    }

    private function validateCertificatePng(string $path): void
    {
        if (! is_file($path) || is_link($path) || filesize($path) > 40_000_000) {
            throw new RuntimeException('MEMBERSHIP_CERTIFICATE_IMAGE_RENDER_FAILED');
        }

        $dimensions = @getimagesize($path);
        if (! is_array($dimensions)
            || ($dimensions['mime'] ?? null) !== 'image/png'
            || $dimensions[0] < 3504
            || $dimensions[0] > 3512
            || $dimensions[1] < 2476
            || $dimensions[1] > 2484) {
            throw new RuntimeException('MEMBERSHIP_CERTIFICATE_IMAGE_DIMENSIONS_INVALID');
        }
    }

    private function safeName(string $value, string $fallback): string
    {
        $value = preg_replace('/[^A-Za-z0-9_-]+/', '-', trim($value)) ?? '';
        $value = trim($value, '-_');

        return $value === '' ? $fallback : mb_substr($value, 0, 100);
    }

    private function artifactKey(MembershipCredential $credential, string $kind): string
    {
        $hash = $kind === 'card'
            ? (string) $credential->card_pdf_sha256
            : (string) $credential->certificate_pdf_sha256;

        if (! preg_match('/\A[a-f0-9]{64}\z/', $hash)) {
            throw new RuntimeException('MEMBERSHIP_CREDENTIAL_ARTIFACT_HASH_INVALID');
        }

        return 'credential-'.(int) $credential->id.'-v'.max(1, (int) $credential->version).'-'.$hash;
    }

    private function ensurePrivateDirectory(string $directory): void
    {
        if (is_link($directory)
            || (! is_dir($directory) && ! @mkdir($directory, 0700, true) && ! is_dir($directory))
            || realpath($directory) !== $directory
            || (fileperms($directory) & 0077) !== 0) {
            throw new RuntimeException('MEMBERSHIP_CARD_IMAGE_TEMP_UNAVAILABLE');
        }
    }
}
