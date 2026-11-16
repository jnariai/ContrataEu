---
name: reviewing-security
description: "Use when touching authentication, authorization, permissions, file uploads or downloads, payments, webhooks, user-supplied URLs or HTML, secrets, sessions, CORS or security headers, or when reviewing a change for security. Also when adding raw SQL, redirects to user-provided targets, or shell commands. Not for tenant isolation (see scoping-tenants)."
---

# Reviewing Security

Go through every section the change touches. Report each finding as: `file:line` → risk → fix.

## Rules
1. **Authorization:** every route touching a resource authorizes it (FormRequest `authorize()`, `Gate::authorize()` or `can:` middleware); logged in isn't the same as allowed. Nested routes use `scopeBindings()`; `exists` / `unique` rules are scoped to the owner or tenant.
2. **Input:** only `validated()` / `safe()` data reaches models, through behavior methods. SQL uses bindings; sort, filter and column names come from an allow-list.
3. **Output:** routes, props and responses expose `public_id`, never the auto-increment `id`. Blade uses `{{ }}`; Inertia props carry no secrets or hidden fields.
4. **No `unserialize`, `eval`, `exec` or `shell_exec` on input**; use `Process` with an argument array when a command is unavoidable.
5. **Redirect targets** are named routes or come from a host allow-list (no open redirects).
6. **Files:** `File::types([...])->max(...)` checks real MIME types; storage is a private disk with generated names; downloads go through an authorized controller, `Storage::temporaryUrl()` or signed routes. No user uploads under `public/`.
7. **Outbound requests** have timeouts (`building-api-gateways`). User-supplied URLs: `https` only, resolved IP not private/loopback/link-local, redirects off or re-validated (SSRF).
8. **Secrets and data:** no secrets in code, tests, logs or exception messages. PII and secret columns use `encrypted` or `hashed` casts; secrets are compared with `hash_equals()`. Logs carry IDs, never tokens, passwords, card data or raw auth request bodies.
9. **Authentication:** `Password::defaults()`; login, register, reset and OTP use a strict `throttle:auth` limiter; the session is regenerated on login and invalidated on logout; cookies are `secure`, `http_only`, `same_site=lax` or stricter. Sanctum tokens have minimal abilities and an expiration, and are revoked on password change.
10. **Webhooks:** verify the signature with `hash_equals()` over the **raw** body, enforce a timestamp tolerance, and process idempotently on the provider event ID.
11. **Platform:** HTTPS forced ([references/app-hardening.md](references/app-hardening.md)), baseline security headers ([references/security-headers.md](references/security-headers.md)), a CSP for the web UI, CORS with explicit origins, trusted proxies configured explicitly, `APP_DEBUG=false` outside local.

## Edge cases (decided)
- **403 vs 404:** use `Response::deny('…')` for a helpful 403, and `denyAsNotFound()` where the resource's existence is itself sensitive.
- **`{!! !!}`** only for HTML sanitized (purified) before it's stored.
- **`Gate::before` super-admin bypass:** never add one without explicit approval.
- **Mass assignment:** an explicit fillable list, never `$guarded = []`, and `create()` / `update()` / `fill()` only inside the model's own methods, never with request input (`writing-models`).

## Example
A finding, as reported:
```text
app/Http/Controllers/InvoiceDownloadController.php:18 → any logged-in user can download any invoice by id (IDOR)
  → bind by public_id, add ->can('view', 'invoice') on the route, add a test that another user gets 403.
```

## Checklist
1. Every touched section above reviewed; findings reported as `file:line` → risk → fix.
2. A test proves another user gets 403/404 for each new resource route.
3. Verify: `php artisan test --compact`, `composer audit` and `npm audit --omit=dev` are clean.
