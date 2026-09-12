<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Membership;
use App\Models\MembershipPeriod;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class MembershipRegistry
{
    public const PROFILE = ['full_name', 'latin_name', 'membership_type', 'professional_title',
        'country_code', 'preferred_locale', 'email', 'phone', 'private_notes'];
    public const IDENTITY = ['full_name', 'latin_name', 'membership_type', 'professional_title', 'country_code', 'preferred_locale'];

    public function requirePermission(User $actor, string $permission): void
    {
        abort_unless($actor->status === 'active' && $actor->canDo('memberships.view') && $actor->canDo($permission), 403);
    }

    public function scoped(User $actor): Builder
    {
        $this->requirePermission($actor, 'memberships.view');
        return Membership::query()->whereIn('organization_id', app(InstitutionalAccess::class)->organizationIds($actor));
    }

    public static function digest(array $data): string
    {
        $canonical = function (array $value) use (&$canonical): array {
            if (! array_is_list($value)) { ksort($value, SORT_STRING); }
            foreach ($value as $key => $item) {
                if (is_array($item)) { $value[$key] = $canonical($item); }
            }
            return $value;
        };
        return hash('sha256', json_encode($canonical($data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function snapshot(Membership $membership): array
    {
        $data = ['schema' => 'iuoamc-membership-record-v1', 'id' => (int) $membership->id,
            'record_uuid' => $membership->record_uuid, 'organization_id' => (int) $membership->organization_id,
            'membership_number' => $membership->membership_number, 'status' => $membership->status,
            'lock_version' => (int) $membership->lock_version, 'created_by' => (int) $membership->created_by,
            'updated_by' => (int) $membership->updated_by, 'last_reason' => $membership->last_reason];
        foreach (self::PROFILE as $field) { $data[$field] = $membership->getAttribute($field); }
        return $data;
    }

    public function verify(Membership $membership): bool
    {
        try {
            $audit = $membership->integrityAudit;
            $valid = $audit !== null && hash_equals((string) $membership->record_hash, self::digest($this->snapshot($membership)))
                && $audit->auditable_type === $membership->getMorphClass()
                && (int) $audit->auditable_id === (int) $membership->id
                && ($audit->new_values['record_hash'] ?? null) === $membership->record_hash
                && (int) ($audit->new_values['lock_version'] ?? 0) === (int) $membership->lock_version
                && (int) AuditLog::query()->where('auditable_type', $membership->getMorphClass())
                    ->where('auditable_id', $membership->id)->orderByDesc('sequence_number')->value('id') === (int) $audit->id
                && app(IntegrityService::class)->verifyAuditLog($audit)['valid'];
            if (!$valid) { return false; }
            $expectedPeriods = AuditLog::query()->where('auditable_type', $membership->getMorphClass())
                ->where('auditable_id', $membership->id)->whereIn('event', ['membership.approve','membership.renew','membership.correct'])->count();
            return $membership->periods->count() === $expectedPeriods
                && $membership->periods->every(fn (MembershipPeriod $period) => $this->verifyPeriod($period));
        } catch (Throwable) { return false; }
    }

    public function verifyPeriod(MembershipPeriod $period): bool
    {
        try {
            $audit = $period->audit;
            $payload = $period->payload;
            return $audit !== null && hash_equals((string) $period->payload_sha256, self::digest($payload))
                && ($payload['period_uuid'] ?? null) === $period->period_uuid
                && (int) ($payload['membership_id'] ?? 0) === (int) $period->membership_id
                && (int) ($payload['version'] ?? 0) === (int) $period->version
                && ($payload['valid_from'] ?? null) === $period->valid_from->toDateString()
                && ($payload['valid_until'] ?? null) === $period->valid_until->toDateString()
                && (int) ($payload['approved_by'] ?? 0) === (int) $period->approved_by
                && (int) $audit->actor_id === (int) $period->approved_by
                && \Carbon\CarbonImmutable::parse($payload['approved_at'])->utc()->format('Y-m-d H:i:s')
                    === $period->created_at->utc()->format('Y-m-d H:i:s')
                && ($audit->new_values['period_uuid'] ?? null) === $period->period_uuid
                && ($audit->new_values['snapshot_sha256'] ?? null) === $period->payload_sha256
                && (int) $audit->auditable_id === (int) $period->membership_id
                && $audit->auditable_type === (new Membership())->getMorphClass()
                && app(IntegrityService::class)->verifyAuditLog($audit)['valid'];
        } catch (Throwable) { return false; }
    }

    private function append(Membership $membership, User $actor, string $event, array $old, ?array $period = null): AuditLog
    {
        $membership->record_hash = self::digest($this->snapshot($membership));
        $values = ['organization_id' => (int) $membership->organization_id, 'record_uuid' => $membership->record_uuid,
            'membership_number' => $membership->membership_number, 'status' => $membership->status,
            'lock_version' => (int) $membership->lock_version, 'record_hash' => $membership->record_hash];
        if ($period !== null) {
            $values += ['period_uuid' => $period['period_uuid'], 'snapshot_sha256' => self::digest($period), 'period_version' => $period['version']];
        }
        $metadata = ['module' => 'memberships', 'organization_id' => (int) $membership->organization_id];
        if ($membership->last_reason) { $metadata['reason_encrypted'] = Crypt::encryptString($membership->last_reason); }
        $audit = AuditTrail::record($event, $membership, $old, $values, $metadata, (int) $actor->id);
        $membership->integrity_audit_id = $audit->id;
        $membership->saveQuietly();
        $membership->unsetRelation('integrityAudit');
        return $audit;
    }

    private function stop(string $message): never
    {
        throw ValidationException::withMessages(['record' => trans('memberships.errors.'.$message)]);
    }

    private function locked(User $actor, int $id, int $version, string $permission): Membership
    {
        $this->requirePermission($actor, $permission);
        $membership = $this->scoped($actor)->lockForUpdate()->findOrFail($id);
        if ($membership->lock_version !== $version) { $this->stop('stale'); }
        if (! $this->verify($membership)) { $this->stop('integrity'); }
        return $membership;
    }

    public function create(User $actor, array $data): Membership
    {
        $this->requirePermission($actor, 'memberships.manage');
        return DB::transaction(function () use ($actor, $data): Membership {
            $organization = Organization::query()->lockForUpdate()->findOrFail($data['organization_id']);
            app(InstitutionalAccess::class)->authorizeOrganization($actor, $organization);
            if ($organization->status !== 'active') { $this->stop('inactive_organization'); }
            $values = array_intersect_key($data, array_flip(self::PROFILE));
            $membership = Membership::create($values + [
                'record_uuid' => (string) Str::uuid(), 'organization_id' => $organization->id,
                'status' => 'draft', 'lock_version' => 1, 'created_by' => $actor->id, 'updated_by' => $actor->id,
            ]);
            $this->append($membership, $actor, 'membership.created', []);
            return $membership->fresh();
        }, 3);
    }

    public function createForVerifiedAccount(User $actor, array $data): Membership
    {
        abort_unless($actor->isActive() && $actor->hasVerifiedEmail(), 403);
        $email = AccountRecordAccess::normalizeEmail($actor->email);
        abort_unless(hash_equals($email, AccountRecordAccess::normalizeEmail($data['email'] ?? null)), 403);

        return DB::transaction(function () use ($actor, $data): Membership {
            $organization = Organization::query()->lockForUpdate()->findOrFail($data['organization_id']);
            abort_unless($organization->status === 'active', 409);
            $values = array_intersect_key($data, array_flip(self::PROFILE));
            $membership = Membership::create($values + [
                'record_uuid' => (string) Str::uuid(), 'organization_id' => $organization->id,
                'status' => 'draft', 'lock_version' => 1, 'created_by' => $actor->id, 'updated_by' => $actor->id,
            ]);
            $this->append($membership, $actor, 'membership.created', []);

            return $membership->fresh(['organization', 'integrityAudit']);
        }, 3);
    }

    public function submitForVerifiedAccount(User $actor, Membership $membership): Membership
    {
        abort_unless($actor->isActive() && $actor->hasVerifiedEmail(), 403);
        abort_unless(app(AccountRecordAccess::class)->owns(
            $actor, AccountRecordAccess::MEMBERSHIP, (int) $membership->id
        ), 404);

        return DB::transaction(function () use ($actor, $membership): Membership {
            $membership = Membership::query()->lockForUpdate()->findOrFail($membership->id);
            abort_unless($membership->status === 'draft' && $this->verify($membership), 409);
            abort_unless(app(MembershipCredentialRegistry::class)->ready($membership), 409);
            $old = $membership->only(['status', 'lock_version', 'record_hash']);
            $membership->status = 'pending';
            $membership->lock_version++;
            $membership->updated_by = $actor->id;
            $membership->last_reason = null;
            $membership->save();
            $this->append($membership, $actor, 'membership.submit', $old);

            return $membership->fresh(['organization', 'application']);
        }, 3);
    }

    public function update(User $actor, int $id, int $version, array $data): Membership
    {
        return DB::transaction(function () use ($actor, $id, $version, $data): Membership {
            $membership = $this->locked($actor, $id, $version, 'memberships.manage');
            if (! in_array($membership->status, ['draft', 'active', 'suspended'], true)) { $this->stop('transition'); }
            $values = array_intersect_key($data, array_flip(self::PROFILE));
            if ($membership->status !== 'draft') {
                foreach (self::IDENTITY as $field) {
                    if (($values[$field] ?? null) !== $membership->getAttribute($field)) { $this->stop('identity_locked'); }
                }
            }
            $old = $membership->only(['status', 'lock_version', 'record_hash']);
            $membership->fill($values);
            if (! $membership->isDirty()) { return $membership; }
            $membership->lock_version++;
            $membership->updated_by = $actor->id;
            $membership->last_reason = null;
            $membership->save();
            $this->append($membership, $actor, 'membership.updated', $old);
            return $membership->fresh();
        }, 3);
    }

    public function correctIdentity(User $actor, int $id, int $version, array $data): Membership
    {
        return DB::transaction(function () use ($actor, $id, $version, $data): Membership {
            $membership = $this->locked($actor, $id, $version, 'memberships.correct');
            if (! in_array($membership->status, ['active', 'suspended'], true)) { $this->stop('correction_state'); }

            $values = array_intersect_key($data, array_flip([
                'full_name', 'latin_name', 'membership_type', 'professional_title',
            ]));
            foreach ($values as $field => $value) {
                $values[$field] = is_string($value) ? trim($value) : $value;
            }
            if (($values['full_name'] ?? '') === '') { $this->stop('name_required'); }
            if (! in_array($values['membership_type'] ?? null, app(MembershipApplicationPolicy::class)->categoryNames(), true)) {
                throw ValidationException::withMessages([
                    'membership_type' => trans('memberships.membership_category_error'),
                ]);
            }
            if (! in_array($values['professional_title'] ?? null,
                app(MembershipApplicationPolicy::class)->professionalTitles('en'), true)) {
                throw ValidationException::withMessages([
                    'professional_title' => trans('memberships.professional_title_required'),
                ]);
            }

            $replacementNumber = trim((string) ($data['replacement_membership_number'] ?? ''));
            $replacementNumber = $replacementNumber === '' ? null : Str::upper($replacementNumber);
            if ($replacementNumber !== null
                && ! preg_match('/\A[A-Z0-9][A-Z0-9.\/_-]{4,79}\z/D', $replacementNumber)) {
                throw ValidationException::withMessages([
                    'replacement_membership_number' => trans('memberships.membership_number_invalid'),
                ]);
            }
            if ($replacementNumber !== null && Membership::query()->where('membership_number', $replacementNumber)
                ->whereKeyNot($membership->id)->exists()) {
                throw ValidationException::withMessages([
                    'replacement_membership_number' => trans('memberships.membership_number_taken'),
                ]);
            }
            $previousMembershipNumber = (string) $membership->membership_number;
            if ($replacementNumber !== null) {
                $values['membership_number'] = $replacementNumber;
            }

            $last = $membership->periods()->lockForUpdate()->first();
            if ($last === null || ! $this->verifyPeriod($last)) { $this->stop('integrity'); }

            $membership->fill($values);
            if (! $membership->isDirty()) { $this->stop('correction_no_changes'); }

            $reason = trim((string) ($data['reason'] ?? ''));
            if ($reason === '') { $this->stop('reason'); }

            $old = $membership->only([
                'membership_number', 'full_name', 'latin_name', 'membership_type',
                'professional_title', 'status', 'lock_version', 'record_hash',
            ]);
            $membership->lock_version++;
            $membership->updated_by = $actor->id;
            $membership->last_reason = $reason;
            $membership->save();

            $organization = $membership->organization;
            $period = [
                'schema' => 'iuoamc-membership-period-v1',
                'period_uuid' => (string) Str::uuid(),
                'membership_id' => (int) $membership->id,
                'record_uuid' => $membership->record_uuid,
                'membership_number' => $membership->membership_number,
                'version' => (int) $last->version + 1,
                'full_name' => $membership->full_name,
                'latin_name' => $membership->latin_name,
                'membership_type' => $membership->membership_type,
                'professional_title' => $membership->professional_title,
                'organization' => $organization->only(['id', 'code', 'legal_name', 'display_name', 'jurisdiction', 'registration_number']),
                'valid_from' => $last->valid_from->toDateString(),
                'valid_until' => $last->valid_until->toDateString(),
                'approved_by' => (int) $actor->id,
                'approved_at' => now()->utc()->toIso8601String(),
                'correction_of_period_uuid' => $last->period_uuid,
                'change_kind' => 'controlled_upgrade_or_reissue',
            ];
            if ($previousMembershipNumber !== (string) $membership->membership_number) {
                $period['previous_membership_number'] = $previousMembershipNumber;
            }

            $audit = $this->append($membership, $actor, 'membership.correct', $old, $period);
            MembershipPeriod::create([
                'period_uuid' => $period['period_uuid'],
                'membership_id' => $membership->id,
                'version' => $period['version'],
                'valid_from' => $period['valid_from'],
                'valid_until' => $period['valid_until'],
                'payload' => $period,
                'payload_sha256' => self::digest($period),
                'audit_log_id' => $audit->id,
                'approved_by' => $actor->id,
                'created_at' => $period['approved_at'],
            ]);

            return $membership->fresh(['periods', 'organization']);
        }, 3);
    }

    public function transition(User $actor, int $id, int $version, string $action, array $data = []): Membership
    {
        $policy = [
            'submit' => ['memberships.manage', ['draft'], 'pending'],
            'return' => ['memberships.review', ['pending'], 'draft'],
            'reject' => ['memberships.review', ['pending'], 'rejected'],
            'approve' => ['memberships.review', ['pending'], 'active'],
            'reopen' => ['memberships.manage', ['rejected'], 'draft'],
            'suspend' => ['memberships.status', ['active'], 'suspended'],
            'reinstate' => ['memberships.status', ['suspended'], 'active'],
            'revoke' => ['memberships.status', ['active', 'suspended'], 'revoked'],
            'renew' => ['memberships.renew', ['active'], 'active'],
        ];
        if (! isset($policy[$action])) { $this->stop('transition'); }
        [$permission, $allowed, $next] = $policy[$action];
        return DB::transaction(function () use ($actor, $id, $version, $action, $data, $permission, $allowed, $next): Membership {
            $membership = $this->locked($actor, $id, $version, $permission);
            if (! in_array($membership->status, $allowed, true)) { $this->stop('transition'); }
            if (in_array($action, ['submit', 'approve'], true)
                && ! app(MembershipCredentialRegistry::class)->ready($membership)) {
                $this->stop('application_incomplete');
            }
            if ($next === 'active' && $membership->organization->status !== 'active') { $this->stop('inactive_organization'); }
            if ($action !== 'submit' && trim((string) ($data['reason'] ?? '')) === '') { $this->stop('reason'); }
            $old = $membership->only(['status', 'lock_version', 'record_hash']);
            $period = null;
            if (in_array($action, ['approve', 'renew'], true)) {
                $dates = validator($data, ['valid_from' => ['required', 'date_format:Y-m-d'],
                    'valid_until' => ['required', 'date_format:Y-m-d', 'after_or_equal:valid_from']])->validate();
                $last = $membership->periods()->lockForUpdate()->first();
                if ($last !== null && ! $this->verifyPeriod($last)) { $this->stop('integrity'); }
                if ($action === 'renew' && ($last === null || $dates['valid_from'] <= $last->valid_until->toDateString())) { $this->stop('overlap'); }
                if ($action === 'approve' && $last !== null) { $this->stop('transition'); }
                $membership->membership_number ??= 'IUOAMC-MEM-'.now()->utc()->format('Y').'-'.str_pad((string) $membership->id, 6, '0', STR_PAD_LEFT);
                $organization = $membership->organization;
                $period = [
                    'schema' => 'iuoamc-membership-period-v1', 'period_uuid' => (string) Str::uuid(),
                    'membership_id' => (int) $membership->id, 'record_uuid' => $membership->record_uuid,
                    'membership_number' => $membership->membership_number, 'version' => (int) ($last?->version ?? 0) + 1,
                    'full_name' => $membership->full_name, 'latin_name' => $membership->latin_name,
                    'membership_type' => $membership->membership_type, 'professional_title' => $membership->professional_title,
                    'organization' => $organization->only(['id', 'code', 'legal_name', 'display_name', 'jurisdiction', 'registration_number']),
                    'valid_from' => $dates['valid_from'], 'valid_until' => $dates['valid_until'],
                    'approved_by' => (int) $actor->id, 'approved_at' => now()->utc()->toIso8601String(),
                ];
            }
            $membership->status = $next;
            $membership->lock_version++;
            $membership->updated_by = $actor->id;
            $membership->last_reason = $data['reason'] ?? null;
            $membership->save();
            $audit = $this->append($membership, $actor, 'membership.'.$action, $old, $period);
            if ($period !== null) {
                MembershipPeriod::create([
                    'period_uuid' => $period['period_uuid'], 'membership_id' => $membership->id,
                    'version' => $period['version'], 'valid_from' => $period['valid_from'], 'valid_until' => $period['valid_until'],
                    'payload' => $period, 'payload_sha256' => self::digest($period), 'audit_log_id' => $audit->id,
                    'approved_by' => $actor->id, 'created_at' => $period['approved_at'],
                ]);
            }
            return $membership->fresh(['periods', 'organization']);
        }, 3);
    }
}
