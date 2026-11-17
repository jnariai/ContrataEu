<?php

namespace App\Http\Controllers\Settings;

use App\Enums\PromptKey;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdatePromptOverrideRequest;
use App\Models\PromptOverride;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PromptOverrideController extends Controller
{
    public function index(Request $request): Response
    {
        $overrides = $request->user()->promptOverrides()->get()
            ->keyBy(fn (PromptOverride $override): string => $override->key->value);

        return Inertia::render('settings/prompts', [
            'prompts' => array_map(fn (PromptKey $key): array => [
                'key' => $key->value,
                'label' => $key->label(),
                'description' => $key->description(),
                'default' => $key->defaultContent(),
                'override' => $overrides->get($key->value)?->content,
            ], PromptKey::cases()),
            'maxCharacters' => UpdatePromptOverrideRequest::MAX_CHARACTERS,
        ]);
    }

    public function update(UpdatePromptOverrideRequest $request, PromptKey $key): RedirectResponse
    {
        PromptOverride::saveFor($request->user(), $key, $request->string('content')->toString());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':prompt saved. New generations will use it.', ['prompt' => $key->label()])]);

        return to_route('prompt-overrides.index');
    }

    public function destroy(Request $request, PromptKey $key): RedirectResponse
    {
        PromptOverride::resetFor($request->user(), $key);

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':prompt reset to the default.', ['prompt' => $key->label()])]);

        return to_route('prompt-overrides.index');
    }
}
