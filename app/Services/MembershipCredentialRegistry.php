<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Membership;
use App\Models\MembershipApplication;
use App\Models\MembershipCredential;
use App\Models\MembershipPeriod;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final class MembershipCredentialRegistry
{
    public function applicationFor(Membership $membership): ?MembershipApplication
    {
        return MembershipApplication::query()
            ->with('integrityAudit')
            ->where('membership_id', $membership->id)
            ->first();
    }

    public function saveApplication(
        User $actor,
        Membership $membership,
        array $data,
        ?UploadedFile $photo
    ): MembershipApplication {
        $registry = app(MembershipRegistry::class);
        $registry->requirePermission($actor, 'memberships.manage');
        abort_unless($registry->scoped($actor)->whereKey($membership->id)->exists(), 404);
        abort_unless($registry->verify($membership), 409, trans('memberships.errors.integrity'));
        abort_unless(in_array($membership->status, ['draft', 'active', 'suspended'], true), 409);
        abort_if(MembershipCredential::query()->where('membership_id', $membership->id)->exists(), 409,
            trans('memberships.errors.application_locked'));

        $validated = validator($data, [
            'membership_category_code' => ['required', Rule::in(app(MembershipApplicationPolicy::class)->categoryCodes())],
            'membership_term_years' => ['required', 'integer', Rule::in(app(MembershipApplicationPolicy::class)->termYears())],
            'member_title_code' => ['required', Rule::in(app(MembershipApplicationPolicy::class)->professionalTitleCodes())],
            'requested_payment_method_code' => ['required', Rule::in(app(MembershipApplicationPolicy::class)->paymentMethodCodes())],
            'fee_waiver_reason' => ['nullable', 'string', 'min:20', 'max:1500'],
            'discount_pence' => ['nullable', 'integer', 'min:0'],
            'date_of_birth' => ['required', 'date_format:Y-m-d', 'before:today'],
            'nationality_code' => ['required', 'regex:/^[A-Z]{2}$/'],
            'address' => ['required', 'string', 'max:1000'],
            'city' => ['required', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'residence_country_code' => ['required', 'regex:/^[A-Z]{2}$/'],
            'identification_type' => ['required', 'string', 'max:60'],
            'identification_number' => ['required', 'string', 'max:120'],
            'qualifications' => ['nullable', 'string', 'max:3000'],
            'application_consent' => ['accepted'],
            'terms_consent' => ['accepted'],
            'service_start_choice' => ['required', Rule::in(['immediate', 'after_cooling_off'])],
        ])->validate();

        foreach ($validated as $field => $value) {
            if (is_string($value)) {
                $validated[$field] = trim($value);
            }
        }

        $existing = $this->applicationFor($membership);
        if ($existing !== null && ! $this->verifyApplication($existing)) {
            $this->stop('application_integrity');
        }
        if ($photo === null && $existing === null) {
            $this->stop('photo_required');
        }

        $commercialFields = $this->commercialFields($validated, true);
        $photoData = $photo !== null ? $this->storePhoto($membership, $photo) : null;

        return DB::transaction(function () use ($actor, $membership, $validated, $existing, $photoData, $commercialFields): MembershipApplication {
            $application = MembershipApplication::query()
                ->where('membership_id', $membership->id)
                ->lockForUpdate()
                ->first();

            if ($application !== null && ! $this->verifyApplication($application)) {
                $this->stop('application_integrity');
            }

            $old = $application === null ? [] : $this->applicationAuditValues($application);
            $application ??= new MembershipApplication([
                'membership_id' => (int) $membership->id,
                'lock_version' => 0,
            ]);
            $application->fill([
                'date_of_birth' => $validated['date_of_birth'],
                'nationality_code' => $validated['nationality_code'],
                'address' => $validated['address'],
                'city' => $validated['city'],
                'postal_code' => $validated['postal_code'] ?? null,
                'residence_country_code' => $validated['residence_country_code'],
                'identification_type' => $validated['identification_type'],
                'identification_number' => $validated['identification_number'],
                'qualifications' => $validated['qualifications'] ?? null,
                'consent_at' => now()->utc()->startOfSecond(),
                ...$commercialFields,
                'updated_by' => (int) $actor->id,
            ]);
            if ($photoData !== null) {
                $application->fill($photoData);
            }
            $application->lock_version++;
            $application->save();
            $this->sealApplication($application, $actor, $old);

            return $application->fresh(['integrityAudit']);
        }, 3);
    }

    public function saveApplicationForVerifiedAccount(
        User $actor,
        Membership $membership,
        array $data,
        UploadedFile $photo
    ): MembershipApplication {
        abort_unless($actor->isActive() && $actor->hasVerifiedEmail(), 403);
        abort_unless(app(AccountRecordAccess::class)->owns(
            $actor, AccountRecordAccess::MEMBERSHIP, (int) $membership->id
        ), 404);
        abort_unless($membership->status === 'draft', 409);
        abort_unless(app(MembershipRegistry::class)->verify($membership), 409);
        abort_unless(! MembershipCredential::query()->where('membership_id', $membership->id)->exists(), 409);

        $validated = validator($data, [
            'membership_category_code' => ['required', Rule::in(app(MembershipApplicationPolicy::class)->categoryCodes())],
            'membership_term_years' => ['required', 'integer', Rule::in(app(MembershipApplicationPolicy::class)->termYears())],
            'member_title_code' => ['required', Rule::in(app(MembershipApplicationPolicy::class)->professionalTitleCodes())],
            'requested_payment_method_code' => ['required', Rule::in(app(MembershipApplicationPolicy::class)->paymentMethodCodes())],
            'fee_waiver_reason' => ['nullable', 'string', 'min:20', 'max:1500'],
            'date_of_birth' => ['required', 'date_format:Y-m-d', 'before:today'],
            'nationality_code' => ['required', 'regex:/^[A-Z]{2}$/'],
            'address' => ['required', 'string', 'max:1000'],
            'city' => ['required', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'residence_country_code' => ['required', 'regex:/^[A-Z]{2}$/'],
            'identification_type' => ['required', 'string', 'max:60'],
            'identification_number' => ['required', 'string', 'max:120'],
            'qualifications' => ['nullable', 'string', 'max:3000'],
            'application_consent' => ['accepted'],
            'terms_consent' => ['accepted'],
            'service_start_choice' => ['required', Rule::in(['immediate', 'after_cooling_off'])],
        ])->validate();
        foreach ($validated as $field => $value) {
            if (is_string($value)) { $validated[$field] = trim($value); }
        }
        $commercialFields = $this->commercialFields($validated, false);
        $photoData = $this->storePhoto($membership, $photo);

        return DB::transaction(function () use ($actor, $membership, $validated, $photoData, $commercialFields): MembershipApplication {
            abort_if(MembershipApplication::query()->where('membership_id', $membership->id)->exists(), 409);
            $application = new MembershipApplication([
                'membership_id' => (int) $membership->id,
                'lock_version' => 1,
                'date_of_birth' => $validated['date_of_birth'],
                'nationality_code' => $validated['nationality_code'],
                'address' => $validated['address'],
                'city' => $validated['city'],
                'postal_code' => $validated['postal_code'] ?? null,
                'residence_country_code' => $validated['residence_country_code'],
                'identification_type' => $validated['identification_type'],
                'identification_number' => $validated['identification_number'],
                'qualifications' => $validated['qualifications'] ?? null,
                'consent_at' => now()->utc()->startOfSecond(),
                ...$commercialFields,
                'updated_by' => (int) $actor->id,
            ] + $photoData);
            $application->save();
            $this->sealApplication($application, $actor, []);

            return $application->fresh(['integrityAudit']);
        }, 3);
    }

    public function ready(Membership $membership): bool
    {
        $application = $this->applicationFor($membership);
        if ($application === null || ! $this->verifyApplication($application)) {
            return false;
        }

        foreach ([
            'date_of_birth', 'nationality_code', 'address', 'city', 'residence_country_code',
            'identification_type', 'identification_number', 'photo_path', 'photo_sha256', 'consent_at',
        ] as $field) {
            if ($application->getAttribute($field) === null || $application->getAttribute($field) === '') {
                return false;
            }
        }

        if ($application->terms_version !== null) {
            foreach ([
                'membership_category_code', 'membership_term_years', 'standard_fee_pence',
                'discount_pence', 'payable_fee_pence', 'fee_currency', 'privacy_version',
                'terms_accepted_at', 'immediate_service_requested', 'service_start_at',
            ] as $field) {
                if ($application->getAttribute($field) === null || $application->getAttribute($field) === '') {
                    return false;
                }
            }
        }

        if ($application->member_title_code !== null || $application->requested_payment_method_code !== null) {
            foreach (['member_title_code', 'requested_payment_method_code'] as $field) {
                if ($application->getAttribute($field) === null || $application->getAttribute($field) === '') {
                    return false;
                }
            }
            if (app(MembershipApplicationPolicy::class)->paymentMethodRequiresWaiverReason(
                (string) $application->requested_payment_method_code
            ) && blank($application->fee_waiver_reason)) {
                return false;
            }
        }

        return $this->validPhoto($application);
    }

    public function verifyApplication(MembershipApplication $application): bool
    {
        try {
            $current = MembershipApplication::query()->with('integrityAudit')->find($application->id);
            if ($current === null
                || $current->record_hash !== $application->record_hash
                || $current->integrity_audit_id !== $application->integrity_audit_id
                || ! hash_equals((string) $current->record_hash, MembershipRegistry::digest($this->applicationSnapshot($current)))) {
                return false;
            }

            $audit = $current->integrityAudit;

            return $audit !== null
                && $audit->auditable_type === $current->getMorphClass()
                && (int) $audit->auditable_id === (int) $current->id
                && ($audit->new_values['record_hash'] ?? null) === $current->record_hash
                && (int) ($audit->new_values['lock_version'] ?? 0) === (int) $current->lock_version
                && (int) \App\Models\AuditLog::query()
                    ->where('auditable_type', $current->getMorphClass())
                    ->where('auditable_id', $current->id)
                    ->orderByDesc('sequence_number')
                    ->value('id') === (int) $audit->id
                && app(IntegrityService::class)->verifyAuditLog($audit)['valid']
                && $this->validPhoto($current);
        } catch (Throwable) {
            return false;
        }
    }

    public function issue(User $actor, Membership $membership): MembershipCredential
    {
        $registry = app(MembershipRegistry::class);
        $registry->requirePermission($actor, 'memberships.issue');
        $membership = $registry->scoped($actor)
            ->with(['organization', 'periods.audit', 'integrityAudit'])
            ->findOrFail($membership->id);

        abort_unless($registry->verify($membership), 409, trans('memberships.errors.integrity'));
        abort_unless($membership->status === 'active' && $membership->effectiveStatus() === 'active', 409,
            trans('memberships.errors.credential_state'));
        abort_unless($this->ready($membership), 409, trans('memberships.errors.application_incomplete'));

        $period = $membership->periods->first(fn (MembershipPeriod $candidate): bool =>
            $candidate->valid_from->toDateString() <= now()->utc()->toDateString()
            && $candidate->valid_until->toDateString() >= now()->utc()->toDateString()
        );
        abort_unless($period instanceof MembershipPeriod && $registry->verifyPeriod($period), 409,
            trans('memberships.errors.integrity'));

        if (MembershipCredential::query()->where('membership_period_id', $period->id)->exists()) {
            $this->stop('credential_exists');
        }

        $application = $this->applicationFor($membership);
        abort_unless($application !== null, 409);
        $token = bin2hex(random_bytes(32));
        $issuedAt = now()->utc()->startOfSecond();
        $payload = [
            'schema' => 'iuoamc-membership-credential-v1',
            'membership_id' => (int) $membership->id,
            'record_uuid' => $membership->record_uuid,
            'membership_number' => $membership->membership_number,
            'period_uuid' => $period->period_uuid,
            'version' => (int) $period->version,
            'full_name' => $membership->full_name,
            'latin_name' => $membership->latin_name,
            'membership_type' => $membership->membership_type,
            'professional_title' => $membership->professional_title,
            'country_code' => $membership->country_code,
            'organization' => $membership->organization->only([
                'id', 'code', 'legal_name', 'display_name', 'jurisdiction', 'registration_number',
            ]),
            'valid_from' => $period->valid_from->toDateString(),
            'valid_until' => $period->valid_until->toDateString(),
            'photo_sha256' => $application->photo_sha256,
            'verification_url' => url('/verify/m/'.$token),
            'issued_by' => (int) $actor->id,
            'issued_at' => $issuedAt->toIso8601String(),
            'template_version' => MembershipCredentialPdf::TEMPLATE_VERSION,
            'electronic_signature' => [
                'name' => 'Master Chef Ahmad Maadarani',
                'title' => 'President General & Authorised Signatory',
                'standard' => 'PAdES/X.509',
            ],
        ];
        $payload['credential_data_sha256'] = MembershipRegistry::digest($payload);
        $pdf = app(MembershipCredentialPdf::class);
        $photoPath = $this->safePrivatePath($application->photo_path);
        $signer = app(ProCertificatePadesSigner::class);
        abort_unless($signer->readiness()['ready'], 409, trans('memberships.errors.pades'));

        $card = $signer->sign($pdf->renderCard($payload, $photoPath));
        $certificate = $signer->sign($pdf->renderCertificate($payload, $photoPath));
        $base = 'memberships/credentials/'.preg_replace('/[^A-Za-z0-9_-]/', '-', (string) $membership->membership_number)
            .'/v'.(int) $period->version.'-'.substr($token, 0, 16);
        $cardPath = $base.'/membership-card.pdf';
        $certificatePath = $base.'/membership-certificate.pdf';

        $this->writePrivate($cardPath, $card['bytes']);
        try {
            $this->writePrivate($certificatePath, $certificate['bytes']);

            return DB::transaction(function () use (
                $actor, $membership, $period, $token, $payload, $card, $certificate,
                $cardPath, $certificatePath, $issuedAt
            ): MembershipCredential {
                $credential = MembershipCredential::query()->create([
                    'membership_id' => (int) $membership->id,
                    'membership_period_id' => (int) $period->id,
                    'public_token' => $token,
                    'membership_number' => $membership->membership_number,
                    'version' => (int) $period->version,
                    'payload' => $payload,
                    'payload_sha256' => MembershipRegistry::digest($payload),
                    'card_pdf_path' => $cardPath,
                    'card_pdf_sha256' => hash('sha256', $card['bytes']),
                    'certificate_pdf_path' => $certificatePath,
                    'certificate_pdf_sha256' => hash('sha256', $certificate['bytes']),
                    'pades_profile' => $card['profile'],
                    'pades_status' => $card['status'],
                    'signing_certificate_sha256' => $card['certificate_sha256'],
                    'signed_at' => $card['signed_at'],
                    'issued_by' => (int) $actor->id,
                    'issued_at' => $issuedAt,
                    'created_at' => $issuedAt,
                ]);
                $this->sealCredential($credential, $actor);

                return $credential->fresh(['integrityAudit', 'membership', 'period']);
            }, 3);
        } catch (Throwable $error) {
            $this->removeNewFile($cardPath);
            $this->removeNewFile($certificatePath);
            throw $error;
        }
    }

    public function credentialsFor(Membership $membership)
    {
        return MembershipCredential::query()
            ->where('membership_id', $membership->id)
            ->with(['period', 'issuer', 'integrityAudit'])
            ->orderByDesc('version')
            ->get();
    }

    public function publicRecord(string $token): ?MembershipCredential
    {
        if (! preg_match('/\A[0-9a-f]{64}\z/D', $token)) {
            return null;
        }

        $credential = MembershipCredential::query()
            ->where('public_token', $token)
            ->with(['membership.organization', 'membership.periods.audit', 'membership.integrityAudit', 'period.audit', 'integrityAudit'])
            ->first();

        return $credential !== null
            && $this->verifyCredential($credential)
            && app(MembershipRegistry::class)->verify($credential->membership)
            && app(MembershipRegistry::class)->verifyPeriod($credential->period)
                ? $credential
                : null;
    }

    public function verifyCredential(MembershipCredential $credential): bool
    {
        try {
            $current = MembershipCredential::query()->with('integrityAudit')->find($credential->id);
            $credentialData = $current?->payload ?? [];
            $credentialDataSha256 = $credentialData['credential_data_sha256'] ?? '';
            unset($credentialData['credential_data_sha256']);
            $credentialDataDigestValid = ($credentialData['template_version'] ?? null) !== MembershipCredentialPdf::TEMPLATE_VERSION
                || (is_string($credentialDataSha256)
                    && hash_equals($credentialDataSha256, MembershipRegistry::digest($credentialData)));
            if ($current === null
                || $current->record_hash !== $credential->record_hash
                || ! hash_equals((string) $current->record_hash, MembershipRegistry::digest($this->credentialSnapshot($current)))
                || ! hash_equals((string) $current->payload_sha256, MembershipRegistry::digest($current->payload))
                || (int) ($current->payload['membership_id'] ?? 0) !== (int) $current->membership_id
                || (string) ($current->payload['membership_number'] ?? '') !== (string) $current->membership_number
                || (int) ($current->payload['version'] ?? 0) !== (int) $current->version
                || (string) ($current->payload['period_uuid'] ?? '') !== (string) $current->period?->period_uuid
                || ! $credentialDataDigestValid
                || $current->pades_status !== 'valid') {
                return false;
            }
            foreach ([
                $current->card_pdf_path => $current->card_pdf_sha256,
                $current->certificate_pdf_path => $current->certificate_pdf_sha256,
            ] as $path => $expected) {
                if (! hash_equals((string) $expected, hash_file('sha256', $this->safePrivatePath($path)) ?: '')) {
                    return false;
                }
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

    public function downloadPath(MembershipCredential $credential, string $kind): string
    {
        abort_unless($this->verifyCredential($credential), 409, trans('memberships.errors.credential_integrity'));
        abort_unless(in_array($kind, ['card', 'certificate'], true), 404);

        return $this->safePrivatePath(
            $kind === 'card' ? $credential->card_pdf_path : $credential->certificate_pdf_path
        );
    }

    private function sealApplication(MembershipApplication $application, User $actor, array $old): void
    {
        $application->record_hash = MembershipRegistry::digest($this->applicationSnapshot($application));
        $values = $this->applicationAuditValues($application);
        $audit = AuditTrail::record(
            'membership.application_saved',
            $application,
            $old,
            $values,
            ['module' => 'memberships', 'membership_id' => (int) $application->membership_id],
            (int) $actor->id
        );
        $application->integrity_audit_id = (int) $audit->id;
        $application->saveQuietly();
    }

    private function sealCredential(MembershipCredential $credential, User $actor): void
    {
        $credential->record_hash = MembershipRegistry::digest($this->credentialSnapshot($credential));
        $audit = AuditTrail::record(
            'membership.credentials_issued',
            $credential,
            [],
            [
                'membership_id' => (int) $credential->membership_id,
                'membership_number' => $credential->membership_number,
                'version' => (int) $credential->version,
                'record_hash' => $credential->record_hash,
                'card_pdf_sha256' => $credential->card_pdf_sha256,
                'certificate_pdf_sha256' => $credential->certificate_pdf_sha256,
            ],
            ['module' => 'memberships'],
            (int) $actor->id
        );
        $credential->integrity_audit_id = (int) $audit->id;
        $credential->saveQuietly();
    }

    private function applicationSnapshot(MembershipApplication $application): array
    {
        $attributes = $application->getAttributes();

        $snapshot = [
            'schema' => 'iuoamc-membership-application-v1',
            'id' => (int) $application->id,
            'membership_id' => (int) $application->membership_id,
            'date_of_birth_encrypted' => $attributes['date_of_birth'] ?? null,
            'nationality_code' => $application->nationality_code,
            'address_encrypted' => $attributes['address'] ?? null,
            'city' => $application->city,
            'postal_code' => $application->postal_code,
            'residence_country_code' => $application->residence_country_code,
            'identification_type' => $application->identification_type,
            'identification_number_encrypted' => $attributes['identification_number'] ?? null,
            'qualifications_encrypted' => $attributes['qualifications'] ?? null,
            'photo_path' => $application->photo_path,
            'photo_sha256' => $application->photo_sha256,
            'photo_mime' => $application->photo_mime,
            'consent_at' => $application->consent_at?->utc()->format('Y-m-d\TH:i:sP'),
            'lock_version' => (int) $application->lock_version,
            'updated_by' => (int) $application->updated_by,
        ];

        if ($application->terms_version === null) {
            return $snapshot;
        }

        $v2 = [
            ...$snapshot,
            'schema' => 'iuoamc-membership-application-v2',
            'membership_category_code' => $application->membership_category_code,
            'membership_term_years' => (int) $application->membership_term_years,
            'standard_fee_pence' => (int) $application->standard_fee_pence,
            'discount_pence' => (int) $application->discount_pence,
            'payable_fee_pence' => (int) $application->payable_fee_pence,
            'fee_currency' => $application->fee_currency,
            'terms_version' => $application->terms_version,
            'privacy_version' => $application->privacy_version,
            'terms_accepted_at' => $application->terms_accepted_at?->utc()->format('Y-m-d\TH:i:sP'),
            'immediate_service_requested' => (bool) $application->immediate_service_requested,
            'service_start_at' => $application->service_start_at?->utc()->format('Y-m-d\TH:i:sP'),
        ];

        if ($application->member_title_code === null && $application->requested_payment_method_code === null) {
            return $v2;
        }

        return [
            ...$v2,
            'schema' => 'iuoamc-membership-application-v3',
            'member_title_code' => $application->member_title_code,
            'requested_payment_method_code' => $application->requested_payment_method_code,
            'fee_waiver_reason_encrypted' => $attributes['fee_waiver_reason'] ?? null,
        ];
    }

    private function applicationAuditValues(MembershipApplication $application): array
    {
        $values = [
            'membership_id' => (int) $application->membership_id,
            'photo_sha256' => $application->photo_sha256,
            'lock_version' => (int) $application->lock_version,
            'record_hash' => $application->record_hash,
        ];

        if ($application->terms_version !== null) {
            $values += [
                'membership_category_code' => $application->membership_category_code,
                'membership_term_years' => (int) $application->membership_term_years,
                'standard_fee_pence' => (int) $application->standard_fee_pence,
                'discount_pence' => (int) $application->discount_pence,
                'payable_fee_pence' => (int) $application->payable_fee_pence,
                'fee_currency' => $application->fee_currency,
                'terms_version' => $application->terms_version,
                'privacy_version' => $application->privacy_version,
                'immediate_service_requested' => (bool) $application->immediate_service_requested,
                'service_start_at' => $application->service_start_at?->utc()->format('Y-m-d\TH:i:sP'),
            ];
        }


        if ($application->member_title_code !== null || $application->requested_payment_method_code !== null) {
            $values += [
                'member_title_code' => $application->member_title_code,
                'requested_payment_method_code' => $application->requested_payment_method_code,
                'fee_waiver_reason_recorded' => filled($application->fee_waiver_reason),
            ];
        }

        return $values;
    }

    /** @param array<string, mixed> $validated */
    private function commercialFields(array $validated, bool $allowDiscount): array
    {
        $policy = app(MembershipApplicationPolicy::class);
        $standardFee = $policy->feePence((int) $validated['membership_term_years']);
        $paymentMethod = (string) $validated['requested_payment_method_code'];
        $waiverReason = trim((string) ($validated['fee_waiver_reason'] ?? ''));
        $requiresWaiverReason = $policy->paymentMethodRequiresWaiverReason($paymentMethod);
        if ($requiresWaiverReason && mb_strlen($waiverReason) < 20) {
            throw ValidationException::withMessages([
                'fee_waiver_reason' => trans('memberships.fee_waiver_reason_required'),
            ]);
        }
        if (! $requiresWaiverReason) {
            $waiverReason = '';
        }
        $discount = $allowDiscount ? (int) ($validated['discount_pence'] ?? 0) : 0;
        if ($discount > $standardFee) {
            throw ValidationException::withMessages([
                'discount_pence' => trans('memberships.discount_exceeds_fee'),
            ]);
        }

        $acceptedAt = now()->utc()->startOfSecond();
        $immediate = $validated['service_start_choice'] === 'immediate';

        return [
            'membership_category_code' => $validated['membership_category_code'],
            'member_title_code' => $validated['member_title_code'],
            'membership_term_years' => (int) $validated['membership_term_years'],
            'standard_fee_pence' => $standardFee,
            'discount_pence' => $discount,
            'payable_fee_pence' => $standardFee - $discount,
            'fee_currency' => MembershipApplicationPolicy::CURRENCY,
            'requested_payment_method_code' => $paymentMethod,
            'fee_waiver_reason' => $waiverReason !== '' ? $waiverReason : null,
            'terms_version' => MembershipApplicationPolicy::TERMS_VERSION,
            'privacy_version' => MembershipApplicationPolicy::PRIVACY_VERSION,
            'terms_accepted_at' => $acceptedAt,
            'immediate_service_requested' => $immediate,
            'service_start_at' => $immediate ? $acceptedAt : $acceptedAt->addDays(14),
        ];
    }

    private function credentialSnapshot(MembershipCredential $credential): array
    {
        return [
            'schema' => 'iuoamc-membership-credential-record-v1',
            'id' => (int) $credential->id,
            'membership_id' => (int) $credential->membership_id,
            'membership_period_id' => (int) $credential->membership_period_id,
            'public_token' => $credential->public_token,
            'membership_number' => $credential->membership_number,
            'version' => (int) $credential->version,
            'payload_encrypted' => $credential->getAttributes()['payload'] ?? null,
            'payload_sha256' => $credential->payload_sha256,
            'card_pdf_path' => $credential->card_pdf_path,
            'card_pdf_sha256' => $credential->card_pdf_sha256,
            'certificate_pdf_path' => $credential->certificate_pdf_path,
            'certificate_pdf_sha256' => $credential->certificate_pdf_sha256,
            'pades_profile' => $credential->pades_profile,
            'pades_status' => $credential->pades_status,
            'signing_certificate_sha256' => $credential->signing_certificate_sha256,
            'signed_at' => $credential->signed_at?->utc()->format('Y-m-d\TH:i:sP'),
            'issued_by' => (int) $credential->issued_by,
            'issued_at' => $credential->issued_at?->utc()->format('Y-m-d\TH:i:sP'),
        ];
    }

    private function storePhoto(Membership $membership, UploadedFile $photo): array
    {
        validator(['photo' => $photo], [
            'photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ])->validate();

        $bytes = file_get_contents($photo->getRealPath());
        $dimensions = is_string($bytes) ? @getimagesizefromstring($bytes) : false;
        if (! is_string($bytes) || ! is_array($dimensions)
            || $dimensions[0] < 300 || $dimensions[1] < 300
            || $dimensions[0] > 6000 || $dimensions[1] > 6000) {
            $this->stop('photo_invalid');
        }

        $normalized = $this->normalizePhoto($bytes);
        $relative = 'memberships/applications/'.$membership->record_uuid.'/'.bin2hex(random_bytes(16)).'.jpg';
        $this->writePrivate($relative, $normalized);

        return [
            'photo_path' => $relative,
            'photo_sha256' => hash('sha256', $normalized),
            'photo_mime' => 'image/jpeg',
        ];
    }

    private function normalizePhoto(string $bytes): string
    {
        if (class_exists(\Imagick::class)) {
            $image = new \Imagick();
            $image->readImageBlob($bytes);
            $image->setIteratorIndex(0);
            $image->stripImage();
            $image->setImageFormat('jpeg');
            $image->setImageCompressionQuality(90);
            if ($image->getImageWidth() > 1600 || $image->getImageHeight() > 1600) {
                $image->thumbnailImage(1600, 1600, true, true);
            }
            $result = $image->getImageBlob();
            $image->clear();
            if (is_string($result) && strlen($result) > 1024) {
                return $result;
            }
        }

        if (function_exists('imagecreatefromstring') && function_exists('imagejpeg')) {
            $source = @imagecreatefromstring($bytes);
            if ($source !== false) {
                $width = imagesx($source);
                $height = imagesy($source);
                $scale = min(1, 1600 / max($width, $height));
                $target = imagecreatetruecolor(max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));
                imagecopyresampled($target, $source, 0, 0, 0, 0, imagesx($target), imagesy($target), $width, $height);
                ob_start();
                imagejpeg($target, null, 90);
                $result = ob_get_clean();
                imagedestroy($source);
                imagedestroy($target);
                if (is_string($result) && strlen($result) > 1024) {
                    return $result;
                }
            }
        }

        throw new RuntimeException('MEMBERSHIP_PHOTO_PROCESSING_UNAVAILABLE');
    }

    private function validPhoto(MembershipApplication $application): bool
    {
        try {
            $path = $this->safePrivatePath($application->photo_path);

            return is_file($path)
                && ! is_link($path)
                && hash_equals((string) $application->photo_sha256, hash_file('sha256', $path) ?: '');
        } catch (Throwable) {
            return false;
        }
    }

    private function writePrivate(string $relative, string $bytes): void
    {
        $path = storage_path('app/private/'.$relative);
        $directory = dirname($path);
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('MEMBERSHIP_PRIVATE_DIRECTORY_UNAVAILABLE');
        }
        chmod($directory, 0700);
        if (file_put_contents($path, $bytes, LOCK_EX) !== strlen($bytes)) {
            throw new RuntimeException('MEMBERSHIP_PRIVATE_FILE_WRITE_FAILED');
        }
        chmod($path, 0600);
    }

    private function safePrivatePath(string $relative): string
    {
        if ($relative === '' || str_contains($relative, '..') || str_starts_with($relative, '/')) {
            throw new RuntimeException('MEMBERSHIP_PRIVATE_PATH_INVALID');
        }

        return storage_path('app/private/'.$relative);
    }

    private function removeNewFile(string $relative): void
    {
        $path = storage_path('app/private/'.$relative);
        if (is_file($path) && ! is_link($path)) {
            @unlink($path);
        }
    }

    private function stop(string $key): never
    {
        throw ValidationException::withMessages(['record' => trans('memberships.errors.'.$key)]);
    }
}
