<?php

namespace App\Http\Controllers\Settings;

use App\Actions\DeleteAiCredential;
use App\Actions\StoreAiCredential;
use App\Enums\AiProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreAiCredentialRequest;
use App\Http\Requests\Settings\UpdateAiCredentialRequest;
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
            // The active key first, then the newest.
            'credentials' => $request->user()->aiCredentials()->orderByDesc('is_default')->latest('id')->get()
                ->map(fn (AiCredential $credential): array => [
                    'id' => $credential->public_id,
                    'name' => $credential->name,
                    'provider' => ['value' => $credential->provider->value, 'label' => $credential->provider->label()],
                    'model' => $credential->model,
                    'masked_key' => $credential->maskedKey()->display(),
                    'active' => $credential->is_default,
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
            $request->string('name')->trim()->toString() ?: null,
            $request->boolean('activate'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('API key saved. Use “Test connection” to check it works.')]);

        return to_route('ai-credentials.index');
    }

    public function update(UpdateAiCredentialRequest $request, AiCredential $aiCredential): RedirectResponse
    {
        $modelChanged = $request->string('model')->toString() !== $aiCredential->model;

        $aiCredential->rename($request->string('name')->toString());
        $aiCredential->changeModel($request->string('model')->toString());

        Inertia::flash('toast', ['type' => 'success', 'message' => $modelChanged
            ? __('Key saved. The model changed, so use “Test connection” to check it works.')
            : __('Key saved.')]);

        return to_route('ai-credentials.index');
    }

    public function destroy(AiCredential $aiCredential, DeleteAiCredential $deleteAiCredential): RedirectResponse
    {
        $deleteAiCredential->handle($aiCredential);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('API key deleted.')]);

        return to_route('ai-credentials.index');
    }
}
