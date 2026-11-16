---
name: writing-actions
description: "Use when adding or changing a business write or use case in app/Actions, a business exception in app/Exceptions, or a domain event in app/Events. Also when deciding where a rule or new class belongs, whether a pattern (Action, Data object, interface, event, subfolder) is worth adding, or when a write needs a transaction, lock or idempotency. Not for the model's own behavior methods (see writing-models)."
---

# Writing Actions

## Rules
1. **Four virtual layers:** a classification, not folders. Never create layer folders or namespaces (`app/Domain`, `app/Application`); the table says which layer each default folder belongs to. Dependencies point inward: Presentation → Application → Domain; Infrastructure serves any layer but never decides business outcomes.

| Layer | Its job | Folders |
|---|---|---|
| **Presentation** | Translate input and output: authenticate, authorize, validate, call the model or a use case, shape the response | `routes/`, `app/Http/*`, `app/Console/Commands`, `app/Mail`, `app/Notifications`, `resources/js` |
| **Application** | Run one use case: load, call model behavior, transaction, dispatch events and jobs | `app/Actions`, `app/Datas`, `app/Jobs`, `app/Listeners` |
| **Domain** | Business state and rules; models change only through their own methods | `app/Models`, `app/Enums`, `app/ValueObjects`, `app/Policies`, `app/Events`, `app/Exceptions` (business) |
| **Infrastructure** | Technical plumbing and external systems; no business decisions | `app/Providers`, `app/Gateways/{Vendor}`, `app/Casts`, `app/Observers`, `app/Models/Concerns`, `config/`, `database/` |

2. **Ceremony we skip until the trigger is real:**

| Pattern | Introduce only when |
|---|---|
| Repository | Never for the database: use Eloquent |
| Interface + implementation | It wraps an external system you must fake or swap |
| Data object (`app/Datas`) | More than ~3 inputs, nested data, or several callers (`writing-data-objects`) |
| Value object | The value has rules or units (Money), or is easily confused (`writing-value-objects`) |
| Action | See rule 3 |
| Query Action | A complex aggregate read (dashboard, report); returns a Data object |
| Event + listener | The side effect is async, optional, or has several consumers |
| Subfolders (`Actions/Orders/`) | A folder passes ~15 files |

3. **Create an Action only when at least one is true:** it writes more than one model or table; it needs a transaction across models or a row lock; it needs an injected service (mailer, gateway, dispatcher, queue); it's a complex aggregate read (a query Action); or the same use case runs from more than one entry point. Otherwise the controller calls the model's behavior method. Never create an Action that only forwards one call.
4. **Shape:** a plain class in `app/Actions` (`php artisan make:class Actions/CancelOrder`), named Verb + Noun with no `Action` suffix, with one public `handle()` and constructor-injected `private` dependencies. **Not `final`, not `readonly`**, so tests can `$this->mock()` or spy on it.
5. **`handle()` receives** models, Data objects, value objects, enums, scalars and `CarbonImmutable` (the actor as a model), never a `Request`. It **returns** a model, Data object, scalar or `void`, never a response.
6. **No HTTP inside:** no `Request`, `request()`, `auth()`, `session()`, `response()`, `redirect()` or `Inertia`. **No authorization inside** either: the FormRequest or route middleware already decided who may act; the models decide whether the state allows it.
7. **The Action orchestrates; the rules stay in the models** it calls through behavior methods and named constructors (`writing-models`). It passes the current time in (`now()->toImmutable()`).
8. **Consistency:** several writes in one `transaction`; `lockForUpdate()` (re-read inside the transaction) for check-then-act on contended rows; no HTTP calls, mail or uploads inside the transaction.
9. **Side effects after commit:** events implement `ShouldDispatchAfterCommit`; queued listeners and jobs implement `ShouldQueueAfterCommit`.
10. **Events** are business facts: past tense (`OrderCancelled`), in `app/Events`, carrying the model with `SerializesModels`. Only Actions and jobs dispatch them, through an injected `Dispatcher`; models never do.
11. **Business exceptions** extend the project's base business exception (or [references/business-rule-exception.md](references/business-rule-exception.md) if it has none), flat in `app/Exceptions`, with one named constructor per reason, a user-facing message and a stable `code`. Never `\Exception`, `\RuntimeException` or `abort()` for a business rule.

## Edge cases (decided)
- **Injection:** constructor injection in Actions, jobs, listeners and gateways; method injection in controllers and commands. A facade or `app()` only where injection isn't available (models, enums, value objects, static contexts, closures in routes and config). Tests use facades freely. Inject contracts: `DatabaseManager`, `Contracts\Events\Dispatcher`, `Contracts\Cache\Repository`, `Http\Client\Factory`, `Illuminate\Config\Repository` (the concrete class, for its typed getters).
- **Helpers** are fine anywhere and beat their classes: `collect()` over `Collection::make()`, `str()` over `Str::of()`, `now()` over `Carbon::now()`. These are examples, not a closed list: `today()`, `__()`, `route()`, `to_route()`, `back()`, `filled()`, `blank()`, `tap()` and other value or URL helpers are fine too.
- **A single write** doesn't need a transaction.
- **Conditional update instead of a lock:** inside a model method, `static::whereKey($this->id)->where('status', $from)->update([...]) === 1`.
- **Uniqueness** is a unique index. Catch `UniqueConstraintViolationException` and turn it into a business exception, because validation-only uniqueness checks race.
- **Idempotency:** retried or externally triggered writes (webhooks, payment callbacks, client retries) store the provider event ID or `Idempotency-Key` under a unique index and return the first result on a replay.
- **Query Actions** are named `Get{Noun}` / `List{Nouns}` and always return a Data object. Simple or reused reads stay on the model as scopes (`writing-models`).
- **Catch an exception only** where the use case has a real fallback, or at a boundary to translate a framework or vendor exception into your own. Never catch to log and rethrow: the global handler renders and reports it.
- **Exception status** defaults to 409; pass `status:` for others (402, 423…). Group into `Exceptions/Orders/` only once the folder passes ~15 files.
- **Use events** for side effects that are async, optional or have several consumers, not to hide the main flow: the Action reads top to bottom.
- **Data objects** are unpacked by the Action; models never receive them.

## Example
```php
namespace App\Actions;

class CancelOrder
{
    public function __construct(
        private DatabaseManager $db,
        private Dispatcher $events,
    ) {}

    public function handle(Order $order, User $actor, CancellationReason $reason): Order
    {
        return $this->db->transaction(function () use ($order, $actor, $reason): Order {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            $order->cancel($actor, $reason, now()->toImmutable());

            $this->events->dispatch(new OrderCancelled($order));

            return $order;
        });
    }
}

namespace App\Exceptions;

class OrderCannotBeCancelled extends BusinessRuleException
{
    public static function fromStatus(OrderStatus $status): self
    {
        return new self(
            message: "This order can't be cancelled because it is {$status->label()}.",
            errorCode: 'order.cannot_be_cancelled',
            meta: ['current_status' => $status->value],
            logContext: ['status' => $status->value],
        );
    }
}
```

The Action re-reads the order under lock, lets the model check its rules, and dispatches an event that implements `ShouldDispatchAfterCommit`. In the exception, `meta` is rendered to API clients and `logContext` goes only to the logs; wrap the message in `__()` only in localized projects.

## Checklist
1. At least one rule 3 reason applies; otherwise the controller calls the model.
2. Plain class (not `final`/`readonly`), Verb + Noun, one public `handle()`, injected dependencies.
3. No `Request`, HTTP helpers or authorization inside; no column writes or `save()`.
4. Transaction for several writes; lock for contended check-then-act; idempotent when retried.
5. Events after commit, dispatched through the injected `Dispatcher`.
6. Business exceptions: one named constructor per reason, a stable `code`.
7. A separate Action test when it has more than one entry point or logic beyond one model call (locks, idempotency, events, several models, a gateway): success, each business exception (assert `errorCode`), `Event::fake([OrderCancelled::class])` and idempotency, calling `handle()` on the resolved Action. Otherwise the model and feature tests cover it.
8. Verify: `php artisan test --compact --filter=CancelOrder`, then Pint as Boost's guideline says, and `vendor/bin/phpstan analyse` if the project uses Larastan.
