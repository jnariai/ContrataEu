---
name: scoping-tenants
description: "Use when the project is multi-tenant (teams, organizations, accounts) and a change adds or touches a tenant-owned model, query, validation rule, route binding, job, cache key, file path, broadcast channel or scheduled command. Also when adding tenant or team ownership to an app for the first time. Not for single-tenant apps."
---

# Scoping Tenants

First find out what the project already does: a `tenant_id` / `team_id` / `organization_id` column, a tenancy package, or a recorded rule in `.ai/rules`. Follow it.

## Rules
1. **`tenant_id`** is a constrained, indexed foreign key; composite indexes start with it; unique indexes include it (`unique(['tenant_id', 'slug'])`).
2. **One `BelongsToTenant` trait** applies a global scope and fills `tenant_id` on `creating` from the **current tenant**, which a middleware resolves and stores in a scoped singleton or `Context`.
3. **Validation is scoped by hand:** `Rule::exists(...)->where('tenant_id', $tenantId)`, and the same for `unique`. Global scopes do **not** apply to validation rules.
4. **Route model binding** goes through the scoped model, and nested routes use `scopeBindings()`.
5. **Raw `DB::table()` queries** bypass global scopes: add `where('tenant_id', …)` by hand.
6. **Queues:** jobs carry the tenant ID and restore tenant context in `handle()` (or job middleware) before touching data.
7. **Cache keys and file paths** are prefixed with the tenant (`tenant:{id}:…`, `tenants/{id}/…`).
8. **Broadcast channels** authorize the tenant as well as the user.
9. **Scheduled commands** loop over tenants explicitly; they never run with an implicit "current" tenant.

## Edge cases (decided)
- **No strategy yet:** shared tables + `tenant_id` is the default for SaaS (many small tenants, shared features). Choose database-per-tenant (e.g. stancl/tenancy) only for strict isolation or compliance, per-tenant restore, or a few large tenants; it costs migrations ×N, connection switching and harder reporting. Record the choice with Boost's **Record Rule**.
- **Intentional cross-tenant code** (admin, reports) uses `withoutGlobalScope(...)` explicitly, with a comment and its own authorization check.

## Example
```php
it('does not let another tenant see an order', function (): void {
    $order = Order::factory()->for(Tenant::factory())->create();
    $outsider = User::factory()->for(Tenant::factory())->create();

    $this->actingAs($outsider)->get(route('orders.show', $order))->assertNotFound();
});

it('rejects referencing another tenant\'s address', function (): void {
    $user = User::factory()->for(Tenant::factory())->create();
    $foreign = Address::factory()->for(Tenant::factory())->create();

    $this->actingAs($user)->post(route('orders.store'), ['address_id' => $foreign->public_id])
        ->assertSessionHasErrors('address_id');
});
```

## Checklist
1. Tenant-owned models use `BelongsToTenant`; indexes and unique constraints include `tenant_id`.
2. Validation rules, raw queries, jobs, cache keys, file paths, channels and scheduled commands are all scoped.
3. Tests per endpoint/page: a user of tenant B can't list, view, update or reference (via `exists`) tenant A's records → 404/403/422; a new model gets the current `tenant_id` automatically.
4. Verify: `php artisan test --compact --filter=Tenant`, then Pint as Boost's guideline says, and `vendor/bin/phpstan analyse` if the project uses Larastan.
