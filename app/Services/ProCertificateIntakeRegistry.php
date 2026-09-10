<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\ProCertificateIntake;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

final class ProCertificateIntakeRegistry
{
    public const RESPONSE_FIELDS = ['name_ar', 'name_en', 'email', 'phone', 'country', 'specialization', 'notes', 'confirmation'];
    private const SOURCE_FIELDS = ['name_ar', 'name_en', 'registration_number', 'certificate_number', 'source_url',
        'program_code', 'program_name_ar', 'program_name_en', 'completion_date', 'source_type', 'status', 'version'];

    public function requirePermission(User $actor, string $permission = 'certificates.manage'): void
    {
        abort_unless(in_array($permission, ['certificates.manage', 'certificates.review'], true), 403);
        app(ProCertificateRegistry::class)->requirePermission($actor, 'certificates.manage');
        if ($permission === 'certificates.review') {
            app(ProCertificateRegistry::class)->requirePermission($actor, 'certificates.review');
        }
    }

    public function query(User $actor): Builder
    {
        $this->requirePermission($actor);
        return ProCertificateIntake::query()->whereIn('organization_id', app(InstitutionalAccess::class)->organizationIds($actor));
    }

    /** Import exact reviewed source values only. An existing identity is never silently overwritten. */
    public function importSource(User $actor, int $organizationId, array $rows): array
    {
        $this->requirePermission($actor);
        if (!array_is_list($rows) || count($rows) > 5000 || $organizationId < 1) { $this->invalidSource(); }
        $seen = [];
        foreach ($rows as $row) {
            if (!is_array($row)) { $this->invalidSource(); }
            $this->validateSource($row);
            $identity = $row['source_system']."\0".$row['source_id'];
            if (isset($seen[$identity])) { $this->invalidSource(); }
            $seen[$identity] = true;
        }

        return DB::transaction(function () use ($actor, $organizationId, $rows): array {
            $this->requirePermission($actor);
            $organization = Organization::query()->lockForUpdate()->findOrFail($organizationId);
            app(InstitutionalAccess::class)->authorizeOrganization($actor, $organization);
            abort_unless($organization->status === 'active', 409, __('certificate_intake.errors.organization'));
            $result = ['created' => 0, 'existing' => 0];
            foreach ($rows as $row) {
                $record = ProCertificateIntake::query()->where('organization_id', $organizationId)
                    ->where('source_system', $row['source_system'])->where('source_id', $row['source_id'])->lockForUpdate()->first();
                if ($record !== null) {
                    if (!$this->verify($record)
                        || $record->source_certificate_number !== ($row['source_certificate_number'] ?? null)
                        || $this->json($record->source_snapshot) !== $this->json($row['source_snapshot'])) { $this->invalidSource(); }
                    $result['existing']++;
                    continue;
                }
                $record = ProCertificateIntake::create([
                    'organization_id' => $organizationId, 'source_system' => $row['source_system'],
                    'source_id' => $row['source_id'], 'source_certificate_number' => $row['source_certificate_number'] ?? null,
                    'source_snapshot' => $row['source_snapshot'], 'status' => 'draft', 'created_by' => (int) $actor->id,
                ]);
                $this->append($record, 'imported', [], $actor);
                $result['created']++;
            }
            return $result;
        }, 3);
    }

    public function invite(User $actor, int $id): ProCertificateIntake
    {
        return DB::transaction(function () use ($actor, $id): ProCertificateIntake {
            $record = $this->locked($actor, $id);
            abort_unless(in_array($record->status, ['draft', 'open'], true), 409, __('certificate_intake.errors.transition'));
            $old = $this->auditValues($record);
            $token = bin2hex(random_bytes(32));
            $record->status = 'open';
            $record->token_hash = hash('sha256', $token);
            $record->token_encrypted = $token;
            $record->expires_at = now()->utc()->startOfSecond()->addDays(14);
            $record->save();
            $this->append($record, 'invited', $old, $actor);
            return $record->fresh('organization');
        }, 3);
    }

    public function publicRecord(string $token): ?ProCertificateIntake
    {
        if (!$this->validToken($token)) { return null; }
        $record = $this->publicQuery($token)->first();
        return $record !== null && $this->verify($record) ? $record : null;
    }

    public function responseRules(): array
    {
        $text = ['string', 'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F\x{202A}-\x{202E}\x{2066}-\x{2069}]/u'];
        return ['name_ar' => ['nullable', ...$text, 'max:180'],
            'name_en' => ['required', ...$text, 'max:180'],
            'email' => ['required', 'string', 'email:rfc', 'max:254'],
            'phone' => ['nullable', ...$text, 'max:40'], 'country' => ['nullable', ...$text, 'max:100'],
            'specialization' => ['required', ...$text, 'max:160'],
            'notes' => ['nullable', ...$text, 'max:1000'], 'confirmation' => ['required', 'accepted']];
    }

    public function responseValidator(array $data): \Illuminate\Validation\Validator
    {
        $messages = [];
        foreach (['required', 'string', 'max', 'email', 'accepted', 'not_regex'] as $rule) {
            $messages[$rule] = __('certificate_intake.validation.'.$rule);
        }
        $attributes = [];
        foreach (self::RESPONSE_FIELDS as $field) { $attributes[$field] = __('certificate_intake.fields.'.$field); }
        return Validator::make($data, $this->responseRules(), $messages, $attributes);
    }

    public function submit(string $token, array $data): void
    {
        abort_unless($this->validToken($token), 404);
        $data = array_intersect_key($data, array_flip(self::RESPONSE_FIELDS));
        $validated = $this->responseValidator($data)->validate();
        $validated['confirmation'] = true;
        DB::transaction(function () use ($token, $validated): void {
            $candidate = $this->publicQuery($token)->first();
            abort_if($candidate === null, 404);
            $organization = Organization::query()->lockForUpdate()->find($candidate->organization_id);
            abort_if($organization === null || $organization->status !== 'active', 404);
            $record = $this->publicQuery($token)->where('organization_id', $organization->id)->lockForUpdate()->first();
            abort_if($record === null || !$this->verify($record), 404);
            $old = $this->auditValues($record);
            $record->response_payload = $validated;
            $record->submitted_at = now()->utc()->startOfSecond();
            $record->status = 'submitted';
            $record->token_hash = null;
            $record->token_encrypted = null;
            $record->save();
            // Anonymous possession of a link is a data submission, never an award/identity decision.
            $this->append($record, 'submitted', $old, null);
        }, 3);
    }

    public function review(User $actor, int $id): ProCertificateIntake
    {
        return DB::transaction(function () use ($actor, $id): ProCertificateIntake {
            $record = $this->locked($actor, $id, 'certificates.review');
            abort_unless($record->status === 'submitted', 409, __('certificate_intake.errors.transition'));
            $old = $this->auditValues($record);
            $record->status = 'reviewed';
            $record->reviewed_at = now()->utc()->startOfSecond();
            $record->reviewed_by = (int) $actor->id;
            $record->save();
            $this->append($record, 'reviewed', $old, $actor);
            return $record->fresh('organization');
        }, 3);
    }

    public function cancel(User $actor, int $id): ProCertificateIntake
    {
        return DB::transaction(function () use ($actor, $id): ProCertificateIntake {
            $record = $this->locked($actor, $id);
            abort_unless(in_array($record->status, ['draft', 'open'], true), 409, __('certificate_intake.errors.transition'));
            $old = $this->auditValues($record);
            $record->status = 'cancelled';
            $record->token_hash = null;
            $record->token_encrypted = null;
            $record->save();
            $this->append($record, 'cancelled', $old, $actor);
            return $record->fresh('organization');
        }, 3);
    }

    public function invitationUrl(ProCertificateIntake $record, string $locale): ?string
    {
        if (!in_array($locale, ['ar', 'en', 'fr'], true) || $record->status !== 'open'
            || !$this->verify($record) || !is_string($record->token_encrypted)) { return null; }
        $token = $record->token_encrypted;
        if (!$this->validToken($token) || !$this->publicRecord($token)) { return null; }
        return 'https://iuoamc.pro/'.$locale.'/certificate-data/'.$token;
    }

    public function verify(ProCertificateIntake $record): bool
    {
        try {
            if (!$record->exists || !$record->integrity_audit_id) { return false; }
            $current = ProCertificateIntake::query()->find($record->id);
            if ($current === null || $this->digest($current) !== $this->digest($record)
                || $current->integrity_audit_id !== $record->integrity_audit_id) { return false; }
            $audits = AuditLog::query()->where('auditable_type', $record->getMorphClass())
                ->where('auditable_id', $record->id)->orderBy('sequence_number')->orderBy('id')->get();
            if ($audits->isEmpty() || (int) $audits->last()->id !== $record->integrity_audit_id) { return false; }
            $prior = null;
            foreach ($audits as $audit) {
                if (!(app(IntegrityService::class)->verifyAuditLog($audit)['valid'] ?? false)
                    || !$this->auditTransition($audit, $prior)
                    || ($audit->metadata['module'] ?? null) !== 'certificate_data_confirmation'
                    || ($audit->metadata['credential_issued'] ?? null) !== false
                    || $this->json($audit->old_values ?? []) !== $this->json($prior?->new_values ?? [])) { return false; }
                $prior = $audit;
            }
            return $this->json($audits->last()->new_values ?? []) === $this->json($this->auditValues($current));
        } catch (Throwable) { return false; }
    }

    private function publicQuery(string $token): Builder
    {
        return ProCertificateIntake::query()->with('organization')->where('token_hash', hash('sha256', $token))
            ->where('status', 'open')->where('expires_at', '>', now()->utc())
            ->whereNull('response_payload')->whereHas('organization', fn ($q) => $q->where('status', 'active'));
    }

    private function auditTransition(AuditLog $audit, ?AuditLog $prior): bool
    {
        $next = $audit->new_values['status'] ?? null;
        if ($prior === null) { return $audit->event === 'pro_certificate_intake.imported' && $next === 'draft'; }
        $before = $prior->new_values['status'] ?? null;
        return match ($audit->event) {
            'pro_certificate_intake.invited' => in_array($before, ['draft', 'open'], true) && $next === 'open',
            'pro_certificate_intake.submitted' => $before === 'open' && $next === 'submitted',
            'pro_certificate_intake.reviewed' => $before === 'submitted' && $next === 'reviewed',
            'pro_certificate_intake.cancelled' => in_array($before, ['draft', 'open'], true) && $next === 'cancelled',
            default => false,
        };
    }

    private function locked(User $actor, int $id, string $permission = 'certificates.manage'): ProCertificateIntake
    {
        $this->requirePermission($actor, $permission);
        $candidate = $this->query($actor)->findOrFail($id);
        $organization = Organization::query()->lockForUpdate()->findOrFail($candidate->organization_id);
        app(InstitutionalAccess::class)->authorizeOrganization($actor, $organization);
        abort_unless($organization->status === 'active', 409, __('certificate_intake.errors.organization'));
        $record = $this->query($actor)->where('organization_id', $organization->id)->lockForUpdate()->findOrFail($id);
        abort_unless($this->verify($record), 409, __('certificate_intake.errors.integrity'));
        return $record;
    }

    private function validateSource(array $row): void
    {
        if (array_diff(array_keys($row), ['source_system', 'source_id', 'source_certificate_number', 'source_snapshot'])) { $this->invalidSource(); }
        Validator::make($row, ['source_system' => ['required', 'string', 'max:80', 'regex:/\A[A-Za-z0-9][A-Za-z0-9._-]*\z/D'],
            'source_id' => ['required', 'string', 'max:80', 'regex:/\A[A-Za-z0-9][A-Za-z0-9._-]*\z/D'],
            'source_certificate_number' => ['nullable', 'string', 'max:120'],
            'source_snapshot' => ['required', 'array']])->validate();
        if (array_diff(array_keys($row['source_snapshot']), self::SOURCE_FIELDS)) { $this->invalidSource(); }
        foreach ($row['source_snapshot'] as $field => $value) {
            if ($value !== null && (!is_string($value) || mb_strlen($value) > ($field === 'source_url' ? 2000 : 500))) { $this->invalidSource(); }
        }
        if (empty($row['source_snapshot']['name_ar']) && empty($row['source_snapshot']['name_en'])) { $this->invalidSource(); }
    }

    private function invalidSource(): never
    {
        throw ValidationException::withMessages(['source' => __('certificate_intake.errors.source')]);
    }

    private function validToken(string $token): bool { return preg_match('/\A[a-f0-9]{64}\z/D', $token) === 1; }
    private function json(array $value): string { return ProCertificateSigner::canonicalJson($value); }

    private function digest(ProCertificateIntake $record): string
    {
        $values = $record->getAttributes();
        unset($values['integrity_audit_id']);
        // Ciphertext seals prevent private names/contact/token values from entering the audit trail.
        return hash('sha256', $this->json($values));
    }

    private function auditValues(ProCertificateIntake $record): array
    {
        return ['intake_id' => (int) $record->id, 'organization_id' => (int) $record->organization_id,
            'status' => $record->status, 'state_sha256' => $this->digest($record)];
    }

    private function append(ProCertificateIntake $record, string $action, array $old, ?User $actor): void
    {
        // Include database defaults/null columns and persisted timestamp representation in the seal.
        $record->refresh();
        $audit = AuditTrail::record('pro_certificate_intake.'.$action, $record, $old, $this->auditValues($record),
            ['module' => 'certificate_data_confirmation', 'actor_kind' => $actor === null ? 'recipient_link' : 'administrator',
                'credential_issued' => false], $actor === null ? null : (int) $actor->id);
        $record->integrity_audit_id = (int) $audit->id;
        $timestamps = $record->timestamps;
        try { $record->timestamps = false; $record->saveQuietly(); }
        finally { $record->timestamps = $timestamps; }
        $record->unsetRelation('integrityAudit');
    }
}
