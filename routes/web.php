<?php

use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\ApplicationStatusController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\GeneratedAnswerController;
use App\Http\Controllers\GeneratedAnswerRevertController;
use App\Http\Controllers\GeneratedDocumentController;
use App\Http\Controllers\GeneratedDocumentRevertController;
use App\Http\Controllers\GenerationController;
use App\Http\Controllers\LocaleController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::patch('locale', [LocaleController::class, 'update'])->middleware('throttle:30,1')->name('locale.update');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('applications', ApplicationController::class)->except('index')
        ->middlewareFor('show', 'can:view,application')
        ->middlewareFor('edit', 'can:update,application')
        ->middlewareFor('destroy', 'can:delete,application');
    Route::patch('applications/{application}/status', [ApplicationStatusController::class, 'update'])->name('application-statuses.update');
    Route::post('applications/{application}/generations', [GenerationController::class, 'store'])
        ->middleware('can:generate,application')
        ->name('generations.store');

    Route::patch('generated-documents/{document}', [GeneratedDocumentController::class, 'update'])->name('generated-documents.update');
    Route::post('generated-documents/{document}/revert', [GeneratedDocumentRevertController::class, 'store'])
        ->middleware('can:update,document')
        ->name('generated-documents.revert');
    Route::patch('generated-answers/{answer}', [GeneratedAnswerController::class, 'update'])->name('generated-answers.update');
    Route::post('generated-answers/{answer}/revert', [GeneratedAnswerRevertController::class, 'store'])
        ->middleware('can:update,answer')
        ->name('generated-answers.revert');

    Route::get('files/{storedFile}', [FileController::class, 'show'])
        ->middleware('can:view,storedFile')
        ->name('files.show');
});

require __DIR__.'/settings.php';
