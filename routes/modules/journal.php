<?php

declare(strict_types=1);

use App\Http\Controllers\Control\JournalArticleController;
use App\Http\Controllers\Control\JournalIssueController;
use App\Http\Controllers\Control\JournalReviewController;
use App\Http\Controllers\JournalPublicController;
use Illuminate\Support\Facades\Route;

Route::prefix('{locale}/journal')->where(['locale' => 'ar|en|fr'])->middleware('locale')->name('journal.public.')->group(function (): void {
    Route::get('/', [JournalPublicController::class, 'index'])->name('index');
    Route::get('/issues', [JournalPublicController::class, 'issues'])->name('issues.index');
    Route::get('/issues/{issue:slug}', [JournalPublicController::class, 'issue'])->name('issues.show');
    Route::get('/articles/{article:slug}', [JournalPublicController::class, 'show'])->name('articles.show');
    Route::get('/policies', [JournalPublicController::class, 'policies'])->name('policies');
});

Route::prefix('{locale}/control/journal')->where(['locale' => 'ar|en|fr'])
    ->middleware(['locale', 'auth', 'active', 'password.changed', 'permission:journal.view', \App\Http\Middleware\JournalControlHeaders::class])
    ->name('journal.control.')->group(function (): void {
        Route::get('/', [JournalArticleController::class, 'index'])->name('articles.index');
        Route::get('/articles/create', [JournalArticleController::class, 'create'])->middleware('permission:journal.manage')->name('articles.create');
        Route::post('/articles', [JournalArticleController::class, 'store'])->middleware(['permission:journal.manage', 'throttle:20,1'])->name('articles.store');
        Route::get('/articles/{article}', [JournalArticleController::class, 'show'])->whereNumber('article')->name('articles.show');
        Route::get('/articles/{article}/edit', [JournalArticleController::class, 'edit'])->whereNumber('article')->middleware('permission:journal.manage')->name('articles.edit');
        Route::put('/articles/{article}', [JournalArticleController::class, 'update'])->whereNumber('article')->middleware(['permission:journal.manage', 'throttle:30,1'])->name('articles.update');
        Route::post('/articles/{article}/transition', [JournalArticleController::class, 'transition'])->whereNumber('article')->middleware('throttle:30,1')->name('articles.transition');
        Route::post('/articles/{article}/corrections', [JournalArticleController::class, 'correction'])->whereNumber('article')->middleware(['permission:journal.publish', 'throttle:10,1'])->name('articles.corrections.store');
        Route::post('/articles/{article}/reviews', [JournalReviewController::class, 'store'])->whereNumber('article')->middleware(['permission:journal.review', 'throttle:20,1'])->name('reviews.store');
        Route::put('/reviews/{review}', [JournalReviewController::class, 'update'])->whereNumber('review')->middleware('throttle:20,1')->name('reviews.update');

        Route::get('/issues', [JournalIssueController::class, 'index'])->name('issues.index');
        Route::get('/issues/create', [JournalIssueController::class, 'create'])->middleware('permission:journal.manage')->name('issues.create');
        Route::post('/issues', [JournalIssueController::class, 'store'])->middleware(['permission:journal.manage', 'throttle:20,1'])->name('issues.store');
        Route::post('/issues/{issue}/publish', [JournalIssueController::class, 'publish'])->whereNumber('issue')->middleware(['permission:journal.publish', 'throttle:10,1'])->name('issues.publish');
    });
