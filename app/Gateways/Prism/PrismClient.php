<?php

namespace App\Gateways\Prism;

use App\Enums\AiProvider;
use App\Exceptions\AiProviderRejectedRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Exceptions\PrismException;
use Prism\Prism\Exceptions\PrismProviderOverloadedException;
use Prism\Prism\Exceptions\PrismRateLimitedException;
use Prism\Prism\Facades\Prism;
use Throwable;

/**
 * The only place that talks to Prism. Calls run with the user's own key (BYOK), passed per request so it never
 * lands in config, and every Prism or HTTP failure is translated into our own exceptions here.
 */
class PrismClient
{
    private const int CONNECT_TIMEOUT_SECONDS = 5;

    private const int PING_TIMEOUT_SECONDS = 20;

    /**
     * Makes the smallest possible call, to prove the key and model work.
     *
     * @throws AiProviderRejectedRequest when the key or model is wrong (the user can fix it)
     * @throws AiProviderException when the provider is unavailable
     */
    public function ping(AiProvider $provider, string $model, string $apiKey): void
    {
        $this->call($provider, $model, fn () => Prism::text()
            ->using(self::prismProvider($provider), $model, ['api_key' => $apiKey])
            ->withClientOptions(['timeout' => self::PING_TIMEOUT_SECONDS, 'connect_timeout' => self::CONNECT_TIMEOUT_SECONDS])
            ->withMaxTokens(5)
            ->withPrompt('Reply with the single word: OK')
            ->asText());
    }

    /**
     * @template T
     *
     * @param  callable(): T  $request
     * @return T
     */
    private function call(AiProvider $provider, string $model, callable $request): mixed
    {
        try {
            return $request();
        } catch (PrismRateLimitedException $exception) {
            throw AiProviderException::rateLimited($exception->retryAfter, $exception);
        } catch (PrismProviderOverloadedException $exception) {
            throw AiProviderException::badResponse(529, $exception);
        } catch (ConnectionException $exception) {
            throw AiProviderException::unreachable($exception);
        } catch (PrismException|RequestException $exception) {
            if (self::connectionFailure($exception)) {
                throw AiProviderException::unreachable($exception);
            }

            throw match (true) {
                in_array(self::upstreamStatus($exception), [401, 403], true) => AiProviderRejectedRequest::invalidKey($provider, $exception),
                self::upstreamStatus($exception) === 402 => AiProviderRejectedRequest::outOfCredit($provider, $exception),
                in_array(self::upstreamStatus($exception), [400, 404, 422], true) => AiProviderRejectedRequest::invalidRequest($provider, $model, $exception),
                default => AiProviderException::badResponse(self::upstreamStatus($exception), $exception),
            };
        }
    }

    private static function prismProvider(AiProvider $provider): Provider
    {
        return match ($provider) {
            AiProvider::Anthropic => Provider::Anthropic,
            AiProvider::Gemini => Provider::Gemini,
            AiProvider::OpenRouter => Provider::OpenRouter,
            AiProvider::Groq => Provider::Groq,
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
