<?php

namespace App\Http\Controllers\Settings;

use App\Actions\MakeAiCredentialDefault;
use App\Http\Controllers\Controller;
use App\Models\AiCredential;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class DefaultAiCredentialController extends Controller
{
    public function store(AiCredential $aiCredential, MakeAiCredentialDefault $makeAiCredentialDefault): RedirectResponse
    {
        $makeAiCredentialDefault->handle($aiCredential);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Default API key changed.')]);

        return to_route('ai-credentials.index');
    }
}
