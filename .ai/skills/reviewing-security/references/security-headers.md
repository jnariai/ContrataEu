# Reference: security headers

Adds `App\Http\Middleware\SecurityHeaders`: `nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy`, `Permissions-Policy`, COOP, and HSTS on HTTPS in production.

## Detect an equivalent
- Headers set in nginx/Apache/Caddy config, Cloudflare, `spatie/laravel-csp`, or existing middleware. Ask before duplicating them; conflicting values are worse than none.
- Apps embedded in iframes: `X-Frame-Options: DENY` would break them. Adjust to `SAMEORIGIN` or a CSP `frame-ancestors` rule.

## Install
1. Create the file below.
2. In `bootstrap/app.php` → `withMiddleware`: `$middleware->append(\App\Http\Middleware\SecurityHeaders::class);`
3. For web UIs, recommend a Content-Security-Policy (e.g. spatie/laravel-csp) as a separate step.

```php
// app/Http/Middleware/SecurityHeaders.php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline security headers for every response.
 * Add a Content-Security-Policy tailored to your frontend (e.g. via spatie/laravel-csp) for web UIs.
 * Also set `expose_php = Off` in php.ini so PHP doesn't send X-Powered-By.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);
        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        if ($request->isSecure() && app()->isProduction()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
```
