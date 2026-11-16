# Reference: `GatewayException`

A base class for **infrastructure** failures of an external API: the vendor is unreachable, timed out, rate-limited us, or answered with a 5xx or a malformed body. Create it only when the project has no equivalent (an existing base for integration errors is the one to extend instead).

Without it, a vendor outage reaches users as a generic 500 ("something went wrong on our side"), which is wrong twice: it isn't our bug, and the useful advice is "try again in a few minutes". With it:

| Failure | Status | Problem `code` | Header |
|---|---|---|---|
| Unreachable or timed out (`ConnectionException`) | 503 | `gateway_unavailable` | — |
| Rate-limited by the vendor (429 after retries) | 503 | `gateway_unavailable` | `Retry-After` when the vendor sent one |
| Vendor says it's down (503/504) | 503 | `gateway_unavailable` | `Retry-After` when the vendor sent one |
| Other 5xx, or a malformed body | 502 | `gateway_error` | — |

- The user sees one safe, generic message (`errors.gateway`). The vendor name, URL and upstream body never reach the response; they go to the log through `context()`.
- Unlike `BusinessRuleException`, these **are reported**: an outage is an incident someone should see.
- "Not found" or "rejected" answers the user can fix are **not** gateway exceptions: throw a business exception for those (see the skill).

How it's shown to users is a presentation concern:
- API: `ProblemDetails` has a branch for it (`building-api-endpoints` skill, `references/error-contract.md`).
- Inertia: a render callback turns it into the `Error` page on GET and `back()` + `error` flash on writes (`building-inertia-pages` skill, `references/error-handling.md`).

## Install
1. Create `app/Gateways/GatewayException.php` below, and make each vendor exception extend it.
2. Add the `GatewayException` branch to the API and/or Inertia error handling (already included in those references), and the `errors.gateway` key to `lang/en/errors.php`.
3. Optional, so an outage doesn't flood the logs: in `bootstrap/app.php` → `withExceptions`:
```php
$exceptions->throttle(static fn (\Throwable $e) => $e instanceof \App\Gateways\GatewayException
    ? \Illuminate\Cache\RateLimiting\Limit::perMinute(10)->by($e::class)
    : null);
```
4. Run `php artisan test --compact --filter=GatewayErrors`.

## Files

```php
// app/Gateways/GatewayException.php
<?php

namespace App\Gateways;

use Illuminate\Http\Client\Response as ClientResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * An external API failed for infrastructure reasons (Infrastructure layer). Not the user's fault, not our bug:
 * rendered as 502/503 with a generic "try again later" message, and reported with the vendor details.
 *
 * Each vendor has its own subclass in app/Gateways/{Vendor}:
 *
 *     class ViaCepException extends GatewayException {}
 *     throw ViaCepException::unreachable($exception);
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

    /** Connection refused, DNS failure or timeout. */
    public static function unreachable(Throwable $previous): static
    {
        return new static(static::vendor().' is unreachable: '.$previous->getMessage(), previous: $previous);
    }

    /** The vendor still answered 429 after our retries. */
    public static function rateLimited(ClientResponse $response): static
    {
        $retryAfter = $response->header('Retry-After');

        return new static(
            static::vendor().' rate-limited the request.',
            retryAfter: is_numeric($retryAfter) ? (int) $retryAfter : null,
            logContext: ['upstream_status' => 429],
        );
    }

    /** A 5xx, or a body that isn't what the contract says. A vendor's own 503/504 stays "unavailable". */
    public static function badResponse(ClientResponse $response): static
    {
        $down = in_array($response->status(), [Response::HTTP_SERVICE_UNAVAILABLE, Response::HTTP_GATEWAY_TIMEOUT], true);
        $retryAfter = $response->header('Retry-After');

        return new static(
            static::vendor()." answered with an unusable response (HTTP {$response->status()}).",
            status: $down ? Response::HTTP_SERVICE_UNAVAILABLE : Response::HTTP_BAD_GATEWAY,
            retryAfter: $down && is_numeric($retryAfter) ? (int) $retryAfter : null,
            logContext: ['upstream_status' => $response->status(), 'upstream_body' => mb_substr($response->body(), 0, 500)],
        );
    }

    public function code(): string
    {
        return $this->status === Response::HTTP_BAD_GATEWAY ? 'gateway_error' : 'gateway_unavailable';
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return ['vendor' => static::vendor(), ...$this->logContext];
    }

    /** "ViaCepException" → "ViaCep". Override for a nicer name in logs. */
    protected static function vendor(): string
    {
        return str(class_basename(static::class))->beforeLast('Exception')->toString();
    }
}
```

```php
// lang/en/errors.php — add next to the other keys
'gateway' => 'A service we rely on isn’t responding right now. Please try again in a few minutes.',
```

```php
// tests/Feature/GatewayErrorsTest.php
<?php

use App\Gateways\GatewayException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

class ExampleVendorException extends GatewayException {}

beforeEach(function (): void {
    Route::middleware('api')->prefix('api')->group(function (): void {
        Route::get('_test/vendor-down', fn () => throw ExampleVendorException::unreachable(new RuntimeException('cURL error 7: internal-vendor.local:8443')));
        Route::get('_test/vendor-broken', fn () => throw ExampleVendorException::badResponse(app(Factory::class)->get('https://vendor.test/x')));
        Route::get('_test/vendor-limited', fn () => throw ExampleVendorException::rateLimited(app(Factory::class)->get('https://vendor.test/x')));
    });
});

it('renders an unreachable vendor as 503 without internals', function (): void {
    config(['app.debug' => false]);

    $response = $this->getJson('/api/_test/vendor-down')
        ->assertStatus(503)
        ->assertJsonPath('code', 'gateway_unavailable')
        ->assertJsonPath('detail', __('errors.gateway'));

    expect($response->getContent())->not->toContain('internal-vendor')->not->toContain('ExampleVendor');
});

it('renders a broken vendor response as 502', function (): void {
    Http::fake(['vendor.test/*' => Http::response('<html>oops</html>', 500)]);

    $this->getJson('/api/_test/vendor-broken')
        ->assertStatus(502)
        ->assertJsonPath('code', 'gateway_error');
});

it('passes the vendor Retry-After on to the client', function (): void {
    Http::fake(['vendor.test/*' => Http::response('', 429, ['Retry-After' => '30'])]);

    $this->getJson('/api/_test/vendor-limited')
        ->assertStatus(503)
        ->assertHeader('Retry-After', '30');
});

it('reports gateway failures with the vendor context', function (): void {
    $exception = ExampleVendorException::unreachable(new RuntimeException('timeout'));

    expect($exception->context())->toMatchArray(['vendor' => 'ExampleVendor']);
});
```
- The API tests need `ProblemDetails` (`building-api-endpoints` skill). For Inertia-only projects, replace them with a web route asserting the `Error` page (`assertInertia(fn ($page) => $page->component('Error')->where('status', 503))`) on GET, and `assertSessionHas('error', __('errors.gateway'))` on a POST.
