<?php
declare(strict_types=1);

use App\Http\Controllers\Control\WicpController;
use App\Http\Controllers\WicpVerificationController;
use Illuminate\Support\Facades\Route;

Route::prefix('{locale}/control/wicp')->where(['locale' => 'ar|en|fr'])
    ->middleware(['locale', 'auth', 'active', 'password.changed', 'permission:wicp.view'])->name('wicp.')->group(function (): void {
        Route::get('/', [WicpController::class, 'index'])->name('index');
        Route::get('/programs/create', [WicpController::class, 'createProgram'])->middleware('permission:wicp.register')->name('programs.create');
        Route::post('/programs', [WicpController::class, 'storeProgram'])->middleware(['permission:wicp.register', 'throttle:20,1'])->name('programs.store');
        Route::get('/certificates/create', [WicpController::class, 'createCertificate'])->middleware('permission:wicp.register')->name('certificates.create');
        Route::post('/certificates', [WicpController::class, 'storeCertificate'])->middleware(['permission:wicp.register', 'throttle:30,1'])->name('certificates.store');
        Route::get('/{record}', [WicpController::class, 'show'])->whereNumber('record')->name('show');
        Route::post('/{record}/revoke', [WicpController::class, 'revoke'])->whereNumber('record')->middleware(['permission:wicp.revoke', 'throttle:10,1'])->name('revoke');
    });

Route::get('/verify/wicp/{token}', [WicpVerificationController::class, 'show'])->where('token', '[a-f0-9]{64}')->middleware('throttle:60,1')->name('wicp.verify');
Route::get('/verify/wicp/{token}/proof', [WicpVerificationController::class, 'proof'])->where('token', '[a-f0-9]{64}')->middleware('throttle:30,1')->name('wicp.proof');
Route::get('/verify/wicp/{invalidToken}', [WicpVerificationController::class, 'show'])->where('invalidToken', '[^/]+')->middleware('throttle:60,1')->name('wicp.invalid');
Route::get('/verify/wicp/{invalidToken}/proof', [WicpVerificationController::class, 'proof'])->where('invalidToken', '[^/]+')->middleware('throttle:30,1')->name('wicp.invalid-proof');
