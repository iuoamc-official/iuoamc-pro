<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ProCertificateStudentArchive;
use Illuminate\Console\Command;
use Throwable;

final class ArchiveCertificateStudents extends Command
{
    protected $signature = 'iuoamc:archive-certificate-students
        {--expected=13 : Required unique student count}
        {--commit : Write the encrypted student archive}
        {--confirm= : Required confirmation phrase for writes}';

    protected $description = 'Inventory and preserve certificate student names without displaying them';

    public function handle(ProCertificateStudentArchive $archive): int
    {
        try {
            $expected = filter_var(
                $this->option('expected'),
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );

            if (! is_int($expected)) {
                $this->error('EXPECTED_COUNT_INVALID');

                return self::INVALID;
            }

            $inventory = $archive->inventory();

            $this->components->info('Private certificate student inventory');
            $this->line('SOURCE_RECORDS='.$inventory['source_records']);
            $this->line('UNIQUE_STUDENTS='.$inventory['unique_students']);

            foreach ($inventory['statuses'] as $status => $count) {
                $this->line('STATUS_'.strtoupper($status).'='.$count);
            }

            if ($inventory['unique_students'] !== $expected) {
                $this->error(sprintf(
                    'STUDENT_COUNT_MISMATCH expected=%d actual=%d',
                    $expected,
                    $inventory['unique_students']
                ));

                return self::FAILURE;
            }

            if (! $this->option('commit')) {
                $this->warn('DRY_RUN_ONLY');
                $this->line('No database rows were changed.');
                $this->line('No student names were displayed.');

                return self::SUCCESS;
            }

            if (! hash_equals(
                'PRESERVE-'.$expected.'-STUDENT-NAMES',
                (string) $this->option('confirm')
            )) {
                $this->error('CONFIRMATION_PHRASE_INVALID');

                return self::INVALID;
            }

            $result = $archive->archive($expected);

            $this->components->info('Encrypted student archive verified');
            $this->line('SOURCE_RECORDS='.$result['source_records']);
            $this->line('ARCHIVED_STUDENTS='.$result['archived_students']);
            $this->line('ALREADY_ARCHIVED='.($result['already_archived'] ? 'YES' : 'NO'));
            $this->line('NO_STUDENT_NAMES_DISCLOSED');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('CERTIFICATE_STUDENT_ARCHIVE_FAILED');
            $this->line($exception->getMessage());

            return self::FAILURE;
        }
    }
}
