<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\ProCertificateType;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

final class ProCertificateCatalog
{
    public const FIELDS = ['code', 'number_prefix', 'name_ar', 'name_en', 'name_fr', 'category', 'layout',
        'title_ar', 'title_en', 'title_fr', 'statement_ar', 'statement_en', 'statement_fr',
        'signatory_name', 'signatory_title', 'active'];

    public function requirePermission(User $actor): void
    {
        app(ProCertificateRegistry::class)->requirePermission($actor, 'certificates.view');
        abort_unless($actor->canDo('certificates.catalog'), 403);
    }

    public function query(User $actor): Builder
    {
        app(ProCertificateRegistry::class)->requirePermission($actor, 'certificates.view');
        return ProCertificateType::query()->whereIn('organization_id', app(InstitutionalAccess::class)->organizationIds($actor));
    }

    public function create(User $actor, array $data): ProCertificateType
    {
        $this->requirePermission($actor);
        $this->rejectSystemInput($data);
        $org = Validator::make($data, ['organization_id' => ['required', 'integer', 'min:1']])->validate();
        $values = $this->validateProfile($data);
        return DB::transaction(function () use ($actor, $org, $values): ProCertificateType {
            $organization = Organization::query()->lockForUpdate()->findOrFail($org['organization_id']);
            app(InstitutionalAccess::class)->authorizeOrganization($actor, $organization);
            if ($organization->status !== 'active') { $this->stop('inactive_organization'); }
            $this->validateUnique($values, (int) $organization->id);
            $time = now()->utc()->startOfSecond();
            $type = ProCertificateType::create($values + ['organization_id' => (int) $organization->id,
                'lock_version' => 1, 'created_by' => (int) $actor->id, 'updated_by' => (int) $actor->id,
                'created_at' => $time, 'updated_at' => $time]);
            $this->append($type, $actor, 'created', []);
            return $type->fresh(['organization']);
        }, 3);
    }

    public function update(User $actor, int $id, int $lockVersion, array $data): ProCertificateType
    {
        $this->requirePermission($actor);
        $this->rejectSystemInput($data);
        return DB::transaction(function () use ($actor, $id, $lockVersion, $data): ProCertificateType {
            $type = $this->query($actor)->lockForUpdate()->findOrFail($id);
            if ($lockVersion < 1 || $type->lock_version !== $lockVersion) { $this->stop('stale'); }
            if (! $this->verify($type)) { $this->stop('integrity'); }
            foreach (['organization_id', 'code', 'number_prefix'] as $field) {
                if (array_key_exists($field, $data) && (string) $data[$field] !== (string) $type->getAttribute($field)) {
                    $this->stop('catalog_locked');
                }
            }
            $values = $this->validateProfile(array_replace($type->only(self::FIELDS), $data));
            $old = $this->auditValues($type);
            $type->fill($values);
            if (! $type->isDirty()) { return $type; }
            $type->lock_version++;
            $type->updated_by = (int) $actor->id;
            $type->updated_at = now()->utc()->startOfSecond();
            $type->save();
            $this->append($type, $actor, 'updated', $old);
            return $type->fresh(['organization']);
        }, 3);
    }

    /** Current configuration is consulted only to prepare or authorize new work. */
    public function snapshotFor(User $actor, int $id, int $organizationId): array
    {
        $query = $this->query($actor)->where('organization_id', $organizationId);
        if (DB::transactionLevel() > 0) { $query->lockForUpdate(); }
        $type = $query->findOrFail($id);
        if (! $this->verify($type)) { $this->stop('integrity'); }
        $organization = Organization::query()->findOrFail($organizationId);
        app(InstitutionalAccess::class)->authorizeOrganization($actor, $organization);
        if (! $type->active || $organization->status !== 'active') { $this->stop('inactive_type'); }
        return ['id' => (int) $type->id, 'organization_id' => (int) $type->organization_id,
            'code' => $type->code, 'number_prefix' => $type->number_prefix,
            'names' => ['ar' => $type->name_ar, 'en' => $type->name_en, 'fr' => $type->name_fr],
            'category' => $type->category, 'layout' => $type->layout, 'version' => $type->lock_version];
    }

    /** Prefills contain no personal recipient data. Explicit user overrides remain drafts. */
    public function defaults(User $actor, int $id, string $language): array
    {
        Validator::make(['language' => $language], ['language' => ['required', Rule::in(['ar', 'en', 'fr'])]])->validate();
        $query = $this->query($actor);
        if (DB::transactionLevel() > 0) { $query->lockForUpdate(); }
        $type = $query->findOrFail($id);
        $this->snapshotFor($actor, $id, (int) $type->organization_id);
        return ['catalog_type_id' => (int) $type->id, 'organization_id' => (int) $type->organization_id,
            'language' => $language, 'certificate_type' => $type->category,
            'certificate_title' => $type->getAttribute('title_'.$language),
            'statement' => $type->getAttribute('statement_'.$language),
            'signatory_name' => $type->signatory_name, 'signatory_title' => $type->signatory_title];
    }

    /** Validate frozen data without querying mutable catalog rows or their active state. */
    public static function validSnapshot(mixed $snapshot, int $id, int $organizationId): bool
    {
        if ($id < 1 || $organizationId < 1 || ! is_array($snapshot) || array_diff(array_keys($snapshot), ['id', 'organization_id', 'code', 'number_prefix', 'names', 'category', 'layout', 'version']) !== []
            || count($snapshot) !== 8 || ! is_int($snapshot['id'] ?? null) || $snapshot['id'] !== $id
            || ! is_int($snapshot['organization_id'] ?? null) || $snapshot['organization_id'] !== $organizationId
            || ! is_int($snapshot['version'] ?? null) || $snapshot['version'] < 1
            || ! is_array($snapshot['names'] ?? null) || count($snapshot['names']) !== 3
            || array_diff(array_keys($snapshot['names']), ['ar', 'en', 'fr']) !== []) { return false; }
        $validation = Validator::make($snapshot, [
            'code' => ['required', 'string', 'regex:/\A[A-Z][A-Z0-9-]{1,19}\z/D'],
            'number_prefix' => ['required', 'string', 'regex:/\A[A-Z][A-Z0-9-]{1,39}\z/D', Rule::notIn(['IUOAMC-PRO-CERT'])],
            'category' => ['required', Rule::in(['participation', 'completion', 'appreciation', 'diploma', 'professional_master'])],
            'layout' => ['required', Rule::in(['classic', 'diploma', 'master_a4_v1'])],
            'names.ar' => ['required', 'string', 'max:120', self::plain(false)],
            'names.en' => ['required', 'string', 'max:120', self::plain(false)],
            'names.fr' => ['required', 'string', 'max:120', self::plain(false)],
        ]);
        return ! $validation->fails()
            && (($snapshot['layout'] ?? null) !== 'master_a4_v1'\n                || in_array($snapshot['category'] ?? null, ['diploma', 'professional_master'], true));
    }

    public function verify(ProCertificateType $type): bool
    {
        try {
            if (! $type->exists || $type->lock_version < 1 || ! is_string($type->record_hash)) { return false; }
            $current = ProCertificateType::query()->find($type->id);
            if ($current === null || $current->integrity_audit_id !== $type->integrity_audit_id
                || $current->record_hash !== $type->record_hash
                || ProCertificateSigner::digest($this->snapshot($current)) !== ProCertificateSigner::digest($this->snapshot($type))) { return false; }
            $this->validateProfile($current->only(self::FIELDS));
            if (! hash_equals($current->record_hash, ProCertificateSigner::digest($this->snapshot($current)))) { return false; }
            $audits = AuditLog::query()->where('auditable_type', $type->getMorphClass())->where('auditable_id', $type->id)
                ->orderBy('sequence_number')->orderBy('id')->get();
            if ($audits->count() !== $type->lock_version) { return false; }
            $previous = null;
            foreach ($audits as $index => $audit) {
                $new = $audit->new_values ?? [];
                if (! app(IntegrityService::class)->verifyAuditLog($audit)['valid']
                    || $audit->event !== 'pro_certificate_type.'.($index === 0 ? 'created' : 'updated')
                    || (int) ($new['lock_version'] ?? 0) !== $index + 1
                    || (int) ($new['organization_id'] ?? 0) !== $type->organization_id
                    || ($new['code'] ?? null) !== $type->code || ($new['number_prefix'] ?? null) !== $type->number_prefix
                    || (int) ($new['updated_by'] ?? 0) !== (int) $audit->actor_id
                    || ($audit->metadata['module'] ?? null) !== 'pro_certificate_catalog'
                    || ($audit->metadata['record_hash'] ?? null) !== ($new['record_hash'] ?? null)
                    || ProCertificateSigner::canonicalJson($audit->old_values ?? []) !== ProCertificateSigner::canonicalJson($previous?->new_values ?? [])) { return false; }
                $previous = $audit;
            }
            return $previous !== null && (int) $previous->id === $type->integrity_audit_id
                && ProCertificateSigner::canonicalJson($previous->new_values) === ProCertificateSigner::canonicalJson($this->auditValues($current));
        } catch (Throwable) { return false; }
    }

    private function validateProfile(array $data): array
    {
        $values = array_intersect_key($data, array_flip(self::FIELDS));
        foreach ($values as $field => $value) { if (is_string($value)) { $values[$field] = trim($value); } }
        $rules = ['code' => ['required', 'string', 'regex:/\A[A-Z][A-Z0-9-]{1,19}\z/D'],
            'number_prefix' => ['required', 'string', 'regex:/\A[A-Z][A-Z0-9-]{1,39}\z/D', Rule::notIn(['IUOAMC-PRO-CERT'])],
            'category' => ['required', Rule::in(['participation', 'completion', 'appreciation', 'diploma', 'professional_master'])],
            'layout' => ['required', Rule::in(['classic', 'diploma', 'master_a4_v1'])],
            'signatory_name' => ['required', 'string', 'max:120', self::plain(false)],
            'signatory_title' => ['required', 'string', 'max:120', self::plain(false)], 'active' => ['required', 'boolean']];
        foreach (['ar', 'en', 'fr'] as $locale) {
            $rules['name_'.$locale] = ['required', 'string', 'max:120', self::plain(false)];
            $rules['title_'.$locale] = ['required', 'string', 'max:120', self::plain(false)];
            $rules['statement_'.$locale] = ['required', 'string', 'max:1500', self::plain(true)];
        }
        $result = Validator::make($values, $rules)->validate();
        if ($result['layout'] === 'master_a4_v1'\n            && ! in_array($result['category'], ['diploma', 'professional_master'], true)) {
            throw ValidationException::withMessages(['category' => trans('validation.in', ['attribute' => 'category'])]);
        }
        $result['active'] = (bool) $result['active'];
        return $result;
    }

    private function validateUnique(array $values, int $organizationId): void
    {
        Validator::make($values, [
            'code' => [Rule::unique('pro_certificate_types', 'code')->where('organization_id', $organizationId)],
            'number_prefix' => [Rule::unique('pro_certificate_types', 'number_prefix')],
        ])->validate();
    }

    private function rejectSystemInput(array $data): void
    {
        foreach (['id', 'lock_version', 'created_by', 'updated_by', 'record_hash', 'integrity_audit_id', 'created_at', 'updated_at'] as $field) {
            if (array_key_exists($field, $data)) { $this->stop('transition'); }
        }
    }

    private static function plain(bool $multiline): \Closure
    {
        return static function (string $attribute, mixed $value, \Closure $fail) use ($multiline): void {
            if (is_string($value) && (preg_match('//u', $value) !== 1
                || preg_match($multiline ? '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u' : '/[\x00-\x1F\x7F]/u', $value) !== 0)) {
                $fail(trans('validation.string', ['attribute' => $attribute]));
            }
        };
    }

    private function snapshot(ProCertificateType $type): array
    {
        return ['schema' => 'iuoamc-pro-certificate-type-state-v1', 'id' => (int) $type->id,
            'organization_id' => (int) $type->organization_id, 'lock_version' => $type->lock_version,
            'created_by' => $type->created_by, 'updated_by' => $type->updated_by,
            'created_at' => $this->iso($type->created_at), 'updated_at' => $this->iso($type->updated_at)] + $type->only(self::FIELDS);
    }

    private function auditValues(ProCertificateType $type): array
    {
        return ['organization_id' => (int) $type->organization_id, 'code' => $type->code, 'number_prefix' => $type->number_prefix,
            'lock_version' => $type->lock_version, 'record_hash' => $type->record_hash, 'updated_by' => $type->updated_by,
            'active' => $type->active];
    }

    private function append(ProCertificateType $type, User $actor, string $action, array $old): void
    {
        $type->record_hash = ProCertificateSigner::digest($this->snapshot($type));
        $audit = AuditTrail::record('pro_certificate_type.'.$action, $type, $old, $this->auditValues($type),
            ['module' => 'pro_certificate_catalog', 'record_hash' => $type->record_hash], (int) $actor->id);
        $type->integrity_audit_id = (int) $audit->id;
        $timestamps = $type->timestamps;
        try { $type->timestamps = false; $type->saveQuietly(); }
        finally { $type->timestamps = $timestamps; }
    }

    private function iso(?DateTimeInterface $date): ?string
    {
        return $date === null ? null : \Carbon\CarbonImmutable::instance($date)->utc()->format('Y-m-d\TH:i:sP');
    }

    private function stop(string $key): never
    {
        throw ValidationException::withMessages(['catalog' => trans((in_array($key, ['catalog_locked', 'inactive_type'], true)
            ? 'certificate_catalog.errors.' : 'certificates.errors.').$key)]);
    }
}
