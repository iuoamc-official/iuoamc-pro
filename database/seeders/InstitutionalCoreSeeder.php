<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\AuditTrail;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InstitutionalCoreSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $definitions = [
                ['core.users.view', 'core'],
                ['core.roles.view', 'core'],
                ['organizations.view', 'organizations'],
                ['organizations.manage', 'organizations'],
                ['core.users.manage', 'core'],
                ['core.roles.manage', 'core'],
                ['core.audit.view', 'core'],
                ['public-content.manage', 'public-content'],
                ['public-content.publish', 'public-content'],
            ];

            foreach ($definitions as [$code, $module]) {
                Permission::updateOrCreate(
                    ['code' => $code],
                    [
                        'name' => str($code)->replace(['.', '_'], ' ')->title()->toString(),
                        'module' => $module,
                        'description' => "IUOAMC institutional permission: {$code}",
                    ]
                );
            }

            $permissionIds = Permission::query()->pluck('id');
            $superAdmin = Role::query()->where('slug', 'super-admin')->firstOrFail();
            $superAdmin->permissions()->syncWithoutDetaching($permissionIds);

            $roleMap = [
                'institution-admin' => [
                    'core.dashboard.view', 'core.users.view', 'core.users.manage',
                    'core.roles.view', 'organizations.view', 'organizations.manage',
                    'core.audit.view',
                    'public-content.manage',
                ],
                'auditor' => [
                    'core.dashboard.view', 'core.users.view', 'core.roles.view',
                    'organizations.view', 'core.audit.view',
                ],
                'read-only' => [
                    'core.dashboard.view', 'core.users.view', 'core.roles.view',
                    'organizations.view',
                ],
            ];

            foreach ($roleMap as $slug => $codes) {
                $role = Role::query()->where('slug', $slug)->first();
                if ($role) {
                    $role->permissions()->syncWithoutDetaching(
                        Permission::query()->whereIn('code', $codes)->pluck('id')
                    );
                }
            }

            $rootOrganization = Organization::query()->where('is_root', true)->first();
            SystemSetting::updateOrCreate(
                [
                    'organization_id' => $rootOrganization?->id,
                    'key' => 'institutional_core_version',
                ],
                [
                    'group' => 'releases',
                    'value' => ['version' => '1.0.0', 'installed_at' => now()->toIso8601String()],
                    'is_public' => false,
                ]
            );

            SystemSetting::updateOrCreate(
                [
                    'organization_id' => $rootOrganization?->id,
                    'key' => 'audit_integrity_policy',
                ],
                [
                    'group' => 'security',
                    'value' => [
                        'version' => 'ed25519-sha256-v1',
                        'hash' => 'sha256',
                        'signature' => 'ed25519',
                        'chain' => true,
                    ],
                    'is_public' => false,
                ]
            );

            $actor = User::query()->whereHas('roles', fn ($query) => $query->where('slug', 'super-admin'))->first();
            AuditTrail::record(
                'system.institutional_core_installed',
                $rootOrganization,
                [],
                ['version' => '1.0.0'],
                ['legacy_imported' => false],
                $actor?->id
            );
        });
    }
}
