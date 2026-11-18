<?php

use App\Enums\AiProvider;
use App\Models\AiCredential;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;

it('lists keys masked, never the key itself', function (): void {
    $user = User::factory()->create();
    AiCredential::factory()->for($user)->asDefault()->create(['api_key' => 'sk-ant-SUPERSECRET-a1b2', 'key_hint' => 'a1b2']);

    $response = $this->actingAs($user)->get(route('ai-credentials.index'));

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('settings/ai')
        ->where('credentials.0.masked_key', '••••a1b2')
        ->where('credentials.0.active', true)
        ->missing('credentials.0.api_key'));
    expect($response->getContent())->not->toContain('SUPERSECRET');
});

it('stores the first key as the active one, encrypted, with the provider default model', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('ai-credentials.store'), ['provider' => 'anthropic', 'api_key' => ' sk-ant-api03-abcdef-a1b2 ', 'model' => ''])
        ->assertRedirect(route('ai-credentials.index'));

    $credential = AiCredential::query()->sole();
    expect($credential->is_default)->toBeTrue()
        ->and($credential->api_key)->toBe('sk-ant-api03-abcdef-a1b2')
        ->and($credential->key_hint)->toBe('a1b2')
        ->and($credential->model)->toBe(AiProvider::Anthropic->defaultModel())
        ->and($credential->getRawOriginal('api_key'))->not->toContain('sk-ant');
});

it('keeps the active key when another key is added without “Use this key now”', function (): void {
    $user = User::factory()->create();
    $first = AiCredential::factory()->for($user)->asDefault()->create();

    $this->actingAs($user)->post(route('ai-credentials.store'), ['provider' => 'groq', 'api_key' => 'gsk_abcdefghijkl', 'model' => 'llama-3.3-70b-versatile']);

    expect($first->fresh()->is_default)->toBeTrue()
        ->and(AiCredential::query()->where('is_default', true)->count())->toBe(1);
});

it('makes a new key the active one when “Use this key now” is ticked', function (): void {
    $user = User::factory()->create();
    $first = AiCredential::factory()->for($user)->asDefault()->create();

    $this->actingAs($user)->post(route('ai-credentials.store'), ['provider' => 'groq', 'api_key' => 'gsk_abcdefghijkl', 'model' => '', 'activate' => '1']);

    expect($first->fresh()->is_default)->toBeFalse()
        ->and(AiCredential::query()->where('is_default', true)->sole()->provider)->toBe(AiProvider::Groq);
});

it('stores an optional name for the key, trimmed, and none when left blank', function (?string $name, ?string $stored): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('ai-credentials.store'), ['provider' => 'openrouter', 'api_key' => 'sk-or-abcdefgh', 'name' => $name]);

    expect(AiCredential::query()->sole()->name)->toBe($stored);
})->with([
    'named' => ['  Free tier  ', 'Free tier'],
    'blank' => ['   ', null],
    'missing' => [null, null],
]);

it('lists the active key first, with its name', function (): void {
    $user = User::factory()->create();
    AiCredential::factory()->for($user)->asDefault()->create(['name' => 'Work']);
    AiCredential::factory()->for($user)->create();

    $this->actingAs($user)->get(route('ai-credentials.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('credentials.0.name', 'Work')
            ->where('credentials.0.active', true)
            ->where('credentials.1.active', false));
});

it('validates new keys', function (array $input, string $field): void {
    $this->actingAs(User::factory()->create())
        ->post(route('ai-credentials.store'), ['provider' => 'anthropic', 'api_key' => 'sk-ant-abcdefgh', ...$input])
        ->assertSessionHasErrors($field);
})->with([
    'unknown provider' => [['provider' => 'mistral'], 'provider'],
    'key required' => [['api_key' => ''], 'api_key'],
    'key too short' => [['api_key' => 'sk-123'], 'api_key'],
    'key with spaces' => [['api_key' => 'sk ant abcdefgh'], 'api_key'],
    'model with spaces' => [['model' => 'claude sonnet'], 'model'],
    'name too long' => [['name' => str_repeat('n', 61)], 'name'],
]);

it('marks a key verified when the provider accepts it', function (): void {
    $this->freezeSecond();
    Http::fake(['api.anthropic.com/*' => Http::response([
        'id' => 'msg_1', 'type' => 'message', 'role' => 'assistant', 'model' => 'm',
        'content' => [['type' => 'text', 'text' => 'OK']], 'stop_reason' => 'end_turn',
        'usage' => ['input_tokens' => 5, 'output_tokens' => 1],
    ])]);
    $credential = AiCredential::factory()->create();

    $this->actingAs($credential->user)->post(route('ai-credential-verifications.store', $credential))
        ->assertRedirect(route('ai-credentials.index'))
        ->assertInertiaFlash('toast.type', 'success');

    expect($credential->fresh()->last_verified_at?->equalTo(now()))->toBeTrue();
});

it('explains a rejected key and leaves it unverified', function (): void {
    Http::fake(['api.anthropic.com/*' => Http::response(['type' => 'error', 'error' => ['type' => 'authentication_error', 'message' => 'invalid x-api-key']], 401)]);
    $credential = AiCredential::factory()->create();

    $this->actingAs($credential->user)
        ->from(route('ai-credentials.index'))
        ->post(route('ai-credential-verifications.store', $credential))
        ->assertRedirect(route('ai-credentials.index'))
        ->assertInertiaFlash('toast.type', 'error')
        ->assertInertiaFlash('toast.message', 'Anthropic rejected the API key. Check that it is correct and still active, then save it again. Details from Anthropic: “invalid x-api-key”');

    expect($credential->fresh()->last_verified_at)->toBeNull();
});

it('shows a generic message when the provider is down', function (): void {
    Http::fake(['api.anthropic.com/*' => Http::response(['type' => 'error', 'error' => ['type' => 'api_error', 'message' => 'internal: db-7 down']], 500)]);
    $credential = AiCredential::factory()->create();

    $this->actingAs($credential->user)
        ->from(route('ai-credentials.index'))
        ->post(route('ai-credential-verifications.store', $credential))
        ->assertInertiaFlash('toast.message', 'A service we rely on isn’t responding right now. Please try again in a few minutes.');
});

it('switches the active key and goes back to the page it was switched from', function (): void {
    $user = User::factory()->create();
    $old = AiCredential::factory()->for($user)->asDefault()->create();
    $new = AiCredential::factory()->for($user)->create(['provider' => AiProvider::OpenRouter, 'name' => 'Free tier', 'model' => 'openrouter/free']);

    $this->actingAs($user)->from('/applications/app_123')->post(route('active-ai-credentials.store', $new))
        ->assertRedirect('/applications/app_123')
        ->assertInertiaFlash('toast.message', 'Now using Free tier (openrouter/free).');

    expect($old->fresh()->is_default)->toBeFalse()->and($new->fresh()->is_default)->toBeTrue();
});

it('makes the latest remaining key active when the active key is deleted', function (): void {
    $user = User::factory()->create();
    $default = AiCredential::factory()->for($user)->asDefault()->create();
    $older = AiCredential::factory()->for($user)->create();
    $newest = AiCredential::factory()->for($user)->create();

    $this->actingAs($user)->delete(route('ai-credentials.destroy', $default))->assertRedirect(route('ai-credentials.index'));

    expect(AiCredential::query()->find($default->id))->toBeNull()
        ->and($newest->fresh()->is_default)->toBeTrue()
        ->and($older->fresh()->is_default)->toBeFalse();
});

it("answers 404 when acting on another user's key", function (string $method, string $route): void {
    $theirs = AiCredential::factory()->asDefault()->create();

    $this->actingAs(User::factory()->create())->{$method}(route($route, $theirs))->assertNotFound();

    expect($theirs->fresh())->not->toBeNull();
})->with([
    'verify' => ['post', 'ai-credential-verifications.store'],
    'activate' => ['post', 'active-ai-credentials.store'],
    'delete' => ['delete', 'ai-credentials.destroy'],
]);

it('stores an OpenAI key with its suggested model', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('ai-credentials.store'), ['provider' => 'openai', 'api_key' => 'sk-proj-abcdefgh-9z8y', 'model' => ''])
        ->assertRedirect(route('ai-credentials.index'))
        ->assertSessionHasNoErrors();

    expect(AiCredential::query()->sole())
        ->provider->toBe(AiProvider::OpenAI)
        ->model->toBe('gpt-6-astra')
        ->key_hint->toBe('9z8y');
});

it('warns about DeepSeek before a key is added', function (): void {
    $this->actingAs(User::factory()->create())->get(route('ai-credentials.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('providers.5.value', 'deepseek')
            ->where('providers.5.defaultModel', 'deepseek-chat')
            ->where('providers.5.notice', 'DeepSeek processes requests on servers in China, and your CV holds personal data. It can’t read PDFs, so CV import needs another key.')
            ->where('providers.0.notice', null));
});

it('tells the user which OrcaRouter models work before a key is added', function (): void {
    $this->actingAs(User::factory()->create())->get(route('ai-credentials.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('providers.6.value', 'orcarouter')
            ->where('providers.6.defaultModel', 'openai/gpt-5')
            ->where('providers.6.notice', 'Pick a model that supports JSON schema output, such as OpenAI, Gemini or Grok models (not Anthropic ones). It can’t read PDFs, so CV import needs another key.'));
});

it('explains a rate-limited verification instead of reporting an outage', function (): void {
    $user = User::factory()->create();
    $credential = AiCredential::factory()->for($user)->asDefault()->create(['provider' => AiProvider::OpenRouter, 'model' => 'google/gemma-4-31b-it:free']);
    Http::fake(['openrouter.ai/*' => Http::response(['error' => ['message' => 'rate-limited upstream', 'code' => 429]], 429)]);

    $this->actingAs($user)->post(route('ai-credential-verifications.store', $credential))
        ->assertRedirect()
        ->assertInertiaFlash('toast.type', 'error')
        ->assertInertiaFlash('toast.message', 'OpenRouter is limiting requests for “google/gemma-4-31b-it:free” right now. Wait a minute and try again. Free models (ending in “:free”) have low limits and are often busy; a paid model avoids this. Details from OpenRouter: “rate-limited upstream”');

    expect($credential->fresh()->last_verified_at)->toBeNull();
});

it('changes the model of a key and asks to test it again', function (): void {
    $user = User::factory()->create();
    $credential = AiCredential::factory()->for($user)->asDefault()->create(['provider' => AiProvider::OpenRouter, 'model' => 'google/gemma-4-31b-it:free', 'last_verified_at' => now()]);
    $encryptedKey = $credential->getRawOriginal('api_key');

    $this->actingAs($user)->patch(route('ai-credentials.update', $credential), ['model' => ' anthropic/claude-sonnet-5.5 '])
        ->assertRedirect(route('ai-credentials.index'))
        ->assertInertiaFlash('toast.type', 'success');

    $credential->refresh();
    expect($credential->model)->toBe('anthropic/claude-sonnet-5.5')
        ->and($credential->last_verified_at)->toBeNull()
        ->and($credential->getRawOriginal('api_key'))->toBe($encryptedKey)
        ->and($credential->is_default)->toBeTrue();
});

it('keeps the verification when the model is saved unchanged', function (): void {
    $user = User::factory()->create();
    $credential = AiCredential::factory()->for($user)->create(['model' => 'claude-sonnet-5-5', 'last_verified_at' => now()]);

    $this->actingAs($user)->patch(route('ai-credentials.update', $credential), ['model' => 'claude-sonnet-5-5']);

    expect($credential->fresh()->last_verified_at)->not->toBeNull();
});

it('renames a key without touching its model or verification', function (): void {
    $user = User::factory()->create();
    $credential = AiCredential::factory()->for($user)->create(['model' => 'claude-sonnet-5-5', 'last_verified_at' => now()]);

    $this->actingAs($user)->patch(route('ai-credentials.update', $credential), ['name' => ' Personal ', 'model' => 'claude-sonnet-5-5'])
        ->assertInertiaFlash('toast.message', 'Key saved.');

    expect($credential->fresh())
        ->name->toBe('Personal')
        ->model->toBe('claude-sonnet-5-5')
        ->last_verified_at->not->toBeNull();
});

it('validates the new model', function (string $model): void {
    $user = User::factory()->create();
    $credential = AiCredential::factory()->for($user)->create(['model' => 'claude-sonnet-5-5']);

    $this->actingAs($user)->patch(route('ai-credentials.update', $credential), ['model' => $model])->assertSessionHasErrors('model');

    expect($credential->fresh()->model)->toBe('claude-sonnet-5-5');
})->with(['empty' => [''], 'spaces' => ['claude sonnet'], 'too long' => [str_repeat('m', 201)]]);

it("answers 404 when changing another user's key", function (): void {
    $credential = AiCredential::factory()->create(['model' => 'claude-sonnet-5-5']);

    $this->actingAs(User::factory()->create())->patch(route('ai-credentials.update', $credential), ['model' => 'other'])->assertNotFound();

    expect($credential->fresh()->model)->toBe('claude-sonnet-5-5');
});
