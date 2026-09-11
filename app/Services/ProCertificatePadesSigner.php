<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Process;
use RuntimeException;
use Throwable;

final class ProCertificatePadesSigner
{
    /** @return array{ready: bool, reason: string|null} */
    public function readiness(): array
    {
        try {
            $this->configuration();

            return ['ready' => true, 'reason' => null];
        } catch (Throwable $error) {
            return ['ready' => false, 'reason' => $error->getMessage()];
        }
    }

    /**
     * @return array{bytes: string, profile: string, status: string, field: string, certificate_sha256: string, signed_at: string}
     */
    public function sign(string $unsignedPdf): array
    {
        if (! str_starts_with($unsignedPdf, '%PDF-')) {
            throw new RuntimeException('PADES_INPUT_INVALID');
        }

        $configuration = $this->configuration();
        $directory = $this->temporaryDirectory();
        $nonce = bin2hex(random_bytes(16));
        $input = $directory.'/'.$nonce.'-unsigned.pdf';
        $output = $directory.'/'.$nonce.'-signed.pdf';

        try {
            $this->writePrivateFile($input, $unsignedPdf);
            $command = [
                $configuration['binary'], 'sign', 'addsig',
                '--field', $configuration['field'], '--use-pades',
            ];
            if ($configuration['timestamp_url'] !== null) {
                $command[] = '--timestamp-url';
                $command[] = $configuration['timestamp_url'];
            }
            array_push(
                $command,
                'pkcs12', '--passfile', $configuration['passphrase_file'],
                $input, $output, $configuration['pkcs12_path'],
            );

            $result = Process::timeout($configuration['timeout'])->run($command);
            if (! $result->successful() || ! is_file($output) || is_link($output)) {
                throw new RuntimeException('PADES_SIGNING_FAILED');
            }

            $bytes = file_get_contents($output);
            if (! is_string($bytes) || ! str_starts_with($bytes, '%PDF-') || strlen($bytes) <= strlen($unsignedPdf)) {
                throw new RuntimeException('PADES_OUTPUT_INVALID');
            }

            $validation = [$configuration['binary'], 'sign', 'validate', '--pretty-print'];
            if ($configuration['trust_root_path'] !== null) {
                array_push($validation, '--trust-replace', '--trust', $configuration['trust_root_path']);
            }
            $validation[] = $output;
            if (! Process::timeout($configuration['timeout'])->run($validation)->successful()) {
                throw new RuntimeException('PADES_VALIDATION_FAILED');
            }

            return [
                'bytes' => $bytes,
                'profile' => $configuration['profile'],
                'status' => 'valid',
                'field' => $this->fieldName($configuration['field']),
                'certificate_sha256' => $this->certificateFingerprint(
                    $configuration['pkcs12_path'],
                    $configuration['passphrase_file'],
                ),
                'signed_at' => now()->utc()->startOfSecond()->format('Y-m-d\TH:i:sP'),
            ];
        } finally {
            foreach ([$input, $output] as $path) {
                if (is_file($path) && ! is_link($path)) {
                    unlink($path);
                }
            }
        }
    }

    /** @return array{binary: string, pkcs12_path: string, passphrase_file: string, trust_root_path: string|null, timestamp_url: string|null, profile: string, field: string, timeout: int} */
    private function configuration(): array
    {
        if (config('certificates.pades.enabled') !== true) {
            throw new RuntimeException('PADES_NOT_ENABLED');
        }

        $binary = $this->safeFile((string) config('certificates.pades.binary'), true, false, 'BINARY');
        $pkcs12 = $this->safeFile((string) config('certificates.pades.pkcs12_path'), false, true, 'PKCS12');
        $passphrase = $this->safeFile((string) config('certificates.pades.passphrase_file'), false, true, 'PASSPHRASE');
        $trust = config('certificates.pades.trust_root_path');
        $trust = is_string($trust) && trim($trust) !== ''
            ? $this->safeFile($trust, false, false, 'TRUST_ROOT')
            : null;
        $profile = (string) config('certificates.pades.profile');
        if (! in_array($profile, ['PAdES-B-B', 'PAdES-B-T'], true)) {
            throw new RuntimeException('PADES_PROFILE_INVALID');
        }
        $timestamp = config('certificates.pades.timestamp_url');
        $timestamp = is_string($timestamp) && trim($timestamp) !== '' ? trim($timestamp) : null;
        if ($profile === 'PAdES-B-T' && ($timestamp === null
            || filter_var($timestamp, FILTER_VALIDATE_URL) === false
            || parse_url($timestamp, PHP_URL_SCHEME) !== 'https')) {
            throw new RuntimeException('PADES_TIMESTAMP_REQUIRED');
        }
        $field = (string) config('certificates.pades.signature_field');
        if (preg_match('/\A1\/[0-9]{1,3},[0-9]{1,3},[0-9]{1,3},[0-9]{1,3}\/[A-Za-z][A-Za-z0-9_]{2,63}\z/D', $field) !== 1) {
            throw new RuntimeException('PADES_SIGNATURE_FIELD_INVALID');
        }
        $timeout = (int) config('certificates.pades.timeout_seconds');
        if ($timeout < 15 || $timeout > 300) {
            throw new RuntimeException('PADES_TIMEOUT_INVALID');
        }

        return [
            'binary' => $binary,
            'pkcs12_path' => $pkcs12,
            'passphrase_file' => $passphrase,
            'trust_root_path' => $trust,
            'timestamp_url' => $timestamp,
            'profile' => $profile,
            'field' => $field,
            'timeout' => $timeout,
        ];
    }

    private function safeFile(string $path, bool $executable, bool $private, string $label): string
    {
        if ($path === '' || ! str_starts_with($path, '/') || is_link($path) || ! is_file($path)) {
            throw new RuntimeException('PADES_'.$label.'_UNAVAILABLE');
        }
        $real = realpath($path);
        if ($real === false || $real !== $path || ! is_readable($real) || ($executable && ! is_executable($real))) {
            throw new RuntimeException('PADES_'.$label.'_UNAVAILABLE');
        }
        if ($private && (fileperms($real) & 0077) !== 0) {
            throw new RuntimeException('PADES_SECRET_PERMISSIONS_INVALID');
        }

        return $real;
    }

    private function temporaryDirectory(): string
    {
        $directory = storage_path('app/private/pro-certificates/pades-temp');
        if (is_link($directory) || (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory))) {
            throw new RuntimeException('PADES_TEMP_UNAVAILABLE');
        }
        $real = realpath($directory);
        if ($real !== $directory || (fileperms($directory) & 0077) !== 0) {
            throw new RuntimeException('PADES_TEMP_NOT_PRIVATE');
        }

        return $directory;
    }

    private function writePrivateFile(string $path, string $bytes): void
    {
        $stream = fopen($path, 'xb');
        if ($stream === false) {
            throw new RuntimeException('PADES_TEMP_WRITE_FAILED');
        }
        try {
            if (! chmod($path, 0600)) {
                throw new RuntimeException('PADES_TEMP_WRITE_FAILED');
            }
            $length = strlen($bytes);
            $written = 0;
            while ($written < $length) {
                $chunk = fwrite($stream, substr($bytes, $written));
                if ($chunk === false || $chunk === 0) {
                    throw new RuntimeException('PADES_TEMP_WRITE_FAILED');
                }
                $written += $chunk;
            }
            if (! fflush($stream) || ! fsync($stream)) {
                throw new RuntimeException('PADES_TEMP_WRITE_FAILED');
            }
        } finally {
            fclose($stream);
        }
    }

    private function certificateFingerprint(string $pkcs12Path, string $passphrasePath): string
    {
        $archive = file_get_contents($pkcs12Path);
        $passphrase = file_get_contents($passphrasePath);
        $certificates = [];
        if (! is_string($archive) || ! is_string($passphrase)
            || ! openssl_pkcs12_read($archive, $certificates, rtrim($passphrase, "\r\n"))
            || ! is_string($certificates['cert'] ?? null)) {
            throw new RuntimeException('PADES_CERTIFICATE_UNREADABLE');
        }
        $fingerprint = openssl_x509_fingerprint($certificates['cert'], 'sha256');
        if (! is_string($fingerprint)) {
            throw new RuntimeException('PADES_CERTIFICATE_FINGERPRINT_FAILED');
        }
        $fingerprint = strtolower(str_replace(':', '', $fingerprint));
        if (preg_match('/\A[0-9a-f]{64}\z/D', $fingerprint) !== 1) {
            throw new RuntimeException('PADES_CERTIFICATE_FINGERPRINT_FAILED');
        }

        return $fingerprint;
    }

    private function fieldName(string $field): string
    {
        return substr($field, (int) strrpos($field, '/') + 1);
    }
}
