<?php

namespace App\Exceptions;

use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * An expected business-rule violation — not a bug, so it is never reported.
 *
 * - The message is shown to the user as-is: translated, saying what happened and what to do next.
 * - `errorCode` is the stable machine contract (e.g. "ai_credential.rejected").
 * - `logContext` is only ever logged, never rendered.
 *
 * Subclasses expose one named constructor per reason; `new` only works inside them.
 */
abstract class BusinessRuleException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $logContext  Internal diagnostics; never rendered.
     */
    final protected function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $status = Response::HTTP_CONFLICT,
        public readonly array $logContext = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return ['error_code' => $this->errorCode, ...$this->logContext];
    }
}
