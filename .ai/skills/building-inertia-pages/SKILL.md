---
name: building-inertia-pages
description: "Use when building or changing an Inertia page or form: a web controller that renders or redirects, props, shared props in HandleInertiaRequests, can flags, flash messages, error pages, or a page in resources/js/pages. Also when deciding what data reaches the browser or how a business error is shown to the user. Not for JSON APIs (see building-api-endpoints)."
---

# Building Inertia Pages

The page and form side, on top of `writing-controllers`, which owns routes, FormRequests and Policies. Check the installed Inertia version's API with Boost's **Search Docs** before using deferred, optional or merge props.

## Rules
1. **GET renders a page with explicit props**: a Resource or an explicit array. Never a raw model, a secret or a hidden field: everything in props reaches the browser.
2. **Writes use the same FormRequest and Action as the API**, then redirect (`to_route()` / `back()`) with a short flash message. They never render a page.
3. **Shared props** (`HandleInertiaRequests::share`) are minimal and lazy (`fn () => …`).
4. **`can` flags** combine the Policy (may this user?) with the model's state (does it allow it now?). They drive the UI only; the server still checks both.
5. **Business exceptions** become `back()` + an `error` flash in one place (`bootstrap/app.php`); controllers don't catch them. Use the installed Inertia version's built-in flash data and error-page handling when it has them (check Boost's **Search Docs**); otherwise follow the project's existing shape, or [references/error-handling.md](references/error-handling.md) if it has none.
6. **Never return a hand-made 409** from a web route: Inertia reserves it (with `X-Inertia-Location`) for asset-version mismatches.
7. **Dates are ISO 8601, formatted in the browser:** instants as Zulu strings, calendar dates as `YYYY-MM-DD`, shown with `Intl.DateTimeFormat` in the user's timezone. Money (`formatted`) and enum labels arrive ready to display.
8. **Lists:** pass paginators through a Resource collection; keep filters in the query string.

## Edge cases (decided)
- **Flash text** uses `__()` only when the project is localized; otherwise a plain string (`'Order cancelled.'`).
- **Simple writes without an Action** call the model's behavior method (`$task->complete(now()->toImmutable())`), then redirect.
- **Optional or expensive data** goes in deferred or optional props with partial reloads.
- **Error pages:** in production, 403/404/500/503 render an Inertia `Error` page and 419 goes `back()` with "session expired". Show a trace ID on the 500 page so users can quote it to support. Use the version's built-in error-page handling when it exists; otherwise [references/error-handling.md](references/error-handling.md).
- **JS doesn't re-implement business rules**; at most it mirrors simple ones for UX.
- **Forms** follow Boost's Inertia skill (the `<Form>` component, or `useForm` for programmatic control): errors next to each input, submit disabled while processing. Also confirm destructive actions, and show success and error flashes in one global toast.
- **Calendar dates in JS:** don't pass `YYYY-MM-DD` to `new Date()` and format it in local time, because it parses as UTC midnight and shows the previous day west of UTC; format the string's parts, or use `timeZone: 'UTC'`.
- **URLs** come from named routes (Wayfinder/Ziggy).

## Example
`show` has no input, so it's authorized on the route (`->middlewareFor('show', 'can:view,order')`). The shared `flash` props apply when the Inertia version has no built-in flash data.
```php
class OrderController extends Controller
{
    public function show(Request $request, Order $order): Response
    {
        return Inertia::render('Orders/Show', [
            'order' => OrderResource::make($order->load('lines')),
            'can' => ['cancel' => $request->user()->can('cancel', $order) && $order->isCancellable()],
        ]);
    }
}

class CancelledOrderController extends Controller
{
    public function store(StoreCancelledOrderRequest $request, Order $order, CancelOrder $cancelOrder): RedirectResponse
    {
        $cancelOrder->handle($order, $request->user(), CancellationReason::from($request->validated('reason')));

        return to_route('orders.show', $order)->with('success', 'Order cancelled.');
    }
}

// HandleInertiaRequests::share()
return [
    ...parent::share($request),
    'auth' => ['user' => fn () => $request->user()?->only('public_id', 'name', 'email')],
    'flash' => [
        'success' => fn () => $request->session()->get('success'),
        'error' => fn () => $request->session()->get('error'),
    ],
];
```
Tests:
```php
it('shows an order to its owner', function (): void {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create();

    $this->actingAs($user)->get(route('orders.show', $order))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Orders/Show')
            ->where('order.id', $order->public_id)
            ->missing('order.internal_notes'));
});

it('flashes business errors back to the form', function (): void {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->shipped()->create();

    $this->actingAs($user)->from(route('orders.show', $order))
        ->post(route('cancelled-orders.store', $order), ['reason' => 'changed_mind'])
        ->assertRedirect(route('orders.show', $order))
        ->assertSessionHas('error');
});
```

## Checklist
1. Explicit props through a Resource or array; nothing secret; shared props lazy.
2. Writes: same FormRequest and Action as the API, then redirect with a flash.
3. `can` flags combine permission and state.
4. Business errors handled once, globally; no hand-made 409.
5. Tests: `assertInertia` component and props (including **missing** sensitive props), validation → `assertSessionHasErrors` with the field and its message, guest → login redirect, other user → 403, business rule → `assertSessionHas('error')`.
6. Verify: `php artisan test --compact --filter=Order`, then Pint as Boost's guideline says, and `vendor/bin/phpstan analyse` if the project uses Larastan.
