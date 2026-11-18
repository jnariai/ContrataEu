<?php

namespace App\Gateways\Prism;

use App\Datas\BuiltPrompt;
use App\Datas\GenerationOutput;
use App\Enums\AiProvider;
use App\Exceptions\AiProviderRejectedRequest;
use GuzzleHttp\Exception\ResponseTimeoutException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use InvalidArgumentException;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Exceptions\PrismException;
use Prism\Prism\Exceptions\PrismProviderOverloadedException;
use Prism\Prism\Exceptions\PrismRateLimitedException;
use Prism\Prism\Facades\Prism;
use Prism\Prism\ValueObjects\Media\Document;
use Prism\Prism\ValueObjects\Messages\UserMessage;
use Throwable;

/**
 * The only place that talks to Prism. Calls run with the user's own key (BYOK), passed per request so it never
 * lands in config, and every Prism or HTTP failure is translated into our own exceptions here.
 */
class PrismClient
{
    public function __construct(private ProviderErrorDetails $errorDetails) {}

    private const int CONNECT_TIMEOUT_SECONDS = 5;

    private const int PING_TIMEOUT_SECONDS = 20;

    /**
     * Below the jobs' 330 s timeout, so a slow model fails the run with a clear message instead of killing the worker.
     * Five minutes, because free and very large models (often through OpenRouter) can take that long for a full package.
     */
    private const int GENERATION_TIMEOUT_SECONDS = 300;

    private const int GENERATION_MAX_TOKENS = 16_000;

    private const int CV_IMPORT_MAX_TOKENS = 8_000;

    private const int DEEPSEEK_MAX_TOKENS = 8_192;

    private const string ORCAROUTER_URL = 'https://api.orcarouter.ai/v1';

    /**
     * Makes the smallest possible call, to prove the key and model work.
     *
     * @throws AiProviderRejectedRequest when the key or model is wrong (the user can fix it)
     * @throws AiProviderException when the provider is unavailable
     */
    public function ping(AiProvider $provider, string $model, string $apiKey): void
    {
        $this->call($provider, $model, fn () => Prism::text()
            ->using(self::prismProvider($provider), $model, self::providerConfig($provider, $apiKey))
            ->withClientOptions(['timeout' => self::PING_TIMEOUT_SECONDS, 'connect_timeout' => self::CONNECT_TIMEOUT_SECONDS])
            ->withMaxTokens(5)
            ->withPrompt('Reply with the single word: OK')
            ->asText());
    }

    /**
     * One structured call that returns the whole package: report, CV and the requested extras.
     *
     * @throws AiProviderRejectedRequest when the key or model is wrong (the user can fix it)
     * @throws AiProviderException when the provider is unavailable or the answer doesn't fit the contract
     */
    public function generate(AiProvider $provider, string $model, string $apiKey, BuiltPrompt $prompt): GenerationOutput
    {
        $response = $this->call($provider, $model, fn () => Prism::structured()
            ->using(self::prismProvider($provider), $model, self::providerConfig($provider, $apiKey))
            ->withClientOptions(['timeout' => self::GENERATION_TIMEOUT_SECONDS, 'connect_timeout' => self::CONNECT_TIMEOUT_SECONDS])
            ->withMaxTokens(self::generationMaxTokens($provider))
            ->withProviderOptions(self::generationProviderOptions($provider))
            ->withSystemPrompt($prompt->system)
            ->withPrompt($prompt->user)
            ->withSchema(GenerationSchema::for($prompt))
            ->asStructured());

        try {
            return GenerationSchema::read(
                $response->structured ?? [],
                $prompt,
                $response->usage->promptTokens,
                $response->usage->completionTokens,
            );
        } catch (InvalidArgumentException $exception) {
            throw AiProviderException::malformedAnswer($exception);
        }
    }

    /**
     * Sends a CV PDF as a document and returns the model's plain-text answer (the master CV in Markdown).
     *
     * @throws AiProviderRejectedRequest when the key or model is wrong, or the model can't read PDFs
     * @throws AiProviderException when the provider is unavailable
     */
    public function readCv(AiProvider $provider, string $model, string $apiKey, string $pdf, string $instructions): string
    {
        $response = $this->call($provider, $model, fn () => Prism::text()
            ->using(self::prismProvider($provider), $model, self::providerConfig($provider, $apiKey))
            ->withClientOptions(['timeout' => self::GENERATION_TIMEOUT_SECONDS, 'connect_timeout' => self::CONNECT_TIMEOUT_SECONDS])
            ->withMaxTokens(self::CV_IMPORT_MAX_TOKENS)
            ->withSystemPrompt($instructions)
            ->withMessages([new UserMessage('Convert the attached CV.', [Document::fromRawContent($pdf, 'application/pdf', 'cv.pdf')])])
            ->asText());

        return $response->text;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $request
     * @return T
     */
    private function call(AiProvider $provider, string $model, callable $request): mixed
    {
        $this->errorDetails->reset();

        try {
            return $request();
        } catch (PrismRateLimitedException $exception) {
            throw AiProviderRejectedRequest::rateLimited($provider, $model, $exception->retryAfter, $exception, $this->errorDetails->message());
        } catch (PrismProviderOverloadedException $exception) {
            throw AiProviderException::badResponse(529, $exception);
        } catch (ConnectionException $exception) {
            throw self::timedOut($exception)
                ? AiProviderRejectedRequest::tooSlow($provider, $model, $exception)
                : AiProviderException::unreachable($exception);
        } catch (PrismException|RequestException $exception) {
            if (self::timedOut($exception)) {
                throw AiProviderRejectedRequest::tooSlow($provider, $model, $exception);
            }

            if (self::connectionFailure($exception)) {
                throw AiProviderException::unreachable($exception);
            }

            throw match (true) {
                in_array(self::upstreamStatus($exception), [401, 403], true) => AiProviderRejectedRequest::invalidKey($provider, $exception, $this->errorDetails->message()),
                self::upstreamStatus($exception) === 402 => AiProviderRejectedRequest::outOfCredit($provider, $exception, $this->errorDetails->message()),
                self::upstreamStatus($exception) === 429 => AiProviderRejectedRequest::rateLimited($provider, $model, previous: $exception, details: $this->errorDetails->message()),
                in_array(self::upstreamStatus($exception), [400, 404, 422], true) => AiProviderRejectedRequest::invalidRequest($provider, $model, $exception, $this->errorDetails->message()),
                default => AiProviderException::badResponse(self::upstreamStatus($exception), $exception),
            };
        }
    }

    /** DeepSeek's chat model rejects more than 8K output tokens; a typical package needs 3–6K. */
    private static function generationMaxTokens(AiProvider $provider): int
    {
        return $provider === AiProvider::DeepSeek ? self::DEEPSEEK_MAX_TOKENS : self::GENERATION_MAX_TOKENS;
    }

    /**
     * Prism's DeepSeek structured call sends `max_completion_tokens`, while DeepSeek documents `max_tokens`: send
     * both, so the limit holds whichever one the API reads.
     *
     * @return array<string, mixed>
     */
    private static function generationProviderOptions(AiProvider $provider): array
    {
        return $provider === AiProvider::DeepSeek ? ['max_tokens' => self::DEEPSEEK_MAX_TOKENS] : [];
    }

    private static function prismProvider(AiProvider $provider): Provider
    {
        return match ($provider) {
            AiProvider::Anthropic => Provider::Anthropic,
            AiProvider::OpenAI => Provider::OpenAI,
            AiProvider::Gemini => Provider::Gemini,
            AiProvider::OpenRouter => Provider::OpenRouter,
            AiProvider::Groq => Provider::Groq,
            AiProvider::DeepSeek => Provider::DeepSeek,
            AiProvider::OrcaRouter => Provider::OpenRouter,
        };
    }

    /**
     * The user's key, plus the address for providers reached through another one's driver. OrcaRouter speaks OpenAI
     * chat completions with `response_format` JSON schemas, which is what Prism's OpenRouter driver sends (its OpenAI
     * driver uses the Responses API, where OrcaRouter doesn't document structured output).
     *
     * @return array<string, string>
     */
    private static function providerConfig(AiProvider $provider, string $apiKey): array
    {
        return match ($provider) {
            AiProvider::OrcaRouter => ['api_key' => $apiKey, 'url' => self::ORCAROUTER_URL],
            default => ['api_key' => $apiKey],
        };
    }

    /** Prism reports the upstream HTTP status in different places depending on the provider and failure. */
    private static function upstreamStatus(Throwable $exception): ?int
    {
        for ($current = $exception; $current !== null; $current = $current->getPrevious()) {
            if ($current instanceof RequestException) {
                return $current->response->status();
            }

            if ($current instanceof PrismException && $current->httpStatus !== null) {
                return $current->httpStatus;
            }

            if ($current->getCode() >= 400 && $current->getCode() < 600) {
                return $current->getCode();
            }
        }

        return null;
    }

    /**
     * The provider accepted the request but was still answering when our timeout ran out (cURL error 28 after the
     * connection was made). A connect timeout is not this: that one means the provider is unreachable.
     */
    private static function timedOut(Throwable $exception): bool
    {
        for ($current = $exception; $current !== null; $current = $current->getPrevious()) {
            if ($current instanceof ResponseTimeoutException || str_contains($current->getMessage(), 'Operation timed out after')) {
                return true;
            }
        }

        return false;
    }

    private static function connectionFailure(Throwable $exception): bool
    {
        for ($current = $exception; $current !== null; $current = $current->getPrevious()) {
            if ($current instanceof ConnectionException) {
                return true;
            }
        }

        return false;
    }
}
