---
name: writing-enums
description: "Use when creating, changing, renaming or removing a backed enum in app/Enums (status, type, role, currency, priority), or an attribute with a fixed set of options. Also when code compares a column against a raw string, adds a DB ENUM column or class constants for options, or builds select options. Not for values with rules or units (see writing-value-objects)."
---

# Writing Enums

Any attribute with a fixed set of values is a backed enum. **Code uses only the enum.** In the database every enum column is an integer foreign key to a lookup table (`id` + unique `value`), there for validation at the database level, never a second source of truth.

## Rules
1. **Location and backing:** `app/Enums`, always **string**-backed (`enum OrderStatus: string`), never int-backed, so the stored value stays readable in the database, logs and APIs.
2. **Naming:** a singular noun (`OrderStatus`), PascalCase cases, lowercase snake_case values (`case BankTransfer = 'bank_transfer';`).
3. **Behavior that depends only on the case lives on the enum:** `label()`, state checks (`isFinal()`), allowed transitions (`canTransitionTo(self $next)`), case-only rules (`PaymentMethod::acceptsCurrency()`), `options()` for selects.
4. **`match ($this)` never has a `default` arm**, so a new case fails loudly everywhere it must be decided. `label()` is an exhaustive `match`.
5. **Rules that need other data stay in the model**: anything that reads dates, amounts or related models ("cancellable within 14 days") is a model method that asks the enum for the case-only part.
6. **Cast through the lookup cast:** `'status' => LookupCast::of(OrderStatus::class, 'order_statuses', 'status_id')`. The column is `status_id`; the attribute `status` is the enum. If the project has no lookup cast, use [references/lookup-cast.md](references/lookup-cast.md).
7. **Validate** with `Rule::enum(OrderStatus::class)`, narrowed with `->only([...])` / `->except([...])`. Convert in `toData()` with `OrderStatus::from($this->string('status')->toString())`.
8. **Compare against cases**, never raw strings: `$order->status === OrderStatus::Paid`, or better the enum's method (`$order->status->isFinal()`). Queries pass cases to a scope that resolves the ids (`->inStatus(OrderStatus::Paid)`); code never handles lookup ids.
9. **Values are stored data:** never rename or remove one without the steps below.

## Edge cases (decided)
- **Official codes keep their official form** (`case BRL = 'BRL';`, ISO 4217). The `Currency` enum lives next to `Money` (`writing-value-objects`).
- **Labels** are wrapped in `__()` only when the project is localized.
- **UI options come from `cases()`**, never from the lookup table: the table has no labels, order or rules.
- **Sending out:** API Resources and Inertia props use `{value, label}`; a select gets `OrderStatus::options()`.
- **Add a value:** the case plus a migration inserting its lookup row with the next free explicit id, in the same change.
- **Rename a value:** the row keeps its id, so foreign keys never move. (1) Add the new case next to a temporary legacy case that methods treat the same (`case LegacyX = 'old_value';`) and deploy; (2) a migration updates the lookup row's `value`; (3) remove the legacy case in a later deploy.
- **Remove a value:** move the rows that point at it, delete the lookup row (the foreign key refuses while rows still use it), then remove the case (`reviewing-releases`).
- More examples (a case-only rule, scopes, validation, a transition test): [references/more-examples.md](references/more-examples.md).

## Example
```php
<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Shipped = 'shipped';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Paid => 'Paid',
            self::Shipped => 'Shipped',
            self::Cancelled => 'Cancelled',
        };
    }

    public function isFinal(): bool
    {
        return match ($this) {
            self::Pending, self::Paid => false,
            self::Shipped, self::Cancelled => true,
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, match ($this) {
            self::Pending => [self::Paid, self::Cancelled],
            self::Paid => [self::Shipped, self::Cancelled],
            self::Shipped, self::Cancelled => [],
        }, true);
    }

    /** @return list<self> */
    public static function unfinished(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $case): bool => ! $case->isFinal()));
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(static fn (self $case): array => ['value' => $case->value, 'label' => $case->label()], self::cases());
    }
}
```

## Checklist
1. String-backed in `app/Enums`; singular name, PascalCase cases, snake_case values (official codes as-is).
2. `label()` as an exhaustive `match`; no `default` arm in any `match`.
3. Case-only behavior on the enum; rules needing other data in the model.
4. Cast with `LookupCast` onto an integer `{attribute}_id`; validated with `Rule::enum()`; converted with `from()`.
5. Compared against cases or enum methods, never raw strings; queries through scopes that take cases.
6. Lookup row added in a migration with an explicit id; renames keep the id.
7. Tests: every transition, allowed **and** forbidden, as a dataset; case-only rules per case; the lookup-table sync test in [references/lookup-cast.md](references/lookup-cast.md).
8. Verify: `php artisan test --compact --filter=OrderStatus`, then Pint as Boost's guideline says, and `vendor/bin/phpstan analyse` if the project uses Larastan.
