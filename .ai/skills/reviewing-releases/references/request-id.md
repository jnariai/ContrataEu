# Reference: request / trace ID

Gives every request a trace ID so a user's report, the logs and the error response can be matched up after a deploy.

Adds `App\Http\Middleware\AssignRequestId`. Every request gets a trace ID in `Context` (so every log line carries it), in error bodies (`trace_id`), and in the `X-Request-Id` response header.

## Detect an equivalent
- Middleware that sets `X-Request-Id` / `X-Correlation-Id`, or `Context::add(...)` / `Log::shareContext(...)` with a request ID.

## Install
1. Create the file below.
2. In `bootstrap/app.php` → `withMiddleware`: `$middleware->prepend(\App\Http\Middleware\AssignRequestId::class);`
3. Browser clients: add `X-Request-Id` to `exposed_headers` in `config/cors.php`.
4. If clients can reach the app directly (no trusted proxy setting the header), consider removing the incoming-header reuse.

```php
// app/Http/Middleware/AssignRequestId.php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gives every request a trace ID that appears in logs (via Context), in error bodies
 * (`trace_id`) and in the `X-Request-Id` response header, so support can correlate reports.
 *
 * An incoming X-Request-Id (e.g. from your load balancer) is reused only if it is well-formed,
 * preventing log injection. If clients can reach the app directly and you don't want them
 * choosing IDs, drop the header reuse.
 */
class AssignRequestId
{
    private const HEADER = 'X-Request-Id';

    public function handle(Request $request, Closure $next): Response
    {
        $incoming = (string) $request->headers->get(self::HEADER, '');
        $traceId = preg_match('/\A[A-Za-z0-9._-]{8,128}\z/', $incoming) === 1
            ? $incoming
            : (string) Str::ulid();

        Context::add('trace_id', $traceId);

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set(self::HEADER, $traceId);

        return $response;
    }
}
```
