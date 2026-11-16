# Inertia Conventions (jboost, `routes/web.php`)

Use the `building-inertia-pages` skill to build or change a page or form.

## Controllers
- Use the same Actions and FormRequests as the API; only the output differs.
- GET renders a page; writes redirect (`to_route()` / `back()`) with a short flash message saying what happened, and never render a page.

## Props are public
- Everything in props reaches the browser: send only what the page needs, through a Resource or an explicit array, never a raw model or a secret.
- Keep shared props (`HandleInertiaRequests::share`) minimal and lazy (`fn () => …`), because they're sent on every request.

## UI and errors
- Show actions from `can` flags that combine the Policy and the model's state; the server still authorizes.
- Don't re-implement business rules in JS; at most mirror simple ones for UX.
- Business exceptions on writes become `back()` + an `error` flash in one place (`bootstrap/app.php`), not `try/catch` in controllers.
- Don't hand-craft error responses on web routes: Inertia uses 409 for asset-version mismatches.
- Dates arrive as ISO 8601 like the API and are formatted in the browser (`Intl.DateTimeFormat`, in the user's timezone); money (`formatted`) and enum labels come formatted from the server.
