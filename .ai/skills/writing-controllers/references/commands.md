# Console commands

1. **Location:** `app/Console/Commands/{Name}Command.php`, signature `{area}:{verb}` (`orders:cancel-abandoned`).
2. **`handle()`** parses options, calls one Action (injected into `handle()`), prints the result with `$this->info()` / `$this->table()`, and returns `self::SUCCESS` or `self::FAILURE`.
3. A command that changes or deletes data has a `--dry-run` option and reports what it would do (`reviewing-releases` skill).

```php
class CancelAbandonedOrdersCommand extends Command
{
    protected $signature = 'orders:cancel-abandoned {--days=7} {--dry-run}';

    protected $description = 'Cancel pending orders that were never paid.';

    public function handle(CancelAbandonedOrders $cancelAbandoned): int
    {
        $result = $cancelAbandoned->handle(
            olderThan: now()->toImmutable()->subDays((int) $this->option('days')),
            dryRun: (bool) $this->option('dry-run'),
        );

        $this->info("{$result->count} orders ".($this->option('dry-run') ? 'would be' : 'were').' cancelled.');

        return self::SUCCESS;
    }
}
```

