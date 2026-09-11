<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\ProCertificate;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final class ProCertificateRegistry
{
    public const PROFILE = [
        'recipient_name', 'public_name', 'program_title', 'certificate_title', 'certificate_type',
        'language', 'achievement_date', 'expires_on', 'statement', 'signatory_name', 'signatory_title',
    ];
    private const CREDENTIAL_FIELDS = ['credential_basis', 'accreditation_reference', 'accreditation_date'];
    private const PADES_FIELDS = [
        'pdf_signature_profile', 'pdf_signature_status', 'pdf_signature_field',
        'pdf_signing_certificate_sha256', 'pdf_signed_at',
    ];
    public const TEMPLATE_VERSION = 'IUOAMC-PRO-CERT-1.0.0';

    private const POLICY = [
        'submit' => ['certificates.manage', ['draft'], 'review'],
        'return' => ['certificates.review', ['review', 'approved'], 'draft'],
        'approve' => ['certificates.review', ['review'], 'approved'],
        'issue' => ['certificates.issue', ['approved'], 'issued'],
        'revoke' => ['certificates.revoke', ['issued'], 'revoked'],
    ];
    private const ISSUED_FIELDS = [
        'certificate_number', 'public_token', 'issued_at', 'issued_by', 'pdf_path', 'pdf_sha256',
        'issued_payload', 'payload_sha256', 'signature', 'signing_key_id',
    ];

    public function requirePermission(User $actor, string $permission): void
    {
        abort_unless(in_array($permission, [
            'certificates.view', 'certificates.manage', 'certificates.review', 'certificates.issue', 'certificates.revoke',
        ], true), 403);
        abort_unless($actor->exists && $actor->status === 'active'
            && User::query()->whereKey($actor->getKey())->where('status', 'active')->exists()
            && $actor->canDo('certificates.view') && $actor->canDo($permission), 403);
    }

    public function query(User $actor): Builder
    {
        $this->requirePermission($actor, 'certificates.view');

        return ProCertificate::query()->whereIn(
            'organization_id', app(InstitutionalAccess::class)->organizationIds($actor)
        );
    }

    public function create(User $actor, array $data): ProCertificate
    {
        $this->requirePermission($actor, 'certificates.manage');
        $this->rejectSystemInput($data);
        $organizationData = Validator::make($data, ['organization_id' => ['required', 'integer', 'min:1']])->validate();

        return DB::transaction(function () use ($actor, $data, $organizationData): ProCertificate {
            $organization = Organization::query()->lockForUpdate()->findOrFail($organizationData['organization_id']);
            app(InstitutionalAccess::class)->authorizeOrganization($actor, $organization);
            $this->requireActiveOrganization($organization);
            $prepared = $this->validateDraft($actor, $data);
            $version = isset($prepared['catalog_type_id']) ? 4 : 1;
            $prepared['credential_basis'] ??= ProCertificateClaimPolicy::PROGRAMME_COMPLETION;
            $values = $this->validatedProfile($prepared, $version);
            $catalog = $version >= 2 ? app(ProCertificateCatalog::class)->snapshotFor(
                $actor, $prepared['catalog_type_id'], (int) $organization->id
            ) : null;
            $time = now()->utc()->startOfSecond();
            $certificate = ProCertificate::create($values + [
                'schema_version' => $version, 'catalog_type_id' => $prepared['catalog_type_id'] ?? null,
                'catalog_snapshot' => $catalog,
                'record_uuid' => (string) Str::uuid(), 'organization_id' => (int) $organization->id,
                'status' => 'draft', 'lock_version' => 1, 'created_by' => (int) $actor->id,
                'updated_by' => (int) $actor->id, 'created_at' => $time, 'updated_at' => $time,
            ]);
            $this->append($certificate, $actor, 'created', []);

            return $certificate->fresh(['organization']);
        }, 3);
    }

    public function update(User $actor, int $id, int $lockVersion, array $data): ProCertificate
    {
        return DB::transaction(function () use ($actor, $id, $lockVersion, $data): ProCertificate {
            $certificate = $this->locked($actor, $id, $lockVersion, 'certificates.manage');
            if ($certificate->status !== 'draft') { $this->stop('transition'); }
            $this->rejectSystemInput($data);
            if (array_key_exists('organization_id', $data)) {
                $organizationData = Validator::make($data, ['organization_id' => ['required', 'integer', 'min:1']])->validate();
                if ((int) $organizationData['organization_id'] !== (int) $certificate->organization_id) {
                    $this->stop('organization_locked');
                }
            }
            if (array_key_exists('catalog_type_id', $data)
                && (string) ($data['catalog_type_id'] ?? '') !== (string) ($certificate->catalog_type_id ?? '')) {
                $this->stop('organization_locked');
            }
            $values = $this->validatedProfile(array_replace($this->profile($certificate), $data), $this->version($certificate));
            if ($this->version($certificate) >= 2 && $values['certificate_type'] !== $certificate->catalog_snapshot['category']) {
                $this->stop('transition');
            }
            $old = $this->auditValues($certificate);
            $certificate->fill($values);
            if (! $certificate->isDirty()) { return $certificate; }
            $certificate->lock_version++;
            $certificate->updated_by = (int) $actor->id;
            $certificate->updated_at = now()->utc()->startOfSecond();
            $certificate->last_reason = null;
            $certificate->save();
            $this->append($certificate, $actor, 'updated', $old);

            return $certificate->fresh(['organization']);
        }, 3);
    }

    public function transition(User $actor, int $id, int $lockVersion, string $action, array $data = []): ProCertificate
    {
        if (! isset(self::POLICY[$action])) { $this->stop('transition'); }
        [$permission, $allowed, $next] = self::POLICY[$action];
        $this->requirePermission($actor, $permission);
        $this->rejectSystemInput($data);
        $rules = ['reason' => [in_array($action, ['return', 'revoke'], true) ? 'required' : 'nullable',
            'string', 'max:1500', $this->plainTextRule(true)]];
        if ($action === 'issue') { $rules['confirm_issue'] = ['required', 'accepted']; }
        if ($action === 'revoke') { $rules['confirm_revoke'] = ['required', 'accepted']; }
        if (isset($data['reason']) && is_string($data['reason'])) { $data['reason'] = trim($data['reason']); }
        $validated = Validator::make($data, $rules)->validate();
        $createdPath = null;

        try {
            // Filesystem writes are not transactional: never automatically retry an issuing closure.
            return DB::transaction(function () use (
                $actor, $id, $lockVersion, $action, $permission, $allowed, $next, $validated, &$createdPath
            ): ProCertificate {
                $certificate = $this->locked($actor, $id, $lockVersion, $permission);
                if (! in_array($certificate->status, $allowed, true)) { $this->stop('transition'); }
                $organization = Organization::query()->lockForUpdate()->findOrFail($certificate->organization_id);
                app(InstitutionalAccess::class)->authorizeOrganization($actor, $organization);
                if (in_array($action, ['submit', 'approve', 'issue'], true)) {
                    $this->requireActiveOrganization($organization);
                    if ($this->version($certificate) >= 3 && $certificate->credential_basis === null) {
                        throw ValidationException::withMessages([
                            'credential_basis' => trans('certificates.errors.credential_basis_required'),
                        ]);
                    }
                    $this->validatedProfile($this->profile($certificate), $this->version($certificate));
                    if ($this->version($certificate) >= 2) {
                        app(ProCertificateCatalog::class)->snapshotFor($actor, (int) $certificate->catalog_type_id, (int) $certificate->organization_id);
                    }
                }
                $old = $this->auditValues($certificate);
                $time = now()->utc()->startOfSecond();
                $certificate->status = $next;
                $certificate->lock_version++;
                $certificate->updated_by = (int) $actor->id;
                $certificate->updated_at = $time;
                $certificate->last_reason = ($validated['reason'] ?? null) ?: null;

                if ($action === 'return') {
                    $certificate->approved_at = null;
                    $certificate->approved_by = null;
                } elseif ($action === 'approve') {
                    $certificate->approved_at = $time;
                    $certificate->approved_by = (int) $actor->id;
                } elseif ($action === 'issue') {
                    $certificate->certificate_number = $this->nextNumber((int) $time->format('Y'), $certificate);
                    $certificate->public_token = bin2hex(random_bytes(32));
                    $certificate->issued_at = $time;
                    $certificate->issued_by = (int) $actor->id;
                    $payload = $this->pdfPayload($certificate, $this->issuerSnapshot($organization));
                    $unsignedBytes = app(ProCertificatePdf::class)->render($payload);
                    if (! str_starts_with($unsignedBytes, '%PDF-')) { $this->stop('pdf_unavailable'); }
                    $bytes = $unsignedBytes;
                    if ($this->version($certificate) >= 4) {
                        $pades = app(ProCertificatePadesSigner::class)->sign($unsignedBytes);
                        $bytes = $pades['bytes'];
                        $certificate->pdf_signature_profile = $pades['profile'];
                        $certificate->pdf_signature_status = $pades['status'];
                        $certificate->pdf_signature_field = $pades['field'];
                        $certificate->pdf_signing_certificate_sha256 = $pades['certificate_sha256'];
                        $certificate->pdf_signed_at = $pades['signed_at'];
                    }
                    $certificate->pdf_sha256 = hash('sha256', $bytes);
                    $payload['pdf_sha256'] = $certificate->pdf_sha256;
                    if ($this->version($certificate) >= 4) {
                        $payload['pdf_signature'] = [
                            'profile' => $certificate->pdf_signature_profile,
                            'status' => $certificate->pdf_signature_status,
                            'field' => $certificate->pdf_signature_field,
                            'certificate_sha256' => $certificate->pdf_signing_certificate_sha256,
                            'signed_at' => $this->iso($certificate->pdf_signed_at),
                        ];
                    }
                    $certificate->issued_payload = $payload;
                    $certificate->fill(app(ProCertificateSigner::class)->sign($payload));
                    $certificate->pdf_path = 'pro-certificates/'.$time->format('Y').'/'.$certificate->record_uuid.'.pdf';
                    $createdPath = $this->writePdf($certificate->pdf_path, $bytes);
                } elseif ($action === 'revoke') {
                    $certificate->revoked_at = $time;
                    $certificate->revoked_by = (int) $actor->id;
                }

                $certificate->save();
                $this->append($certificate, $actor, $action, $old);

                return $certificate->fresh(['organization']);
            }, $action === 'issue' ? 1 : 3);
        } catch (Throwable $error) {
            if ($createdPath !== null && is_file($createdPath) && ! is_link($createdPath)) {
                if (! unlink($createdPath)) {
                    report(new RuntimeException('An uncommitted private certificate PDF requires cleanup.'));
                }
            }
            throw $error;
        }
    }

    /** Verify the supplied record, every subject audit transition, and the original PDF bytes. */
    public function verify(ProCertificate $certificate): bool
    {
        try {
            if (! $certificate->exists) { return false; }
            $current = ProCertificate::query()->find($certificate->getKey());
            if ($current === null || $current->record_hash !== $certificate->record_hash
                || $current->integrity_audit_id !== $certificate->integrity_audit_id
                || ProCertificateSigner::digest($this->snapshot($current)) !== ProCertificateSigner::digest($this->snapshot($certificate))) {
                return false;
            }
            $certificate = $current;
            if (! $this->validState($certificate)
                || ! is_string($certificate->record_hash)
                || ! hash_equals($certificate->record_hash, ProCertificateSigner::digest($this->snapshot($certificate)))) {
                return false;
            }
            $audits = AuditLog::query()->where('auditable_type', $certificate->getMorphClass())
                ->where('auditable_id', $certificate->id)->orderBy('sequence_number')->orderBy('id')->get();
            if ($audits->count() !== $certificate->lock_version || $audits->isEmpty()) { return false; }
            $prior = null;
            foreach ($audits as $offset => $audit) {
                if (! app(IntegrityService::class)->verifyAuditLog($audit)['valid']
                    || $audit->auditable_type !== $certificate->getMorphClass()
                    || (int) $audit->auditable_id !== (int) $certificate->id
                    || (int) ($audit->new_values['lock_version'] ?? 0) !== $offset + 1
                    || ($audit->new_values['record_uuid'] ?? null) !== $certificate->record_uuid
                    || (int) ($audit->new_values['organization_id'] ?? 0) !== (int) $certificate->organization_id
                    || (int) ($audit->new_values['updated_by'] ?? 0) !== (int) $audit->actor_id
                    || ! preg_match('/\A[0-9a-f]{64}\z/D', (string) ($audit->new_values['record_hash'] ?? ''))
                    || ! $this->validAuditTransition($audit, $prior)) {
                    return false;
                }
                $prior = $audit;
            }
            $audit = $audits->last();
            if ((int) $audit->id !== (int) $certificate->integrity_audit_id
                || (int) $audit->actor_id !== (int) $certificate->updated_by
                || ProCertificateSigner::canonicalJson($audit->new_values) !== ProCertificateSigner::canonicalJson($this->auditValues($certificate))
                || ($audit->metadata['record_hash'] ?? null) !== $certificate->record_hash
                || ($audit->metadata['module'] ?? null) !== 'pro_certificates') {
                return false;
            }
            if (in_array($certificate->status, ['issued', 'revoked'], true)) {
                $payload = $certificate->issued_payload;
                if (! is_array($payload) || ! is_array($payload['issuer'] ?? null)) { return false; }
                $issuer = $payload['issuer'];
                if ((int) ($issuer['id'] ?? 0) !== (int) $certificate->organization_id
                    || array_diff(array_keys($issuer), ['id', 'code', 'legal_name', 'display_name', 'jurisdiction', 'registration_number']) !== []) {
                    return false;
                }
                $expected = $this->pdfPayload($certificate, $issuer) + ['pdf_sha256' => $certificate->pdf_sha256];
                if ($this->version($certificate) >= 4) {
                    $expected['pdf_signature'] = [
                        'profile' => $certificate->pdf_signature_profile,
                        'status' => $certificate->pdf_signature_status,
                        'field' => $certificate->pdf_signature_field,
                        'certificate_sha256' => $certificate->pdf_signing_certificate_sha256,
                        'signed_at' => $this->iso($certificate->pdf_signed_at),
                    ];
                }
                if (ProCertificateSigner::canonicalJson($payload) !== ProCertificateSigner::canonicalJson($expected)
                    || ! app(ProCertificateSigner::class)->verify($payload, $certificate->only(['payload_sha256', 'signature', 'signing_key_id']))) {
                    return false;
                }
                $path = $this->safePdfPath($certificate);
                $actualHash = hash_file('sha256', $path);
                if (! is_string($actualHash) || ! hash_equals($certificate->pdf_sha256, $actualHash)) { return false; }
            }

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function publicRecord(string $token): ?ProCertificate
    {
        if (! preg_match('/\A[0-9a-f]{64}\z/D', $token)) { return null; }
        $certificate = ProCertificate::query()->where('public_token', $token)->whereIn('status', ['issued', 'revoked'])->first();

        return $certificate !== null && $this->verify($certificate) ? $certificate : null;
    }

    public function effectiveStatus(ProCertificate $certificate): string
    {
        if ($certificate->status === 'issued' && $certificate->expires_on !== null
            && $certificate->expires_on->toDateString() < now()->utc()->toDateString()) {
            return 'expired';
        }

        return (string) $certificate->status;
    }

    public function downloadPath(ProCertificate $certificate): string
    {
        if (! in_array($certificate->status, ['issued', 'revoked'], true) || ! $this->verify($certificate)) {
            $this->stop('integrity');
        }

        return $this->safePdfPath($certificate);
    }

    /** Public attestation deliberately contains no private recipient name or original payload. */
    public function proof(ProCertificate $certificate): array
    {
        if (! in_array($certificate->status, ['issued', 'revoked'], true) || ! $this->verify($certificate)) {
            $this->stop('integrity');
        }
        $payload = $certificate->issued_payload;
        $attestation = [
            'schema' => 'iuoamc-pro-certificate-public-attestation-v1',
            'record_uuid' => $certificate->record_uuid, 'certificate_number' => $certificate->certificate_number,
            'public_name' => $certificate->public_name, 'certificate_title' => $certificate->certificate_title,
            'certificate_type' => $certificate->certificate_type, 'program_title' => $certificate->program_title,
            'language' => $certificate->language, 'achievement_date' => $certificate->achievement_date->toDateString(),
            'expires_on' => $certificate->expires_on?->toDateString(), 'issued_at' => $this->iso($certificate->issued_at),
            'issuer' => ['code' => $payload['issuer']['code'], 'display_name' => $payload['issuer']['display_name']],
            'verification_url' => $payload['verification_url'], 'verification_status' => $this->effectiveStatus($certificate),
            'record_status' => $certificate->status, 'revoked_at' => $this->iso($certificate->revoked_at),
            'verified_at' => now()->utc()->startOfSecond()->toIso8601String(),
            'record_hash' => $certificate->record_hash, 'pdf_sha256' => $certificate->pdf_sha256,
            'issued_payload_sha256' => $certificate->payload_sha256,
        ];
        if ($this->version($certificate) >= 2) {
            $attestation['schema'] = 'iuoamc-pro-certificate-public-attestation-v2';
            $attestation['specialization'] = $certificate->specialization;
            $attestation['catalog'] = ['code' => $certificate->catalog_snapshot['code'],
                'name' => $certificate->catalog_snapshot['names'][$certificate->language]];
            $programIpCode = ProMasterCertificatePdf::programIpCodeFromStatement($payload['statement'] ?? null);
            if ($programIpCode !== null) {
                $attestation['program_intellectual_property_code'] = $programIpCode;
            }
        }
        if ($this->version($certificate) >= 3) {
            $attestation['schema'] = 'iuoamc-pro-certificate-public-attestation-v3';
            $attestation['credential_basis'] = $certificate->credential_basis;
            $attestation['accreditation_reference'] = $certificate->accreditation_reference;
            $attestation['accreditation_date'] = $certificate->accreditation_date?->toDateString();
            $attestation['pdf_signature_profile'] = 'registry-seal-ed25519-sha256';
        }
        if ($this->version($certificate) >= 4) {
            $attestation['schema'] = 'iuoamc-pro-certificate-public-attestation-v4';
            $attestation['pdf_signature_profile'] = $certificate->pdf_signature_profile;
            $attestation['pdf_signature_status'] = $certificate->pdf_signature_status;
            $attestation['pdf_signature_field'] = $certificate->pdf_signature_field;
            $attestation['pdf_signing_certificate_sha256'] = $certificate->pdf_signing_certificate_sha256;
            $attestation['pdf_signed_at'] = $this->iso($certificate->pdf_signed_at);
        }
        $signer = app(ProCertificateSigner::class);

        return [
            'schema' => 'iuoamc-pro-certificate-proof-v1',
            'attestation' => $attestation, 'attestation_seal' => $signer->sign($attestation),
            'issued_seal' => $certificate->only(['payload_sha256', 'signature', 'signing_key_id']),
            'public_key' => $signer->publicKey(),
            'signature_format' => 'Ed25519 over IUOAMC-PRO-CERTIFICATE-SIGNATURE-V1 + NUL + raw SHA-256 of canonical JSON',
            'canonical_json' => 'Recursive bytewise sorted object keys; unchanged list order; UTF-8; unescaped Unicode and slashes; preserved zero fractions.',
        ];
    }

    /** A private, visibly watermarked preview never allocates a number, QR token, or stored PDF. */
    public function previewPayload(User $actor, ProCertificate $certificate): array
    {
        $this->requirePermission($actor, 'certificates.manage');
        $current = $this->query($actor)->findOrFail($certificate->getKey());
        if (! in_array($current->status, ['draft', 'review', 'approved'], true)) { $this->stop('transition'); }
        if (! $this->verify($current)) { $this->stop('integrity'); }

        return array_replace($this->pdfPayload($current, $this->issuerSnapshot($current->organization)), [
            'certificate_number' => 'DRAFT', 'verification_url' => '', 'issued_at' => '', 'draft' => true,
        ]);
    }

    /**
     * Report the same non-mutating prerequisites consulted by the authoritative issue transition.
     *
     * @return array{ready: bool, checks: array<string, bool|null>}
     */
    public function issuanceReadiness(User $actor, ProCertificate $certificate): array
    {
        $this->requirePermission($actor, 'certificates.view');
        $current = $this->query($actor)->with('organization')->findOrFail($certificate->getKey());
        $integrity = $this->verify($current);
        $version = $this->version($current);
        $catalog = null;

        if ($integrity && $version >= 2) {
            try {
                app(ProCertificateCatalog::class)->snapshotFor(
                    $actor,
                    (int) $current->catalog_type_id,
                    (int) $current->organization_id,
                );
                $catalog = true;
            } catch (Throwable) {
                $catalog = false;
            }
        }

        $programIp = null;
        $catalogSnapshot = is_array($current->catalog_snapshot) ? $current->catalog_snapshot : [];
        if ($version >= 2 && ($catalogSnapshot['category'] ?? null) === 'professional_master') {
            $programIp = ProMasterCertificatePdf::programIpCodeFromStatement($current->statement) !== null;
        }

        $printAsset = is_file(public_path('assets/brand/iuoamc-pro-logo.png'))
            && ! is_link(public_path('assets/brand/iuoamc-pro-logo.png'));
        if ($version >= 2 && ($catalogSnapshot['layout'] ?? null) === ProMasterCertificatePdf::LAYOUT) {
            $printAsset = ProMasterCertificatePdf::backgroundIsValid();
        }

        $pades = $version >= 4 ? app(ProCertificatePadesSigner::class)->readiness()['ready'] : null;

        $checks = [
            'integrity' => $integrity,
            'approved' => $current->status === 'approved',
            'organization' => $current->organization?->status === 'active',
            'credential_basis' => $version >= 3 ? $current->credential_basis !== null : null,
            'catalog' => $catalog,
            'program_ip' => $programIp,
            'print_asset' => $printAsset,
            'pades' => $pades,
            'authorization' => $actor->canDo('certificates.issue'),
        ];
        $requiredChecks = array_filter($checks, static fn (mixed $check): bool => $check !== null);

        return [
            'ready' => ! in_array(false, $requiredChecks, true),
            'checks' => $checks,
        ];
    }

    private function locked(User $actor, int $id, int $lockVersion, string $permission): ProCertificate
    {
        $this->requirePermission($actor, $permission);
        $certificate = $this->query($actor)->lockForUpdate()->findOrFail($id);
        if ($lockVersion < 1 || $certificate->lock_version !== $lockVersion) { $this->stop('stale'); }
        if (! $this->verify($certificate)) { $this->stop('integrity'); }

        return $certificate;
    }

    /** Normalize a draft without creating a record, reserving numbers or rendering files. */
    public function validateDraft(User $actor, array $data): array
    {
        $this->requirePermission($actor, 'certificates.manage');
        $this->rejectSystemInput($data);
        $identity = Validator::make($data, [
            'organization_id' => ['required', 'integer', 'min:1'],
            'catalog_type_id' => ['nullable', 'integer', 'min:1'],
        ])->validate();
        $organization = Organization::query()->findOrFail($identity['organization_id']);
        app(InstitutionalAccess::class)->authorizeOrganization($actor, $organization);
        $this->requireActiveOrganization($organization);
        $identity['organization_id'] = (int) $organization->id;
        if (($identity['catalog_type_id'] ?? null) === null) {
            if (isset($data['specialization']) && $data['specialization'] !== '') { $this->stop('transition'); }
            return ['organization_id' => (int) $organization->id] + $this->validatedProfile($data);
        }
        $identity['catalog_type_id'] = (int) $identity['catalog_type_id'];
        $catalog = app(ProCertificateCatalog::class);
        $snapshot = $catalog->snapshotFor($actor, $identity['catalog_type_id'], $identity['organization_id']);
        $language = Validator::make($data, ['language' => ['required', 'string', Rule::in(['ar', 'en', 'fr'])]])->validate()['language'];
        $defaults = $catalog->defaults($actor, $identity['catalog_type_id'], $language);
        if (array_key_exists('certificate_type', $data) && $data['certificate_type'] !== $snapshot['category']) { $this->stop('transition'); }
        $profile = $this->validatedProfile(array_replace([
            'credential_basis' => ProCertificateClaimPolicy::PROGRAMME_COMPLETION,
        ], $defaults, $data, ['certificate_type' => $snapshot['category']]), 4);
        return $identity + $profile;
    }

    private function validatedProfile(array $data, int $version = 1): array
    {
        $fields = $version >= 2 ? array_merge(self::PROFILE, ['specialization']) : self::PROFILE;
        if ($version >= 3) {
            $fields = array_merge($fields, self::CREDENTIAL_FIELDS);
        }
        if ($version >= 4) {
            $fields[] = 'recipient_email';
        }
        $values = array_intersect_key($data, array_flip($fields));
        foreach ($values as $field => $value) {
            if (is_string($value)) { $values[$field] = trim($value); }
        }
        if (($values['expires_on'] ?? null) === '') { $values['expires_on'] = null; }
        if ($version >= 3 && ($values['credential_basis'] ?? null) === '') { $values['credential_basis'] = null; }
        if ($version >= 3 && ($values['accreditation_reference'] ?? null) === '') { $values['accreditation_reference'] = null; }
        if ($version >= 3 && ($values['accreditation_date'] ?? null) === '') { $values['accreditation_date'] = null; }
        if ($version >= 2 && ($values['specialization'] ?? null) === '') { $values['specialization'] = null; }
        $short = $this->plainTextRule(false);
        $long = $this->plainTextRule(true);

        $rules = [
            'recipient_name' => ['required', 'string', 'max:180', $short],
            'public_name' => ['required', 'string', 'max:120', $short],
            'recipient_email' => ['nullable', 'string', 'email:rfc', 'max:254'],
            'program_title' => ['required', 'string', 'max:200', $short],
            'certificate_title' => ['required', 'string', 'max:120', $short],
            'certificate_type' => ['required', 'string', Rule::in($version >= 2
                ? ['participation', 'completion', 'appreciation', 'diploma', 'professional_master'] : ['participation', 'completion', 'appreciation'])],
            'credential_basis' => ['nullable', 'string', Rule::in([
                ProCertificateClaimPolicy::PROGRAMME_COMPLETION,
                ProCertificateClaimPolicy::PROFESSIONAL_ACCREDITATION,
            ])],
            'accreditation_reference' => ['nullable', 'string', 'max:120', 'regex:/\A[A-Z0-9][A-Z0-9._\/-]{4,119}\z/D'],
            'accreditation_date' => ['nullable', 'string', 'date_format:Y-m-d', 'after_or_equal:achievement_date'],
            'language' => ['required', 'string', Rule::in(['ar', 'en', 'fr'])],
            'achievement_date' => ['required', 'string', 'date_format:Y-m-d'],
            'expires_on' => ['nullable', 'string', 'date_format:Y-m-d', 'after_or_equal:achievement_date'],
            'statement' => ['required', 'string', 'max:1500', $long],
            'signatory_name' => ['required', 'string', 'max:120', $short],
            'signatory_title' => ['required', 'string', 'max:120', $short],
        ];
        if ($version >= 2) { $rules['specialization'] = ['nullable', 'string', 'max:200', $short]; }
        if ($version < 3) {
            unset($rules['credential_basis'], $rules['accreditation_reference'], $rules['accreditation_date']);
        }
        if ($version < 4) {
            unset($rules['recipient_email']);
        }
        $validated = Validator::make($values, $rules)->validate() + ['expires_on' => null]
            + ($version >= 2 ? ['specialization' => null] : [])
            + ($version >= 3 ? [
                'credential_basis' => null,
                'accreditation_reference' => null,
                'accreditation_date' => null,
            ] : [])
            + ($version >= 4 ? ['recipient_email' => null] : []);
        if ($version >= 3) {
            app(ProCertificateClaimPolicy::class)->enforce($validated);
        }

        return $validated;
    }

    private function plainTextRule(bool $multiline): \Closure
    {
        return static function (string $attribute, mixed $value, \Closure $fail) use ($multiline): void {
            if (! is_string($value)) { return; }
            $pattern = $multiline ? '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u' : '/[\x00-\x1F\x7F]/u';
            if (preg_match('//u', $value) !== 1 || preg_match($pattern, $value) !== 0) {
                $fail(trans('validation.string', ['attribute' => $attribute]));
            }
        };
    }

    private function rejectSystemInput(array $data): void
    {
        foreach (array_merge(self::ISSUED_FIELDS, self::PADES_FIELDS, [
            'id', 'record_uuid', 'status', 'approved_at', 'approved_by', 'revoked_at', 'revoked_by',
            'created_by', 'updated_by', 'created_at', 'updated_at', 'last_reason', 'record_hash', 'integrity_audit_id',
            'schema_version', 'catalog_snapshot',
        ]) as $field) {
            if (array_key_exists($field, $data)) {
                throw ValidationException::withMessages([$field => trans('certificates.errors.transition')]);
            }
        }
    }

    private function profile(ProCertificate $certificate): array
    {
        $profile = $certificate->only(self::PROFILE);
        if ($this->version($certificate) >= 2) { $profile['specialization'] = $certificate->specialization; }
        $profile['achievement_date'] = $certificate->achievement_date?->toDateString();
        $profile['expires_on'] = $certificate->expires_on?->toDateString();
        if ($this->version($certificate) >= 3) {
            $profile['credential_basis'] = $certificate->credential_basis;
            $profile['accreditation_reference'] = $certificate->accreditation_reference;
            $profile['accreditation_date'] = $certificate->accreditation_date?->toDateString();
        }
        if ($this->version($certificate) >= 4) {
            $profile['recipient_email'] = $certificate->recipient_email;
        }

        return $profile;
    }

    private function requireActiveOrganization(Organization $organization): void
    {
        if ($organization->status !== 'active') { $this->stop('inactive_organization'); }
    }

    private function issuerSnapshot(Organization $organization): array
    {
        return [
            'id' => (int) $organization->id, 'code' => $organization->code,
            'legal_name' => $organization->legal_name, 'display_name' => $organization->display_name,
            'jurisdiction' => $organization->jurisdiction, 'registration_number' => $organization->registration_number,
        ];
    }

    private function pdfPayload(ProCertificate $certificate, array $issuer): array
    {
        $profile = $this->profile($certificate);
        unset($profile['recipient_email']);
        $payload = [
            'schema' => 'iuoamc-pro-certificate-v1', 'record_uuid' => $certificate->record_uuid,
            'certificate_number' => $certificate->certificate_number,
        ] + $profile + [
            'issued_at' => $this->iso($certificate->issued_at), 'issuer' => $issuer,
            'verification_url' => $certificate->public_token === null ? '' : 'https://iuoamc.pro/verify/c/'.$certificate->public_token,
            'template_version' => self::TEMPLATE_VERSION,
        ];
        if ($this->version($certificate) >= 2) {
            $payload['schema'] = match (true) {
                $this->version($certificate) >= 4 => 'iuoamc-pro-certificate-v4',
                $this->version($certificate) >= 3 => 'iuoamc-pro-certificate-v3',
                default => 'iuoamc-pro-certificate-v2',
            };
            $payload['catalog_snapshot'] = $certificate->catalog_snapshot;
            $payload['template_version'] = $this->version($certificate) >= 3
                ? 'IUOAMC-PRO-CERT-1.2.0'
                : 'IUOAMC-PRO-CERT-1.1.0';
        }
        $payload['statement'] = str_replace('[RECIPIENT NAME]', $certificate->recipient_name, $payload['statement']);
        return $payload;
    }

    private function nextNumber(int $year, ProCertificate $certificate): string
    {
        if ($this->version($certificate) >= 2) {
            $type = (int) $certificate->catalog_type_id;
            DB::table('pro_certificate_type_sequences')->insertOrIgnore(['type_id' => $type, 'year' => $year, 'last_number' => 0]);
            $query = DB::table('pro_certificate_type_sequences')->where('type_id', $type)->where('year', $year);
            $sequence = (clone $query)->lockForUpdate()->first();
            if ($sequence === null || (int) $sequence->last_number >= 999999) {
                throw new RuntimeException('Certificate type number sequence unavailable or exhausted.');
            }
            $next = (int) $sequence->last_number + 1;
            $query->update(['last_number' => $next]);
            return $certificate->catalog_snapshot['number_prefix'].'-'.$year.'-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
        }
        DB::table('pro_certificate_sequences')->insertOrIgnore(['year' => $year, 'last_number' => 0]);
        $sequence = DB::table('pro_certificate_sequences')->where('year', $year)->lockForUpdate()->first();
        if ($sequence === null || (int) $sequence->last_number >= 999999) {
            throw new RuntimeException('Certificate number sequence unavailable or exhausted.');
        }
        $next = (int) $sequence->last_number + 1;
        DB::table('pro_certificate_sequences')->where('year', $year)->update(['last_number' => $next]);

        return 'IUOAMC-PRO-CERT-'.$year.'-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    private function snapshot(ProCertificate $certificate): array
    {
        $profile = $this->profile($certificate);
        unset($profile['recipient_email']);
        $data = [
            'schema' => 'iuoamc-pro-certificate-current-state-v1', 'id' => (int) $certificate->id,
            'record_uuid' => $certificate->record_uuid, 'organization_id' => (int) $certificate->organization_id,
            'status' => $certificate->status, 'lock_version' => $certificate->lock_version,
            'certificate_number' => $certificate->certificate_number, 'public_token' => $certificate->public_token,
            'created_by' => $certificate->created_by, 'updated_by' => $certificate->updated_by,
            'approved_by' => $certificate->approved_by, 'issued_by' => $certificate->issued_by, 'revoked_by' => $certificate->revoked_by,
            'created_at' => $this->iso($certificate->created_at), 'updated_at' => $this->iso($certificate->updated_at),
            'approved_at' => $this->iso($certificate->approved_at), 'issued_at' => $this->iso($certificate->issued_at),
            'revoked_at' => $this->iso($certificate->revoked_at),
            // Bind the exact encrypted reason; no plaintext reason is copied into an audit payload.
            'last_reason_encrypted' => $certificate->getAttributes()['last_reason'] ?? null,
            'pdf_path' => $certificate->pdf_path, 'pdf_sha256' => $certificate->pdf_sha256,
            'issued_payload' => $certificate->issued_payload, 'payload_sha256' => $certificate->payload_sha256,
            'signature' => $certificate->signature, 'signing_key_id' => $certificate->signing_key_id,
        ];

        if ($this->version($certificate) >= 2) {
            $data['schema'] = match (true) {
                $this->version($certificate) >= 4 => 'iuoamc-pro-certificate-current-state-v4',
                $this->version($certificate) >= 3 => 'iuoamc-pro-certificate-current-state-v3',
                default => 'iuoamc-pro-certificate-current-state-v2',
            };
            $data['schema_version'] = $this->version($certificate);
            $data['catalog_type_id'] = (int) $certificate->catalog_type_id;
            $data['catalog_snapshot'] = $certificate->catalog_snapshot;
        }
        if ($this->version($certificate) >= 4) {
            $data['recipient_email_encrypted'] = $certificate->getAttributes()['recipient_email'] ?? null;
            foreach (self::PADES_FIELDS as $field) {
                $data[$field] = $field === 'pdf_signed_at'
                    ? $this->iso($certificate->pdf_signed_at)
                    : $certificate->getAttribute($field);
            }
        }
        return $data + $profile;
    }

    private function auditValues(ProCertificate $certificate): array
    {
        $values = [
            'record_uuid' => $certificate->record_uuid, 'organization_id' => (int) $certificate->organization_id,
            'certificate_number' => $certificate->certificate_number, 'status' => $certificate->status,
            'lock_version' => $certificate->lock_version, 'record_hash' => $certificate->record_hash,
            'updated_by' => $certificate->updated_by, 'approved_by' => $certificate->approved_by,
            'issued_by' => $certificate->issued_by, 'revoked_by' => $certificate->revoked_by,
            'payload_sha256' => $certificate->payload_sha256, 'pdf_sha256' => $certificate->pdf_sha256,
        ];
        if ($this->version($certificate) >= 4) {
            $values['pdf_signature_profile'] = $certificate->pdf_signature_profile;
            $values['pdf_signature_status'] = $certificate->pdf_signature_status;
            $values['pdf_signing_certificate_sha256'] = $certificate->pdf_signing_certificate_sha256;
            $values['pdf_signed_at'] = $this->iso($certificate->pdf_signed_at);
        }

        return $values;
    }

    private function append(ProCertificate $certificate, User $actor, string $action, array $old): void
    {
        $certificate->record_hash = ProCertificateSigner::digest($this->snapshot($certificate));
        $metadata = ['module' => 'pro_certificates', 'organization_id' => (int) $certificate->organization_id,
            'record_hash' => $certificate->record_hash];
        if ($certificate->last_reason !== null) {
            $metadata['reason_encrypted'] = Crypt::encryptString($certificate->last_reason);
        }
        $audit = AuditTrail::record('pro_certificate.'.$action, $certificate, $old,
            $this->auditValues($certificate), $metadata, (int) $actor->id);
        $certificate->integrity_audit_id = (int) $audit->id;
        // Linking the audit must not advance updated_at after its value has been sealed.
        $timestamps = $certificate->timestamps;
        try {
            $certificate->timestamps = false;
            $certificate->saveQuietly();
        } finally {
            $certificate->timestamps = $timestamps;
        }
        $certificate->unsetRelation('integrityAudit');
    }

    private function validAuditTransition(AuditLog $audit, ?AuditLog $prior): bool
    {
        $new = $audit->new_values ?? [];
        if ($prior === null) {
            return $audit->event === 'pro_certificate.created' && ($new['status'] ?? null) === 'draft'
                && ($audit->old_values ?? []) === [];
        }
        $old = $audit->old_values ?? [];
        if (ProCertificateSigner::canonicalJson($old) !== ProCertificateSigner::canonicalJson($prior->new_values)) {
            return false;
        }
        if ($audit->event === 'pro_certificate.updated') {
            return ($old['status'] ?? null) === 'draft' && ($new['status'] ?? null) === 'draft';
        }
        $action = substr((string) $audit->event, strlen('pro_certificate.'));
        if ($audit->event !== 'pro_certificate.'.$action || ! isset(self::POLICY[$action])) { return false; }

        return in_array($old['status'] ?? null, self::POLICY[$action][1], true)
            && ($new['status'] ?? null) === self::POLICY[$action][2];
    }

    private function validState(ProCertificate $certificate): bool
    {
        if (! preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/D', (string) $certificate->record_uuid)
            || $certificate->lock_version < 1 || $certificate->created_by < 1 || $certificate->updated_by < 1
            || ! in_array($certificate->status, ['draft', 'review', 'approved', 'issued', 'revoked'], true)
            || $certificate->created_at === null || $certificate->updated_at === null) {
            return false;
        }
        $version = $this->version($certificate);
        if ($version < 4) {
            if ($certificate->recipient_email !== null) {
                return false;
            }
            foreach (self::PADES_FIELDS as $field) {
                if ($certificate->getAttribute($field) !== null) {
                    return false;
                }
            }
        }
        if ($version === 1) {
            if ($certificate->catalog_type_id !== null || $certificate->catalog_snapshot !== null
                || $certificate->specialization !== null || $certificate->credential_basis !== null
                || $certificate->accreditation_reference !== null || $certificate->accreditation_date !== null) { return false; }
        } elseif (in_array($version, [2, 3, 4], true)) {
            if (! ProCertificateCatalog::validSnapshot($certificate->catalog_snapshot, (int) $certificate->catalog_type_id, (int) $certificate->organization_id)
                || $certificate->certificate_type !== $certificate->catalog_snapshot['category']) { return false; }
            if ($version === 2 && ($certificate->credential_basis !== null
                || $certificate->accreditation_reference !== null || $certificate->accreditation_date !== null)) { return false; }
            if ($version >= 3 && $certificate->credential_basis === null) { return false; }
        } else { return false; }
        $this->validatedProfile($this->profile($certificate), $version);
        $approved = in_array($certificate->status, ['approved', 'issued', 'revoked'], true);
        if ($approved !== ($certificate->approved_at !== null && $certificate->approved_by !== null)
            || (! $approved && ($certificate->approved_at !== null || $certificate->approved_by !== null))) {
            return false;
        }
        if ($certificate->status === 'revoked') {
            if ($certificate->revoked_at === null || $certificate->revoked_by === null
                || ! is_string($certificate->last_reason) || trim($certificate->last_reason) === '') { return false; }
        } elseif ($certificate->revoked_at !== null || $certificate->revoked_by !== null) {
            return false;
        }
        if (in_array($certificate->status, ['issued', 'revoked'], true)) {
            foreach (self::ISSUED_FIELDS as $field) {
                if ($certificate->getAttribute($field) === null || $certificate->getAttribute($field) === '') { return false; }
            }
            if ($version >= 4) {
                foreach (self::PADES_FIELDS as $field) {
                    if ($certificate->getAttribute($field) === null || $certificate->getAttribute($field) === '') { return false; }
                }
                if ($certificate->pdf_signature_status !== 'valid'
                    || ! in_array($certificate->pdf_signature_profile, ['PAdES-B-B', 'PAdES-B-T'], true)
                    || preg_match('/\A[0-9a-f]{64}\z/D', (string) $certificate->pdf_signing_certificate_sha256) !== 1) {
                    return false;
                }
            }
            return preg_match('/\A'.preg_quote($version >= 2 ? $certificate->catalog_snapshot['number_prefix'] : 'IUOAMC-PRO-CERT', '/').'-'.preg_quote($certificate->issued_at->format('Y'), '/').'-[0-9]{6}\z/D', $certificate->certificate_number) === 1
                && preg_match('/\A[0-9a-f]{64}\z/D', $certificate->public_token) === 1
                && preg_match('/\A[0-9a-f]{64}\z/D', $certificate->pdf_sha256) === 1;
        }
        foreach (self::ISSUED_FIELDS as $field) {
            if ($certificate->getAttribute($field) !== null) { return false; }
        }
        if ($version >= 4) {
            foreach (self::PADES_FIELDS as $field) {
                if ($certificate->getAttribute($field) !== null) { return false; }
            }
        }

        return true;
    }

    private function version(ProCertificate $certificate): int
    {
        return (int) ($certificate->schema_version ?? 1);
    }

    private function iso(?DateTimeInterface $date): ?string
    {
        return $date === null ? null : \Carbon\CarbonImmutable::instance($date)->utc()->format('Y-m-d\TH:i:sP');
    }

    private function safePdfPath(ProCertificate $certificate): string
    {
        $relative = 'pro-certificates/'.$certificate->issued_at->format('Y').'/'.$certificate->record_uuid.'.pdf';
        if ($certificate->pdf_path !== $relative) { throw new RuntimeException('Invalid certificate PDF path.'); }
        $directory = $this->privateDirectory($certificate->issued_at->format('Y'), false);
        $path = $directory.'/'.$certificate->record_uuid.'.pdf';
        if (is_link($path) || ! is_file($path) || ! is_readable($path) || realpath($path) !== $path) {
            throw new RuntimeException('Certificate PDF is unavailable.');
        }

        return $path;
    }

    private function privateDirectory(string $year, bool $create): string
    {
        if (! preg_match('/\A[0-9]{4}\z/D', $year)) { throw new RuntimeException('Invalid certificate year.'); }
        $app = realpath(storage_path('app'));
        if ($app === false || ! is_dir($app)) { throw new RuntimeException('Private certificate storage is unavailable.'); }
        $directory = $app;
        foreach (['private', 'pro-certificates', $year] as $segment) {
            $directory .= '/'.$segment;
            if (is_link($directory)) { throw new RuntimeException('Certificate storage must not contain symlinks.'); }
            if (! is_dir($directory) && $create && ! @mkdir($directory, 0700) && ! is_dir($directory)) {
                throw new RuntimeException('Private certificate storage cannot be created.');
            }
            if (! is_dir($directory) || realpath($directory) !== $directory) {
                throw new RuntimeException('Private certificate storage is unavailable.');
            }
        }

        return $directory;
    }

    private function writePdf(string $relative, string $bytes): string
    {
        if (! preg_match('/\Apro-certificates\/([0-9]{4})\/([0-9a-f-]{36})\.pdf\z/D', $relative, $matches)) {
            throw new RuntimeException('Invalid certificate PDF path.');
        }
        $path = $this->privateDirectory($matches[1], true).'/'.$matches[2].'.pdf';
        if (file_exists($path) || is_link($path)) { throw new RuntimeException('Certificate PDF already exists.'); }
        $stream = fopen($path, 'xb');
        if ($stream === false) { throw new RuntimeException('Certificate PDF cannot be stored.'); }
        try {
            if (! chmod($path, 0600)) { throw new RuntimeException('Certificate PDF cannot be protected.'); }
            $written = 0;
            $length = strlen($bytes);
            while ($written < $length) {
                $count = fwrite($stream, substr($bytes, $written));
                if ($count === false || $count === 0) { throw new RuntimeException('Certificate PDF write failed.'); }
                $written += $count;
            }
            if (! fflush($stream) || ! fsync($stream)) { throw new RuntimeException('Certificate PDF could not be flushed.'); }
        } catch (Throwable $error) {
            fclose($stream);
            unlink($path);
            throw $error;
        }
        fclose($stream);

        return $path;
    }

    private function stop(string $message): never
    {
        throw ValidationException::withMessages(['record' => trans('certificates.errors.'.$message)]);
    }
}
