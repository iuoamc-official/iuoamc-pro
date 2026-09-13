<?php

declare(strict_types=1);

use App\Http\Controllers\Control\JournalArticleController;
use App\Http\Controllers\Control\JournalIssueController;
use App\Http\Controllers\Control\JournalEditorialMemberController;
use App\Http\Controllers\Control\JournalOperationsController;
use App\Http\Controllers\Control\JournalReviewController;
use App\Http\Controllers\Control\JournalSubmissionController as ControlJournalSubmissionController;
use App\Http\Controllers\Control\JournalSectionController;
use App\Http\Controllers\JournalPublicController;
use App\Http\Controllers\JournalMetadataController;
use App\Http\Controllers\JournalSubmissionController;
use App\Http\Middleware\EnsureJournalLaunched;
use Illuminate\Support\Facades\Route;

Route::prefix('{locale}/journal')->where(['locale' => 'ar|en|fr'])->middleware(['locale', EnsureJournalLaunched::class])->name('journal.public.')->group(function (): void {
    Route::get('/', [JournalPublicController::class, 'index'])->name('index');
    Route::get('/issues', [JournalPublicController::class, 'issues'])->name('issues.index');
    Route::get('/issues/{issue:slug}', [JournalPublicController::class, 'issue'])->name('issues.show');
    Route::get('/articles/{article:slug}', [JournalPublicController::class, 'show'])->name('articles.show');
    Route::get('/articles/{article:slug}/pdf', [JournalPublicController::class, 'downloadPdf'])->middleware('throttle:60,1')->name('articles.pdf');
    Route::get('/articles/{article:slug}/citation/{format}', [JournalMetadataController::class, 'citation'])->where('format', 'bibtex|ris')->middleware('throttle:60,1')->name('articles.citation');
    Route::get('/articles/{article:slug}/jats.xml', [JournalMetadataController::class, 'jats'])->middleware('throttle:60,1')->name('articles.jats');
    Route::get('/registry/wicp/{registration}', [JournalPublicController::class, 'registry'])->name('registry.show');
    Route::get('/policies', [JournalPublicController::class, 'policies'])->name('policies');
    Route::get('/author-guidelines', [JournalPublicController::class, 'authorGuidelines'])->name('author-guidelines');
    Route::get('/editorial-governance', [JournalPublicController::class, 'editorialGovernance'])->name('editorial-governance');
    Route::get('/submit', [JournalSubmissionController::class, 'create'])->name('submissions.create');
    Route::post('/submit', [JournalSubmissionController::class, 'store'])->middleware('throttle:5,1')->name('submissions.store');
    Route::get('/submission-confirmation', [JournalSubmissionController::class, 'confirmation'])->name('submissions.confirmation');
    Route::get('/track-submission', [JournalSubmissionController::class, 'tracking'])->name('submissions.tracking');
    Route::post('/track-submission', [JournalSubmissionController::class, 'track'])->middleware('throttle:20,1')->name('submissions.track');
    Route::post('/track-submission/revisions', [JournalSubmissionController::class, 'storeRevision'])->middleware('throttle:5,1')->name('submissions.revisions.store');
});

Route::get('/journal/oai', [JournalMetadataController::class, 'oai'])
    ->middleware(['throttle:120,1', EnsureJournalLaunched::class])
    ->name('journal.oai');

Route::prefix('{locale}/control/journal')->where(['locale' => 'ar|en|fr'])
    ->middleware(['locale', 'auth', 'active', 'password.changed', 'control.access', 'permission:journal.view', \App\Http\Middleware\JournalControlHeaders::class])
    ->name('journal.control.')->group(function (): void {
        Route::get('/', [JournalArticleController::class, 'index'])->name('articles.index');
        Route::get('/articles/create', [JournalArticleController::class, 'create'])->middleware('permission:journal.manage')->name('articles.create');
        Route::post('/articles', [JournalArticleController::class, 'store'])->middleware(['permission:journal.manage', 'throttle:20,1'])->name('articles.store');
        Route::get('/articles/{article}', [JournalArticleController::class, 'show'])->whereNumber('article')->name('articles.show');
        Route::get('/articles/{article}/edit', [JournalArticleController::class, 'edit'])->whereNumber('article')->middleware('permission:journal.manage')->name('articles.edit');
        Route::put('/articles/{article}', [JournalArticleController::class, 'update'])->whereNumber('article')->middleware(['permission:journal.manage', 'throttle:30,1'])->name('articles.update');
        Route::post('/articles/{article}/publication-assets', [JournalArticleController::class, 'publicationAssets'])->whereNumber('article')->middleware(['permission:journal.manage', 'throttle:10,1'])->name('articles.publication-assets');
        Route::post('/articles/{article}/transition', [JournalArticleController::class, 'transition'])->whereNumber('article')->middleware('throttle:30,1')->name('articles.transition');
        Route::post('/articles/{article}/corrections', [JournalArticleController::class, 'correction'])->whereNumber('article')->middleware(['permission:journal.publish', 'throttle:10,1'])->name('articles.corrections.store');
        Route::get('/sections', [JournalSectionController::class, 'index'])->middleware('permission:journal.manage')->name('sections.index');
        Route::get('/sections/create', [JournalSectionController::class, 'create'])->middleware('permission:journal.manage')->name('sections.create');
        Route::post('/sections', [JournalSectionController::class, 'store'])->middleware(['permission:journal.manage', 'throttle:20,1'])->name('sections.store');
        Route::get('/sections/{section}/edit', [JournalSectionController::class, 'edit'])->whereNumber('section')->middleware('permission:journal.manage')->name('sections.edit');
        Route::put('/sections/{section}', [JournalSectionController::class, 'update'])->whereNumber('section')->middleware(['permission:journal.manage', 'throttle:20,1'])->name('sections.update');
        Route::post('/articles/{article}/reviews', [JournalReviewController::class, 'store'])->whereNumber('article')->middleware(['permission:journal.review', 'throttle:20,1'])->name('reviews.store');
        Route::post('/reviews/{review}/response', [JournalReviewController::class, 'respond'])->whereNumber('review')->middleware(['permission:journal.review', 'throttle:20,1'])->name('reviews.respond');
        Route::put('/reviews/{review}', [JournalReviewController::class, 'update'])->whereNumber('review')->middleware('throttle:20,1')->name('reviews.update');

        Route::get('/submissions', [ControlJournalSubmissionController::class, 'index'])->middleware('permission:journal.submissions')->name('submissions.index');
        Route::get('/submissions/{submission}', [ControlJournalSubmissionController::class, 'show'])->whereNumber('submission')->middleware('permission:journal.submissions')->name('submissions.show');
        Route::get('/submissions/{submission}/manuscript', [ControlJournalSubmissionController::class, 'download'])->whereNumber('submission')->middleware(['permission:journal.submissions', 'throttle:30,1'])->name('submissions.download');
        Route::get('/submissions/{submission}/revisions/{revision}/{file}', [ControlJournalSubmissionController::class, 'downloadRevision'])->whereNumber(['submission', 'revision'])->whereIn('file', ['manuscript', 'response-letter'])->middleware(['permission:journal.submissions', 'throttle:30,1'])->name('submissions.revisions.download');
        Route::post('/submissions/{submission}/screen', [ControlJournalSubmissionController::class, 'screen'])->whereNumber('submission')->middleware(['permission:journal.submissions', 'throttle:20,1'])->name('submissions.screen');
        Route::post('/submissions/{submission}/decline', [ControlJournalSubmissionController::class, 'decline'])->whereNumber('submission')->middleware(['permission:journal.submissions', 'throttle:20,1'])->name('submissions.decline');
        Route::post('/submissions/{submission}/convert', [ControlJournalSubmissionController::class, 'convert'])->whereNumber('submission')->middleware(['permission:journal.submissions', 'throttle:10,1'])->name('submissions.convert');

        Route::get('/issues', [JournalIssueController::class, 'index'])->name('issues.index');
        Route::get('/issues/create', [JournalIssueController::class, 'create'])->middleware('permission:journal.manage')->name('issues.create');
        Route::post('/issues', [JournalIssueController::class, 'store'])->middleware(['permission:journal.manage', 'throttle:20,1'])->name('issues.store');
        Route::post('/issues/{issue}/publish', [JournalIssueController::class, 'publish'])->whereNumber('issue')->middleware(['permission:journal.publish', 'throttle:10,1'])->name('issues.publish');

        Route::middleware('permission:journal.governance')->group(function (): void {
            Route::get('/operations', [JournalOperationsController::class, 'index'])->name('operations.index');
            Route::put('/operations', [JournalOperationsController::class, 'update'])->middleware('throttle:20,1')->name('operations.update');
            Route::post('/operations/backup-verifications', [JournalOperationsController::class, 'confirmBackup'])->middleware('throttle:5,1')->name('operations.backup-verifications.store');
            Route::post('/operations/public-launch', [JournalOperationsController::class, 'launch'])->middleware('throttle:5,1')->name('operations.public-launch.store');
            Route::delete('/operations/public-launch', [JournalOperationsController::class, 'unlaunch'])->middleware('throttle:5,1')->name('operations.public-launch.destroy');

            Route::get('/editorial-members', [JournalEditorialMemberController::class, 'index'])->name('editorial-members.index');
            Route::get('/editorial-members/create', [JournalEditorialMemberController::class, 'create'])->name('editorial-members.create');
            Route::post('/editorial-members', [JournalEditorialMemberController::class, 'store'])->middleware('throttle:20,1')->name('editorial-members.store');
            Route::get('/editorial-members/{editorial_member}/edit', [JournalEditorialMemberController::class, 'edit'])->whereNumber('editorial_member')->name('editorial-members.edit');
            Route::put('/editorial-members/{editorial_member}', [JournalEditorialMemberController::class, 'update'])->whereNumber('editorial_member')->middleware('throttle:20,1')->name('editorial-members.update');
            Route::post('/editorial-members/{editorial_member}/archive', [JournalEditorialMemberController::class, 'archive'])->whereNumber('editorial_member')->middleware('throttle:10,1')->name('editorial-members.archive');
        });
    });
