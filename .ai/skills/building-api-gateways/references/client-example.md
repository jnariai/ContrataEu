# Full client example: ViaCEP

```php
// config/services.php
'viacep' => [
    'url' => env('VIACEP_URL', 'https://viacep.com.br'),
    'timeout' => (int) env('VIACEP_TIMEOUT', 5),
],
```

## Client example
```php
<?php

namespace App\Gateways\ViaCep;

use App\Datas\PostalAddress;
use App\Exceptions\PostalCodeNotFound;
use App\ValueObjects\PostalCode;
use Illuminate\Config\Repository as Config;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Throwable;

class ViaCepClient
{
    public function __construct(
        private readonly Factory $http,
        private readonly Config $config,
    ) {}

    public function lookup(PostalCode $postalCode): PostalAddress
    {
        try {
            $response = $this->request()->get("/ws/{$postalCode->digits}/json/");
        } catch (ConnectionException $exception) {
            throw ViaCepException::unreachable($exception);
        }

        // Order matters: 429 is a 4xx too, and a 404 usually has no JSON body.
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
            neighborhood: (string) $response->json('bairro'),
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

    /** ViaCEP lookups are GET requests, so retrying them is safe. */
    private function isTransient(Throwable $exception): bool
    {
        return $exception instanceof ConnectionException
            || ($exception instanceof RequestException
                && ($exception->response->serverError() || $exception->response->status() === 429));
    }
}
```

```php
// app/Gateways/ViaCep/ViaCepException.php: unreachable() → 503, rateLimited() → 503 + Retry-After, badResponse() → 502
class ViaCepException extends GatewayException {}
```

The input value object (the FormRequest validates the format first, so a bad value here is a programmer error):
```php
final readonly class PostalCode
{
    private function __construct(public string $digits) {}

    public static function fromString(string $value): self
    {
        $digits = preg_replace('/\D/', '', $value) ?? '';

        if (strlen($digits) !== 8) {
            throw new \InvalidArgumentException("Invalid postal code [{$value}].");
        }

        return new self($digits);
    }

    public function formatted(): string
    {
        return substr($this->digits, 0, 5).'-'.substr($this->digits, 5);
    }
}
```

