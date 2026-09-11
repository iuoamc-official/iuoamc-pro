<?php

declare(strict_types=1);

use App\Http\Controllers\Control\AccountDocumentController;
use Illuminate\Support\Facades\Route;

Route::prefix('{locale}/control/account-documents')->where(['locale' => 'ar|en|fr'])
    ->middleware(['locale', 'auth', 'active', 'password.changed', 'control.access', 'permission:account-documents.view'])
    ->name('account-documents.')->group(function (): void {
        Route::get('/', [AccountDocumentController::class, 'index'])->name('index');
        Route::get('/create', [AccountDocumentController::class, 'create'])
            ->middleware('permission:account-documents.manage')->name('create');
        Route::post('/', [AccountDocumentController::class, 'store'])
            ->middleware(['permission:account-documents.manage', 'throttle:10,1'])->name('store');
        Route::get('/{document}/download', [AccountDocumentController::class, 'download'])
            ->whereNumber('document')->middleware('throttle:20,1')->name('download');
    });
