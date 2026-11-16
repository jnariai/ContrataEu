# Reference: Inertia error handling

Use this when the project has no equivalent: check `bootstrap/app.php` for `respond(...)`/`render(...)` callbacks, an existing `Error` page, and flash handling in `HandleInertiaRequests::share()` first.

It relies on `BusinessRuleException` (`writing-actions` skill, `references/business-rule-exception.md`) and on `ErrorMessages` + `lang/en/errors.php` below (shared with the API error contract; create them once).

Business errors on Inertia writes become `back()` + `error` flash. Vendor outages (`GatewayException`, `building-api-gateways` skill) render the `Error` page with their 502/503 and a "try again in a few minutes" message on GET, and `back()` + `error` flash on writes. Outside local/testing, 403/404/500/503 render the `Error` page with the trace ID, and 419 goes back with a message.

## Detect an equivalent
- `respond(...)` callbacks in `bootstrap/app.php`, an existing `Error`/`ErrorPage` Inertia page, flash handling in `HandleInertiaRequests::share()`.

## Install
1. Create the files below that the project doesn't have yet.
2. Merge into `bootstrap/app.php` → `withExceptions`, **after** the problem-details callback:
```php
$exceptions->render(static function (\App\Exceptions\BusinessRuleException $e, \Illuminate\Http\Request $request) {
    if ($request->is('api/*')) {
        return null; // the API renders its own error format
    }
    if ($request->expectsJson() && ! $request->header('X-Inertia')) {
        // fetch/axios calls to web routes expect JSON, not a redirect or a page.
        return response()->json(['message' => $e->getMessage(), 'code' => $e->errorCode], $e->status);
    }
    if ($request->isMethod('GET')) {
        return \App\Http\Responses\InertiaErrorPage::make($request, $e->status, $e->getMessage());
    }

    return back()->with('error', $e->getMessage());
});

// Only when the project has gateways (app/Gateways/GatewayException.php).
$exceptions->render(static function (\App\Gateways\GatewayException $e, \Illuminate\Http\Request $request) {
    if ($request->is('api/*')) {
        return null;
    }
    $message = \App\Http\Responses\ErrorMessages::translate('errors.gateway');
    if ($request->expectsJson() && ! $request->header('X-Inertia')) {
        return response()->json(['message' => $message, 'code' => $e->code()], $e->status);
    }
    if ($request->isMethod('GET')) {
        return \App\Http\Responses\InertiaErrorPage::make($request, $e->status, $message);
    }

    return back()->with('error', $message);
});

$exceptions->respond(static function (\Symfony\Component\HttpFoundation\Response $response, \Throwable $e, \Illuminate\Http\Request $request) {
    $jsonCaller = $request->expectsJson() && ! $request->header('X-Inertia');
    if ($request->is('api/*') || $jsonCaller || app()->environment(['local', 'testing'])) {
        return $response;
    }
    if ($e instanceof \App\Gateways\GatewayException) {
        return $response; // already rendered above; don't swap it for the generic 503 "maintenance" page
    }
    $status = $response->getStatusCode();
    if ($status === 419) {
        return back()->with('error', \App\Http\Responses\ErrorMessages::forStatus(419));
    }

    return in_array($status, [403, 404, 500, 503], true)
        ? \App\Http\Responses\InertiaErrorPage::make($request, $status)
        : $response;
});
```
3. Share flash messages in `HandleInertiaRequests::share()`: `'flash' => ['success' => fn () => $request->session()->get('success'), 'error' => fn () => $request->session()->get('error')]`.
4. Create `resources/js/pages/Error.{vue,tsx}` with props `status`, `message` and `traceId`, in the project's frontend stack and styling.
5. Test: a write that throws a business exception redirects back with `error` in the session, and a JSON request to a web route still gets JSON. With gateways: a GET that hits an unreachable vendor renders `Error` with status 503, and a write flashes `errors.gateway`.

## Files

```php
// app/Http/Responses/InertiaErrorPage.php
<?php

namespace App\Http\Responses;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Renders resources/js/pages/Error with a user-safe message and the trace ID support can look up.
 */
class InertiaErrorPage
{
    public static function make(Request $request, int $status, ?string $message = null): Response
    {
        return Inertia::render('Error', [
            'status' => $status,
            'message' => $message ?? ErrorMessages::forStatus($status),
            'traceId' => Context::get('trace_id'),
        ])
            ->toResponse($request)
            ->setStatusCode($status);
    }
}
```

```php
// app/Http/Responses/ErrorMessages.php
<?php

namespace App\Http\Responses;

use Illuminate\Support\Facades\Lang;

/**
 * User-safe, translated texts for errors whose original message must not (or cannot usefully) be shown.
 * Shared by the API (ProblemDetails) and the web (InertiaErrorPage) so both say the same thing.
 */
class ErrorMessages
{
    public static function forStatus(int $status): string
    {
        $key = "errors.http.{$status}";

        if (Lang::has($key)) {
            return self::translate($key);
        }

        return self::translate($status >= 500 ? 'errors.server_error' : 'errors.http.400');
    }

    public static function translate(string $key): string
    {
        $value = __($key);

        return is_string($value) ? $value : $key;
    }
}
```

```php
// lang/en/errors.php
<?php

// Generic, user-safe texts used by App\Support\Http\ProblemDetails.
// Domain-specific messages live in lang/en/{context}.php (e.g. ordering.php).

return [
    'validation_failed' => 'Some of the information you entered is invalid. Please review the highlighted fields.',
    'unauthenticated' => 'Please sign in to continue.',
    'server_error' => 'Something went wrong on our side. Please try again. If it keeps happening, contact support and mention the trace ID.',
    'gateway' => 'A service we rely on isn’t responding right now. Please try again in a few minutes.',

    'http' => [
        400 => 'The request could not be processed. Please check it and try again.',
        403 => 'You don’t have permission to do this.',
        404 => 'We couldn’t find what you were looking for. It may have been moved or deleted.',
        405 => 'This action isn’t supported here.',
        409 => 'This can’t be done right now because the data changed. Please refresh and try again.',
        413 => 'The upload or request is too large.',
        415 => 'This file or content type isn’t supported.',
        419 => 'Your session expired. Please refresh the page and try again.',
        429 => 'Too many attempts. Please wait a moment and try again.',
        503 => 'We’re doing some maintenance. Please try again in a few minutes.',
    ],
];
```
