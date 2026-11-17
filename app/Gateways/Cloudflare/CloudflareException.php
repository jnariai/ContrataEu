<?php

namespace App\Gateways\Cloudflare;

use App\Gateways\GatewayException;

/** Cloudflare Browser Rendering is unreachable, rate-limiting us, or answered with an error or a non-PDF body. */
class CloudflareException extends GatewayException
{
    protected static function vendor(): string
    {
        return 'Cloudflare Browser Rendering';
    }
}
