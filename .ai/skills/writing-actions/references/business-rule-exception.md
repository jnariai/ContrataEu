# Reference: `BusinessRuleException`

A base class for expected business-rule violations. Create it only when the project has no equivalent: an existing base exception (`DomainException`, `BusinessException`…) is the one to extend instead.

How it's shown to users is a presentation concern:
- API: render it as problem+json (`building-api-endpoints` skill, `references/error-contract.md`).
- Inertia: turn it into `back()` + an `error` flash (`building-inertia-pages` skill, `references/error-handling.md`).

```php
// app/Exceptions/BusinessRuleException.php
<?php

namespace App\Exceptions;

use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * An expected business-rule violation (Domain layer) — not a bug, so it is never reported.
 *
 * - The message is shown to end users as-is: translate it, make it actionable.
 * - `errorCode` is the stable machine contract (e.g. "order.cannot_be_cancelled").
 * - `meta` is rendered to API clients; `logContext` is only ever logged.
 *
 * Subclasses expose one named constructor per reason; the constructor is protected, so `new` only works inside them:
 *
 *     throw OrderCannotBeCancelled::fromStatus($order->status);
 *
 * (Named BusinessRuleException to avoid confusion with PHP's built-in \DomainException.)
 */
abstract class BusinessRuleException extends RuntimeException
{
    /**
     * @param  array<string, scalar|array<array-key, scalar|null>|null>  $meta  Safe to expose to clients.
     * @param  array<string, mixed>  $logContext  Internal diagnostics; never rendered.
     */
    final protected function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $status = Response::HTTP_CONFLICT,
        public readonly array $meta = [],
        public readonly array $logContext = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Used by Laravel's handler if the exception is ever reported explicitly.
     *
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return ['error_code' => $this->errorCode, ...$this->logContext];
    }
}
```
