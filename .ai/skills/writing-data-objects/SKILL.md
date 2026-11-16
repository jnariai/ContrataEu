---
name: writing-data-objects
description: "Use when creating, changing or reviewing a Data object (DTO) in app/Datas, a FormRequest toData() method, or the result type of an Action or query Action. Also when an Action takes more than about three parameters, nested input or has several callers, or returns more than a single model. Not for API Resources (see building-api-endpoints) or value objects (see writing-value-objects)."
---

# Writing Data Objects

## Rules
1. **Location and shape:** `app/Datas`, named after what it holds with a noun that doesn't clash with a model or Action (`NewOrder`, `OrderSummary`; never `PlaceOrder` or `OrderLine`), no `Data` suffix, declared `final readonly class` with promoted public properties.
2. **Plain PHP:** no base class, no package, no framework dependencies, no behavior beyond named constructors.
3. **Property types:** scalars, `CarbonImmutable`, value objects, backed enums, other Data objects, or `list<NewOrderLine>` documented in PHPDoc. Never a model, a `Request`, an Eloquent collection or a loose array of mixed values.
4. **Input Data holds converted values.** It's built only by a FormRequest's `toData()` (or by a command or job from its own input), from validated input: enums instead of strings, value objects instead of raw numbers, `CarbonImmutable` instead of date strings.
5. **Input Data is for Actions only.** Model behavior methods never receive one; the Action unpacks it into model calls.
6. **Output Data has a named constructor** that maps its source (`fromModel(Order $order)`, `fromRow(object $row)`), exposes `public_id` as `id`, never the auto-increment `id`, and formats instants with `->toIso8601ZuluString()` and calendar dates with `->toDateString()`.

## Edge cases (decided)
- **When to use input Data:** more than about three values, nested data, or several callers. Fewer values are typed parameters.
- **When to use output Data:** an Action or query Action returns something that isn't a single model (a report, summary, computed view, or a list of those). JSON output is still an API Resource and Inertia props are still a Resource or explicit array; Data objects don't replace them.
- **The project already uses spatie/laravel-data:** follow it instead.
- **Typed getters in `toData()`:** `string()`, `integer()`, `boolean()`, `date()`. For a required enum, use `Enum::from($this->string(...)->toString())`, because `enum()` returns a nullable value.
- **Computed business values** (totals, prices, statuses) aren't calculated in a Data object: they belong to the Action or model.
- **The caller eager-loads** every relation `fromModel()` reads; with strict mode on, a missing one throws instead of silently running N+1 queries.
- **Lists:** `->map(OrderSummary::fromModel(...))->all()`; paginators: `->paginate(20)->through(OrderSummary::fromModel(...))`, which keeps Laravel's paginator shape. The output example is in [references/output-data.md](references/output-data.md).
- **Subfolders** only once `app/Datas` passes ~15 files.

## Example
```php
<?php

namespace App\Datas;

use App\Enums\PaymentMethod;
use Carbon\CarbonImmutable;

final readonly class NewOrder
{
    /**
     * @param  list<NewOrderLine>  $lines
     */
    public function __construct(
        public string $addressId,
        public PaymentMethod $paymentMethod,
        public array $lines,
        public ?CarbonImmutable $deliverOn = null,
        public ?string $note = null,
    ) {}
}

final readonly class NewOrderLine
{
    public function __construct(public string $productId, public int $quantity) {}
}
```
`addressId` holds the address's `public_id`. Built by the FormRequest from validated input:
```php
public function toData(): NewOrder
{
    return new NewOrder(
        addressId: $this->string('address_id')->toString(),
        paymentMethod: PaymentMethod::from($this->string('payment_method')->toString()),
        lines: array_map(
            static fn (array $line): NewOrderLine => new NewOrderLine((string) $line['product_id'], (int) $line['quantity']),
            array_values($this->validated('lines')),
        ),
        deliverOn: $this->date('deliver_on')?->toImmutable(),
        note: $this->filled('note') ? $this->string('note')->trim()->toString() : null,
    );
}
```

## Checklist
1. `final readonly class` in `app/Datas`, a non-clashing noun with no `Data` suffix, promoted public properties, no base class or package.
2. Property types limited to scalars, `CarbonImmutable`, value objects, enums, Data objects, `list<…>` of Data objects.
3. Input Data built only by `toData()` (or a command or job) with converted values; only Actions receive it.
4. Output Data has `fromModel()` / `fromRow()`, `public_id` as `id`, ISO dates (Zulu instants, `YYYY-MM-DD` calendar dates); relations eager-loaded first.
5. Tests: `toData()` converts every field (enum, value object, date) and leaves optional fields `null`; `fromModel()` never exposes the auto-increment `id`.
6. Verify: `php artisan test --compact --filter=PlaceOrder`, then Pint as Boost's guideline says, and `vendor/bin/phpstan analyse` if the project uses Larastan.
