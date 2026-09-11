<?php
declare(strict_types=1);

use App\Http\Controllers\Control\ProCertificateController;
use App\Http\Controllers\Control\ProCertificateCatalogController;
use App\Http\Controllers\Control\ProCertificateBatchController;
use App\Http\Controllers\ProCertificateVerificationController;
use Illuminate\Support\Facades\Route;

Route::prefix('{locale}/control/certificates')->where(['locale' => 'ar|en|fr'])
    ->middleware(['locale', 'auth', 'active', 'password.changed', 'permission:certificates.view'])
    ->name('certificates.')->group(function (): void {
        Route::get('/', [ProCertificateController::class, 'index'])->name('index');
        Route::get('/create', [ProCertificateController::class, 'create'])->middleware('permission:certificates.manage')->name('create');
        Route::post('/', [ProCertificateController::class, 'store'])->middleware(['permission:certificates.manage', 'throttle:60,1'])->name('store');
        Route::get('/types', [ProCertificateCatalogController::class,'index'])->name('catalog.index');
        Route::get('/types/create', [ProCertificateCatalogController::class,'create'])->middleware('permission:certificates.catalog')->name('catalog.create');
        Route::post('/types', [ProCertificateCatalogController::class,'store'])->middleware(['permission:certificates.catalog','throttle:30,1'])->name('catalog.store');
        Route::get('/types/{type}/edit', [ProCertificateCatalogController::class,'edit'])->whereNumber('type')->middleware('permission:certificates.catalog')->name('catalog.edit');
        Route::put('/types/{type}', [ProCertificateCatalogController::class,'update'])->whereNumber('type')->middleware(['permission:certificates.catalog','throttle:30,1'])->name('catalog.update');
        Route::get('/batches', [ProCertificateBatchController::class,'index'])->name('batches.index');
        Route::get('/batches/collections/{collection}', [ProCertificateBatchController::class,'collection'])
            ->where('collection', '[a-f0-9]{64}')->name('batches.collections.show');
        Route::get('/batches/create', [ProCertificateBatchController::class,'create'])->middleware('permission:certificates.manage')->name('batches.create');
        Route::post('/batches', [ProCertificateBatchController::class,'store'])->middleware(['permission:certificates.manage','throttle:15,1,pc-batch-control'])->name('batches.store');
        Route::get('/batches/{batch}', [ProCertificateBatchController::class,'show'])->whereNumber('batch')->name('batches.show');
        Route::post('/batches/{batch}/runs', [ProCertificateBatchController::class,'start'])->whereNumber('batch')->middleware('throttle:15,1,pc-batch-control')->name('batches.start');
        Route::post('/runs/{run}/step', [ProCertificateBatchController::class,'step'])->whereNumber('run')->middleware('throttle:120,1,pc-batch-step')->name('runs.step');
        Route::get('/{certificate}', [ProCertificateController::class, 'show'])->whereNumber('certificate')->name('show');
        Route::get('/{certificate}/edit', [ProCertificateController::class, 'edit'])->whereNumber('certificate')->middleware('permission:certificates.manage')->name('edit');
        Route::put('/{certificate}', [ProCertificateController::class, 'update'])->whereNumber('certificate')->middleware(['permission:certificates.manage', 'throttle:60,1'])->name('update');
        Route::post('/{certificate}/actions/{action}', [ProCertificateController::class, 'transition'])->whereNumber('certificate')
            ->where('action', 'submit|return|approve|issue|revoke')->middleware('throttle:60,1')->name('transition');
        Route::get('/{certificate}/download', [ProCertificateController::class, 'download'])->whereNumber('certificate')->name('download');
        Route::get('/{certificate}/image/{variant}', [ProCertificateController::class, 'downloadImage'])
            ->whereNumber('certificate')->where('variant', 'print|share')->middleware('throttle:15,1')->name('image');
        Route::get('/{certificate}/preview', [ProCertificateController::class, 'preview'])->whereNumber('certificate')
            ->middleware(['permission:certificates.manage', 'throttle:30,1'])->name('preview');
    });

Route::get('/verify/c/{token}', [ProCertificateVerificationController::class, 'show'])
    ->where('token', '[a-f0-9]{64}')->middleware('throttle:60,1')->name('pro-certificates.verify');
Route::get('/verify/c/{token}/proof', [ProCertificateVerificationController::class, 'proof'])
    ->where('token', '[a-f0-9]{64}')->middleware('throttle:60,1')->name('pro-certificates.proof');

// Preserve the same neutral response and privacy headers for malformed references.
Route::get('/verify/c/{invalidToken}', [ProCertificateVerificationController::class, 'show'])
    ->where('invalidToken', '[^/]+')->middleware('throttle:60,1')->name('pro-certificates.invalid');
Route::get('/verify/c/{invalidToken}/proof', [ProCertificateVerificationController::class, 'proof'])
    ->where('invalidToken', '[^/]+')->middleware('throttle:60,1')->name('pro-certificates.invalid-proof');
