<?php

declare(strict_types=1);

use App\Http\Controllers\Account\AccountController;
use App\Http\Controllers\Account\MembershipApplicationController;
use App\Http\Controllers\LegalDocumentController;
use App\Http\Middleware\EnsureVerifiedAccountEmail;
use Illuminate\Support\Facades\Route;

Route::prefix('{locale}/legal')->where(['locale' => 'ar|en|fr'])
    ->middleware('locale')->name('legal.')->group(function (): void {
        Route::get('/membership-terms', [LegalDocumentController::class, 'membershipTerms'])
            ->name('membership-terms');
        Route::get('/membership-privacy', [LegalDocumentController::class, 'membershipPrivacy'])
            ->name('membership-privacy');
    });

Route::prefix('{locale}/account')->where(['locale' => 'ar|en|fr'])
    ->middleware(['locale', 'auth', 'active', EnsureVerifiedAccountEmail::class])
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
        Route::get('/memberships/{membership}/credentials/{credential}/print-images', [AccountController::class, 'downloadMembershipCardPrintImages'])
            ->whereNumber('membership')->whereNumber('credential')
            ->middleware('throttle:10,1')->name('memberships.credentials.print-images');
        Route::get('/memberships/{membership}/credentials/{credential}/{kind}', [AccountController::class, 'downloadMembershipCredential'])
            ->whereNumber('membership')->whereNumber('credential')->where('kind', 'card|certificate')
            ->middleware('throttle:20,1')->name('memberships.credentials.download');
        Route::get('/documents/{document}/download', [AccountController::class, 'downloadDocument'])
            ->whereNumber('document')->middleware('throttle:20,1')->name('documents.download');
    });
