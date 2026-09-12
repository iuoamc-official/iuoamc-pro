<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Control\DashboardController;
use App\Http\Controllers\Control\OrganizationController;
use App\Http\Controllers\Control\UserController;
use App\Http\Controllers\Control\RoleController;
use App\Http\Controllers\Control\AuditLogController;
use App\Http\Controllers\Control\PublicPageController;
use App\Http\Controllers\Control\PublicSiteSettingController;
use App\Http\Controllers\Control\ContentArticleController;
use App\Http\Controllers\Control\ContentSectionController;
use App\Http\Controllers\PublicSiteController;
use App\Http\Controllers\PublicArticleController;
use App\Http\Controllers\DiscoveryController;
use App\Http\Controllers\PublicAiConciergeController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('public.home', ['locale' => auth()->user()?->preferred_locale ?: 'ar']);
});
Route::get('/sitemap.xml', [DiscoveryController::class, 'sitemap'])->name('public.sitemap');

Route::prefix('{locale}')
    ->where(['locale' => 'ar|en|fr'])
    ->middleware('locale')
    ->group(function (): void {
        Route::get('/', [PublicSiteController::class, 'home'])->name('public.home');
        Route::post('/ai/ask', [PublicAiConciergeController::class, 'ask'])
            ->middleware('throttle:12,1')
            ->name('public.ai.ask');
        Route::get('/articles', [PublicArticleController::class, 'index'])->name('public.articles.index');
        Route::get('/articles/feed.xml', [PublicArticleController::class, 'feed'])->name('public.articles.feed');
        Route::get('/articles/sections/{section:slug}', [PublicArticleController::class, 'index'])->name('public.articles.sections.show');
        Route::get('/articles/{article:slug}', [PublicArticleController::class, 'show'])->name('public.articles.show');

        Route::middleware('guest')->group(function (): void {
            Route::get('/login', [AuthenticatedSessionController::class, 'create'])
                ->name('login');
            Route::post('/login', [AuthenticatedSessionController::class, 'store'])
                ->name('login.store');
            Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
            Route::post('/register', [RegisteredUserController::class, 'store'])
                ->middleware('throttle:6,1')->name('register.store');
            Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
            Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
                ->middleware('throttle:6,1')->name('password.email');
            Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
            Route::post('/reset-password', [NewPasswordController::class, 'store'])
                ->middleware('throttle:6,1')->name('password.store');
        });

        Route::middleware(['auth', 'active'])->group(function (): void {
            Route::get('/change-password', [PasswordController::class, 'edit'])
                ->name('password.change');
            Route::put('/change-password', [PasswordController::class, 'update'])
                ->name('password.update');
            Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
                ->name('logout');

            Route::get('/verify-email', [VerifyEmailController::class, 'notice'])
                ->name('verification.notice');
            Route::get('/verify-email/{id}/{hash}', [VerifyEmailController::class, 'verify'])
                ->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
            Route::post('/email/verification-notification', EmailVerificationNotificationController::class)
                ->middleware('throttle:6,1')->name('verification.send');

            Route::middleware(['password.changed', 'control.access'])
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

                    Route::prefix('public-content')
                        ->middleware('permission:public-content.manage')
                        ->name('public-content.')
                        ->group(function (): void {
                            Route::get('/', [PublicPageController::class, 'index'])->name('pages.index');
                            Route::get('/settings', [PublicSiteSettingController::class, 'edit'])->name('settings.edit');
                            Route::put('/settings', [PublicSiteSettingController::class, 'update'])->name('settings.update');
                            Route::get('/articles', [ContentArticleController::class, 'index'])->name('articles.index');
                            Route::get('/articles/create', [ContentArticleController::class, 'create'])->name('articles.create');
                            Route::post('/articles', [ContentArticleController::class, 'store'])->middleware('throttle:20,1')->name('articles.store');
                            Route::get('/articles/{article}/edit', [ContentArticleController::class, 'edit'])->whereNumber('article')->name('articles.edit');
                            Route::put('/articles/{article}', [ContentArticleController::class, 'update'])->whereNumber('article')->middleware('throttle:30,1')->name('articles.update');
                            Route::get('/article-sections', [ContentSectionController::class, 'index'])->name('sections.index');
                            Route::get('/article-sections/create', [ContentSectionController::class, 'create'])->name('sections.create');
                            Route::post('/article-sections', [ContentSectionController::class, 'store'])->middleware('throttle:20,1')->name('sections.store');
                            Route::get('/article-sections/{section}/edit', [ContentSectionController::class, 'edit'])->whereNumber('section')->name('sections.edit');
                            Route::put('/article-sections/{section}', [ContentSectionController::class, 'update'])->whereNumber('section')->middleware('throttle:20,1')->name('sections.update');
                            Route::get('/{public_page}/edit', [PublicPageController::class, 'edit'])->name('pages.edit');
                            Route::put('/{public_page}', [PublicPageController::class, 'update'])->name('pages.update');
                        });
                });
        });

        Route::get('/entities/{entity}', [PublicSiteController::class, 'entity'])
            ->where('entity', 'iuoamc|icga|wsa-ca|wsact|iuoamc-tv|wicp')
            ->name('public.entities.show');

        Route::get('/{public_page:slug}', [PublicSiteController::class, 'show'])
            ->where('public_page', 'about|leadership|governance|entities|programmes|contact')
            ->name('public.pages.show');
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

// MCIJ_SCIENTIFIC_JOURNAL_CORE_1_0_0
require __DIR__.'/modules/journal.php';

// Secure verified-email self-service account portal.
require __DIR__.'/modules/account.php';

// Immutable PDF documents, invoices and receipts issued to account holders.
require __DIR__.'/modules/account_documents.php';
