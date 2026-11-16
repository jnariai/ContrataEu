# Application Conventions (jboost)

Pragmatic, framework-first guidance: what to prefer, and why. Where this project deliberately does otherwise (its code or `.ai/rules`), follow the project. Load the named skill for the how-to.

## Mindset
- **Don't deploy bugs; stay framework-first and DDD-lite:** ask of every change how it could break production and what proves it won't. Prefer default folders, `make:` generators and Eloquent as both domain and persistence; avoid repositories, mappers, command buses, context folders and `Service` classes for business logic. Add a pattern only when a problem you have now needs it (the "ceremony we skip" table in `writing-actions`).

## Layers and placement
- **Four virtual layers:** layers classify code, they aren't folders: never create `app/Domain`, `app/Application` or layer namespaces; each class stays in Laravel's default folder. jboost adds `app/Actions`, `app/Datas`, `app/ValueObjects` and `app/Gateways/{Vendor}`; like any new base folder, ask before creating one the first time. → `writing-actions`
  - **Presentation** translates input and output: `routes`, `app/Http`, commands, mail, notifications, `resources/js`.
  - **Application** runs one use case: `app/Actions`, `app/Datas`, jobs, listeners.
  - **Domain** holds business state and rules: models, `app/Enums`, `app/ValueObjects`, policies, event classes, business exceptions.
  - **Infrastructure** is technical plumbing and makes no business decisions: providers, `app/Gateways/{Vendor}`, `app/Casts`, observers, `app/Models/Concerns`.
- Dependencies point inward: Presentation → Application → Domain. Infrastructure serves any layer but never decides business outcomes.
- **Where a rule goes:** one model's state → model behavior method or enum; spans models or must be atomic → Action; who may act → Policy; input shape → FormRequest; always true in the data → DB constraint.
- Presentation doesn't decide business outcomes: it asks the domain (`$order->isCancellable()`) or calls an Action.
- Actions and jobs don't touch HTTP (`request()`, `auth()`, `session()`, responses); the actor is passed in.
- The domain doesn't call Actions, gateways or HTTP, dispatch events, or import `App\Http`, `App\Actions` or `App\Datas`.
- Vendor SDKs and their exceptions stay in `app/Gateways/{Vendor}`, so a vendor swap touches one folder. → `building-api-gateways`
- Prefer injection; use a facade or `app()` only where injection isn't possible (models, enums, static contexts). Simple helpers (`collect()`, `now()`, `str()`, `route()`… examples, not a closed list) are fine anywhere and beat their classes.
- Controllers use only resource methods; a non-CRUD verb is its own resource (`POST cancelled-orders/{order}` → `CancelledOrderController@store`). → `writing-controllers`
- Only Data objects and value objects are `final` (`final readonly class`); everything else stays open, so tests can mock it and Laravel and packages can extend it.

## Actions and models
- An Action is a class in `app/Actions` with one `handle()`, typed inputs (actor passed in), returning a domain value, never a response; create one only when a use case needs it. → `writing-actions`
- Several writes go in one transaction; events and jobs run after commit; no HTTP calls or mail inside the transaction.
- Events are domain facts in the past tense (`OrderCancelled`); only Actions and jobs dispatch them, through an injected `Dispatcher`.
- Only a model writes its own state: others call a behavior method (`$order->cancel(...)`) or named constructor (`Order::place(...)`) that checks its rules and throws a business exception, never `fill`/`update`/`create`/`save` or attribute assignment (factories and seeders excepted). → `writing-models`
- Declare fillable columns as Boost says (the `#[Fillable]` attribute on Laravel 13+, otherwise `$fillable`), never `$guarded = []`. Mass assignment is still for the model's own methods only, so callers never do `update($request->validated())`.
- Keep Eloquent strict mode on outside production (`Model::shouldBeStrict(! app()->isProduction())`; production logs lazy loading instead of throwing) and fix every violation: it surfaces N+1s and attribute typos.
- Enums are string-backed in code; the DB stores an integer FK to a lookup table, never a DB `ENUM` column. → `writing-enums`
- Models in routes, props or responses expose a prefixed `public_id` (`ord_` + Base58 ULID), never the auto-increment `id`. → `writing-models`
- Return API Resources or explicit Inertia props, never raw models: `$hidden` isn't a contract.
- Dates leave the app (API, Inertia props, Data objects, exports) as ISO 8601: instants in UTC with seconds (`->toIso8601ZuluString()`, `2026-01-31T10:00:00Z`), calendar dates as `YYYY-MM-DD` (`->toDateString()`), never Unix timestamps or display strings; a calendar date is never converted to UTC.

## Errors
- A business-rule violation is a dedicated exception with a user-facing message and a stable `code`, rendered globally (API → problem+json, Inertia → flash), not caught in controllers. → `writing-actions`
- Error messages say what happened, why, and what to do next; no internals (SQL, class names, traces).
- Don't swallow exceptions: rethrow or `report()`.

## Security and performance
- Default deny: authorize every resource route with a Policy; logged in isn't the same as allowed. → `reviewing-security`
- No N+1 queries or unbounded `->get()` on growing tables; queue slow or external work; set timeouts on outbound HTTP. → `reviewing-performance`

## Tests
- Start a bug fix with a test that fails without the fix; if one isn't possible, say why. → `writing-tests`
- Cover each business rule with a test named after the rule (`it('does not cancel a shipped order')`), and change them together.
- For new behaviour, test success, each rule violation, authorization and validation.
- Don't delete, skip or loosen a test to make a change pass: a failing test needs a deliberate decision, so ask or explain.

## Finishing
- Run the narrowest set of tests that covers the change after your last edit; the full suite runs before merging (`reviewing-releases`). Don't claim untested work.
- Report which tests ran, any test you changed and why, and what wasn't verified.
- Risky changes (migrations, money, auth, queues, API contracts) stay backward compatible, ship behind a flag when needed, and have a rollback path. → `reviewing-releases`
- Use `__()` for user-facing strings only when the project is localized (`lang/` exists or you're asked).
