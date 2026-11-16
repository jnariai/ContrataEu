---
name: reviewing-releases
description: "Use before merging or deploying any non-trivial change, and always for migrations, money, auth, queued jobs, cache structures, config/env or API contract changes. Also when renaming a job or listener parameter, planning a feature flag or rollback, or writing a PR description. Not for choosing which tests to write (see writing-tests)."
---

# Reviewing Releases

Ask of every change: **how could this break production, and what proves it won't?** Write the answers in the PR description.

## Rules
1. **Blast radius:** name the users, data and money flows touched, and the endpoints, pages, jobs and scheduled commands. Say what happens if it fails halfway (→ transaction, idempotency).
2. **Old and new code run side by side during a deploy.** The new schema works with the *old* code still running: expand → migrate → contract (`changing-the-database`).
3. **Queued payloads:** don't rename or remove constructor parameters of queued jobs, listeners, notifications or mailables in one step; add new optional ones, deploy, then clean up.
4. **API clients:** within a version, no removed or renamed fields, no type changes, no new required inputs, no changed error `code`s. Additive only, or ship `v2`.
5. **Cache:** a changed cached structure gets a new key version (`…:v3`), so new code never reads old entries.
6. **Config/env:** new env vars are set in every environment *before* the code that needs them deploys, with safe defaults in `config/`.
7. **Rollback:** every deploy has one. Code rollback is safe because migrations are backward compatible; data changes have a documented reverse step.
8. **Before merging, run the project's checks:** Pint as Boost's guideline says, `vendor/bin/phpstan analyse` (Larastan), `php artisan test --compact --parallel`, `vendor/bin/pest --mutate --parallel` on classes that declare `mutates()`, and `composer audit`, as far as the project has them.

## Edge cases (decided)
- **Risky behaviour** ships behind a feature flag (Laravel Pennant), off by default, rolled out gradually, with the old path still tested.
- **Destructive or bulk data changes** run as a queued, chunked, idempotent command with `--dry-run`, tested on a production-like copy first, with a backup/restore path.
- **Migrations on large tables:** test against realistic data volume and understand the locking behaviour.
- **Error paths:** the user sees an actionable message and the logs carry a request/trace ID ([references/request-id.md](references/request-id.md) if the project has none).
- **If anything is off after deploy:** flip the flag off or roll back first, investigate second.

## Example
A PR description section:
```markdown
## Release safety
- Blast radius: order cancellation (web + API v1), refund listener, `orders:cancel-abandoned`.
- Halfway failure: cancel + event dispatch in one transaction; refund listener is idempotent on order id.
- Backward compatible: new nullable `cancelled_by_id`; old code ignores it. No job payload changes.
- Flag: `cancel-by-support` (Pennant), off by default.
- Rollback: revert the deploy; the column stays nullable and unused.
- Not verified: refund provider sandbox was down; covered by Http::fake tests only.
```

## Checklist
1. Regression test for every fixed bug; tests for every new or changed rule (`writing-tests`). Nothing skipped, no tests weakened.
2. Schema, queued payloads, API contract, cache keys and env vars are all backward compatible.
3. Flag and rollback plan written down for risky changes.
4. The PR lists what was **not** verified and why.
5. After deploy: watch the error tracker, Boost's **Last Error** / logs, `php artisan queue:failed` and the key metrics for the touched flows; smoke-test the main path.
6. Verify: run the checks in rule 8 and paste which ran into the PR.
