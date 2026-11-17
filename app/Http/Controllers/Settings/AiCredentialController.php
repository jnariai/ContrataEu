<?php

namespace App\Http\Controllers\Settings;

use App\Actions\DeleteAiCredential;
use App\Actions\StoreAiCredential;
use App\Enums\AiProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreAiCredentialRequest;
use App\Models\AiCredential;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AiCredentialController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('settings/ai', [
            'credentials' => $request->user()->aiCredentials()->latest('id')->get()
                ->map(fn (AiCredential $credential): array => [
                    'id' => $credential->public_id,
                    'provider' => ['value' => $credential->provider->value, 'label' => $credential->provider->label()],
                    'model' => $credential->model,
                    'masked_key' => $credential->maskedKey()->display(),
                    'is_default' => $credential->is_default,
                    'last_verified_at' => $credential->last_verified_at?->toIso8601ZuluString(),
                ])->values(),
            'providers' => AiProvider::options(),
        ]);
    }

    public function store(StoreAiCredentialRequest $request, StoreAiCredential $storeAiCredential): RedirectResponse
    {
        $storeAiCredential->handle(
            $request->user(),
            $request->provider(),
            $request->string('api_key')->trim()->toString(),
            $request->string('model')->trim()->toString() ?: null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('API key saved. Use “Test connection” to check it works.')]);

        return to_route('ai-credentials.index');
    }

    public function destroy(AiCredential $aiCredential, DeleteAiCredential $deleteAiCredential): RedirectResponse
    {
        $deleteAiCredential->handle($aiCredential);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('API key deleted.')]);

        return to_route('ai-credentials.index');
    }
}
