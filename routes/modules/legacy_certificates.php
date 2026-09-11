<?php
declare(strict_types=1);

use App\Http\Controllers\Control\LegacyCertificateController;
use App\Http\Middleware\LegacyCertificateAccess;
use Illuminate\Support\Facades\Route;

Route::prefix('{locale}/control/legacy-certificates')->where(['locale' => 'ar|en|fr'])
    ->middleware(['locale', 'auth', 'active', 'password.changed', 'control.access', LegacyCertificateAccess::class])
    ->name('legacy-certificates.')->group(function (): void {
        Route::get('/', [LegacyCertificateController::class, 'index'])->name('index');
        Route::get('/{certificate}', [LegacyCertificateController::class, 'show'])->whereNumber('certificate')->name('show');
    });
