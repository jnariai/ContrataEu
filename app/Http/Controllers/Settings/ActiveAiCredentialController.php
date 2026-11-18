<?php

namespace App\Http\Controllers\Settings;

use App\Actions\ActivateAiCredential;
use App\Http\Controllers\Controller;
use App\Models\AiCredential;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/** Switching the active AI key, from the AI keys page or from the Generate panel (so it goes back to either). */
class ActiveAiCredentialController extends Controller
{
    public function store(AiCredential $aiCredential, ActivateAiCredential $activateAiCredential): RedirectResponse
    {
        $activateAiCredential->handle($aiCredential);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Now using :key (:model).', ['key' => $aiCredential->displayName(), 'model' => $aiCredential->model])]);

        return back();
    }
}
