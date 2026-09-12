<?php
declare(strict_types=1);

use App\Http\Controllers\Control\MembershipController;
use App\Http\Controllers\Control\MembershipCatalogController;
use App\Http\Controllers\Control\GovernanceHubController;
use App\Http\Controllers\MembershipVerificationController;
use Illuminate\Support\Facades\Route;

Route::get('/{locale}/control/governance', [GovernanceHubController::class, 'index'])
    ->where('locale', 'ar|en|fr')->middleware(['locale', 'auth', 'active', 'password.changed', 'control.access'])->name('governance.index');

Route::prefix('{locale}/control/memberships')->where(['locale' => 'ar|en|fr'])
    ->middleware(['locale', 'auth', 'active', 'password.changed', 'control.access', 'permission:memberships.view'])
    ->name('memberships.')->group(function (): void {
        Route::get('/', [MembershipController::class, 'index'])->name('index');
        Route::get('/settings', [MembershipCatalogController::class, 'index'])->middleware('permission:memberships.manage')->name('settings');
        Route::post('/settings/titles', [MembershipCatalogController::class, 'storeTitle'])->middleware(['permission:memberships.manage', 'throttle:30,1'])->name('settings.titles.store');
        Route::put('/settings/titles/{title}', [MembershipCatalogController::class, 'updateTitle'])->whereNumber('title')->middleware(['permission:memberships.manage', 'throttle:60,1'])->name('settings.titles.update');
        Route::post('/settings/plans', [MembershipCatalogController::class, 'storePlan'])->middleware(['permission:memberships.manage', 'throttle:30,1'])->name('settings.plans.store');
        Route::put('/settings/plans/{plan}', [MembershipCatalogController::class, 'updatePlan'])->whereNumber('plan')->middleware(['permission:memberships.manage', 'throttle:60,1'])->name('settings.plans.update');
        Route::post('/settings/payments', [MembershipCatalogController::class, 'storePaymentMethod'])->middleware(['permission:memberships.manage', 'throttle:30,1'])->name('settings.payments.store');
        Route::put('/settings/payments/{paymentMethod}', [MembershipCatalogController::class, 'updatePaymentMethod'])->whereNumber('paymentMethod')->middleware(['permission:memberships.manage', 'throttle:60,1'])->name('settings.payments.update');
        Route::get('/create', [MembershipController::class, 'create'])->middleware('permission:memberships.manage')->name('create');
        Route::post('/', [MembershipController::class, 'store'])->middleware(['permission:memberships.manage', 'throttle:60,1'])->name('store');
        Route::get('/{membership}', [MembershipController::class, 'show'])->whereNumber('membership')->name('show');
        Route::get('/{membership}/edit', [MembershipController::class, 'edit'])->whereNumber('membership')->middleware('permission:memberships.manage')->name('edit');
        Route::put('/{membership}', [MembershipController::class, 'update'])->whereNumber('membership')->middleware(['permission:memberships.manage', 'throttle:60,1'])->name('update');
        Route::get('/{membership}/correct', [MembershipController::class, 'correct'])->whereNumber('membership')
            ->middleware('permission:memberships.correct')->name('correct');
        Route::post('/{membership}/correct', [MembershipController::class, 'storeCorrection'])->whereNumber('membership')
            ->middleware(['permission:memberships.correct', 'throttle:10,1'])->name('correction.store');
        Route::get('/{membership}/application', [MembershipController::class, 'application'])->whereNumber('membership')
            ->middleware('permission:memberships.manage')->name('application');
        Route::put('/{membership}/application', [MembershipController::class, 'updateApplication'])->whereNumber('membership')
            ->middleware(['permission:memberships.manage', 'throttle:20,1'])->name('application.update');
        Route::post('/{membership}/credentials', [MembershipController::class, 'issueCredentials'])->whereNumber('membership')
            ->middleware(['permission:memberships.issue', 'throttle:5,1'])->name('credentials.issue');
        Route::get('/{membership}/credentials/{credential}/{kind}', [MembershipController::class, 'downloadCredential'])
            ->whereNumber('membership')->whereNumber('credential')->where('kind', 'card|certificate')
            ->middleware('throttle:20,1')->name('credentials.download');
        Route::post('/{membership}/actions/{action}', [MembershipController::class, 'transition'])
            ->whereNumber('membership')->where('action', 'submit|return|reject|approve|reopen|suspend|reinstate|revoke|renew')
            ->middleware('throttle:60,1')->name('transition');
    });


Route::get('/verify/m/{token}', [MembershipVerificationController::class, 'show'])
    ->where('token', '[a-f0-9]{64}')->middleware('throttle:60,1')->name('memberships.verify');
Route::get('/verify/m/{token}/proof', [MembershipVerificationController::class, 'proof'])
    ->where('token', '[a-f0-9]{64}')->middleware('throttle:60,1')->name('memberships.proof');
Route::get('/verify/m/{invalidToken}', [MembershipVerificationController::class, 'show'])
    ->where('invalidToken', '[^/]+')->middleware('throttle:60,1')->name('memberships.verify-invalid');
