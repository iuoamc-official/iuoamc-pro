<?php
declare(strict_types=1);

use App\Http\Controllers\Control\ProCertificateIntakeController;
use App\Http\Controllers\Control\ProCertificateIntakeProgramController;
use App\Http\Controllers\ProCertificateIntakePublicController;
use App\Http\Controllers\ProCertificateIntakeProgramPublicController;
use Illuminate\Support\Facades\Route;

Route::prefix('{locale}/control/certificates/data-confirmations')->where(['locale' => 'ar|en|fr'])
    ->middleware(['locale', 'auth', 'active', 'password.changed', 'control.access', 'permission:certificates.view', 'permission:certificates.manage'])
    ->name('certificates.intakes.')->group(function (): void {
        Route::get('/', [ProCertificateIntakeProgramController::class, 'index'])->name('index');
        Route::get('/programs', [ProCertificateIntakeProgramController::class, 'index'])->name('programs.index');
        Route::get('/programs/{program}', [ProCertificateIntakeProgramController::class, 'show'])->whereNumber('program')->name('programs.show');
        Route::post('/programs/{program}/toggle', [ProCertificateIntakeProgramController::class, 'toggle'])->whereNumber('program')
            ->middleware('throttle:30,1,certificate-intake-admin')->name('programs.toggle');
        Route::get('/responses/{response}', [ProCertificateIntakeProgramController::class, 'response'])->whereNumber('response')->name('responses.show');
        Route::post('/responses/{response}/review', [ProCertificateIntakeProgramController::class, 'review'])->whereNumber('response')
            ->middleware(['permission:certificates.review', 'throttle:30,1,certificate-intake-admin'])->name('responses.review');
        Route::post('/responses/{response}/dismiss', [ProCertificateIntakeProgramController::class, 'dismiss'])->whereNumber('response')
            ->middleware(['permission:certificates.review', 'throttle:30,1,certificate-intake-admin'])->name('responses.dismiss');
        Route::get('/{intake}', [ProCertificateIntakeController::class, 'show'])->whereNumber('intake')->name('show');
        Route::post('/{intake}/invite', [ProCertificateIntakeController::class, 'invite'])->whereNumber('intake')
            ->middleware('throttle:30,1,certificate-intake-admin')->name('invite');
        Route::post('/{intake}/review', [ProCertificateIntakeController::class, 'review'])->whereNumber('intake')
            ->middleware(['permission:certificates.review', 'throttle:30,1,certificate-intake-admin'])->name('review');
        Route::post('/{intake}/cancel', [ProCertificateIntakeController::class, 'cancel'])->whereNumber('intake')
            ->middleware('throttle:30,1,certificate-intake-admin')->name('cancel');
    });

Route::prefix('{locale}/certificate-data')->where(['locale' => 'ar|en|fr'])->middleware('locale')
    ->name('certificate-data.')->group(function (): void {
        Route::get('/received', [ProCertificateIntakePublicController::class, 'received'])->middleware('throttle:60,1,certificate-intake-public')->name('received');
        Route::get('/program/{code}', [ProCertificateIntakeProgramPublicController::class, 'form'])->where('code', '[A-Z0-9][A-Z0-9._-]{0,79}')
            ->middleware('throttle:60,1,certificate-intake-program')->name('program.form');
        Route::post('/program/{code}', [ProCertificateIntakeProgramPublicController::class, 'submit'])->where('code', '[A-Z0-9][A-Z0-9._-]{0,79}')
            ->middleware('throttle:12,1,certificate-intake-program-submit')->name('program.submit');
        Route::match(['GET', 'POST'], '/program/{invalid}', [ProCertificateIntakePublicController::class, 'unavailable'])
            ->where('invalid', '[^/]+')->middleware('throttle:12,1,certificate-intake-program-invalid')->name('program.invalid');
        Route::get('/{token}', [ProCertificateIntakePublicController::class, 'form'])->where('token', '[a-f0-9]{64}')
            ->middleware('throttle:60,1,certificate-intake-public')->name('form');
        Route::post('/{token}', [ProCertificateIntakePublicController::class, 'submit'])->where('token', '[a-f0-9]{64}')
            ->middleware('throttle:12,1,certificate-intake-submit')->name('submit');
        // Invalid references receive the same generic response and privacy headers.
        Route::match(['GET', 'POST'], '/{invalid}', [ProCertificateIntakePublicController::class, 'unavailable'])
            ->where('invalid', '[^/]+')->middleware('throttle:12,1,certificate-intake-invalid')->name('invalid');
    });
