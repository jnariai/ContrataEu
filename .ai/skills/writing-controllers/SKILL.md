---
name: writing-controllers
description: "Use when adding or changing a route, controller, FormRequest or Policy, or an Artisan command or streamed export. Also when deciding where authorization or validation goes, or when a controller starts holding business logic. Not for the JSON contract (see building-api-endpoints) or page props (see building-inertia-pages)."
---

# Writing Controllers

The HTTP layer translates input and output: it authenticates, authorizes, validates, calls **one** model method or **one** Action, and shapes the response. It never decides business outcomes.

## Rules
1. **Resource routes only.** CRUD uses `Route::resource()` / `Route::apiResource()`. A non-CRUD verb is its own resource with standard method names: cancelling an order is `POST cancelled-orders/{order}` → `CancelledOrderController@store`; undoing it would be `destroy`.
2. **Nested resources** use `->shallow()`, and `->scopeBindings()` wherever the parent stays in the URL.
3. **Bind by `public_id`** (`writing-models`), never by `id`.
4. **Web routes** group under `auth` (plus `verified` when the app uses email verification).
5. **Controllers** live in `app/Http/Controllers` (flat until ~15 files), extend the app's base `Controller`, and are **not `final`**, so tests can partially mock them.
6. **A controller method does three things:** receive (FormRequest, bound models, injected Action), call one model method or one Action, return a response.
7. **Never in a controller:** business logic, column writes, `save()` / `update()` / `create()`, `$request->validate()`, inline role or ownership checks, or query building beyond scopes, named query methods and `load()` / `with()` for display.
8. **Every input goes through a FormRequest** named `{Verb}{Noun}Request` after the resource method (`StoreCancelledOrderRequest`); never `$request->validate()`, even for one rule.
9. **`authorize()` calls the Policy, null-safe:** `return $this->user()?->can('cancel', $this->route('order')) ?? false;`. It never holds role or ownership logic itself.
10. **`rules()`** uses array syntax, `Rule::enum()` for enums (`writing-enums`), `exists` / `unique` scoped to the caller, and classes in `app/Rules` for reusable custom rules. Input keys are snake_case.
11. **Policies** (`app/Policies/{Model}Policy.php`) answer only "may this user do this?": ownership, membership, role. Each ability returns `bool` and asks the model for facts (`$order->isPlacedBy($user)`) instead of comparing columns. An ability that isn't defined is refused.

## Edge cases (decided)
- **A route without input** (show, export) has no FormRequest, so authorize it on the route: `->can('view', 'order')` on a single route, `->middlewareFor('show', 'can:view,order')` on a resource route (Laravel 12+; check that `middlewareFor` exists), or `HasMiddleware` on the controller. Pick one style per project.
- **A Policy never checks model state.** "Is this order still cancellable?" is the model's rule, which throws a business exception. **UI flags combine both:** `'can' => ['cancel' => $user->can('cancel', $order) && $order->isCancellable()]`. The server still checks both.
- **No Action needed** when the use case is one behavior method on one model: the controller calls it directly (`$order->writeGiftNote(...)`).
- **`toData()` on the FormRequest** only when the Action takes a Data object (`writing-data-objects`).
- **A custom action route** instead of a resource only when a resource would obscure the domain.
- **No new `Gate::before` super-admin bypass** without explicit approval.
- **Inertia page names** follow the project's existing pages (`Orders/Show` or `orders/show`); the starter kits differ.
- **Console commands and streamed exports** follow the same shape (one Action call, no logic): [references/commands.md](references/commands.md), [references/streamed-responses.md](references/streamed-responses.md). A command that changes or deletes data has `--dry-run`.

## Example
```php
// routes/web.php
Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::resource('orders', OrderController::class)->only(['index', 'show'])
        ->middlewareFor('show', 'can:view,order');
    Route::post('cancelled-orders/{order}', [CancelledOrderController::class, 'store'])->name('cancelled-orders.store');
});

// app/Http/Requests/StoreCancelledOrderRequest.php
class StoreCancelledOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('cancel', $this->route('order')) ?? false;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['reason' => ['required', Rule::enum(CancellationReason::class)]];
    }
}

// app/Http/Controllers/CancelledOrderController.php
class CancelledOrderController extends Controller
{
    public function store(StoreCancelledOrderRequest $request, Order $order, CancelOrder $cancelOrder): RedirectResponse
    {
        $cancelOrder->handle($order, $request->user(), CancellationReason::from($request->string('reason')->toString()));

        return to_route('orders.show', $order)->with('success', 'Order cancelled.');
    }
}

// app/Policies/OrderPolicy.php
class OrderPolicy
{
    public function cancel(User $user, Order $order): bool
    {
        return $order->isPlacedBy($user) || $user->isSupport();
    }
}
```

## Checklist
1. Resource routes only; non-CRUD verbs as their own resource; nested routes `shallow()` / `scopeBindings()`; bound by `public_id`.
2. Routes without input authorized on the route; routes with input through a `{Verb}{Noun}Request` whose `authorize()` calls the Policy.
3. Controller (not `final`): receive, one call, respond.
4. Policy checks permissions only, never state.
5. Feature test per route (the matrix in `writing-tests`); each Policy ability × (owner, other user, guest, role).
6. Verify: `php artisan test --compact --filter=CancelledOrder`, then Pint as Boost's guideline says, and `vendor/bin/phpstan analyse` if the project uses Larastan.
