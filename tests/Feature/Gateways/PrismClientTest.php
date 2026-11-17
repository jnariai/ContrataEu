<?php

use App\Enums\AiProvider;
use App\Exceptions\AiProviderRejectedRequest;
use App\Gateways\Prism\AiProviderException;
use App\Gateways\Prism\PrismClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function anthropicReply(): array
{
    return [
        'id' => 'msg_1', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-sonnet-5-5',
        'content' => [['type' => 'text', 'text' => 'OK']],
        'stop_reason' => 'end_turn', 'usage' => ['input_tokens' => 5, 'output_tokens' => 1],
    ];
}

it("pings the provider with the user's own key and model", function (): void {
    Http::fake(['api.anthropic.com/*' => Http::response(anthropicReply())]);

    app(PrismClient::class)->ping(AiProvider::Anthropic, 'claude-sonnet-5-5', 'sk-ant-user-key');

    Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://api.anthropic.com/v1/messages')
        && $request->header('x-api-key') === ['sk-ant-user-key']
        && $request['model'] === 'claude-sonnet-5-5'
        && $request['max_tokens'] === 5);
});

it('tells the user the key was rejected on 401 and 403', function (int $status): void {
    Http::fake(['api.anthropic.com/*' => Http::response(['type' => 'error', 'error' => ['type' => 'authentication_error', 'message' => 'invalid x-api-key']], $status)]);

    expect(fn () => app(PrismClient::class)->ping(AiProvider::Anthropic, 'claude-sonnet-5-5', 'sk-wrong'))
        ->toThrow(fn (AiProviderRejectedRequest $e) => expect($e->errorCode)->toBe('ai_provider.invalid_key')
            ->and($e->getMessage())->not->toContain('sk-wrong'));
})->with([401, 403]);

it('tells the user the model or request was rejected on 404', function (): void {
    Http::fake(['api.anthropic.com/*' => Http::response(['type' => 'error', 'error' => ['type' => 'not_found_error', 'message' => 'model: nope']], 404)]);

    expect(fn () => app(PrismClient::class)->ping(AiProvider::Anthropic, 'nope', 'sk-ant'))
        ->toThrow(fn (AiProviderRejectedRequest $e) => expect($e->errorCode)->toBe('ai_provider.invalid_request'));
});

it('treats rate limits, overload and server errors as a provider outage', function (int $status, int $expectedStatus): void {
    Http::fake(['api.anthropic.com/*' => Http::response(['type' => 'error', 'error' => ['type' => 'x', 'message' => 'y']], $status)]);

    expect(fn () => app(PrismClient::class)->ping(AiProvider::Anthropic, 'claude-sonnet-5-5', 'sk-ant'))
        ->toThrow(fn (AiProviderException $e) => expect($e->status)->toBe($expectedStatus));
})->with([
    'rate limited' => [429, 503],
    'overloaded' => [529, 503],
    'server error' => [500, 502],
]);

it('treats a connection failure as a provider outage', function (): void {
    Http::fake(fn () => throw new ConnectionException('Connection timed out'));

    expect(fn () => app(PrismClient::class)->ping(AiProvider::Anthropic, 'claude-sonnet-5-5', 'sk-ant'))
        ->toThrow(fn (AiProviderException $e) => expect($e->status)->toBe(503));
});
