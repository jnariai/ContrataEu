<?php

namespace App\Gateways;

use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * An external service failed for infrastructure reasons: unreachable, timed out, rate-limited, 5xx or a malformed
 * answer. Not the user's fault and not our bug: users see a generic "try again in a few minutes", the details
 * (vendor, upstream status) go to the log. Reported, because an outage is an incident someone should see.
 */
abstract class GatewayException extends RuntimeException
{
    /**
     * @param  string  $message  Internal only (logs); never rendered to users.
     * @param  array<string, mixed>  $logContext  Internal diagnostics; never rendered.
     */
    final protected function __construct(
        string $message,
        public readonly int $status = Response::HTTP_SERVICE_UNAVAILABLE,
        public readonly ?int $retryAfter = null,
        public readonly array $logContext = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    abstract protected static function vendor(): string;

    public static function unreachable(Throwable $previous): static
    {
        return new static(static::vendor().' is unreachable: '.$previous->getMessage(), previous: $previous);
    }

    public static function rateLimited(?int $retryAfter = null, ?Throwable $previous = null): static
    {
        return new static(static::vendor().' rate-limited the request.', retryAfter: $retryAfter, logContext: ['upstream_status' => 429], previous: $previous);
    }

    public static function badResponse(?int $upstreamStatus, ?Throwable $previous = null): static
    {
        $unavailable = in_array($upstreamStatus, [503, 504, 529], true);

        return new static(
            static::vendor().' answered with an error'.($upstreamStatus !== null ? " ({$upstreamStatus})" : '').'.',
            status: $unavailable ? Response::HTTP_SERVICE_UNAVAILABLE : Response::HTTP_BAD_GATEWAY,
            logContext: ['upstream_status' => $upstreamStatus],
            previous: $previous,
        );
    }

    /** The user-facing message: generic on purpose, nothing from the vendor leaks. */
    public static function userMessage(): string
    {
        return __('A service we rely on isn’t responding right now. Please try again in a few minutes.');
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return ['vendor' => static::vendor(), 'status' => $this->status, ...$this->logContext];
    }
}
