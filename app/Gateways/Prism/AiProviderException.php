<?php

namespace App\Gateways\Prism;

use App\Gateways\GatewayException;

/** The user's AI provider is down, overloaded, rate-limiting us, or answered with something unusable. */
class AiProviderException extends GatewayException
{
    protected static function vendor(): string
    {
        return 'AI provider';
    }
}
