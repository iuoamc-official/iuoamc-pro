<?php

declare(strict_types=1);

use App\Http\Controllers\Account\SensoryLearningController;
use App\Http\Controllers\Account\SensoryLearningProjectController;
use App\Http\Controllers\Auth\LearningAccessController;
use App\Http\Controllers\Control\LearningInvitationController;
use App\Http\Controllers\Control\SensoryLearningController as LearningControlController;
use App\Http\Controllers\Control\SensoryLearningProjectController as LearningProjectControlController;
use App\Http\Middleware\EnsureVerifiedAccountEmail;
use Illuminate\Support\Facades\Route;

Route::prefix('{locale}/learning')->where(['locale' => 'ar|en|fr'])->middleware('locale')->name('learning.access.')
    ->group(function (): void {
        Route::get('/', [LearningAccessController::class, 'create'])->name('index');
        Route::post('/activate', [LearningAccessController::class, 'store'])->middleware('throttle:6,1')->name('activate');
        Route::post('/login', [LearningAccessController::class, 'login'])->middleware('throttle:6,1')->name('login');
    });

Route::prefix('{locale}/account/learning')->where(['locale' => 'ar|en|fr'])
    ->middleware(['locale', 'auth', 'active', EnsureVerifiedAccountEmail::class])->name('account.learning.')
    ->group(function (): void {
        Route::get('/', [SensoryLearningController::class, 'index'])->name('index');
        Route::get('/lessons/{section}', [SensoryLearningController::class, 'lesson'])->whereNumber('section')->name('lesson');
        Route::post('/lessons/{section}/complete', [SensoryLearningController::class, 'complete'])->whereNumber('section')->middleware('throttle:60,1,learning-complete')->name('complete');
        Route::post('/lessons/{section}/notes', [SensoryLearningController::class, 'saveNote'])->whereNumber('section')->middleware('throttle:30,1,learning-notes')->name('notes.save');
        Route::get('/report', [SensoryLearningController::class, 'report'])->name('report');
        Route::get('/exam', [SensoryLearningController::class, 'exam'])->name('exam');
        Route::post('/exam/start', [SensoryLearningController::class, 'startExam'])->middleware('throttle:6,1')->name('exam.start');
        Route::post('/exam', [SensoryLearningController::class, 'saveExam'])->middleware('throttle:120,1')->name('exam.save');
        Route::get('/project', [SensoryLearningProjectController::class, 'show'])->name('project');
        Route::post('/project', [SensoryLearningProjectController::class, 'store'])->middleware('throttle:30,1,learning-project')->name('project.save');
        Route::get('/assignment', [SensoryLearningController::class, 'assignment'])->name('assignment');
        Route::post('/assignment', [SensoryLearningController::class, 'submitAssignment'])->middleware('throttle:6,1')->name('assignment.submit');
    });

Route::prefix('{locale}/control/learning')->where(['locale' => 'ar|en|fr'])
    ->middleware(['locale', 'auth', 'active', 'password.changed', 'control.access'])->name('learning.control.')
    ->group(function (): void {
        Route::get('/', [LearningControlController::class, 'index'])->name('index');
        Route::post('/enroll', [LearningControlController::class, 'enroll'])->middleware('throttle:20,1')->name('enroll');
        Route::post('/invitations', [LearningInvitationController::class, 'store'])->middleware('throttle:20,1')->name('invitations.store');
        Route::delete('/invitations/{invitation}', [LearningInvitationController::class, 'destroy'])->whereNumber('invitation')->name('invitations.destroy');
        Route::get('/invitations/{invitation}/edit', [LearningInvitationController::class, 'edit'])->whereNumber('invitation')->name('invitations.edit');
        Route::put('/invitations/{invitation}', [LearningInvitationController::class, 'update'])->whereNumber('invitation')->middleware('throttle:20,1')->name('invitations.update');
        Route::post('/invitations/{invitation}/archive', [LearningInvitationController::class, 'archive'])->whereNumber('invitation')->middleware('throttle:20,1')->name('invitations.archive');
        Route::get('/{enrollment}/delete', [LearningControlController::class, 'confirmDelete'])->whereNumber('enrollment')->name('delete');
        Route::delete('/{enrollment}', [LearningControlController::class, 'destroy'])->whereNumber('enrollment')->middleware('throttle:20,1')->name('destroy');
        Route::get('/{enrollment}', [LearningControlController::class, 'show'])->whereNumber('enrollment')->name('show');
        Route::get('/{enrollment}/project', [LearningProjectControlController::class, 'show'])->whereNumber('enrollment')->name('project');
        Route::post('/{enrollment}/project/review', [LearningProjectControlController::class, 'review'])->whereNumber('enrollment')->middleware('throttle:30,1,learning-project-review')->name('project.review');
        Route::get('/{enrollment}/report', [LearningControlController::class, 'report'])->whereNumber('enrollment')->name('report');
        Route::post('/{enrollment}/review', [LearningControlController::class, 'review'])->whereNumber('enrollment')->middleware('throttle:20,1')->name('review');
    });
