# Reference: enum lookup tables (`LookupCast`)

The standard for every enum column: **code uses only the enum**, and the database stores an **integer foreign key to a lookup table** (`id`, unique `value`). The table exists for validation at the database level: an unknown value can't be written by a seeder, a raw query or another app. It isn't a second source of truth: the enum is.

Create the cast only when the project has no equivalent. An existing lookup cast is the one to keep.

## Rules
- One lookup table per enum, named after it in plural snake_case (`OrderStatus` → `order_statuses`): `id` (small integer primary key, **assigned explicitly**, never auto-incremented) and a unique `value` holding the enum's backed value. Nothing else: labels, order and rules live on the enum.
- The model's column is `{attribute}_id` with a foreign key (`restrictOnDelete()`). The attribute itself (`status`) only exists in PHP, through the cast.
- Migrations insert **explicit rows** (`id` + `value`), never `OrderStatus::cases()`: a migration must give the same result after the enum changes. Ids are stable across environments, so the cast's id cache is safe in tests and on every server.
- Queries go through scopes that take enum cases (`Order::query()->inStatus(OrderStatus::Paid)`); code never handles the integer ids.
- A test keeps the table and `cases()` in sync.

## Migration
```php
// database/migrations/xxxx_create_order_statuses_table.php
Schema::create('order_statuses', function (Blueprint $table): void {
    $table->unsignedSmallInteger('id')->primary();
    $table->string('value', 64)->unique();
});

DB::table('order_statuses')->insert([
    ['id' => 1, 'value' => 'pending'],
    ['id' => 2, 'value' => 'paid'],
    ['id' => 3, 'value' => 'shipped'],
    ['id' => 4, 'value' => 'cancelled'],
]);

// database/migrations/xxxx_create_orders_table.php
$table->unsignedSmallInteger('status_id');
$table->foreign('status_id')->references('id')->on('order_statuses')->restrictOnDelete();
```

## The cast
```php
// app/Casts/LookupCast.php
<?php

namespace App\Casts;

use BackedEnum;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Maps an enum attribute to the integer id of its lookup table. Code only ever sees the enum.
 *
 *     'status' => LookupCast::of(OrderStatus::class, 'order_statuses', 'status_id'),
 *
 * The DB facade is used because Eloquent builds casts itself, so nothing can be injected.
 *
 * @implements CastsAttributes<BackedEnum|null, BackedEnum|null>
 */
class LookupCast implements CastsAttributes
{
    /** @var array<string, array<string, int>> value => id, per table */
    private static array $ids = [];

    /** @param class-string<BackedEnum> $enum */
    public function __construct(
        private readonly string $enum,
        private readonly string $table,
        private readonly string $column,
    ) {}

    /** @param class-string<BackedEnum> $enum */
    public static function of(string $enum, string $table, string $column): string
    {
        return self::class.':'.$enum.','.$table.','.$column;
    }

    public static function idOf(string $table, BackedEnum $case): int
    {
        return self::ids($table)[(string) $case->value]
            ?? throw new InvalidArgumentException("[{$case->value}] has no row in [{$table}]: add it in a migration.");
    }

    /**
     * @template T of BackedEnum
     *
     * @param  class-string<T>  $enum
     * @return T
     */
    public static function caseOf(string $enum, string $table, int $id): BackedEnum
    {
        $value = array_search($id, self::ids($table), true);

        if ($value === false) {
            throw new InvalidArgumentException("Id [{$id}] doesn't exist in [{$table}].");
        }

        return $enum::from((string) $value); // jboost enums are always string-backed
    }

    /** @param array<string, mixed> $attributes */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?BackedEnum
    {
        $id = $attributes[$this->column] ?? null;

        return $id === null ? null : self::caseOf($this->enum, $this->table, (int) $id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, int|null>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value !== null && ! $value instanceof $this->enum) {
            throw new InvalidArgumentException("[{$key}] must be set with a {$this->enum} case.");
        }

        return [$this->column => $value === null ? null : self::idOf($this->table, $value)];
    }

    /** @return array<string, int> */
    private static function ids(string $table): array
    {
        return self::$ids[$table] ??= DB::table($table)->pluck('id', 'value')->map(static fn (mixed $id): int => (int) $id)->all();
    }
}
```

## On the model
```php
/**
 * @property int $status_id
 * @property OrderStatus $status
 */
class Order extends Model
{
    /** @param Builder<self> $query */
    #[Scope]
    protected function inStatus(Builder $query, OrderStatus ...$statuses): void
    {
        $query->whereIn('status_id', array_map(static fn (OrderStatus $status): int => LookupCast::idOf('order_statuses', $status), $statuses));
    }

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'status' => LookupCast::of(OrderStatus::class, 'order_statuses', 'status_id'),
        ];
    }
}
```
- Writing: `$this->status = OrderStatus::Cancelled;` sets `status_id`. Reading: `$order->status` is an `OrderStatus`.
- Factories set the enum too (`'status' => OrderStatus::Pending`); the cast resolves the id.
- Raw SQL and reports join the lookup table to read the value: `join order_statuses on order_statuses.id = orders.status_id`.

## Sync test
```php
// tests/Feature/Database/LookupTablesTest.php
it('has exactly one lookup row per enum case', function (string $enum, string $table): void {
    expect(DB::table($table)->orderBy('value')->pluck('value')->all())
        ->toBe(collect($enum::cases())->map(fn (BackedEnum $case): string => (string) $case->value)->sort()->values()->all());
})->with([
    'order statuses' => [OrderStatus::class, 'order_statuses'],
    'payment methods' => [PaymentMethod::class, 'payment_methods'],
    'currencies' => [Currency::class, 'currencies'],
]);
```

## Adding, renaming or removing a value
- **Add:** the case, plus a migration inserting its row with the next free id, in the same change.
- **Rename:** a migration that updates `value` on the existing row (the id, and so every foreign key, stays), in the same deploy as the renamed case. Old code still running during the deploy reads the new value and fails, so for a zero-downtime rename add a temporary legacy case first (`writing-enums` skill).
- **Remove:** move the rows that point at it, then delete the lookup row (the foreign key refuses while rows still use it), then remove the case.
