<?php

namespace App\Http\Controllers\Settings;

use App\Actions\VerifyAiCredential;
use App\Http\Controllers\Controller;
use App\Models\AiCredential;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class AiCredentialVerificationController extends Controller
{
    /** "Test connection" for one key. Failures become an error toast through the global exception handling. */
    public function store(AiCredential $aiCredential, VerifyAiCredential $verifyAiCredential): RedirectResponse
    {
        $verifyAiCredential->handle($aiCredential);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Connection works. :provider accepted the key.', ['provider' => $aiCredential->provider->label()])]);

        return to_route('ai-credentials.index');
    }
}
