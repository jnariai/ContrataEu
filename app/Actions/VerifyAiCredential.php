<?php

namespace App\Actions;

use App\Gateways\Prism\PrismClient;
use App\Models\AiCredential;

/**
 * "Test connection": one tiny call with the stored key and model. On success the key is marked verified;
 * on failure the gateway's exception tells the user what to fix.
 */
class VerifyAiCredential
{
    public function __construct(private PrismClient $client) {}

    public function handle(AiCredential $credential): void
    {
        $this->client->ping($credential->provider, $credential->model, $credential->api_key, $credential->base_url);

        $credential->markVerified(now()->toImmutable());
    }
}
