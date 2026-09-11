<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ProCertificate;
use App\Models\User;
use App\Services\ProCertificateRegistry;
use Illuminate\Console\Command;
use Throwable;

final class IssueProCertificate extends Command
{
    protected $signature = 'iuoamc:issue-pro-certificate
        {certificate : Numeric certificate record ID}
        {--actor= : Active issuing user email}
        {--expected-recipient= : Exact recipient name}
        {--expected-program= : Exact certificate programme code}
        {--confirm= : Required record-specific ISSUE confirmation phrase}';

    protected $description = 'Safely issue one approved professional certificate from the CLI signing runtime';

    public function handle(ProCertificateRegistry $registry): int
    {
        try {
            $certificateId = filter_var(
                $this->argument('certificate'),
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]],
            );
            $actorEmail = trim((string) $this->option('actor'));
            $expectedRecipient = trim((string) $this->option('expected-recipient'));
            $expectedProgram = trim((string) $this->option('expected-program'));

            if (! is_int($certificateId)
                || filter_var($actorEmail, FILTER_VALIDATE_EMAIL) === false
                || $expectedRecipient === ''
                || $expectedProgram === '') {
                $this->error('ISSUANCE_INPUT_INVALID');

                return self::INVALID;
            }

            if (! hash_equals('ISSUE-'.$certificateId, (string) $this->option('confirm'))) {
                $this->error('CONFIRMATION_PHRASE_INVALID');

                return self::INVALID;
            }

            $actor = User::query()
                ->where('email', $actorEmail)
                ->where('status', 'active')
                ->firstOrFail();
            $certificate = ProCertificate::query()->findOrFail($certificateId);

            if (! hash_equals($expectedRecipient, (string) $certificate->recipient_name)) {
                $this->error('RECIPIENT_MISMATCH');

                return self::FAILURE;
            }

            $programCode = (string) ($certificate->catalog_snapshot['code'] ?? '');
            if (! hash_equals($expectedProgram, $programCode)) {
                $this->error('PROGRAM_MISMATCH');

                return self::FAILURE;
            }

            $readiness = $registry->issuanceReadiness($actor, $certificate);
            if (! $readiness['ready']) {
                $this->error('CERTIFICATE_NOT_READY');
                foreach ($readiness['checks'] as $check => $passed) {
                    $this->line(strtoupper($check).'='.($passed === null ? 'NA' : ($passed ? 'READY' : 'BLOCKED')));
                }

                return self::FAILURE;
            }

            $issued = $registry->transition(
                $actor,
                $certificateId,
                (int) $certificate->lock_version,
                'issue',
                ['confirm_issue' => 'yes'],
            );

            if (! $registry->verify($issued) || $issued->pdf_signature_status !== 'valid') {
                $this->error('ISSUED_CERTIFICATE_VERIFICATION_FAILED');

                return self::FAILURE;
            }

            $this->components->info('Professional certificate issued and verified');
            $this->line('CERTIFICATE_ID='.$issued->id);
            $this->line('CERTIFICATE_NUMBER='.$issued->certificate_number);
            $this->line('STATUS='.$issued->status);
            $this->line('PADES_PROFILE='.$issued->pdf_signature_profile);
            $this->line('PADES_STATUS='.$issued->pdf_signature_status);
            $this->line('PDF_SHA256='.$issued->pdf_sha256);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('PRO_CERTIFICATE_ISSUANCE_FAILED');
            $this->line($exception->getMessage());

            return self::FAILURE;
        }
    }
}
