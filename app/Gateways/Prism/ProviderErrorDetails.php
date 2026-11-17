<?php

namespace App\Gateways\Prism;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;

/**
 * Keeps the last error reply an AI provider sent (Prism drops it for some failures, e.g. 429) and turns it into a
 * short, safe line for the user: plain text, no key-like tokens, at most MAX_LENGTH characters.
 * Scoped to the request or job; PrismClient resets it before each call.
 */
class ProviderErrorDetails
{
    public const int MAX_LENGTH = 200;

    private ?Response $response = null;

    public function record(Response $response): void
    {
        $this->response = $response;
    }

    public function reset(): void
    {
        $this->response = null;
    }

    /** The provider's own explanation, cleaned up, or null when there is nothing safe and useful to show. */
    public function message(): ?string
    {
        return $this->response === null ? null : self::sanitize((string) $this->response->body());
    }

    public static function sanitize(string $body): ?string
    {
        $data = json_decode($body, true);

        if (! is_array($data)) {
            return null; // HTML error pages, proxies, empty bodies: nothing a user can act on.
        }

        $text = collect([
            data_get($data, 'error.metadata.raw'), // OpenRouter: the upstream provider's own reason
            data_get($data, 'error.message'),
            data_get($data, 'message'),
            data_get($data, 'error'),
            data_get($data, 'errors.0.message'),
        ])->first(fn (mixed $candidate): bool => is_string($candidate) && trim($candidate) !== '');

        if (! is_string($text)) {
            return null;
        }

        $text = strip_tags($text);
        // Keys and long tokens (sk-…, gsk_…, AIza…, bearer values, ids) never reach the screen.
        $text = (string) preg_replace('/\b(?:sk|pk|rk|gsk|xai|AIza)[-_A-Za-z0-9*.]{4,}/', '[hidden]', $text);
        $text = (string) preg_replace('/[A-Za-z0-9_\-*.]{32,}/', '[hidden]', $text);
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        return $text === '' ? null : Str::limit($text, self::MAX_LENGTH, '…');
    }
}
