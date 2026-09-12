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

        $converter = collect(['/usr/bin/pdftocairo', '/usr/local/bin/pdftocairo'])
            ->first(fn (string $candidate): bool => is_file($candidate) && is_executable($candidate));
        if (! is_string($converter)) {
            throw new RuntimeException('MEMBERSHIP_CARD_IMAGE_CONVERTER_UNAVAILABLE');
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
        $archivePath = $archives.'/'.$identifier.'.zip';
        $number = $this->safeName((string) $credential->membership_number, 'membership-card');
        $version = max(1, (int) $credential->version);

        try {
            $process = new Process([
                $converter,
                '-png',
                '-r',
                '300',
                '-f',
                '1',
                '-l',
                '2',
                $pdfPath,
                $basePath,
            ], null, null, null, 60);
            $process->setIdleTimeout(25);
            $process->mustRun();

            $this->validatePng($frontPath);
            $this->validatePng($backPath);
            if (is_file($basePath.'-3.png')) {
                throw new RuntimeException('MEMBERSHIP_CARD_IMAGE_PAGE_COUNT_INVALID');
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

    private function validatePng(string $path): void
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

    private function safeName(string $value, string $fallback): string
    {
        $value = preg_replace('/[^A-Za-z0-9_-]+/', '-', trim($value)) ?? '';
        $value = trim($value, '-_');

        return $value === '' ? $fallback : mb_substr($value, 0, 100);
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
