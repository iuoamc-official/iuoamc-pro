<?php

declare(strict_types=1);

use App\Http\Controllers\Control\TvControlController;
use Illuminate\Support\Facades\Route;

Route::get('/{locale}/control/tv', [TvControlController::class, 'index'])
    ->where('locale', 'ar|en|fr')
    ->middleware(['locale', 'auth', 'active', 'password.changed', 'control.access'])
    ->name('tv.control.index');
