<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ProCertificate;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

final class ProCertificateCollectionPrintArchive
{
    public function __construct(
        private readonly ProCertificateImage $images,
        private readonly ProCertificateRegistry $registry,
    ) {}

    /**
     * @param Collection<int, ProCertificate> $certificates
     */
    public function create(Collection $certificates, string $folder): string
    {
        if (! class_exists(ZipArchive::class)
            || $certificates->isEmpty()
            || $certificates->count() > 100) {
            throw new RuntimeException('CERTIFICATE_COLLECTION_ARCHIVE_UNAVAILABLE');
        }

        $folder = $this->safeName($folder, 'certificate-collection-300dpi');
        $directory = storage_path('app/private/pro-certificates/collection-archives');
        $this->ensurePrivateDirectory($directory);
        $path = $directory.'/'.Str::uuid().'.zip';
        $archive = new ZipArchive();
        $opened = $archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($opened !== true) {
            throw new RuntimeException('CERTIFICATE_COLLECTION_ARCHIVE_UNAVAILABLE');
        }

        $open = true;

        try {
            if (! $archive->addEmptyDir($folder)) {
                throw new RuntimeException('CERTIFICATE_COLLECTION_ARCHIVE_FAILED');
            }

            $used = [];
            foreach ($certificates as $certificate) {
                if (! $certificate instanceof ProCertificate
                    || ! $this->registry->verify($certificate)
                    || $certificate->status !== 'issued') {
                    throw new RuntimeException('CERTIFICATE_COLLECTION_ARCHIVE_RECORD_INVALID');
                }

                $number = $this->safeName(
                    (string) $certificate->certificate_number,
                    'certificate-'.$certificate->id
                );
                $filename = $number.'-300dpi.png';
                if (isset($used[$filename])) {
                    $filename = $number.'-'.$certificate->id.'-300dpi.png';
                }
                $used[$filename] = true;

                $pdf = $this->registry->downloadPath($certificate);
                $image = $this->images->render($pdf, 'print');
                if ($image['extension'] !== 'png'
                    || $image['mime'] !== 'image/png'
                    || ! $archive->addFromString($folder.'/'.$filename, $image['bytes'])) {
                    throw new RuntimeException('CERTIFICATE_COLLECTION_ARCHIVE_FAILED');
                }
            }

            if (! $archive->close()) {
                throw new RuntimeException('CERTIFICATE_COLLECTION_ARCHIVE_FAILED');
            }
            $open = false;

            if (! is_file($path) || filesize($path) < 1) {
                throw new RuntimeException('CERTIFICATE_COLLECTION_ARCHIVE_FAILED');
            }
        } catch (Throwable $error) {
            if ($open) {
                try {
                    $archive->close();
                } catch (Throwable) {
                    // The temporary file is removed below.
                }
            }
            if (is_file($path)) {
                @unlink($path);
            }
            throw $error;
        }

        @chmod($path, 0600);

        return $path;
    }

    private function safeName(string $value, string $fallback): string
    {
        $value = preg_replace('/[^A-Za-z0-9_-]+/', '-', trim($value)) ?? '';
        $value = trim($value, '-_');

        return $value === '' ? $fallback : mb_substr($value, 0, 120);
    }

    private function ensurePrivateDirectory(string $directory): void
    {
        if (is_link($directory)
            || (! is_dir($directory) && ! @mkdir($directory, 0700, true) && ! is_dir($directory))
            || realpath($directory) !== $directory
            || (fileperms($directory) & 0077) !== 0) {
            throw new RuntimeException('CERTIFICATE_COLLECTION_ARCHIVE_TEMP_UNAVAILABLE');
        }
    }
}
