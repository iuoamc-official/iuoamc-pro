<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Organization;
use App\Models\ProCertificate;
use App\Models\ProCertificateType;
use App\Models\User;
use App\Services\ProCertificateCatalog;
use App\Services\ProCertificateClaimPolicy;
use App\Services\ProCertificateRegistry;
use App\Services\PdrtseCertificateDefinition;
use Illuminate\Console\Command;
use RuntimeException;

final class InstallPdrtseDraft extends Command
{
    protected $signature = 'certificates:install-pdrtse-draft
        {--actor-email= : Authorized certificate administrator email}
        {--recipient-name= : Recipient name exactly as it should appear on the draft}
        {--recipient-email= : Private delivery email stored encrypted}
        {--achievement-date= : Draft achievement date in YYYY-MM-DD format}';

    protected $description = 'Install the controlled PDRTSE type and create one private review draft';

    public function handle(
        ProCertificateCatalog $catalog,
        ProCertificateRegistry $registry,
        PdrtseCertificateDefinition $definition,
    ): int
    {
        $email = trim((string) $this->option('actor-email'));
        $recipientName = trim((string) $this->option('recipient-name'));
        $recipientEmail = trim((string) $this->option('recipient-email'));
        $achievementDate = trim((string) $this->option('achievement-date'));
        if ($email === '' || $recipientName === '' || $recipientEmail === ''
            || preg_match('/\A\d{4}-\d{2}-\d{2}\z/D', $achievementDate) !== 1) {
            $this->error('ACTOR_RECIPIENT_AND_ACHIEVEMENT_DATE_REQUIRED');

            return self::FAILURE;
        }

        $actor = User::query()->where('email', $email)->where('status', 'active')->firstOrFail();
        $organization = Organization::query()->where('code', 'IUOAMC-UK-16649793')->where('status', 'active')->firstOrFail();
        $existingTypes = ProCertificateType::query()->where('code', PdrtseCertificateDefinition::CODE)->get();
        if ($existingTypes->count() > 1) {
            throw new RuntimeException('PDRTSE_TYPE_CODE_DUPLICATED');
        }

        $type = $existingTypes->first();
        if ($type === null) {
            $type = $catalog->create($actor, $definition->typeProfile((int) $organization->id));
            $this->info('PDRTSE_TYPE_CREATED='.$type->id);
        } else {
            if ((int) $type->organization_id !== (int) $organization->id
                || $type->number_prefix !== PdrtseCertificateDefinition::NUMBER_PREFIX
                || ! $catalog->verify($type)) {
                throw new RuntimeException('PDRTSE_TYPE_CODE_CONFLICT');
            }
            $this->info('PDRTSE_TYPE_REUSED='.$type->id);
        }

        $duplicate = ProCertificate::query()
            ->where('catalog_type_id', $type->id)
            ->where('recipient_name', $recipientName)
            ->whereIn('status', ['draft', 'review', 'approved', 'issued'])
            ->first();
        if ($duplicate !== null) {
            $this->warn('PDRTSE_DRAFT_ALREADY_EXISTS='.$duplicate->id);

            return self::SUCCESS;
        }

        $draft = $registry->create($actor, [
            'organization_id' => (int) $organization->id,
            'catalog_type_id' => (int) $type->id,
            'recipient_name' => $recipientName,
            'public_name' => $recipientName,
            'recipient_email' => $recipientEmail,
            'program_title' => '20-hour professional training programme - four progressive levels over one month',
            'certificate_title' => PdrtseCertificateDefinition::TITLE_EN,
            'certificate_type' => 'diploma',
            'credential_basis' => ProCertificateClaimPolicy::PROGRAMME_COMPLETION,
            'language' => 'en',
            'achievement_date' => $achievementDate,
            'expires_on' => null,
            'statement' => $definition->englishStatement(),
            'specialization' => PdrtseCertificateDefinition::DESIGNATION,
            'signatory_name' => 'Master Chef Ahmad Maadarani',
            'signatory_title' => 'President General & Authorized Signatory',
        ]);

        $this->info('PDRTSE_DRAFT_CREATED='.$draft->id);
        $this->line('STATUS=DRAFT');
        $this->line('NO_CERTIFICATE_NUMBER_ALLOCATED');
        $this->line('NO_PUBLIC_VERIFICATION_RECORD_PUBLISHED');

        return self::SUCCESS;
    }
}
