<?php
declare(strict_types=1);

use App\Http\Controllers\Control\MembershipController;
use App\Http\Controllers\Control\GovernanceHubController;
use Illuminate\Support\Facades\Route;

Route::get('/{locale}/control/governance', [GovernanceHubController::class, 'index'])
    ->where('locale', 'ar|en|fr')->middleware(['locale', 'auth', 'active', 'password.changed'])->name('governance.index');

Route::prefix('{locale}/control/memberships')->where(['locale' => 'ar|en|fr'])
    ->middleware(['locale', 'auth', 'active', 'password.changed', 'permission:memberships.view'])
    ->name('memberships.')->group(function (): void {
        Route::get('/', [MembershipController::class, 'index'])->name('index');
        Route::get('/create', [MembershipController::class, 'create'])->middleware('permission:memberships.manage')->name('create');
        Route::post('/', [MembershipController::class, 'store'])->middleware(['permission:memberships.manage', 'throttle:60,1'])->name('store');
        Route::get('/{membership}', [MembershipController::class, 'show'])->whereNumber('membership')->name('show');
        Route::get('/{membership}/edit', [MembershipController::class, 'edit'])->whereNumber('membership')->middleware('permission:memberships.manage')->name('edit');
        Route::put('/{membership}', [MembershipController::class, 'update'])->whereNumber('membership')->middleware(['permission:memberships.manage', 'throttle:60,1'])->name('update');
        Route::get('/{membership}/correct', [MembershipController::class, 'correct'])->whereNumber('membership')
            ->middleware('permission:memberships.correct')->name('correct');
        Route::post('/{membership}/correct', [MembershipController::class, 'storeCorrection'])->whereNumber('membership')
            ->middleware(['permission:memberships.correct', 'throttle:10,1'])->name('correction.store');
        Route::post('/{membership}/actions/{action}', [MembershipController::class, 'transition'])
            ->whereNumber('membership')->where('action', 'submit|return|reject|approve|reopen|suspend|reinstate|revoke|renew')
            ->middleware('throttle:60,1')->name('transition');
    });
