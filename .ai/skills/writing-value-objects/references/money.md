# Money, Currency and MoneyCast

Integer minor units plus a `Currency` enum, never a float. Negative amounts are allowed (refunds, balances); a model that must not go below zero checks `isPositive()` itself.
```php
<?php

namespace App\Enums;

/** ISO 4217 codes keep their official form. */
enum Currency: string
{
    case BRL = 'BRL';
    case EUR = 'EUR';
    case USD = 'USD';

    /** Digits after the decimal point. Add a case like JPY (0) or BHD (3) and this match makes you decide. */
    public function decimals(): int
    {
        return match ($this) {
            self::BRL, self::EUR, self::USD => 2,
        };
    }
}
```

```php
<?php

namespace App\ValueObjects;

use App\Enums\Currency;
use Illuminate\Support\Number;
use InvalidArgumentException;
use JsonSerializable;

final readonly class Money implements JsonSerializable
{
    private function __construct(
        public int $minor,           // smallest unit: cents for BRL/EUR/USD
        public Currency $currency,
    ) {}

    public static function of(int $minor, Currency $currency): self
    {
        return new self($minor, $currency);
    }

    public static function zero(Currency $currency): self
    {
        return new self(0, $currency);
    }

    /** @param non-empty-list<self> $amounts */
    public static function sum(array $amounts): self
    {
        if ($amounts === []) {
            throw new InvalidArgumentException('Cannot sum no amounts: the currency is unknown.');
        }

        return array_reduce(array_slice($amounts, 1), static fn (self $total, self $amount): self => $total->plus($amount), $amounts[0]);
    }

    public function plus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor + $other->minor, $this->currency);
    }

    public function minus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor - $other->minor, $this->currency);
    }

    public function times(int $quantity): self
    {
        return new self($this->minor * $quantity, $this->currency);
    }

    public function isPositive(): bool
    {
        return $this->minor > 0;
    }

    public function isGreaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->minor > $other->minor;
    }

    public function isLessThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->minor < $other->minor;
    }

    public function equals(self $other): bool
    {
        return $this->minor === $other->minor && $this->currency === $other->currency;
    }

    /** "€12.50" in the app's locale. Needs ext-intl. */
    public function formatted(): string
    {
        return (string) Number::currency($this->minor / 10 ** $this->currency->decimals(), in: $this->currency->value);
    }

    /** @return array{amount: int, currency: string, formatted: string} */
    public function jsonSerialize(): array
    {
        return ['amount' => $this->minor, 'currency' => $this->currency->value, 'formatted' => $this->formatted()];
    }

    private function assertSameCurrency(self $other): void
    {
        if ($other->currency !== $this->currency) {
            throw new InvalidArgumentException("Cannot combine {$this->currency->value} with {$other->currency->value}.");
        }
    }
}
```

### The cast
Maps `{key}_minor` plus the row's `currency_id`, an integer foreign key to the `currencies` lookup table like any enum column (`writing-enums` skill). Several money columns in one row (`subtotal`, `shipping`) share that one currency, which also guarantees they can be added. A table that really mixes currencies per column uses `{key}_currency_id` columns instead.
```php
<?php

namespace App\Casts;

use App\Enums\Currency;
use App\ValueObjects\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/** @implements CastsAttributes<Money|null, Money|null> */
class MoneyCast implements CastsAttributes
{
    /** @param array<string, mixed> $attributes */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        $minor = $attributes["{$key}_minor"] ?? null;

        return $minor === null ? null : Money::of((int) $minor, LookupCast::caseOf(Currency::class, 'currencies', (int) $attributes['currency_id']));
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, int|string|null>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return ["{$key}_minor" => null];
        }

        if (! $value instanceof Money) {
            throw new InvalidArgumentException("[{$key}] must be set with a Money value.");
        }

        return ["{$key}_minor" => $value->minor, 'currency_id' => LookupCast::idOf('currencies', $value->currency)];
    }
}
```
On the model: `'total' => MoneyCast::class` in `casts()` and `@property Money $total`. In an API Resource, `'total' => $this->total` renders `{amount, currency, formatted}` through `jsonSerialize()`.


## Unit tests
```php
// tests/Unit/ValueObjects/MoneyTest.php
use App\Enums\Currency;
use App\ValueObjects\Money;

it('adds amounts in the same currency', function (): void {
    expect(Money::of(15_00, Currency::EUR)->plus(Money::of(2_50, Currency::EUR))->equals(Money::of(17_50, Currency::EUR)))->toBeTrue();
});

it('refuses to mix currencies', function (): void {
    Money::of(1_00, Currency::EUR)->plus(Money::of(1_00, Currency::BRL));
})->throws(InvalidArgumentException::class);

it('serializes to the API money shape', function (): void {
    expect(Money::of(12_50, Currency::EUR)->jsonSerialize())->toMatchArray(['amount' => 1250, 'currency' => 'EUR']);
});
```

