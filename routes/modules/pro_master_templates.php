<?php
declare(strict_types=1);

use App\Http\Controllers\Control\ProMasterTemplateController;
use Illuminate\Support\Facades\Route;

Route::prefix('{locale}/control/certificates/master-templates')->where(['locale' => 'ar|en|fr'])
    ->middleware(['locale', 'auth', 'active', 'password.changed', 'control.access', 'permission:certificates.view'])
    ->name('certificates.master.')->group(function (): void {
        Route::get('/', [ProMasterTemplateController::class, 'index'])->name('index');
        Route::get('/{model}/preview', [ProMasterTemplateController::class, 'preview'])
            ->where('model', 'tasting|judging')->middleware('throttle:30,1')->name('preview');
    });
