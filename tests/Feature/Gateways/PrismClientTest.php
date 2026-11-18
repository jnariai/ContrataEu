<?php

use App\Actions\BuildGenerationPrompt;
use App\Enums\AiProvider;
use App\Exceptions\AiProviderRejectedRequest;
use App\Gateways\GatewayException;
use App\Gateways\Prism\AiProviderException;
use App\Gateways\Prism\PrismClient;
use App\Gateways\Prism\ProviderErrorDetails;
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

it('treats overload and server errors as a provider outage', function (int $status, int $expectedStatus): void {
    Http::fake(['api.anthropic.com/*' => Http::response(['type' => 'error', 'error' => ['type' => 'x', 'message' => 'y']], $status)]);

    expect(fn () => app(PrismClient::class)->ping(AiProvider::Anthropic, 'claude-sonnet-5-5', 'sk-ant'))
        ->toThrow(fn (AiProviderException $e) => expect($e->status)->toBe($expectedStatus));
})->with([
    'overloaded' => [529, 503],
    'server error' => [500, 502],
]);

it('treats a connection failure as a provider outage', function (): void {
    Http::fake(fn () => throw new ConnectionException('Connection timed out'));

    expect(fn () => app(PrismClient::class)->ping(AiProvider::Anthropic, 'claude-sonnet-5-5', 'sk-ant'))
        ->toThrow(fn (AiProviderException $e) => expect($e->status)->toBe(503));
});

it('tells the user the model took too long, instead of reporting an outage, when the answer times out', function (): void {
    Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out after 300000 milliseconds with 440 bytes received'));

    expect(fn () => app(PrismClient::class)->ping(AiProvider::OpenRouter, 'openrouter/free', 'sk-or-key'))
        ->toThrow(fn (AiProviderRejectedRequest $e) => expect($e->errorCode)->toBe('ai_provider.too_slow')
            ->and($e->status)->toBe(504)
            ->and($e->getMessage())->toBe('OpenRouter took too long to answer with “openrouter/free”, so the attempt was stopped. Try again, or change the key’s model in AI keys to a faster one; free and very large models are often slow.'));
});

/** A minimal OpenAI Responses API reply with one text output. */
function openAiReply(string $text = 'OK'): array
{
    return [
        'id' => 'resp_1', 'object' => 'response', 'status' => 'completed', 'model' => 'gpt-6-astra',
        'output' => [['type' => 'message', 'id' => 'msg_1', 'status' => 'completed', 'role' => 'assistant',
            'content' => [['type' => 'output_text', 'text' => $text, 'annotations' => []]]]],
        'usage' => ['input_tokens' => 5, 'output_tokens' => 1, 'total_tokens' => 6],
    ];
}

it('pings OpenAI with the user’s key on the Responses API', function (): void {
    Http::fake(['api.openai.com/*' => Http::response(openAiReply())]);

    app(PrismClient::class)->ping(AiProvider::OpenAI, 'gpt-6-astra', 'sk-openai-user-key');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.openai.com/v1/responses'
        && $request->header('Authorization') === ['Bearer sk-openai-user-key']
        && $request['model'] === 'gpt-6-astra');
});

it('tells the user an OpenAI key was rejected', function (): void {
    Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'Incorrect API key provided', 'type' => 'invalid_request_error', 'code' => 'invalid_api_key']], 401)]);

    expect(fn () => app(PrismClient::class)->ping(AiProvider::OpenAI, 'gpt-6-astra', 'sk-wrong'))
        ->toThrow(fn (AiProviderRejectedRequest $e) => expect($e->errorCode)->toBe('ai_provider.invalid_key')
            ->and($e->getMessage())->toContain('OpenAI')
            ->and($e->getMessage())->not->toContain('sk-wrong'));
});

it('asks OpenAI for the package as a JSON schema and reads it back', function (): void {
    Http::fake(['api.openai.com/*' => Http::response(openAiReply(json_encode(generationAnswer(), JSON_THROW_ON_ERROR)))]);
    $prompt = app(BuildGenerationPrompt::class)->handle(promptContext());

    $output = app(PrismClient::class)->generate(AiProvider::OpenAI, 'gpt-6-astra', 'sk-openai', $prompt);

    expect($output->cvMarkdown)->toBe(trim(generationAnswer()['cv_markdown']));
    Http::assertSent(fn (Request $request): bool => $request['text']['format']['type'] === 'json_schema'
        && $request['text']['format']['name'] === 'application_package');
});

it('sends the CV PDF to OpenAI as a file', function (): void {
    Http::fake(['api.openai.com/*' => Http::response(openAiReply("# Jane Doe\n\n## Summary\nEngineer."))]);

    $markdown = app(PrismClient::class)->readCv(AiProvider::OpenAI, 'gpt-6-astra', 'sk-openai', '%PDF-1.4 jane', 'INSTRUCTIONS');

    expect($markdown)->toBe("# Jane Doe\n\n## Summary\nEngineer.");
    Http::assertSent(function (Request $request): bool {
        $parts = collect($request['input'])->flatMap(fn (array $message) => is_array($message['content'] ?? null) ? $message['content'] : []);

        return $parts->contains(fn (array $part) => ($part['type'] ?? null) === 'input_file'
            && str_contains((string) ($part['file_data'] ?? ''), base64_encode('%PDF-1.4 jane')));
    });
});

/** A minimal DeepSeek (OpenAI chat completions style) reply. */
function deepSeekReply(string $content = 'OK'): array
{
    return [
        'id' => 'chatcmpl-1', 'object' => 'chat.completion', 'created' => 1_794_900_000, 'model' => 'deepseek-chat',
        'choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => $content], 'finish_reason' => 'stop']],
        'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 1, 'total_tokens' => 6],
    ];
}

it('pings DeepSeek with the user’s key', function (): void {
    Http::fake(['api.deepseek.com/*' => Http::response(deepSeekReply())]);

    app(PrismClient::class)->ping(AiProvider::DeepSeek, 'deepseek-chat', 'sk-deepseek-user-key');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.deepseek.com/v1/chat/completions'
        && $request->header('Authorization') === ['Bearer sk-deepseek-user-key']
        && $request['model'] === 'deepseek-chat');
});

it('asks DeepSeek for JSON within its 8K output limit and reads the package back', function (): void {
    Http::fake(['api.deepseek.com/*' => Http::response(deepSeekReply(json_encode(generationAnswer(), JSON_THROW_ON_ERROR)))]);
    $prompt = app(BuildGenerationPrompt::class)->handle(promptContext());

    $output = app(PrismClient::class)->generate(AiProvider::DeepSeek, 'deepseek-chat', 'sk-deepseek', $prompt);

    expect($output->cvMarkdown)->toBe(trim(generationAnswer()['cv_markdown']));
    Http::assertSent(fn (Request $request): bool => $request['response_format'] === ['type' => 'json_object']
        && $request['max_tokens'] === 8192
        && $request['max_completion_tokens'] === 8192);
});

it('sends OrcaRouter calls to its OpenAI-compatible chat completions with the user’s key', function (): void {
    Http::fake(['api.orcarouter.ai/*' => Http::response(deepSeekReply())]);

    app(PrismClient::class)->ping(AiProvider::OrcaRouter, 'openai/gpt-5', 'sk-orca-user-key');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.orcarouter.ai/v1/chat/completions'
        && $request->header('Authorization') === ['Bearer sk-orca-user-key']
        && $request['model'] === 'openai/gpt-5');
});

it('asks OrcaRouter for the package as a JSON schema response and reads it back', function (): void {
    Http::fake(['api.orcarouter.ai/*' => Http::response(deepSeekReply(json_encode(generationAnswer(), JSON_THROW_ON_ERROR)))]);
    $prompt = app(BuildGenerationPrompt::class)->handle(promptContext());

    $output = app(PrismClient::class)->generate(AiProvider::OrcaRouter, 'openai/gpt-5', 'sk-orca', $prompt);

    expect($output->cvMarkdown)->toBe(trim(generationAnswer()['cv_markdown']));
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.orcarouter.ai/v1/chat/completions'
        && $request['response_format']['type'] === 'json_schema'
        && $request['max_tokens'] === 16000);
});

it('keeps the larger output limit for the other providers', function (): void {
    Http::fake(['api.openai.com/*' => Http::response(openAiReply(json_encode(generationAnswer(), JSON_THROW_ON_ERROR)))]);
    $prompt = app(BuildGenerationPrompt::class)->handle(promptContext());

    app(PrismClient::class)->generate(AiProvider::OpenAI, 'gpt-6-astra', 'sk-openai', $prompt);

    Http::assertSent(fn (Request $request): bool => $request['max_output_tokens'] === 16000);
});

it('tells the user the provider is rate-limiting their key or model, instead of reporting an outage', function (): void {
    Http::fake(['openrouter.ai/*' => Http::response(['error' => ['message' => 'Rate limit exceeded: free-models-per-day. Add 10 credits to unlock 1000 free model requests per day', 'code' => 429]], 429)]);

    expect(fn () => app(PrismClient::class)->ping(AiProvider::OpenRouter, 'google/gemma-4-31b-it:free', 'sk-or-key'))
        ->toThrow(fn (AiProviderRejectedRequest $e) => expect($e->errorCode)->toBe('ai_provider.rate_limited')
            ->and($e->status)->toBe(429)
            ->and($e->getMessage())->toBe('OpenRouter is limiting requests for “google/gemma-4-31b-it:free” right now. Wait a minute and try again. Free models (ending in “:free”) have low limits and are often busy; a paid model avoids this. Details from OpenRouter: “Rate limit exceeded: free-models-per-day. Add 10 credits to unlock 1000 free model requests per day”'));
});

it('prefers the upstream provider’s own reason that OpenRouter passes on', function (): void {
    Http::fake(['openrouter.ai/*' => Http::response(['error' => ['message' => 'Provider returned error', 'code' => 429, 'metadata' => ['raw' => 'google/gemma-4-31b-it:free is temporarily rate-limited upstream.', 'provider_name' => 'Google AI Studio']]], 429)]);

    expect(fn () => app(PrismClient::class)->ping(AiProvider::OpenRouter, 'google/gemma-4-31b-it:free', 'sk-or-key'))
        ->toThrow(fn (AiProviderRejectedRequest $e) => expect($e->getMessage())->toEndWith('Details from OpenRouter: “google/gemma-4-31b-it:free is temporarily rate-limited upstream.”'));
});

it('never shows a key the provider echoes back', function (): void {
    Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'Incorrect API key provided: sk-proj-AbC123dEf456****wxyz. You can find your API key at https://platform.openai.com/account/api-keys.', 'type' => 'invalid_request_error', 'code' => 'invalid_api_key']], 401)]);

    expect(fn () => app(PrismClient::class)->ping(AiProvider::OpenAI, 'gpt-6-astra', 'sk-proj-AbC123dEf456ghi789wxyz'))
        ->toThrow(fn (AiProviderRejectedRequest $e) => expect($e->getMessage())
            ->toContain('Details from OpenAI: “Incorrect API key provided: [hidden]')
            ->not->toContain('sk-proj')
            ->not->toContain('AbC123'));
});

it('shows no details when the provider answers with an HTML page', function (): void {
    Http::fake(['openrouter.ai/*' => Http::response('<html><body><h1>429 Too Many Requests</h1></body></html>', 429)]);

    expect(fn () => app(PrismClient::class)->ping(AiProvider::OpenRouter, 'google/gemma-4-31b-it:free', 'sk-or-key'))
        ->toThrow(fn (AiProviderRejectedRequest $e) => expect($e->getMessage())->not->toContain('Details from'));
});

it('keeps provider outages generic, without details', function (): void {
    Http::fake(['api.anthropic.com/*' => Http::response(['type' => 'error', 'error' => ['type' => 'api_error', 'message' => 'Internal server error at node 7']], 500)]);

    expect(fn () => app(PrismClient::class)->ping(AiProvider::Anthropic, 'claude-sonnet-5-5', 'sk-ant'))
        ->toThrow(fn (AiProviderException $e) => expect(GatewayException::userMessage())->not->toContain('node 7'));
});

it('cleans and shortens the provider’s reason', function (string $body, ?string $expected): void {
    expect(ProviderErrorDetails::sanitize($body))->toBe($expected);
})->with([
    'nested error message' => ['{"error":{"message":"model  not\n found"}}', 'model not found'],
    'top-level message' => ['{"message":"Quota exceeded"}', 'Quota exceeded'],
    'string error' => ['{"error":"Invalid model"}', 'Invalid model'],
    'HTML stripped' => ['{"error":{"message":"<b>Bad</b> request"}}', 'Bad request'],
    'Gemini key hidden' => ['{"error":{"message":"API key AIzaSyA1b2C3d4E5f6G7h8 not valid"}}', 'API key [hidden] not valid'],
    'long token hidden' => ['{"error":{"message":"request abcdefghijklmnopqrstuvwxyz0123456789ABCD failed"}}', 'request [hidden] failed'],
    'not JSON' => ['Bad Gateway', null],
    'no message' => ['{"error":{"code":400}}', null],
    'truncated' => ['{"error":{"message":"'.str_repeat('a ', 150).'"}}', rtrim(str_repeat('a ', 100)).'…'],
]);
