<?php

namespace App\Exceptions;

use App\Enums\AiProvider;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/** The provider refused the request for a reason the user can fix in Settings → AI keys. */
class AiProviderRejectedRequest extends BusinessRuleException
{
    public static function invalidKey(AiProvider $provider, ?Throwable $previous = null): self
    {
        return new self(
            message: __(':provider rejected the API key. Check that it is correct and still active, then save it again.', ['provider' => $provider->label()]),
            errorCode: 'ai_provider.invalid_key',
            status: Response::HTTP_UNPROCESSABLE_ENTITY,
            logContext: ['provider' => $provider->value],
            previous: $previous,
        );
    }

    public static function invalidRequest(AiProvider $provider, string $model, ?Throwable $previous = null): self
    {
        return new self(
            message: __(':provider rejected the request. Check that the model “:model” exists and that your account can use it.', ['provider' => $provider->label(), 'model' => $model]),
            errorCode: 'ai_provider.invalid_request',
            status: Response::HTTP_UNPROCESSABLE_ENTITY,
            logContext: ['provider' => $provider->value, 'model' => $model],
            previous: $previous,
        );
    }

    public static function outOfCredit(AiProvider $provider, ?Throwable $previous = null): self
    {
        return new self(
            message: __(':provider says your account has no credit left. Add credit with the provider, then try again.', ['provider' => $provider->label()]),
            errorCode: 'ai_provider.out_of_credit',
            status: Response::HTTP_PAYMENT_REQUIRED,
            logContext: ['provider' => $provider->value],
            previous: $previous,
        );
    }
}
