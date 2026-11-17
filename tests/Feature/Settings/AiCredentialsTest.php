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
        ->where('credentials.0.is_default', true)
        ->missing('credentials.0.api_key'));
    expect($response->getContent())->not->toContain('SUPERSECRET');
});

it('stores the first key as the default, encrypted, with the provider default model', function (): void {
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

it('keeps the existing default when another key is added', function (): void {
    $user = User::factory()->create();
    $first = AiCredential::factory()->for($user)->asDefault()->create();

    $this->actingAs($user)->post(route('ai-credentials.store'), ['provider' => 'groq', 'api_key' => 'gsk_abcdefghijkl', 'model' => 'llama-3.3-70b-versatile']);

    expect($first->fresh()->is_default)->toBeTrue()
        ->and(AiCredential::query()->where('is_default', true)->count())->toBe(1);
});

it('validates new keys', function (array $input, string $field): void {
    $this->actingAs(User::factory()->create())
        ->post(route('ai-credentials.store'), ['provider' => 'anthropic', 'api_key' => 'sk-ant-abcdefgh', ...$input])
        ->assertSessionHasErrors($field);
})->with([
    'unknown provider' => [['provider' => 'openai'], 'provider'],
    'key required' => [['api_key' => ''], 'api_key'],
    'key too short' => [['api_key' => 'sk-123'], 'api_key'],
    'key with spaces' => [['api_key' => 'sk ant abcdefgh'], 'api_key'],
    'model with spaces' => [['model' => 'claude sonnet'], 'model'],
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
        ->assertInertiaFlash('toast.message', 'Anthropic rejected the API key. Check that it is correct and still active, then save it again.');

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

it('switches the default key', function (): void {
    $user = User::factory()->create();
    $old = AiCredential::factory()->for($user)->asDefault()->create();
    $new = AiCredential::factory()->for($user)->create();

    $this->actingAs($user)->post(route('default-ai-credentials.store', $new))->assertRedirect(route('ai-credentials.index'));

    expect($old->fresh()->is_default)->toBeFalse()->and($new->fresh()->is_default)->toBeTrue();
});

it('hands the default to the latest remaining key when the default is deleted', function (): void {
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
    'make default' => ['post', 'default-ai-credentials.store'],
    'delete' => ['delete', 'ai-credentials.destroy'],
]);
