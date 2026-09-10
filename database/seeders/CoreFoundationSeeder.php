<?php

namespace Database\Seeders;

use App\Services\AuditTrail;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class CoreFoundationSeeder extends Seeder
{
    public function run(): void
    {
        $password = (string) env('IUOAMC_BOOTSTRAP_PASSWORD');

        if ($password === '') {
            throw new RuntimeException('Bootstrap password was not supplied.');
        }

        DB::transaction(function () use ($password): void {
            $organization = Organization::updateOrCreate(
                ['code' => 'IUOAMC-UK-16649793'],
                [
                    'legal_name' => 'INTERNATIONAL UNION OF ARAB MASTER CHEFS LTD',
                    'display_name' => 'IUOAMC Global System',
                    'jurisdiction' => 'GB',
                    'registration_number' => '16649793',
                    'status' => 'active',
                    'is_root' => true,
                    'metadata' => ['ukprn' => '10099301'],
                ]
            );

            $roles = [
                ['name' => 'Super Administrator', 'slug' => 'super-admin'],
                ['name' => 'Institutional Administrator', 'slug' => 'institution-admin'],
                ['name' => 'Module Manager', 'slug' => 'module-manager'],
                ['name' => 'Auditor', 'slug' => 'auditor'],
                ['name' => 'Read Only', 'slug' => 'read-only'],
            ];

            foreach ($roles as $roleData) {
                Role::updateOrCreate(
                    ['slug' => $roleData['slug']],
                    $roleData + ['is_system' => true]
                );
            }

            $permissionDefinitions = [
                ['core.dashboard.view', 'core'],
                ['core.users.manage', 'core'],
                ['core.roles.manage', 'core'],
                ['core.audit.view', 'core'],
                ['organizations.view', 'organizations'],
                ['organizations.manage', 'organizations'],
                ['certificates.view', 'certificates'],
                ['certificates.manage', 'certificates'],
                ['memberships.view', 'memberships'],
                ['memberships.manage', 'memberships'],
                ['accounting.view', 'accounting'],
                ['accounting.manage', 'accounting'],
                ['magazine.view', 'magazine'],
                ['magazine.manage', 'magazine'],
                ['research.view', 'research'],
                ['research.manage', 'research'],
            ];

            foreach ($permissionDefinitions as [$code, $module]) {
                Permission::updateOrCreate(
                    ['code' => $code],
                    [
                        'name' => ucwords(str_replace(['.', '_'], ' ', $code)),
                        'module' => $module,
                    ]
                );
            }

            $superAdmin = Role::where('slug', 'super-admin')->firstOrFail();
            $superAdmin->permissions()->sync(Permission::pluck('id'));

            $user = User::updateOrCreate(
                ['email' => 'iuomca2018@gmail.com'],
                [
                    'name' => 'Ahmad Maadarani',
                    'password' => Hash::make($password),
                    'email_verified_at' => now(),
                    'status' => 'active',
                    'preferred_locale' => 'ar',
                    'must_change_password' => true,
                ]
            );

            $user->roles()->syncWithoutDetaching([$superAdmin->id]);
            $user->organizations()->syncWithoutDetaching([
                $organization->id => [
                    'title' => 'President General',
                    'is_primary' => true,
                ],
            ]);

            SystemSetting::updateOrCreate(
                ['organization_id' => $organization->id, 'key' => 'supported_locales'],
                [
                    'group' => 'localization',
                    'value' => ['ar', 'en', 'fr'],
                    'is_public' => true,
                ]
            );

            SystemSetting::updateOrCreate(
                ['organization_id' => $organization->id, 'key' => 'legacy_mode'],
                [
                    'group' => 'migration',
                    'value' => ['enabled' => false, 'source' => 'read_only'],
                    'is_public' => false,
                ]
            );

            AuditTrail::record(
                'system.foundation_initialized',
                $organization,
                [],
                ['locales' => ['ar', 'en', 'fr'], 'version' => 'phase-1'],
                [],
                (int) $user->id
            );
        });
    }
}
