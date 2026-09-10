<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ProCertificateUnreleasedReset;
use Illuminate\Console\Command;
use Throwable;

final class ResetUnreleasedCertificates extends Command
{
    protected $signature = 'iuoamc:reset-unreleased-certificates
        {--expected-certificates=50 : Required certificate record count}
        {--expected-students=13 : Required preserved student count}
        {--backup-reference= : Recovery backup identifier or path}
        {--apply : Quarantine PDFs and reset the active certificate domain}
        {--confirm= : Required confirmation phrase for reset}';

    protected $description = 'Safely reset unreleased certificates while preserving encrypted student names';

    public function handle(ProCertificateUnreleasedReset $reset): int
    {
        try {
            $expectedCertificates = $this->positiveInteger('expected-certificates');
            $expectedStudents = $this->positiveInteger('expected-students');

            if ($expectedCertificates === null || $expectedStudents === null) {
                $this->error('EXPECTED_COUNTS_INVALID');

                return self::INVALID;
            }

            $inventory = $reset->inventory();

            $this->components->info('Unreleased certificate reset inventory');
            $this->line('CERTIFICATES='.$inventory['certificates']);
            $this->line('ISSUED='.$inventory['issued']);
            $this->line('PDF_RECORDS='.$inventory['pdfs']);
            $this->line('PRESERVED_STUDENTS='.$inventory['students']);

            foreach ($inventory['table_counts'] as $table => $count) {
                $this->line(strtoupper($table).'='.$count);
            }

            if ($inventory['certificates'] !== $expectedCertificates
                || $inventory['issued'] !== $expectedCertificates
                || $inventory['pdfs'] !== $expectedCertificates
                || $inventory['students'] !== $expectedStudents) {
                $this->error('RESET_PREFLIGHT_COUNTS_MISMATCH');

                return self::FAILURE;
            }

            if (! $this->option('apply')) {
                $this->warn('DRY_RUN_ONLY');
                $this->line('No database rows were changed.');
                $this->line('No PDF files were moved.');
                $this->line('No student names were displayed.');

                return self::SUCCESS;
            }

            $confirmation = sprintf(
                'RESET-%d-CERTIFICATES-KEEP-%d-STUDENTS',
                $expectedCertificates,
                $expectedStudents
            );

            if (! hash_equals($confirmation, (string) $this->option('confirm'))) {
                $this->error('CONFIRMATION_PHRASE_INVALID');

                return self::INVALID;
            }

            if (! app()->isDownForMaintenance()) {
                $this->error('APPLICATION_MUST_BE_IN_MAINTENANCE_MODE');

                return self::FAILURE;
            }

            $result = $reset->reset(
                $expectedCertificates,
                $expectedStudents,
                (string) $this->option('backup-reference')
            );

            $this->components->info('Certificate registry reset completed');
            $this->line('ALREADY_RESET='.($result['already_reset'] ? 'YES' : 'NO'));
            $this->line('RESET_ID='.($result['reset_id'] ?? 'NONE'));
            $this->line('REMOVED_CERTIFICATES='.$result['removed_certificates']);
            $this->line('QUARANTINED_PDFS='.$result['quarantined_pdfs']);
            $this->line('QUARANTINE_PATH='.($result['quarantine_path'] ?? 'NONE'));
            $this->line('PRESERVED_STUDENTS='.$expectedStudents);
            $this->line('NEXT_CERTIFICATE_SEQUENCE=000001');
            $this->warn('APPLICATION_REMAINS_IN_MAINTENANCE_MODE');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('UNRELEASED_CERTIFICATE_RESET_FAILED');
            $this->line($exception->getMessage());
            $this->warn('Inspect the backup and quarantine before leaving maintenance mode.');

            return self::FAILURE;
        }
    }

    private function positiveInteger(string $option): ?int
    {
        $value = filter_var(
            $this->option($option),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        return is_int($value) ? $value : null;
    }
}
