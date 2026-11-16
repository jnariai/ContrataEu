---
name: writing-models
description: "Use when creating or changing an Eloquent model in app/Models: a state change, named constructor, scope, query method, relationship, cast, public ID or factory. Also when code outside a model is about to call fill(), update(), create() or save(), or assign attributes. Not for migrations (see changing-the-database) or enums (see writing-enums)."
---

# Writing Models

The model is both entity and repository: it holds the business state, protects its rules, and answers queries about itself.

## Rules
1. Models live in `app/Models` and are **not** `final` (Eloquent, factories and packages extend or proxy them). Shared Eloquent behavior goes in traits in `app/Models/Concerns`.
2. **Declare the fillable columns, never `$guarded = []`:** every column the model's own constructors and behavior methods write, including state columns like `status`; never `id`, `public_id`, timestamps, or foreign keys set through `associate()`. Mass assignment (`new self([...])`, `$this->update([...])`) happens only inside the model; callers never call `create()`, `fill()` or `update()`.
3. **State changes only through behavior methods** named after business verbs (`cancel()`, `ship()`, `assignTo()`), never generic setters (`setStatus()`). Each one follows the same order:
   1. check every business rule, and throw a business exception (one named constructor per reason) when one fails (`writing-actions`);
   2. change relations with `associate()` / `dissociate()`;
   3. write attributes and persist with `$this->update([...])` (or `$this->save()` when only relations changed).
4. **Creation goes through a named static constructor** that checks rules, builds with `new self([...])`, associates relations, then `save()`s (`Order::place(...)`, `Invoice::issue(...)`); `self::create([...])` is fine when there is no relation to associate. Never `Order::create([...])` from outside.
5. **Parameters** are models, value objects, enums, `CarbonImmutable` and scalars, never a `Request`, FormRequest or Data object: the caller unpacks those.
6. **Time is a parameter** (`cancel(..., CarbonImmutable $at)`, `isOverdue(CarbonImmutable $today)`), so tests control it and the model never calls `now()`.
7. The model checks business rules only; input shape (required, max length, format) stays in the FormRequest.
8. **Reads live on the model:** local scopes for reusable filters and named static query methods for queries callers ask for by name (`Order::openFor($user)`). No repository classes. A complex aggregate read is a query Action (`writing-actions`).
9. **Casts** go in `casts()`: enums through `LookupCast` onto their integer `{attribute}_id` column (`writing-enums`), dates to `immutable_date` / `immutable_datetime`, PII to `encrypted`, secrets to `hashed`, value objects through their cast in `app/Casts` (`writing-value-objects`).
10. Every relationship has a native return type and a generic PHPDoc (`/** @return BelongsTo<User, $this> */`).
11. An explicit `@property` docblock for every column (with its cast type), and `@property-read` for relations, so IDEs and Larastan see the real types.
12. Models that appear in routes, Inertia props or API responses use `HasPublicId` ([references/has-public-id.md](references/has-public-id.md)): `public_id` is the route key and the auto-increment `id` stays internal.
13. **Observers only for technical concerns** (cache, search sync). Business rules hidden in observers are hard to find and test.

## Edge cases (decided)
- **`#[Fillable]` or `$fillable`:** use the `#[Fillable([...])]` class attribute when `Illuminate\Database\Eloquent\Attributes\Fillable` exists in `vendor` (Laravel 13+); otherwise `protected $fillable = [...]`.
- **`#[Scope]` or `scopeX()`:** use the `#[Scope]` attribute (a `protected` method without the prefix) when `Illuminate\Database\Eloquent\Attributes\Scope` exists in `vendor`; otherwise follow the project's `scopeOpen()` naming.
- **A scope never shares a name with a static method.** `Order::place()` is a constructor, so a "still being placed" scope can't be `place`; pick another business word (`unfinished`, `pending`).
- **Plain `$model->delete()`** is fine when deleting has no rule; otherwise use a behavior method (`archive()`).
- **Soft deletes** only when the business needs restore or audit: they leak into every query and unique index.
- **Factories and seeders** are the only code that writes attributes directly. The default factory state is a valid model; add named states per status and common relationship ([references/factories.md](references/factories.md)).
- **Public ID prefix:** `public const string PUBLIC_ID_PREFIX = 'ord_';`, 2–4 lowercase letters plus `_`, unique across the app, never changed once IDs exist. The column is in `changing-the-database`.

## Example
A trimmed model. The full one, with lines, payment method and money, is in [references/order-model.md](references/order-model.md).
```php
<?php

namespace App\Models;

use App\Casts\LookupCast;
use App\Enums\CancellationReason;
use App\Enums\OrderStatus;
use App\Exceptions\OrderCannotBeCancelled;
use App\Exceptions\OrderCannotBePlaced;
use App\Models\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $public_id
 * @property int $customer_id
 * @property int $status_id
 * @property OrderStatus $status
 * @property CancellationReason|null $cancellation_reason
 * @property CarbonImmutable|null $cancelled_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read User $customer
 */
#[Fillable(['status', 'cancellation_reason', 'cancelled_at'])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;
    use HasPublicId;

    public const string PUBLIC_ID_PREFIX = 'ord_';

    public static function place(User $customer, Address $address): self
    {
        if (! $address->belongsTo($customer)) {
            throw OrderCannotBePlaced::foreignAddress($address);
        }

        $order = new self(['status' => OrderStatus::Pending]);
        $order->customer()->associate($customer);
        $order->save();

        return $order;
    }

    public function cancel(User $actor, CancellationReason $reason, CarbonImmutable $at): void
    {
        if (! $this->isCancellable()) {
            throw OrderCannotBeCancelled::fromStatus($this->status);
        }

        $this->update([
            'status' => OrderStatus::Cancelled,
            'cancellation_reason' => $reason,
            'cancelled_at' => $at,
        ]);
    }

    public function isCancellable(): bool
    {
        return $this->status->canTransitionTo(OrderStatus::Cancelled);
    }

    /** @return Builder<self> */
    public static function openFor(User $customer): Builder
    {
        return static::query()->whereBelongsTo($customer, 'customer')->unfinished()->latest('id');
    }

    /** @param Builder<self> $query */
    #[Scope]
    protected function unfinished(Builder $query): void
    {
        $query->whereIn('status_id', array_map(
            static fn (OrderStatus $status): int => LookupCast::idOf('order_statuses', $status),
            OrderStatus::unfinished(),
        ));
    }

    /** @return BelongsTo<User, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'status' => LookupCast::of(OrderStatus::class, 'order_statuses', 'status_id'),
            'cancellation_reason' => CancellationReason::class,
            'cancelled_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
```
Callers: `$order->cancel($actor, $reason, now()->toImmutable());`, never `$order->update([...])` or `$order->status = …; $order->save();`.

## Checklist
1. Not `final`; fillable columns declared (`#[Fillable]` on Laravel 13+), no `$guarded = []`; casts in `casts()`.
2. Every state change is a behavior method: check rules → associate → `update([...])`. Creation through a named constructor. No `create()` / `fill()` / `update()` outside the model.
3. Parameters are models, value objects, enums, `CarbonImmutable` or scalars; time is passed in.
4. Each broken rule throws a business exception with its own named constructor.
5. Reads are scopes or named static query methods, with no name clashes.
6. `@property` for every column and relation; relations typed with generics.
7. `HasPublicId` with a unique prefix when the model is exposed.
8. One test per rule on the model, named after the rule (`it('does not cancel a shipped order')`), asserting the exception's `errorCode` and passing fixed times (`CarbonImmutable::parse('2026-01-31 10:00')`).
9. Verify: `php artisan test --compact --filter=Order`, then Pint as Boost's guideline says, and `vendor/bin/phpstan analyse` if the project uses Larastan.
