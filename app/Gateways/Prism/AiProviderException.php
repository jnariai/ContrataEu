<?php

namespace App\Gateways\Prism;

use App\Gateways\GatewayException;
use Throwable;

/** The user's AI provider is down, overloaded, rate-limiting us, or answered with something unusable. */
class AiProviderException extends GatewayException
{
    /** The model answered, but not with the JSON the output contract asks for (cut off, wrong shape, missing parts). */
    public static function malformedAnswer(Throwable $previous): self
    {
        return self::badResponse(null, $previous);
    }

    protected static function vendor(): string
    {
        return 'AI provider';
    }
}
