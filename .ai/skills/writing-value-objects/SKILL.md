---
name: writing-value-objects
description: "Use when creating or changing a value object in app/ValueObjects or its cast in app/Casts: money, percentages, email, postal codes, CPF/CNPJ, date ranges, quantities with units. Also when the same primitive is validated or formatted in more than one place, two primitives always travel together (amount + currency, start + end), or code does arithmetic on money. Not for a fixed set of values (see writing-enums)."
---

# Writing Value Objects

## Rules
1. **Location and shape:** `app/ValueObjects`, `final readonly class`, public readonly properties.
2. **Private constructor.** Creation goes through named constructors that validate: `Money::of()`, `PostalCode::fromString()`, `DateRange::between()`.
3. **Never invalid.** A named constructor rejects bad input with `InvalidArgumentException`.
4. **Immutable:** no setters; every operation returns a new instance (`$a->plus($b)`).
5. **Compare with `equals()`**, never `==` or `===`. Ordering comparisons are methods too (`isGreaterThan()`).
6. **Operations mixing incompatible values** (different currencies or units) throw `InvalidArgumentException`.
7. **Stored on a model through a cast** in `app/Casts` that only maps columns ↔ object and holds no checks, so the named constructor validates whether the value comes from the database or from code. The model lists it in `casts()` and its `@property` docblock.
8. **Sent out through `JsonSerializable`** with a fixed array shape, so Data objects, Inertia props and logs show the same thing.

## Edge cases (decided)
- **Not every string is a value object:** a `title` with no rules stays a `string`.
- **`InvalidArgumentException` vs business exception:** the FormRequest already validated user input, so a bad value reaching a named constructor is a programmer error. Rules about the *business situation* (this product's currency doesn't match this order) belong to the model, which throws a business exception (`writing-models`).
- **Money** is integer minor units plus a `Currency` enum, never a float. Negative amounts are allowed (refunds, balances); a model that must not go below zero checks `isPositive()` itself. Its JSON shape is `{amount, currency, formatted}`. The full `Money`, `Currency` and `MoneyCast` are in [references/money.md](references/money.md).
- **Several money columns in one row** (`subtotal`, `shipping`) share one `currency_id`, which also guarantees they can be added. A table that really mixes currencies per column uses `{key}_currency_id` columns.
- **Date ranges** are inclusive calendar days, normalized to start and end of day ([references/date-range.md](references/date-range.md)). Scopes take the value object as is.

## Example
```php
<?php

namespace App\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

/** A Brazilian postal code (CEP). */
final readonly class PostalCode implements JsonSerializable
{
    private function __construct(public string $digits) {}

    public static function fromString(string $value): self
    {
        $digits = preg_replace('/\D/', '', $value) ?? '';

        if (strlen($digits) !== 8) {
            throw new InvalidArgumentException("Invalid postal code [{$value}].");
        }

        return new self($digits);
    }

    public function formatted(): string
    {
        return substr($this->digits, 0, 5).'-'.substr($this->digits, 5);
    }

    public function equals(self $other): bool
    {
        return $this->digits === $other->digits;
    }

    public function jsonSerialize(): string
    {
        return $this->formatted();
    }
}
```
Unit tests in `tests/Unit/ValueObjects`:
```php
it('normalizes the format', fn () => expect(PostalCode::fromString('01001-000')->digits)->toBe('01001000'));

it('rejects a short code', fn () => PostalCode::fromString('0100'))->throws(InvalidArgumentException::class);
```

## Checklist
1. `final readonly class` in `app/ValueObjects`, private constructor, validating named constructors.
2. Bad input throws `InvalidArgumentException`; business situations are the model's job.
3. Operations return new instances; `equals()` and comparison methods, never `==`.
4. Incompatible values (currency, unit) refused.
5. Cast in `app/Casts` with no checks; listed in `casts()` and `@property`.
6. `JsonSerializable` with a fixed shape.
7. Unit tests for every rule in `tests/Unit/ValueObjects`.
8. Verify: `php artisan test --compact --filter=PostalCode`, then Pint as Boost's guideline says, and `vendor/bin/phpstan analyse` if the project uses Larastan.
