<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ProCertificate;
use App\Models\ProCertificateDeliveryContact;
use App\Models\ProCertificateReplacement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

final class ProCertificateCorrection
{
    public function effectiveDeliveryEmail(ProCertificate $certificate): ?string
    {
        $contact = ProCertificateDeliveryContact::query()
            ->where('certificate_id', $certificate->id)
            ->with('integrityAudit')
            ->first();

        return $contact !== null && $this->verifyContact($contact)
            ? $contact->email
            : $certificate->recipient_email;
    }

    public function updateDeliveryEmail(
        User $actor,
        ProCertificate $certificate,
        ?string $email
    ): ProCertificateDeliveryContact {
        $registry = app(ProCertificateRegistry::class);
        $registry->requirePermission($actor, 'certificates.correct');
        abort_unless($registry->verify($certificate), 409, trans('certificates.errors.integrity'));
        abort_unless(in_array($certificate->status, ['issued', 'revoked'], true), 409);

        $validated = Validator::make(
            ['email' => is_string($email) ? trim($email) : $email],
            ['email' => ['nullable', 'email:rfc', 'max:254']]
        )->validate();

        return DB::transaction(function () use ($actor, $certificate, $validated): ProCertificateDeliveryContact {
            $contact = ProCertificateDeliveryContact::query()
                ->where('certificate_id', $certificate->id)
                ->lockForUpdate()
                ->with('integrityAudit')
                ->first();

            if ($contact !== null && ! $this->verifyContact($contact)) {
                throw ValidationException::withMessages([
                    'email' => trans('certificates.errors.contact_integrity'),
                ]);
            }
            if ($contact !== null && $contact->email === ($validated['email'] ?? null)) {
                return $contact;
            }

            $old = $contact === null ? [] : $this->contactAuditValues($contact);
            $contact ??= new ProCertificateDeliveryContact([
                'certificate_id' => (int) $certificate->id,
                'lock_version' => 0,
            ]);
            $contact->email = $validated['email'] ?? null;
            $contact->lock_version++;
            $contact->updated_by = (int) $actor->id;
            $contact->save();
            $this->sealContact($contact, $actor, $old);

            return $contact->fresh(['integrityAudit']);
        }, 3);
    }

    public function createReplacementDraft(
        User $actor,
        ProCertificate $source,
        array $input
    ): ProCertificate {
        $registry = app(ProCertificateRegistry::class);
        $registry->requirePermission($actor, 'certificates.correct');
        $registry->requirePermission($actor, 'certificates.manage');
        abort_unless($registry->verify($source), 409, trans('certificates.errors.integrity'));
        abort_unless($source->status === 'issued', 409, trans('certificates.errors.correction_state'));

        $validated = Validator::make($input, [
            'recipient_name' => ['required', 'string', 'max:180'],
            'public_name' => ['required', 'string', 'max:120'],
            'recipient_email' => ['nullable', 'email:rfc', 'max:254'],
            'reason' => ['required', 'string', 'max:1500'],
        ])->validate();
        foreach ($validated as $key => $value) {
            if (is_string($value)) {
                $validated[$key] = trim($value);
            }
        }
        if ($validated['recipient_name'] === $source->recipient_name
            && $validated['public_name'] === $source->public_name
            && ($validated['recipient_email'] ?? null) === $this->effectiveDeliveryEmail($source)) {
            throw ValidationException::withMessages([
                'recipient_name' => trans('certificates.errors.correction_no_changes'),
            ]);
        }

        return DB::transaction(function () use ($actor, $source, $validated, $registry): ProCertificate {
            $existing = ProCertificateReplacement::query()
                ->where('source_certificate_id', $source->id)
                ->lockForUpdate()
                ->first();
            if ($existing !== null) {
                throw ValidationException::withMessages([
                    'recipient_name' => trans('certificates.errors.correction_exists'),
                ]);
            }

            $profile = [
                'organization_id' => (int) $source->organization_id,
                'catalog_type_id' => (int) $source->catalog_type_id,
                'recipient_name' => $validated['recipient_name'],
                'public_name' => $validated['public_name'],
                'recipient_email' => $validated['recipient_email'] ?? null,
                'program_title' => $source->program_title,
                'certificate_title' => $source->certificate_title,
                'certificate_type' => $source->certificate_type,
                'credential_basis' => $source->credential_basis,
                'accreditation_reference' => $source->accreditation_reference,
                'accreditation_date' => $source->accreditation_date?->toDateString(),
                'language' => $source->language,
                'achievement_date' => $source->achievement_date?->toDateString(),
                'expires_on' => $source->expires_on?->toDateString(),
                'statement' => $source->statement,
                'signatory_name' => $source->signatory_name,
                'signatory_title' => $source->signatory_title,
                'specialization' => $source->specialization,
            ];
            $replacement = $registry->create($actor, $profile);
            $link = ProCertificateReplacement::query()->create([
                'source_certificate_id' => (int) $source->id,
                'replacement_certificate_id' => (int) $replacement->id,
                'reason' => $validated['reason'],
                'created_by' => (int) $actor->id,
                'created_at' => now()->utc()->startOfSecond(),
            ]);
            $this->sealReplacement($link, $actor);

            return $replacement->fresh(['organization']);
        }, 3);
    }

    public function replacementFor(ProCertificate $source): ?ProCertificate
    {
        $link = ProCertificateReplacement::query()
            ->where('source_certificate_id', $source->id)
            ->with(['integrityAudit', 'replacement'])
            ->first();

        return $link !== null && $this->verifyReplacement($link)
            ? $link->replacement
            : null;
    }

    public function sourceFor(ProCertificate $replacement): ?ProCertificate
    {
        $link = ProCertificateReplacement::query()
            ->where('replacement_certificate_id', $replacement->id)
            ->with(['integrityAudit', 'source'])
            ->first();

        return $link !== null && $this->verifyReplacement($link)
            ? $link->source
            : null;
    }

    public function isSuperseded(ProCertificate $source): bool
    {
        $replacement = $this->replacementFor($source);

        return $replacement !== null
            && $replacement->status === 'issued'
            && app(ProCertificateRegistry::class)->verify($replacement);
    }

    public function verifyContact(ProCertificateDeliveryContact $contact): bool
    {
        try {
            $current = ProCertificateDeliveryContact::query()
                ->with('integrityAudit')
                ->find($contact->id);
            if ($current === null
                || $current->record_hash !== $contact->record_hash
                || $current->integrity_audit_id !== $contact->integrity_audit_id
                || ! hash_equals((string) $current->record_hash, ProCertificateSigner::digest($this->contactSnapshot($current)))) {
                return false;
            }
            $audit = $current->integrityAudit;

            return $audit !== null
                && $audit->auditable_type === $current->getMorphClass()
                && (int) $audit->auditable_id === (int) $current->id
                && ($audit->new_values['record_hash'] ?? null) === $current->record_hash
                && (int) ($audit->new_values['lock_version'] ?? 0) === (int) $current->lock_version
                && app(IntegrityService::class)->verifyAuditLog($audit)['valid'];
        } catch (Throwable) {
            return false;
        }
    }

    public function verifyReplacement(ProCertificateReplacement $link): bool
    {
        try {
            $current = ProCertificateReplacement::query()
                ->with(['integrityAudit', 'source', 'replacement'])
                ->find($link->id);
            if ($current === null
                || $current->record_hash !== $link->record_hash
                || $current->integrity_audit_id !== $link->integrity_audit_id
                || ! hash_equals((string) $current->record_hash, ProCertificateSigner::digest($this->replacementSnapshot($current)))) {
                return false;
            }
            $audit = $current->integrityAudit;

            return $audit !== null
                && $audit->auditable_type === $current->getMorphClass()
                && (int) $audit->auditable_id === (int) $current->id
                && ($audit->new_values['record_hash'] ?? null) === $current->record_hash
                && app(IntegrityService::class)->verifyAuditLog($audit)['valid'];
        } catch (Throwable) {
            return false;
        }
    }

    private function sealContact(ProCertificateDeliveryContact $contact, User $actor, array $old): void
    {
        $contact->record_hash = ProCertificateSigner::digest($this->contactSnapshot($contact));
        $values = $this->contactAuditValues($contact);
        $audit = AuditTrail::record(
            'pro_certificate.delivery_contact_updated',
            $contact,
            $old,
            $values,
            ['module' => 'pro_certificates', 'certificate_id' => (int) $contact->certificate_id],
            (int) $actor->id
        );
        $contact->integrity_audit_id = (int) $audit->id;
        $contact->saveQuietly();
    }

    private function sealReplacement(ProCertificateReplacement $link, User $actor): void
    {
        $link->record_hash = ProCertificateSigner::digest($this->replacementSnapshot($link));
        $audit = AuditTrail::record(
            'pro_certificate.replacement_created',
            $link,
            [],
            [
                'source_certificate_id' => (int) $link->source_certificate_id,
                'replacement_certificate_id' => (int) $link->replacement_certificate_id,
                'record_hash' => $link->record_hash,
            ],
            ['module' => 'pro_certificates'],
            (int) $actor->id
        );
        $link->integrity_audit_id = (int) $audit->id;
        $link->saveQuietly();
    }

    private function contactSnapshot(ProCertificateDeliveryContact $contact): array
    {
        return [
            'schema' => 'iuoamc-pro-certificate-delivery-contact-v1',
            'id' => (int) $contact->id,
            'certificate_id' => (int) $contact->certificate_id,
            'email_encrypted' => $contact->getAttributes()['email'] ?? null,
            'lock_version' => (int) $contact->lock_version,
            'updated_by' => (int) $contact->updated_by,
        ];
    }

    private function contactAuditValues(ProCertificateDeliveryContact $contact): array
    {
        return [
            'certificate_id' => (int) $contact->certificate_id,
            'lock_version' => (int) $contact->lock_version,
            'updated_by' => (int) $contact->updated_by,
            'record_hash' => $contact->record_hash,
        ];
    }

    private function replacementSnapshot(ProCertificateReplacement $link): array
    {
        return [
            'schema' => 'iuoamc-pro-certificate-replacement-v1',
            'id' => (int) $link->id,
            'source_certificate_id' => (int) $link->source_certificate_id,
            'replacement_certificate_id' => (int) $link->replacement_certificate_id,
            'reason_encrypted' => $link->getAttributes()['reason'] ?? null,
            'created_by' => (int) $link->created_by,
            'created_at' => $link->created_at?->utc()->format('Y-m-d\TH:i:sP'),
        ];
    }
}
