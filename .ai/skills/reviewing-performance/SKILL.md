---
name: reviewing-performance
description: "Use when a page, endpoint, job or command is slow, or when building imports, exports, reports, bulk writes, queued jobs or caching. Also when a query runs on a large or growing table, a loop touches the database, or a lazy-loading violation appears. Not for schema design (see changing-the-database)."
---

# Reviewing Performance

Measure first: Boost's **Database Query** (`EXPLAIN`), Telescope/Pulse/Debugbar locally, logged lazy-loading warnings in production. Optimize what the numbers show.

## Rules
1. **No N+1:** eager load exactly what the response uses (`with`, `load`, `loadMissing`); Resources use `whenLoaded`.
2. **Aggregate in SQL:** `withCount`, `withSum`, `withExists`, `exists()`.
3. **Large sets:** `chunkById`, `lazyById` or `cursor`. Never `::all()` or an unbounded `get()` on growing tables.
4. **Bulk writes:** `upsert` / `insert` in batches, or a query-level `update()` instead of looping models when per-model events aren't needed, inside a named static method on the model.
5. **Queue anything slow or external:** mail, notifications, outgoing webhooks, PDFs, exports, third-party syncs. Target p95 < 300 ms per request.
6. **Every job:** idempotent, with explicit `$tries`, `backoff()`, `$timeout`, and `failed()` for outcomes the user sees. Pass models (serialized by ID) or IDs, not big payloads.
7. **Cache** read-mostly, expensive data with explicit TTLs. Keys include every dimension that changes the result (`tenant:{id}:catalog:v2`); never put per-user data under a shared key.
8. **HTTP client:** timeouts always; `retry()` only for idempotent calls, on connection errors, 5xx and 429 (`building-api-gateways`).

## Edge cases (decided)
- **Selecting fewer columns** only when the consumer doesn't read the omitted ones: strict mode throws on missing attributes.
- **A new query on a large table:** `EXPLAIN`, then add a fitting (composite) index in the same change.
- **Deduplicate jobs** with `ShouldBeUnique` / `WithoutOverlapping`; use `ShouldQueueAfterCommit` when dispatched inside a transaction.
- **Queue priorities:** split queues (`high`, `default`, `low`) so an export never delays a password reset email.
- **Hot keys:** `Cache::flexible()` (stale-while-revalidate); `Cache::lock()` against stampedes. Invalidate where the data changes (the Action or an after-commit listener).
- **Parallel independent calls:** `Http::pool()`.
- **Runtime:** deploys run `php artisan optimize`; OPcache in production with `validate_timestamps=0`. Under Octane, no request state in static properties or singletons.

## Example
Before: an N+1 and an unbounded load.
```php
foreach (Order::where('status_id', $paid)->get() as $order) {
    $rows[] = [$order->public_id, $order->customer->name, $order->lines->count()];
}
```
After: scoped, eager-loaded, counted in SQL and streamed.
```php
Order::query()->inStatus(OrderStatus::Paid)
    ->with('customer:id,name')
    ->withCount('lines')
    ->lazyById()
    ->each(fn (Order $order) => $writer->row([$order->public_id, $order->customer->name, $order->lines_count]));
```

## Checklist
1. Measured before and after (query count, `EXPLAIN`, timing).
2. No N+1, no unbounded loads; aggregates in SQL; indexes match the new queries.
3. Slow or external work queued; jobs idempotent with tries, backoff and timeout.
4. Cache keys complete, TTLs explicit, invalidation where the data changes.
5. Verify: `php artisan test --compact --filter=Order` with `Model::preventLazyLoading()` active (strict mode), and re-run `EXPLAIN` on the changed queries.
