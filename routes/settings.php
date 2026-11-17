<?php

use App\Http\Controllers\Settings\AiCredentialController;
use App\Http\Controllers\Settings\AiCredentialVerificationController;
use App\Http\Controllers\Settings\CandidateProfileController;
use App\Http\Controllers\Settings\DefaultAiCredentialController;
use App\Http\Controllers\Settings\MasterDocumentController;
use App\Http\Controllers\Settings\MasterDocumentFileController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\PromptOverrideController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'throttle:writes'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified', 'throttle:writes'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');

    Route::get('settings/candidate', [CandidateProfileController::class, 'edit'])->name('candidate-profile.edit');
    Route::put('settings/candidate', [CandidateProfileController::class, 'update'])->name('candidate-profile.update');

    Route::get('settings/documents', [MasterDocumentController::class, 'index'])->name('master-documents.index');
    Route::put('settings/documents/{type}', [MasterDocumentController::class, 'update'])->name('master-documents.update');
    Route::post('settings/documents/{type}/file', [MasterDocumentFileController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('master-document-files.store');
    Route::delete('settings/documents/{type}/file', [MasterDocumentFileController::class, 'destroy'])->name('master-document-files.destroy');

    Route::get('settings/ai', [AiCredentialController::class, 'index'])->name('ai-credentials.index');
    Route::post('settings/ai', [AiCredentialController::class, 'store'])->name('ai-credentials.store');
    Route::delete('settings/ai/{aiCredential}', [AiCredentialController::class, 'destroy'])
        ->middleware('can:delete,aiCredential')
        ->name('ai-credentials.destroy');
    Route::post('settings/ai/{aiCredential}/verification', [AiCredentialVerificationController::class, 'store'])
        ->middleware(['can:update,aiCredential', 'throttle:10,1'])
        ->name('ai-credential-verifications.store');
    Route::post('settings/ai/{aiCredential}/default', [DefaultAiCredentialController::class, 'store'])
        ->middleware('can:update,aiCredential')
        ->name('default-ai-credentials.store');

    Route::get('settings/prompts', [PromptOverrideController::class, 'index'])->name('prompt-overrides.index');
    Route::put('settings/prompts/{key}', [PromptOverrideController::class, 'update'])->name('prompt-overrides.update');
    Route::delete('settings/prompts/{key}', [PromptOverrideController::class, 'destroy'])->name('prompt-overrides.destroy');
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
