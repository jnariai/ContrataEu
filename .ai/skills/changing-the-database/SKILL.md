---
name: changing-the-database
description: "Use when creating or changing a migration, index, foreign key, lookup table or a non-trivial Eloquent or raw SQL query. Also when renaming or dropping a column, backfilling data, doing bulk inserts or updates, or adding a public_id column. Not for model code (see writing-models)."
---

# Changing the Database

First inspect the real schema with Boost's **Database Schema** tool. Never guess columns or indexes.

## Rules
1. **Every foreign key is constrained with a deliberate delete rule:** `foreignId('user_id')->constrained()->restrictOnDelete()`. Use `cascadeOnDelete()` or `nullOnDelete()` only when the business wants that.
2. **Invariants the DB can express go in the DB:** `unique`, `NOT NULL`, check constraints where supported.
3. **Index for real, frequent query patterns**, not for every column in a `WHERE`, `ORDER BY` or join: weigh selectivity and write cost, confirm with `EXPLAIN`, and don't duplicate the index a foreign key already has. Composite indexes follow the query: equality columns first, then range or sort columns.
4. **Enums** are an integer foreign key to a lookup table (`id` + unique `value`), never a DB `ENUM` column.
5. **Money** is `unsignedBigInteger('total_minor')` (`bigInteger` when negatives are possible: refunds, balances) plus `unsignedSmallInteger('currency_id')` with a foreign key to the `currencies` lookup table. Timestamps in UTC.
6. **`public_id`:** `$table->string('public_id', 40)->unique();`, plus `->collation('ascii_bin')` on MySQL/MariaDB because Base58 is case-sensitive. Keep the bigint `id` as the primary key.
7. **Never edit a merged migration**; add a new one.
8. **Zero downtime (expand → migrate → contract):** add a nullable column → deploy code writing both → backfill in a queued job with `chunkById` → enforce `NOT NULL` or drop the old column in a later deploy. Never rename or drop a column that running code still reads.
9. **SQL uses bindings** (`whereRaw('total_minor > ?', [$min])`); identifiers (sort column, direction, table) come from an allow-list.

## Edge cases (decided)
- **Lookup rows are inserted explicitly**, not from `OrderStatus::cases()`: a migration must give the same result after the enum changes. Ids are assigned, never auto-incremented. Adding, renaming and removing values is in `writing-enums`.
- **Large tables:** check whether the operation locks the table on this engine, and schedule or split it. Run `EXPLAIN` on new queries with Boost's **Database Query** tool and add the index in the same change.
- **Aggregate in SQL** (`withCount`, `withSum`, `withExists`, `exists()`); don't load collections to count them.
- **Bulk work:** `chunkById` / `lazyById` to read, `upsert` / `insert` in batches of ~500–1000 to write, inside a named static method on the model (`Order::importBatch($rows)`). These bypass events and casts, so set timestamps and cast values explicitly.

## Example
Lookup ids are assigned explicitly; `value` is the enum's backed value. The `ascii_bin` collation is MySQL/MariaDB only.
```php
Schema::create('order_statuses', function (Blueprint $table): void {
    $table->unsignedSmallInteger('id')->primary();
    $table->string('value', 64)->unique();
});

DB::table('order_statuses')->insert([['id' => 1, 'value' => 'pending'], ['id' => 2, 'value' => 'paid']]);

Schema::create('orders', function (Blueprint $table): void {
    $table->id();
    $table->string('public_id', 40)->unique()->collation('ascii_bin');
    $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
    $table->unsignedSmallInteger('status_id');
    $table->foreign('status_id')->references('id')->on('order_statuses')->restrictOnDelete();
    $table->unsignedBigInteger('total_minor');
    $table->unsignedSmallInteger('currency_id');
    $table->foreign('currency_id')->references('id')->on('currencies')->restrictOnDelete();
    $table->timestamps();

    $table->index(['customer_id', 'status_id', 'created_at']);
});
```

## Checklist
1. Schema inspected first; foreign keys constrained with a deliberate delete rule.
2. Invariants as DB constraints; indexes only for real query patterns, confirmed with `EXPLAIN`, none redundant.
3. Enums as lookup-table FKs; money as minor units + `currency_id`; `public_id` unique (and `ascii_bin` on MySQL).
4. New migration, never an edited one; expand → migrate → contract for anything running code reads.
5. Bindings for values, allow-lists for identifiers; bulk work chunked and batched.
6. Verify: `php artisan migrate` then `php artisan migrate:rollback --step=1` and `migrate` again locally, then `php artisan test --compact`.
