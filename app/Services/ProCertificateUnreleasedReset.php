<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ProCertificate;
use App\Models\ProCertificateStudent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class ProCertificateUnreleasedReset
{
    private const RESET_TABLES = [
        'pro_certificate_batch_runs',
        'pro_certificate_batches',
        'pro_certificate_intake_responses',
        'pro_certificate_intakes',
        'pro_certificate_intake_programs',
        'pro_certificates',
        'pro_certificate_type_sequences',
        'pro_certificate_types',
        'pro_certificate_sequences',
    ];

    /**
     * @return array{
     *   certificates:int,
     *   issued:int,
     *   students:int,
     *   pdfs:int,
     *   table_counts:array<string,int>
     * }
     */
    public function inventory(): array
    {
        $tableCounts = [];

        foreach (self::RESET_TABLES as $table) {
            $tableCounts[$table] = DB::table($table)->count();
        }

        return [
            'certificates' => $tableCounts['pro_certificates'],
            'issued' => DB::table('pro_certificates')->where('status', 'issued')->count(),
            'students' => ProCertificateStudent::query()->count(),
            'pdfs' => DB::table('pro_certificates')->whereNotNull('pdf_path')->count(),
            'table_counts' => $tableCounts,
        ];
    }

    /**
     * Quarantine PDFs and remove certificate-domain rows while keeping students,
     * organizations, users, and immutable audit evidence.
     *
     * @return array{
     *   already_reset:bool,
     *   reset_id:?string,
     *   removed_certificates:int,
     *   quarantined_pdfs:int,
     *   quarantine_path:?string
     * }
     */
    public function reset(
        int $expectedCertificates,
        int $expectedStudents,
        string $backupReference
    ): array {
        if (! app()->isDownForMaintenance()) {
            throw new RuntimeException('APPLICATION_MUST_BE_IN_MAINTENANCE_MODE');
        }

        $backupReference = trim($backupReference);

        if ($backupReference === '' || mb_strlen($backupReference) > 255) {
            throw new RuntimeException('BACKUP_REFERENCE_INVALID');
        }

        $inventory = $this->inventory();

        if ($inventory['certificates'] === 0 && $inventory['students'] === $expectedStudents) {
            return [
                'already_reset' => true,
                'reset_id' => null,
                'removed_certificates' => 0,
                'quarantined_pdfs' => 0,
                'quarantine_path' => null,
            ];
        }

        if ($inventory['certificates'] !== $expectedCertificates
            || $inventory['issued'] !== $expectedCertificates
            || $inventory['pdfs'] !== $expectedCertificates
            || $inventory['students'] !== $expectedStudents) {
            throw new RuntimeException(sprintf(
                'RESET_PREFLIGHT_MISMATCH certificates=%d issued=%d pdfs=%d students=%d',
                $inventory['certificates'],
                $inventory['issued'],
                $inventory['pdfs'],
                $inventory['students']
            ));
        }

        $records = ProCertificate::query()
            ->select(['id', 'record_uuid', 'pdf_path', 'pdf_sha256'])
            ->orderBy('id')
            ->get();

        $files = [];

        foreach ($records as $record) {
            $relative = (string) $record->pdf_path;
            $source = $this->certificatePath($relative);
            $actualHash = hash_file('sha256', $source);

            if (! is_string($actualHash)
                || ! is_string($record->pdf_sha256)
                || ! hash_equals($record->pdf_sha256, $actualHash)) {
                throw new RuntimeException('CERTIFICATE_PDF_INTEGRITY_FAILED id='.$record->id);
            }

            $files[] = [
                'record_id' => (int) $record->id,
                'record_uuid' => (string) $record->record_uuid,
                'source' => $source,
                'relative' => $relative,
                'sha256' => $actualHash,
            ];
        }

        $resetId = (string) Str::uuid();
        $quarantine = $this->quarantineDirectory($resetId);
        $manifest = [
            'schema' => 'iuoamc-unreleased-certificate-reset-v1',
            'reset_id' => $resetId,
            'backup_reference' => $backupReference,
            'preserved_students' => $expectedStudents,
            'certificate_count' => $expectedCertificates,
            'files' => array_map(
                static fn (array $file): array => [
                    'record_id' => $file['record_id'],
                    'record_uuid' => $file['record_uuid'],
                    'relative_path' => $file['relative'],
                    'sha256' => $file['sha256'],
                ],
                $files
            ),
        ];
        $manifestJson = json_encode(
            $manifest,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR
        );
        $manifestHash = hash('sha256', $manifestJson);
        $this->writeProtected($quarantine.'/manifest.json', $manifestJson."\n");

        $moved = [];

        try {
            foreach ($files as $file) {
                $year = $this->yearFromRelativePath($file['relative']);
                $destinationDirectory = $this->protectedDirectory($quarantine.'/files/'.$year);
                $destination = $destinationDirectory.'/'.$file['record_uuid'].'.pdf';

                if (file_exists($destination) || is_link($destination)
                    || ! rename($file['source'], $destination)) {
                    throw new RuntimeException('CERTIFICATE_PDF_QUARANTINE_FAILED id='.$file['record_id']);
                }

                $moved[] = [
                    'source' => $file['source'],
                    'destination' => $destination,
                ];
            }

            DB::transaction(function () use (
                $expectedCertificates,
                $expectedStudents,
                $backupReference,
                $resetId,
                $quarantine,
                $manifestHash,
                $files
            ): void {
                foreach (self::RESET_TABLES as $table) {
                    DB::table($table)->delete();
                }

                if (DB::table('pro_certificates')->count() !== 0
                    || DB::table('pro_certificate_sequences')->count() !== 0
                    || DB::table('pro_certificate_type_sequences')->count() !== 0
                    || ProCertificateStudent::query()->count() !== $expectedStudents) {
                    throw new RuntimeException('CERTIFICATE_RESET_POSTCONDITION_FAILED');
                }

                DB::table('pro_certificate_resets')->insert([
                    'record_uuid' => $resetId,
                    'preserved_student_count' => $expectedStudents,
                    'removed_certificate_count' => $expectedCertificates,
                    'quarantined_pdf_count' => count($files),
                    'backup_reference' => $backupReference,
                    'quarantine_path' => Str::after($quarantine, storage_path('app/private/')),
                    'manifest_sha256' => $manifestHash,
                    'executed_at' => now()->utc()->startOfSecond(),
                    'created_at' => now()->utc()->startOfSecond(),
                ]);
            }, 1);
        } catch (Throwable $exception) {
            $this->restoreMovedFiles($moved);
            throw $exception;
        }

        return [
            'already_reset' => false,
            'reset_id' => $resetId,
            'removed_certificates' => $expectedCertificates,
            'quarantined_pdfs' => count($files),
            'quarantine_path' => Str::after($quarantine, storage_path('app/private/')),
        ];
    }

    private function certificatePath(string $relative): string
    {
        if (! preg_match(
            '/\Apro-certificates\/([0-9]{4})\/([0-9a-f-]{36})\.pdf\z/D',
            $relative,
            $matches
        )) {
            throw new RuntimeException('CERTIFICATE_PDF_PATH_INVALID');
        }

        $base = realpath(storage_path('app/private/pro-certificates'));
        $expected = storage_path('app/private/'.$relative);
        $actual = realpath($expected);

        if ($base === false || $actual === false || is_link($expected)
            || ! is_file($actual) || ! is_readable($actual)
            || ! str_starts_with($actual, $base.'/') || $actual !== $expected) {
            throw new RuntimeException('CERTIFICATE_PDF_PATH_UNSAFE');
        }

        return $actual;
    }

    private function quarantineDirectory(string $resetId): string
    {
        $private = realpath(storage_path('app/private'));

        if ($private === false || ! is_dir($private) || is_link($private)) {
            throw new RuntimeException('PRIVATE_STORAGE_UNAVAILABLE');
        }

        return $this->protectedDirectory(
            $private.'/certificate-reset-quarantine/'.$resetId
        );
    }

    private function protectedDirectory(string $path): string
    {
        if (is_link($path)) {
            throw new RuntimeException('QUARANTINE_PATH_UNSAFE');
        }

        if (! is_dir($path) && ! mkdir($path, 0700, true) && ! is_dir($path)) {
            throw new RuntimeException('QUARANTINE_DIRECTORY_CREATE_FAILED');
        }

        if (realpath($path) !== $path || ! chmod($path, 0700)) {
            throw new RuntimeException('QUARANTINE_DIRECTORY_PROTECTION_FAILED');
        }

        return $path;
    }

    private function writeProtected(string $path, string $contents): void
    {
        if (file_exists($path) || is_link($path)) {
            throw new RuntimeException('RESET_MANIFEST_ALREADY_EXISTS');
        }

        $stream = fopen($path, 'xb');

        if ($stream === false) {
            throw new RuntimeException('RESET_MANIFEST_CREATE_FAILED');
        }

        try {
            if (! chmod($path, 0600)
                || fwrite($stream, $contents) !== strlen($contents)
                || ! fflush($stream)
                || ! fsync($stream)) {
                throw new RuntimeException('RESET_MANIFEST_WRITE_FAILED');
            }
        } finally {
            fclose($stream);
        }
    }

    /**
     * @param list<array{source:string,destination:string}> $moved
     */
    private function restoreMovedFiles(array $moved): void
    {
        foreach (array_reverse($moved) as $file) {
            $sourceDirectory = dirname($file['source']);

            if (! is_dir($sourceDirectory)
                && ! mkdir($sourceDirectory, 0700, true)
                && ! is_dir($sourceDirectory)) {
                throw new RuntimeException('CERTIFICATE_PDF_RESTORE_DIRECTORY_FAILED');
            }

            if (! rename($file['destination'], $file['source'])) {
                throw new RuntimeException('CERTIFICATE_PDF_RESTORE_FAILED');
            }
        }
    }

    private function yearFromRelativePath(string $relative): string
    {
        if (! preg_match('/\Apro-certificates\/([0-9]{4})\//D', $relative, $matches)) {
            throw new RuntimeException('CERTIFICATE_PDF_YEAR_INVALID');
        }

        return $matches[1];
    }
}
