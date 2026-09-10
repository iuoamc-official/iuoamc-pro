<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class InstitutionalAccess
{
    /** @var array<int, array<int, int>> */
    private array $organizationCache = [];

    /** @return array<int, int> */
    public function organizationIds(User $user): array
    {
        $userId = (int) $user->getKey();

        if (array_key_exists($userId, $this->organizationCache)) {
            return $this->organizationCache[$userId];
        }

        if ($user->hasRole('super-admin')) {
            return $this->organizationCache[$userId] = Organization::query()
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->all();
        }

        $visible = $user->organizations()
            ->pluck('organizations.id')
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
        $frontier = $visible;

        while ($frontier !== []) {
            $children = Organization::query()
                ->whereIn('parent_id', $frontier)
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->diff($visible)
                ->unique()
                ->values()
                ->all();

            $visible = array_values(array_unique(array_merge($visible, $children)));
            $frontier = $children;
        }

        return $this->organizationCache[$userId] = $visible;
    }

    public function constrainOrganizations(Builder $query, User $user): Builder
    {
        return $query->whereIn('organizations.id', $this->organizationIds($user));
    }

    public function constrainUsers(Builder $query, User $user): Builder
    {
        if ($user->hasRole('super-admin')) {
            return $query;
        }

        $organizationIds = $this->organizationIds($user);

        return $query->whereHas(
            'organizations',
            static fn (Builder $organization): Builder => $organization->whereIn(
                'organizations.id',
                $organizationIds
            )
        );
    }

    public function authorizeOrganization(User $user, Organization $organization): void
    {
        abort_unless(in_array((int) $organization->id, $this->organizationIds($user), true), 403);
    }

    /** @param array<int, int> $organizationIds */
    public function authorizeOrganizationIds(User $user, array $organizationIds): void
    {
        $requested = array_values(array_unique(array_map('intval', $organizationIds)));
        $allowed = $this->organizationIds($user);

        abort_unless(array_diff($requested, $allowed) === [], 403);
    }

    public function authorizeUser(User $actor, User $target): void
    {
        if ($actor->hasRole('super-admin')) {
            return;
        }

        $visibleOrganizationIds = $this->organizationIds($actor);
        $targetOrganizationIds = $target->organizations()
            ->pluck('organizations.id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
        $visible = $targetOrganizationIds !== []
            && array_diff($targetOrganizationIds, $visibleOrganizationIds) === [];

        abort_unless($visible, 403);
    }

    public function constrainAudit(Builder $query, User $user): Builder
    {
        if ($user->hasRole('super-admin')) {
            return $query;
        }

        $organizationIds = $this->organizationIds($user);
        $userIds = DB::table('organization_user')
            ->whereIn('organization_id', $organizationIds)
            ->distinct()
            ->pluck('user_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
        $organizationType = (new Organization())->getMorphClass();
        $userType = (new User())->getMorphClass();

        return $query->where(function (Builder $scope) use (
            $organizationIds,
            $userIds,
            $organizationType,
            $userType
        ): void {
            $scope->where(function (Builder $subject) use ($organizationIds, $organizationType): void {
                    $subject->where('auditable_type', $organizationType)
                        ->whereIn('auditable_id', $organizationIds);
                })
                ->orWhere(function (Builder $subject) use ($userIds, $userType): void {
                    $subject->where('auditable_type', $userType)
                        ->whereIn('auditable_id', $userIds);
                })
                ->orWhere(function (Builder $system) use ($userIds): void {
                    $system->whereNull('auditable_id')
                        ->whereIn('actor_id', $userIds);
                });
        });
    }

    public function authorizeAudit(User $user, AuditLog $auditLog): void
    {
        $visible = $this->constrainAudit(
            AuditLog::query()->whereKey($auditLog->getKey()),
            $user
        )->exists();

        abort_unless($visible, 403);
    }
}
