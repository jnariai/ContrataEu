<?php

namespace App\Exceptions;

use App\Enums\AiProvider;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * The provider refused the request for a reason the user can fix (key, model, credit, limits). The message says what
 * to do in ContrataEu; when the provider explained itself, its (sanitized) reason follows as "Details from …".
 */
class AiProviderRejectedRequest extends BusinessRuleException
{
    public static function invalidKey(AiProvider $provider, ?Throwable $previous = null, ?string $details = null): self
    {
        return self::make(
            __(':provider rejected the API key. Check that it is correct and still active, then save it again.', ['provider' => $provider->label()]),
            'ai_provider.invalid_key', Response::HTTP_UNPROCESSABLE_ENTITY, $provider, $details, $previous,
        );
    }

    public static function invalidRequest(AiProvider $provider, string $model, ?Throwable $previous = null, ?string $details = null): self
    {
        $message = $provider === AiProvider::OpenAICompatible
            ? __('The server rejected the request. Check that the model “:model” exists, that your account can use it, and that it supports JSON schema output (many servers or models don’t; pick another model if so).', ['model' => $model])
            : __(':provider rejected the request. Check that the model “:model” exists and that your account can use it.', ['provider' => $provider->label(), 'model' => $model]);

        return self::make(
            $message,
            'ai_provider.invalid_request', Response::HTTP_UNPROCESSABLE_ENTITY, $provider, $details, $previous, ['model' => $model],
        );
    }

    /** Checked again before every call, because the host's DNS can change after the key was saved. */
    public static function baseUrlNotPublic(AiProvider $provider): self
    {
        return self::make(
            __('The key’s address must be a public HTTPS server. Edit the key in AI keys and enter the address your provider documents.'),
            'ai_provider.base_url_not_public', Response::HTTP_UNPROCESSABLE_ENTITY, $provider, null, null,
        );
    }

    public static function outOfCredit(AiProvider $provider, ?Throwable $previous = null, ?string $details = null): self
    {
        return self::make(
            __(':provider says your account has no credit left. Add credit with the provider, then try again.', ['provider' => $provider->label()]),
            'ai_provider.out_of_credit', Response::HTTP_PAYMENT_REQUIRED, $provider, $details, $previous,
        );
    }

    /**
     * The provider throttles this key or model (HTTP 429): the user's quota or the model's capacity, not an outage
     * of ours. Free OpenRouter models (`:free`) hit this often.
     */
    public static function rateLimited(AiProvider $provider, string $model, ?int $retryAfterSeconds = null, ?Throwable $previous = null, ?string $details = null): self
    {
        return self::make(
            __(':provider is limiting requests for “:model” right now. Wait a minute and try again. Free models (ending in “:free”) have low limits and are often busy; a paid model avoids this.', ['provider' => $provider->label(), 'model' => $model]),
            'ai_provider.rate_limited', Response::HTTP_TOO_MANY_REQUESTS, $provider, $details, $previous, ['model' => $model, 'retry_after' => $retryAfterSeconds],
        );
    }

    /**
     * The model was still answering when the time limit ran out: a slow model (free and very large ones often are),
     * not an outage. Trying again or picking a faster model fixes it.
     */
    public static function tooSlow(AiProvider $provider, string $model, ?Throwable $previous = null): self
    {
        return self::make(
            __(':provider took too long to answer with “:model”, so the attempt was stopped. Try again, or change the key’s model in AI keys to a faster one; free and very large models are often slow.', ['provider' => $provider->label(), 'model' => $model]),
            'ai_provider.too_slow', Response::HTTP_GATEWAY_TIMEOUT, $provider, null, $previous, ['model' => $model],
        );
    }

    /** @param array<string, mixed> $logContext */
    private static function make(string $message, string $errorCode, int $status, AiProvider $provider, ?string $details, ?Throwable $previous, array $logContext = []): self
    {
        if ($details !== null) {
            $message .= ' '.__('Details from :provider: “:details”', ['provider' => $provider->label(), 'details' => $details]);
        }

        return new self(
            message: $message,
            errorCode: $errorCode,
            status: $status,
            logContext: ['provider' => $provider->value, 'provider_details' => $details, ...$logContext],
            previous: $previous,
        );
    }
}
