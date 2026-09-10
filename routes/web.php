<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Control\DashboardController;
use App\Http\Controllers\Control\OrganizationController;
use App\Http\Controllers\Control\UserController;
use App\Http\Controllers\Control\RoleController;
use App\Http\Controllers\Control\AuditLogController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $locale = auth()->user()?->preferred_locale ?: 'ar';

    return redirect()->route(
        auth()->check() ? 'dashboard' : 'login',
        ['locale' => $locale]
    );
});

Route::prefix('{locale}')
    ->where(['locale' => 'ar|en|fr'])
    ->middleware('locale')
    ->group(function (): void {
        Route::middleware('guest')->group(function (): void {
            Route::get('/login', [AuthenticatedSessionController::class, 'create'])
                ->name('login');
            Route::post('/login', [AuthenticatedSessionController::class, 'store'])
                ->name('login.store');
        });

        Route::middleware(['auth', 'active'])->group(function (): void {
            Route::get('/change-password', [PasswordController::class, 'edit'])
                ->name('password.change');
            Route::put('/change-password', [PasswordController::class, 'update'])
                ->name('password.update');
            Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
                ->name('logout');

            Route::middleware('password.changed')
                ->prefix('control')
                ->group(function (): void {
                    Route::get('/', [DashboardController::class, 'index'])
                        ->name('dashboard');

                    Route::get('/organizations', [OrganizationController::class, 'index'])
                        ->middleware('permission:organizations.view')
                        ->name('organizations.index');
                    Route::get('/organizations/create', [OrganizationController::class, 'create'])
                        ->middleware('permission:organizations.manage')
                        ->name('organizations.create');
                    Route::post('/organizations', [OrganizationController::class, 'store'])
                        ->middleware('permission:organizations.manage')
                        ->name('organizations.store');
                    Route::get('/organizations/{organization}/edit', [OrganizationController::class, 'edit'])
                        ->middleware('permission:organizations.manage')
                        ->name('organizations.edit');
                    Route::put('/organizations/{organization}', [OrganizationController::class, 'update'])
                        ->middleware('permission:organizations.manage')
                        ->name('organizations.update');

                    Route::get('/users', [UserController::class, 'index'])
                        ->middleware('permission:core.users.view')
                        ->name('users.index');
                    Route::get('/users/create', [UserController::class, 'create'])
                        ->middleware('permission:core.users.manage')
                        ->name('users.create');
                    Route::post('/users', [UserController::class, 'store'])
                        ->middleware('permission:core.users.manage')
                        ->name('users.store');
                    Route::get('/users/{user}/edit', [UserController::class, 'edit'])
                        ->middleware('permission:core.users.manage')
                        ->name('users.edit');
                    Route::put('/users/{user}', [UserController::class, 'update'])
                        ->middleware('permission:core.users.manage')
                        ->name('users.update');

                    Route::get('/roles', [RoleController::class, 'index'])
                        ->middleware('permission:core.roles.view')
                        ->name('roles.index');
                    Route::get('/roles/create', [RoleController::class, 'create'])
                        ->middleware('permission:core.roles.manage')
                        ->name('roles.create');
                    Route::post('/roles', [RoleController::class, 'store'])
                        ->middleware('permission:core.roles.manage')
                        ->name('roles.store');
                    Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])
                        ->middleware('permission:core.roles.manage')
                        ->name('roles.edit');
                    Route::put('/roles/{role}', [RoleController::class, 'update'])
                        ->middleware('permission:core.roles.manage')
                        ->name('roles.update');

                    Route::get('/audit', [AuditLogController::class, 'index'])
                        ->middleware('permission:core.audit.view')
                        ->name('audit.index');
                    Route::get('/audit/{auditLog}', [AuditLogController::class, 'show'])
                        ->middleware('permission:core.audit.view')
                        ->name('audit.show');
                });
        });
    });

// IUOAMC_MEMBERSHIP_ROUTES_1_0_0
require __DIR__.'/modules/memberships.php';

// IUOAMC_LEGACY_CERTIFICATE_ROUTES_1_0_0
require __DIR__.'/modules/legacy_certificates.php';

// IUOAMC_PRO_CERTIFICATE_ROUTES_1_0_0
require __DIR__.'/modules/pro_certificates.php';

// WICP registry 1.0.0: independent program and certificate documentation.
require __DIR__.'/modules/wicp.php';

// Professional Master templates 1.2.0: original logos and portrait certificate models.
require __DIR__.'/modules/pro_master_templates.php';

// IUOAMC_CERTIFICATE_DATA_FORM_1_0_0
require __DIR__.'/modules/pro_certificate_intakes.php';
