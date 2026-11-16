---
name: building-api-gateways
description: "Use when integrating a third-party HTTP API or vendor SDK, or creating or changing a client in app/Gateways/{Vendor}. Also when adding outbound HTTP calls, retries, timeouts, or mapping vendor errors to app errors, and when testing code that calls an external service with Http::fake. Not for receiving webhooks (see reviewing-security)."
---

# Building API Gateways

## Rules
1. **Location:** `app/Gateways/{Vendor}/{Vendor}Client.php`, with the vendor's exception next to it (`{Vendor}Exception.php`). Vendor SDK classes and exceptions never leave this folder.
2. **Inject `Illuminate\Http\Client\Factory`** through the constructor, never the `Http` facade inside the client, and read config through the injected `Illuminate\Config\Repository`.
3. **Config** (base URL, keys, timeouts) lives in `config/services.php` under the vendor key; `env()` is read only there.
4. **One public method per remote operation**, named for what it does for the app (`lookup()`, `createCharge()`, `refund()`), never after the HTTP verb. Helpers are `private`.
5. **Value objects or scalars in; value objects or Data objects out**, never raw arrays or `Response` objects: the mapping from the vendor's shape happens here, once.
6. **Every request sets `connectTimeout()` and `timeout()`** and uses `throw: false`, so the client decides what a failure means.
7. **Retry only idempotent requests** (`GET`, or a `POST` protected by an `Idempotency-Key`), and only on connection errors, 5xx and 429. Never retry a 4xx.
8. **Two kinds of failure:**
   - outage, timeout, rate limit, 5xx or a malformed body → an **infrastructure** exception extending `GatewayException` (`ViaCepException::unreachable()`, `::rateLimited()`, `::badResponse()`), reported and rendered as 503 or 502 with a "try again in a few minutes" message, never a generic 500. If the project has no base, use [references/gateway-exception.md](references/gateway-exception.md);
   - "not found" or "rejected" that the user can fix → a **business** exception from `app/Exceptions` (`PostalCodeNotFound::forCode()`).
9. **Callers** inject the client into an Action or job (it counts as an injected service). No HTTP calls inside a database transaction.

## Edge cases (decided)
- **No interface by default.** The injected `Factory` is the same instance the `Http` facade resolves, so `Http::fake()` fakes the client too. Add an interface only when you must swap the vendor.
- **Check responses in order:** 429 → 5xx → 4xx / "not found" → malformed body, because 429 is a 4xx too and a 404 usually has no JSON body.
- **503 vs 502:** unreachable, rate-limited and vendor 503/504 → 503 (rate-limited adds `Retry-After`); other 5xx and malformed bodies → 502.
- **User-facing rendering** of gateway exceptions (API problem+json, the Inertia `Error` page, the flash on writes) is tested once in `tests/Feature/GatewayErrorsTest.php`, not per client.
- The full client with the retry predicate and the `PostalCode` value object: [references/client-example.md](references/client-example.md). All three test paths: [references/tests.md](references/tests.md).

## Example
```php
class ViaCepClient
{
    public function __construct(private readonly Factory $http, private readonly Config $config) {}

    public function lookup(PostalCode $postalCode): PostalAddress
    {
        try {
            $response = $this->request()->get("/ws/{$postalCode->digits}/json/");
        } catch (ConnectionException $exception) {
            throw ViaCepException::unreachable($exception);
        }

        if ($response->status() === 429) {
            throw ViaCepException::rateLimited($response);
        }

        if ($response->serverError()) {
            throw ViaCepException::badResponse($response);
        }

        if ($response->clientError() || filled($response->json('erro'))) {
            throw PostalCodeNotFound::forCode($postalCode);
        }

        if (! is_array($response->json())) {
            throw ViaCepException::badResponse($response);
        }

        return new PostalAddress(
            postalCode: $postalCode->formatted(),
            street: (string) $response->json('logradouro'),
            city: (string) $response->json('localidade'),
            state: (string) $response->json('uf'),
        );
    }

    private function request(): PendingRequest
    {
        return $this->http
            ->baseUrl($this->config->string('services.viacep.url'))
            ->acceptJson()
            ->connectTimeout(3)
            ->timeout($this->config->integer('services.viacep.timeout'))
            ->retry(2, 200, $this->isTransient(...), throw: false);
    }
}
```

## Checklist
1. In `app/Gateways/{Vendor}/`, `Factory` injected, no facade, no interface unless the vendor must be swappable.
2. Config from `config/services.php`, read through the injected repository.
3. `connectTimeout()`, `timeout()` and `throw: false` on every request; retries only for idempotent requests on connection errors, 5xx and 429.
4. Business-named public methods; value objects or Data objects out, never raw arrays.
5. Infrastructure vs business exception per failure, checked in order.
6. `Http::fake()` tests with `Http::preventStrayRequests()`: the success mapping (plus `Http::assertSent` on URL and payload), the business path and the infrastructure path.
7. Verify: `php artisan test --compact --filter=ViaCep`, then Pint as Boost's guideline says, and `vendor/bin/phpstan analyse` if the project uses Larastan.
