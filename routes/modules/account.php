<?php

declare(strict_types=1);

use App\Http\Controllers\Account\AccountController;
use App\Http\Controllers\Account\MembershipApplicationController;
use Illuminate\Support\Facades\Route;

Route::prefix('{locale}/account')->where(['locale' => 'ar|en|fr'])
    ->middleware(['locale', 'auth', 'active', 'verified'])
    ->name('account.')->group(function (): void {
        Route::get('/', [AccountController::class, 'index'])->name('dashboard');
        Route::patch('/profile', [AccountController::class, 'updateProfile'])
            ->middleware('throttle:10,1')->name('profile.update');
        Route::post('/refresh', [AccountController::class, 'refresh'])
            ->middleware('throttle:6,1')->name('refresh');
        Route::get('/membership/apply', [MembershipApplicationController::class, 'create'])
            ->name('membership.create');
        Route::post('/membership/apply', [MembershipApplicationController::class, 'store'])
            ->middleware('throttle:3,10')->name('membership.store');
        Route::get('/certificates/{certificate}/download', [AccountController::class, 'downloadCertificate'])
            ->whereNumber('certificate')->middleware('throttle:20,1')->name('certificates.download');
        Route::get('/memberships/{membership}/credentials/{credential}/{kind}', [AccountController::class, 'downloadMembershipCredential'])
            ->whereNumber('membership')->whereNumber('credential')->where('kind', 'card|certificate')
            ->middleware('throttle:20,1')->name('memberships.credentials.download');
        Route::get('/documents/{document}/download', [AccountController::class, 'downloadDocument'])
            ->whereNumber('document')->middleware('throttle:20,1')->name('documents.download');
    });
