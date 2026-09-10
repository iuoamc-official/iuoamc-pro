<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\ProCertificate;
use App\Models\User;
use App\Models\WicpRecord;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/** A local WICP documentation register. It does not change or reissue a certificate. */
final class WicpRegistry
{
    public const AUTHORITY_CODE = 'WICP-UK-SC882611';
    public const AUTHORITY_NUMBER = 'SC882611';
    public const AUTHORITY_LEGAL_NAME = 'WORLD CENTRE FOR INTELLECTUAL PROTECTION LTD';

    public function requirePermission(User $actor, string $permission): void
    {
        abort_unless(in_array($permission, ['wicp.view', 'wicp.register', 'wicp.revoke'], true), 403);
        abort_unless($actor->exists && $actor->status === 'active'
            && User::query()->whereKey($actor->getKey())->where('status', 'active')->exists()
            && $actor->canDo('wicp.view') && $actor->canDo($permission), 403);
    }

    public function query(User $actor): Builder
    {
        $this->requirePermission($actor, 'wicp.view');
        return WicpRecord::query()->whereIn('organization_id', app(InstitutionalAccess::class)->organizationIds($actor));
    }

    /** Resolve one existing institutional identity; never create or silently rename a company. */
    public function authority(): Organization
    {
        return $this->resolveAuthority(false);
    }

    private function resolveAuthority(bool $lock): Organization
    {
        $query = Organization::query()
            ->whereRaw('UPPER(TRIM(jurisdiction)) = ?', ['GB'])
            ->whereRaw('UPPER(TRIM(registration_number)) = ?', [self::AUTHORITY_NUMBER]);
        if ($lock) { $query->lockForUpdate(); }
        // A second row with this legal registration number is an ambiguity,
        // even when its descriptive name or canonical metadata does not match.
        $candidates = $query->get();
        if ($candidates->count() !== 1) { $this->stop('authority'); }
        $organization = $candidates->first();
        $legal = preg_replace('/\s+/u', ' ', trim((string) $organization->legal_name));
        $metadata = $organization->metadata;
        $canonical = is_array($metadata) && is_array($metadata['entity_registry'] ?? null)
            ? ($metadata['entity_registry']['canonical_code'] ?? null) : null;
        if ($organization->status !== 'active' || strtoupper((string) $legal) !== self::AUTHORITY_LEGAL_NAME
            || ($organization->code !== self::AUTHORITY_CODE && $canonical !== self::AUTHORITY_CODE)) {
            $this->stop('authority');
        }
        return $organization;
    }

    public function registerProgram(User $actor, array $data): WicpRecord
    {
        $this->requirePermission($actor, 'wicp.register');
        if (array_diff(array_keys($data), ['organization_id', 'program_code', 'program_title', 'program_version']) !== []) {
            $this->stop('input');
        }
        foreach (['program_code', 'program_title', 'program_version'] as $field) {
            if (isset($data[$field]) && is_string($data[$field])) { $data[$field] = trim($data[$field]); }
        }
        $data = Validator::make($data, [
            'organization_id' => ['required', 'integer', 'min:1'],
            'program_code' => ['required', 'string', 'max:80', 'regex:/\A[A-Z0-9][A-Z0-9._-]{0,79}\z/D'],
            'program_title' => ['required', 'string', 'max:255', $this->plainTextRule()],
            'program_version' => ['required', 'string', 'max:80', 'regex:/\A[A-Za-z0-9][A-Za-z0-9._-]{0,79}\z/D'],
        ])->validate();
        return DB::transaction(function () use ($actor, $data): WicpRecord {
            $authority = $this->mutationAuthority($actor);
            // Serialize program identities for this owner even before a unique key exists.
            $organization = Organization::query()->lockForUpdate()->findOrFail($data['organization_id']);
            app(InstitutionalAccess::class)->authorizeOrganization($actor, $organization);
            if ($organization->status !== 'active') { $this->stop('inactive_organization'); }
            $existing = $this->query($actor)->where('kind', 'program')->where('organization_id', $organization->id)
                ->where('program_code', $data['program_code'])->where('program_version', $data['program_version'])->lockForUpdate()->first();
            if ($existing !== null) {
                if ($existing->status !== 'registered' || $existing->program_title !== $data['program_title'] || ! $this->verify($existing)) {
                    $this->stop('identity_conflict');
                }
                return $existing;
            }
            $record = $this->prototype($actor, $authority, (int) $organization->id, 'program');
            $record->fill($data);
            $this->register($record, $actor, null);
            return $record->fresh(['organization']);
        }, 3);
    }

    public function registerCertificate(User $actor, int $certificateId, int $parentId): WicpRecord
    {
        $this->requirePermission($actor, 'wicp.register');
        if ($certificateId < 1 || $parentId < 1) { $this->stop('input'); }
        return DB::transaction(function () use ($actor, $certificateId, $parentId): WicpRecord {
            $authority = $this->mutationAuthority($actor);
            $sourceRegistry = app(ProCertificateRegistry::class);
            // This query also requires certificate viewing permission and owner scope.
            $certificate = $sourceRegistry->query($actor)->lockForUpdate()->findOrFail($certificateId);
            $parent = $this->query($actor)->lockForUpdate()->findOrFail($parentId);
            $organization = Organization::query()->lockForUpdate()->findOrFail($certificate->organization_id);
            app(InstitutionalAccess::class)->authorizeOrganization($actor, $organization);
            if ($organization->status !== 'active') { $this->stop('inactive_organization'); }
            if ($parent->kind !== 'program' || $parent->status !== 'registered'
                || $parent->organization_id !== $certificate->organization_id || ! $this->verify($parent)) {
                $this->stop('parent');
            }
            if ($certificate->status !== 'issued' || $sourceRegistry->effectiveStatus($certificate) !== 'issued'
                || ! $sourceRegistry->verify($certificate)) { $this->stop('source'); }
            $existing = WicpRecord::query()->where('certificate_id', $certificateId)->lockForUpdate()->first();
            if ($existing !== null) {
                if ($existing->organization_id !== $certificate->organization_id || $existing->parent_id !== $parentId
                    || $existing->status !== 'registered' || ! $this->verify($existing)) { $this->stop('identity_conflict'); }
                return $existing;
            }
            $record = $this->prototype($actor, $authority, (int) $organization->id, 'certificate');
            $record->fill([
                'parent_id' => (int) $parent->id, 'certificate_id' => (int) $certificate->id,
                'source_record_uuid' => $certificate->record_uuid, 'source_number' => $certificate->certificate_number,
                'source_payload_sha256' => $certificate->payload_sha256, 'source_pdf_sha256' => $certificate->pdf_sha256,
            ]);
            $this->register($record, $actor, $parent);
            return $record->fresh(['organization', 'parent']);
        }, 3);
    }

    public function revoke(User $actor, int $id, int $lockVersion, string $reason): WicpRecord
    {
        $this->requirePermission($actor, 'wicp.revoke');
        $reason = Validator::make(['reason' => trim($reason)], ['reason' => ['required', 'string', 'max:1500', $this->plainTextRule(true)]])->validate()['reason'];
        return DB::transaction(function () use ($actor, $id, $lockVersion, $reason): WicpRecord {
            $this->mutationAuthority($actor);
            $record = $this->query($actor)->lockForUpdate()->findOrFail($id);
            if ($lockVersion < 1 || $record->lock_version !== $lockVersion) { $this->stop('stale'); }
            if ($record->status !== 'registered') { $this->stop('transition'); }
            // A source certificate being revoked or expired does not block recording a WICP revocation.
            if (! $this->verifyOwn($record)) { $this->stop('integrity'); }
            $old = $this->auditValues($record);
            $record->status = 'revoked';
            $record->lock_version = 2;
            $record->updated_by = (int) $actor->id;
            $record->revoked_by = (int) $actor->id;
            $record->updated_at = now()->utc()->startOfSecond();
            $record->revoked_at = $record->updated_at;
            $record->last_reason = $reason;
            $record->save();
            $this->append($record, $actor, 'revoked', $old);
            return $record->fresh(['organization', 'parent']);
        }, 3);
    }

    /** Verify signed WICP state, its complete subject audit chain and all pinned content. */
    public function verify(WicpRecord $record): bool
    {
        try {
            if (! $this->verifyOwn($record)) { return false; }
            if ($record->kind === 'program') { return true; }
            $parent = WicpRecord::query()->find($record->parent_id);
            if ($parent === null || $parent->kind !== 'program' || ! $this->verifyOwn($parent)
                || $parent->organization_id !== $record->organization_id
                || ! hash_equals((string) $record->registration_payload['parent_binding_sha256'], (string) $parent->binding_sha256)
                || WicpSigner::canonicalJson($record->registration_payload['program']) !== WicpSigner::canonicalJson($parent->registration_payload['program'])) {
                return false;
            }
            $source = ProCertificate::query()->find($record->certificate_id);
            return $source !== null && $this->sourceMatches($record, $source) && app(ProCertificateRegistry::class)->verify($source);
        } catch (Throwable) { return false; }
    }

    public function publicRecord(string $token): ?WicpRecord
    {
        if (! preg_match('/\A[0-9a-f]{64}\z/D', $token)) { return null; }
        $record = WicpRecord::query()->where('public_token', $token)->first();
        // Keep the registry record accessible when the source becomes unavailable;
        // effectiveStatus explicitly withholds a successful source result.
        return $record !== null && $this->verifyOwn($record) ? $record : null;
    }

    public function effectiveStatus(WicpRecord $record): string
    {
        try {
            if (! $this->verifyOwn($record)) { return 'integrity_failed'; }
            if ($record->status === 'revoked') { return 'revoked'; }
            if ($record->kind === 'program') { return 'registered'; }
            $parent = WicpRecord::query()->find($record->parent_id);
            if ($parent === null || $parent->kind !== 'program' || ! $this->verifyOwn($parent)
                || $parent->organization_id !== $record->organization_id
                || ! hash_equals((string) $record->registration_payload['parent_binding_sha256'], (string) $parent->binding_sha256)
                || WicpSigner::canonicalJson($record->registration_payload['program']) !== WicpSigner::canonicalJson($parent->registration_payload['program'])) {
                return 'integrity_failed';
            }
            if ($parent->status === 'revoked') { return 'parent_revoked'; }
            $source = ProCertificate::query()->find($record->certificate_id);
            if ($source === null || ! $this->sourceMatches($record, $source) || ! app(ProCertificateRegistry::class)->verify($source)) { return 'source_unavailable'; }
            return match (app(ProCertificateRegistry::class)->effectiveStatus($source)) {
                'issued' => 'registered', 'revoked' => 'source_revoked', 'expired' => 'source_expired', default => 'source_unavailable',
            };
        } catch (Throwable) { return 'integrity_failed'; }
    }

    /** Public proof deliberately excludes recipients, private reasons and source verification tokens. */
    public function proof(WicpRecord $record): array
    {
        if (! $this->verifyOwn($record)) { $this->stop('integrity'); }
        $attestation = [
            'schema' => 'iuoamc-wicp-current-attestation-v1', 'reference' => $record->reference,
            'registration_sha256' => $record->binding_sha256, 'signed_state_sha256' => $record->payload_sha256,
            'registry_state' => $record->status, 'effective_status' => $this->effectiveStatus($record),
            'checked_at' => now()->utc()->startOfSecond()->toIso8601String(),
        ];
        $signer = app(WicpSigner::class);
        return [
            'schema' => 'iuoamc-wicp-proof-v1', 'registration' => $record->registration_payload,
            'signed_state' => $record->signed_payload,
            'record_seal' => $record->only(['payload_sha256', 'signature', 'signing_key_id']),
            'attestation' => $attestation, 'attestation_seal' => $signer->sign($attestation),
            'public_key' => $signer->publicKey(),
            'signature_format' => 'Ed25519 over IUOAMC-WICP-REGISTRY-SIGNATURE-V1 + NUL + raw SHA-256 of canonical JSON',
            'canonical_json' => 'Recursive bytewise sorted object keys; unchanged list order; UTF-8; unescaped Unicode and slashes; preserved zero fractions.',
        ];
    }

    private function mutationAuthority(User $actor): Organization
    {
        $authority = $this->resolveAuthority(true);
        app(InstitutionalAccess::class)->authorizeOrganization($actor, $authority);
        return $authority;
    }

    private function prototype(User $actor, Organization $authority, int $organizationId, string $kind): WicpRecord
    {
        $time = now()->utc()->startOfSecond();
        return new WicpRecord([
            'record_uuid' => (string) Str::uuid(), 'reference' => 'WICP-PRO-'.($kind === 'program' ? 'P' : 'C').'-'.$time->format('Y').'-'.bin2hex(random_bytes(16)),
            'public_token' => bin2hex(random_bytes(32)), 'kind' => $kind,
            'organization_id' => $organizationId, 'authority_organization_id' => (int) $authority->id,
            'authority_snapshot' => ['code' => $authority->code, 'canonical_code' => self::AUTHORITY_CODE,
                'legal_name' => self::AUTHORITY_LEGAL_NAME, 'jurisdiction' => 'GB', 'registration_number' => self::AUTHORITY_NUMBER],
            'status' => 'registered', 'lock_version' => 1, 'registered_at' => $time,
            'created_by' => (int) $actor->id, 'updated_by' => (int) $actor->id, 'created_at' => $time, 'updated_at' => $time,
        ]);
    }

    private function register(WicpRecord $record, User $actor, ?WicpRecord $parent): void
    {
        $record->registration_payload = $this->registrationPayload($record, $parent);
        $record->binding_sha256 = WicpSigner::digest($record->registration_payload);
        $record->save();
        $this->append($record, $actor, 'registered', []);
    }

    private function registrationPayload(WicpRecord $record, ?WicpRecord $parent): array
    {
        return [
            'schema' => 'iuoamc-wicp-registration-v1', 'record_uuid' => $record->record_uuid,
            'reference' => $record->reference, 'kind' => $record->kind,
            'authority' => $record->authority_snapshot,
            'program' => $record->kind === 'program'
                ? ['reference' => $record->reference, 'code' => $record->program_code, 'title' => $record->program_title, 'version' => $record->program_version]
                : $parent?->registration_payload['program'],
            'parent_binding_sha256' => $parent?->binding_sha256,
            'source' => $record->kind === 'program' ? null : [
                'record_uuid' => $record->source_record_uuid, 'certificate_number' => $record->source_number,
                'issued_payload_sha256' => $record->source_payload_sha256, 'pdf_sha256' => $record->source_pdf_sha256,
            ],
            'registered_at' => $this->iso($record->registered_at),
        ];
    }

    private function signedPayload(WicpRecord $record): array
    {
        return [
            'schema' => 'iuoamc-wicp-signed-state-v1', 'registration' => $record->registration_payload,
            'registration_sha256' => $record->binding_sha256,
            'status' => $record->status, 'lock_version' => $record->lock_version,
            'revoked_at' => $this->iso($record->revoked_at),
        ];
    }

    private function snapshot(WicpRecord $record): array
    {
        $values = $record->only([
            'record_uuid', 'reference', 'public_token', 'kind', 'organization_id', 'authority_organization_id', 'authority_snapshot',
            'parent_id', 'certificate_id', 'program_code', 'program_title', 'program_version',
            'source_record_uuid', 'source_number', 'source_payload_sha256', 'source_pdf_sha256',
            'registration_payload', 'binding_sha256', 'status', 'lock_version', 'created_by', 'updated_by', 'revoked_by',
            'signed_payload', 'payload_sha256', 'signature', 'signing_key_id',
        ]);
        return ['schema' => 'iuoamc-wicp-private-state-v1', 'id' => (int) $record->id] + $values + [
            'registered_at' => $this->iso($record->registered_at), 'revoked_at' => $this->iso($record->revoked_at),
            'created_at' => $this->iso($record->created_at), 'updated_at' => $this->iso($record->updated_at),
            'reason_encrypted' => $record->getAttributes()['last_reason'] ?? null,
        ];
    }

    private function auditValues(WicpRecord $record): array
    {
        return $record->only([
            'record_uuid', 'reference', 'organization_id', 'authority_organization_id', 'kind', 'parent_id', 'certificate_id',
            'binding_sha256', 'status', 'lock_version', 'updated_by', 'revoked_by', 'payload_sha256', 'record_hash',
        ]);
    }

    private function append(WicpRecord $record, User $actor, string $action, array $old): void
    {
        $record->signed_payload = $this->signedPayload($record);
        $record->fill(app(WicpSigner::class)->sign($record->signed_payload));
        $record->record_hash = WicpSigner::digest($this->snapshot($record));
        $audit = AuditTrail::record('wicp.'.$action, $record, $old, $this->auditValues($record),
            ['module' => 'wicp_registry', 'record_hash' => $record->record_hash], (int) $actor->id);
        $record->integrity_audit_id = (int) $audit->id;
        $timestamps = $record->timestamps;
        try { $record->timestamps = false; $record->saveQuietly(); }
        finally { $record->timestamps = $timestamps; }
        $record->unsetRelation('integrityAudit');
    }

    private function verifyOwn(WicpRecord $record): bool
    {
        try {
            if (! $record->exists) { return false; }
            $current = WicpRecord::query()->find($record->getKey());
            if ($current === null || $current->record_hash !== $record->record_hash || $current->integrity_audit_id !== $record->integrity_audit_id
                || WicpSigner::digest($this->snapshot($current)) !== WicpSigner::digest($this->snapshot($record))) { return false; }
            $record = $current;
            if (! $this->validState($record) || ! is_string($record->record_hash)
                || ! hash_equals($record->record_hash, WicpSigner::digest($this->snapshot($record)))
                || ! app(WicpSigner::class)->verify($record->signed_payload, $record->only(['payload_sha256', 'signature', 'signing_key_id']))) {
                return false;
            }
            $audits = AuditLog::query()->where('auditable_type', $record->getMorphClass())->where('auditable_id', $record->id)
                ->orderBy('sequence_number')->orderBy('id')->get();
            if ($audits->isEmpty() || $audits->count() !== $record->lock_version) { return false; }
            $prior = null;
            foreach ($audits as $offset => $audit) {
                $new = $audit->new_values ?? [];
                if (! app(IntegrityService::class)->verifyAuditLog($audit)['valid']
                    || $audit->auditable_type !== $record->getMorphClass() || (int) $audit->auditable_id !== (int) $record->id
                    || (int) ($new['lock_version'] ?? 0) !== $offset + 1 || (int) ($new['updated_by'] ?? 0) !== (int) $audit->actor_id
                    || ($audit->metadata['module'] ?? null) !== 'wicp_registry'
                    || ($audit->metadata['record_hash'] ?? null) !== ($new['record_hash'] ?? null)
                    || ! preg_match('/\A[0-9a-f]{64}\z/D', (string) ($new['record_hash'] ?? ''))
                    || ! preg_match('/\A[0-9a-f]{64}\z/D', (string) ($new['payload_sha256'] ?? ''))) { return false; }
                foreach (['record_uuid', 'reference', 'organization_id', 'authority_organization_id', 'kind', 'parent_id', 'certificate_id', 'binding_sha256'] as $field) {
                    if (($new[$field] ?? null) !== $record->getAttribute($field)) { return false; }
                }
                if ($prior === null) {
                    if ($audit->event !== 'wicp.registered' || ($new['status'] ?? null) !== 'registered'
                        || ($new['revoked_by'] ?? null) !== null || ($audit->old_values ?? []) !== []) { return false; }
                } else {
                    if ($audit->event !== 'wicp.revoked' || ($prior->new_values['status'] ?? null) !== 'registered'
                        || ($new['status'] ?? null) !== 'revoked' || (int) ($new['revoked_by'] ?? 0) !== (int) $audit->actor_id
                        || WicpSigner::canonicalJson($audit->old_values ?? []) !== WicpSigner::canonicalJson($prior->new_values)) { return false; }
                }
                $prior = $audit;
            }
            $last = $audits->last();
            return (int) $last->id === $record->integrity_audit_id
                && (int) $last->actor_id === $record->updated_by
                && WicpSigner::canonicalJson($last->new_values) === WicpSigner::canonicalJson($this->auditValues($record));
        } catch (Throwable) { return false; }
    }

    private function validState(WicpRecord $record): bool
    {
        if (! preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/D', (string) $record->record_uuid)
            || ! preg_match('/\A[0-9a-f]{64}\z/D', (string) $record->public_token)
            || ! in_array($record->kind, ['program', 'certificate'], true)
            || ! in_array($record->status, ['registered', 'revoked'], true)
            || $record->organization_id < 1 || $record->authority_organization_id < 1 || $record->created_by < 1 || $record->updated_by < 1
            || $record->registered_at === null || $record->created_at === null || $record->updated_at === null
            || $this->iso($record->registered_at) !== $this->iso($record->created_at)
            || ! preg_match('/\AWICP-PRO-'.($record->kind === 'program' ? 'P' : 'C').'-'.preg_quote($record->registered_at->format('Y'), '/').'-[0-9a-f]{32}\z/D', (string) $record->reference)) { return false; }
        $authority = $record->authority_snapshot;
        if (! is_array($authority) || count($authority) !== 5
            || array_diff(array_keys($authority), ['code', 'canonical_code', 'legal_name', 'jurisdiction', 'registration_number']) !== []
            || ! is_string($authority['code']) || $authority['code'] === ''
            || $authority['canonical_code'] !== self::AUTHORITY_CODE || $authority['legal_name'] !== self::AUTHORITY_LEGAL_NAME
            || $authority['jurisdiction'] !== 'GB' || $authority['registration_number'] !== self::AUTHORITY_NUMBER) { return false; }
        if ($record->status === 'registered') {
            if ($record->lock_version !== 1 || $record->revoked_at !== null || $record->revoked_by !== null || $record->last_reason !== null
                || $record->created_by !== $record->updated_by || $this->iso($record->created_at) !== $this->iso($record->updated_at)) { return false; }
        } else {
            if ($record->lock_version !== 2 || $record->revoked_at === null || $record->revoked_by < 1 || $record->revoked_by !== $record->updated_by
                || $record->revoked_at->lt($record->registered_at) || $this->iso($record->revoked_at) !== $this->iso($record->updated_at)
                || ! is_string($record->last_reason) || trim($record->last_reason) === '') { return false; }
        }
        if (! is_array($record->registration_payload) || ! is_array($record->signed_payload)
            || ! hash_equals((string) $record->binding_sha256, WicpSigner::digest($record->registration_payload))) { return false; }
        $parent = null;
        if ($record->kind === 'program') {
            if ($record->parent_id !== null || $record->certificate_id !== null || $record->source_record_uuid !== null
                || $record->source_number !== null || $record->source_payload_sha256 !== null || $record->source_pdf_sha256 !== null
                || ! preg_match('/\A[A-Z0-9][A-Z0-9._-]{0,79}\z/D', (string) $record->program_code)
                || ! preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]{0,79}\z/D', (string) $record->program_version)
                || ! is_string($record->program_title) || trim($record->program_title) === '' || mb_strlen($record->program_title) > 255
                || preg_match('//u', $record->program_title) !== 1 || preg_match('/[\x00-\x1F\x7F]/u', $record->program_title) !== 0) { return false; }
        } else {
            if ($record->parent_id < 1 || $record->parent_id === (int) $record->id || $record->certificate_id < 1
                || $record->program_code !== null || $record->program_title !== null || $record->program_version !== null
                || ! preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/D', (string) $record->source_record_uuid)
                || ! is_string($record->source_number) || $record->source_number === '' || mb_strlen($record->source_number) > 80
                || ! preg_match('/\A[0-9a-f]{64}\z/D', (string) $record->source_payload_sha256)
                || ! preg_match('/\A[0-9a-f]{64}\z/D', (string) $record->source_pdf_sha256)) { return false; }
            // Do not trust a relation already loaded by another caller. The immutable
            // program payload is compared here; its own signature is checked by verify().
            $parent = WicpRecord::query()->find($record->parent_id);
            if ($parent === null || $parent->kind !== 'program' || $parent->organization_id !== $record->organization_id
                || ! is_array($parent->registration_payload) || ! is_array($parent->registration_payload['program'] ?? null)) { return false; }
        }
        return WicpSigner::canonicalJson($record->registration_payload) === WicpSigner::canonicalJson($this->registrationPayload($record, $parent))
            && WicpSigner::canonicalJson($record->signed_payload) === WicpSigner::canonicalJson($this->signedPayload($record));
    }

    private function sourceMatches(WicpRecord $record, ProCertificate $source): bool
    {
        return $source->organization_id === $record->organization_id
            && in_array($source->status, ['issued', 'revoked'], true)
            && $source->record_uuid === $record->source_record_uuid && $source->certificate_number === $record->source_number
            && is_string($source->payload_sha256) && hash_equals((string) $record->source_payload_sha256, $source->payload_sha256)
            && is_string($source->pdf_sha256) && hash_equals((string) $record->source_pdf_sha256, $source->pdf_sha256);
    }

    private function plainTextRule(bool $multiline = false): \Closure
    {
        return static function (string $attribute, mixed $value, \Closure $fail) use ($multiline): void {
            if (is_string($value) && (preg_match('//u', $value) !== 1
                || preg_match($multiline ? '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u' : '/[\x00-\x1F\x7F]/u', $value) !== 0)) {
                $fail(trans('validation.string', ['attribute' => $attribute]));
            }
        };
    }

    private function iso(?DateTimeInterface $date): ?string
    {
        return $date === null ? null : \Carbon\CarbonImmutable::instance($date)->utc()->format('Y-m-d\TH:i:sP');
    }

    private function stop(string $key): never
    {
        throw ValidationException::withMessages(['wicp' => trans('wicp.errors.'.$key)]);
    }
}
