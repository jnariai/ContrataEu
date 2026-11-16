# Gateway tests

```php
use App\Exceptions\PostalCodeNotFound;
use App\Gateways\ViaCep\ViaCepClient;
use App\Gateways\ViaCep\ViaCepException;
use App\ValueObjects\PostalCode;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('maps a found postal code to an address', function (): void {
    Http::fake([
        'viacep.com.br/ws/01001000/json/' => Http::response([
            'cep' => '01001-000', 'logradouro' => 'Praça da Sé', 'bairro' => 'Sé', 'localidade' => 'São Paulo', 'uf' => 'SP',
        ]),
    ]);

    $address = app(ViaCepClient::class)->lookup(PostalCode::fromString('01001-000'));

    expect($address->city)->toBe('São Paulo')->and($address->state)->toBe('SP');
    Http::assertSent(fn (Request $request) => $request->url() === 'https://viacep.com.br/ws/01001000/json/');
});

it('throws a business exception when the postal code does not exist', function (): void {
    Http::fake(['viacep.com.br/*' => Http::response(['erro' => true])]);

    app(ViaCepClient::class)->lookup(PostalCode::fromString('99999-999'));
})->throws(PostalCodeNotFound::class);

it('throws an infrastructure exception when ViaCEP fails', function (): void {
    Http::fake(['viacep.com.br/*' => Http::response('', 503)]);

    app(ViaCepClient::class)->lookup(PostalCode::fromString('01001-000'));
})->throws(ViaCepException::class);
```
How those exceptions reach users (502/503 problem+json, the Inertia `Error` page, the flash on writes) is tested once in `tests/Feature/GatewayErrorsTest.php` ([references/gateway-exception.md](references/gateway-exception.md)), not per client.

