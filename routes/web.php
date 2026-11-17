<?php

use App\Http\Controllers\FileController;
use App\Http\Controllers\LocaleController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::patch('locale', [LocaleController::class, 'update'])->middleware('throttle:30,1')->name('locale.update');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::get('files/{storedFile}', [FileController::class, 'show'])
        ->middleware('can:view,storedFile')
        ->name('files.show');
});

require __DIR__.'/settings.php';
