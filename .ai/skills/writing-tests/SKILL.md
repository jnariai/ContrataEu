---
name: writing-tests
description: "Use when fixing a bug, adding or changing behaviour, writing or changing a Pest test, or before declaring work done. Also when a test fails after a change, when deciding which tests a change needs, or when tests are flaky. Not for pre-deploy risk review (see reviewing-releases)."
---

# Writing Tests

Tests exist so we don't deploy bugs: a broken business rule should fail a test, not reach a customer.

## Rules
1. **Bug fixes start with a failing test.** (1) Reproduce with Boost's **Last Error** / **Read Log Entries** and the report. (2) Write the test at the level where the bug lives (model or Action for logic; endpoint or page for validation, authorization or response shape), named after the correct behaviour with the issue: `it('keeps the discount when an order line is removed (#482)')`. (3) Run it and confirm it fails **for the right reason**. (4) Fix with the smallest change. (5) Look for the same bug in other callers and sibling rules.
2. **One test per business rule**, named in business language after the result and its condition, never after a class or method: `it('does not cancel a shipped order')` on the model or Action; an API error test also names the status, `it('returns 409 when cancelling a shipped order')`.
3. **Assert the outcome the business cares about** (state, money, who was notified, what the user sees, the error `code`), not implementation details.
4. **Arrange with factory states** that read like the domain: `Order::factory()->for($customer)->shipped()->create()`.
5. **Never delete, skip or loosen a test to make a change pass.** Changing a rule means changing its test in the same commit; if the intent changed, say so explicitly.
6. **Fake only the boundary under test** (`Queue::fake()`, `Event::fake([OrderCancelled::class])`, `Mail::fake()`, `Notification::fake()`, `Storage::fake()`, `Http::fake([...])`), then assert what was dispatched or sent, including its payload.
7. **Deterministic:** no reliance on order, real time, unseeded randomness or the network. A flaky test is a bug: fix it, don't retry it.
8. **Coverage matrix:**

| Change | Required tests |
|---|---|
| API endpoint | success (status, `assertJsonPath`, DB state) · validation dataset → 422 with the field and its message · guest → 401 · other user/tenant → 403/404 · each business rule → status + `code` |
| Inertia page or form | `assertInertia` component and props (including **missing** sensitive props) · validation → `assertSessionHasErrors` with the field and its message · guest → login redirect · other user → 403 · business rule → `assertSessionHas('error')` |
| Model behavior method | success + each business exception (assert `errorCode`) + boundaries, with time passed in |
| Action | only with more than one entry point or logic beyond one model call: success + each business exception + idempotency/concurrency, calling `handle()` directly |
| Enum / value object | every transition (allowed **and** forbidden), invariants, arithmetic |
| Policy | each ability × (owner, other user, guest, role) |
| Job / listener | succeeds, idempotent (runs twice → one effect), `failed()` behaviour |

## Edge cases (decided)
- **A regression test isn't possible** (infrastructure, timing, third-party behaviour): say why in the report instead of skipping silently.
- **A test fails after your change:** decide deliberately whether the code or the test is wrong; if the test encodes an old rule, ask or explain before changing it.
- **Boundaries** are where most rule bugs live: exactly at the limit, one over, one under, empty, zero, negative, null, another currency or timezone.
- **Time:** pass fixed times into models where you can; otherwise `freezeTime()` / `travelTo()`, and test the expiry instant itself.
- **Money:** assert exact integer minor units, never rounded floats.
- **Retries:** double-submit or re-run the job → exactly one effect.
- **Setup:** Pest with `LazilyRefreshDatabase` in `tests/Pest.php`, plus `beforeEach(fn () => Http::preventStrayRequests());`.
- **Critical rules** (money, permissions, state machines): declare `mutates(CancelOrder::class, OrderStatus::class);` and run `vendor/bin/pest --mutate --parallel`. A surviving mutant is a rule no test noticed being broken; coverage percentage alone doesn't prove this.

## Example
```php
it('does not cancel a shipped order', function (): void {
    $order = Order::factory()->shipped()->create();

    expect(fn () => $order->cancel(User::factory()->create(), CancellationReason::ChangedMind, CarbonImmutable::parse('2026-01-31 10:00')))
        ->toThrow(fn (OrderCannotBeCancelled $e) => expect($e->errorCode)->toBe('order.cannot_be_cancelled'));

    expect($order->fresh()->status)->toBe(OrderStatus::Shipped);
});
```

## Checklist
1. Bug fix: the regression test was written first and failed for the right reason.
2. Every new or changed business rule has a test named after it.
3. The rows of the coverage matrix that apply are covered.
4. No test deleted, skipped or loosened; any changed test is explained.
5. Verify: the narrowest set that covers the change (`php artisan test --compact --filter=CancelOrder`), then Pint as Boost's guideline says, and `vendor/bin/phpstan analyse` if the project uses Larastan. The full suite runs before merging (`reviewing-releases`).
