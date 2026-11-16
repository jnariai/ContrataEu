# HasPublicId trait

A public ID is a short **type prefix** plus a **Base58 ULID**: `ord_6CyQq2Y8aQ1xZ3bW9hKt4m`, `cus_…`, `pay_…`. The prefix tells a human or a log reader what the ID points to; the ULID is unguessable and sortable.
- Each model declares `public const string PUBLIC_ID_PREFIX = 'ord_';`: 2–4 lowercase letters and `_`, unique across the app, never changed once IDs exist.
- The ID is set on create and never changes afterwards.
```php
<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LogicException;

/**
 * Keeps the auto-increment primary key internal and exposes "{prefix}{Base58 ULID}" as the public identifier.
 * The model declares: public const string PUBLIC_ID_PREFIX = 'ord_';
 */
trait HasPublicId
{
    use HasUlids;

    public static function bootHasPublicId(): void
    {
        static::updating(static function (Model $model): void {
            if ($model->isDirty('public_id')) {
                throw new LogicException('A public_id never changes once it exists.');
            }
        });
    }

    public function newUniqueId(): string
    {
        return static::PUBLIC_ID_PREFIX.Str::ulid()->toBase58();
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * Route binding rejects anything that isn't this model's prefix plus a 22-character Base58 ULID, before querying (404, no query).
     * Required: HasUlids' default check expects a bare 26-character ULID and would reject every prefixed key.
     */
    protected function isValidUniqueId(mixed $value): bool
    {
        return is_string($value)
            && preg_match('/^'.preg_quote(static::PUBLIC_ID_PREFIX, '/').'[1-9A-HJ-NP-Za-km-z]{22}$/', $value) === 1;
    }
}
```
- Because `public_id` isn't the primary key, `HasUlids` leaves `id` incrementing and only fills `public_id` on create.
- Column: `$table->string('public_id', 40)->unique();` Base58 is case-sensitive: on MySQL/MariaDB add `->collation('ascii_bin')`; PostgreSQL and SQLite already compare case-sensitively.
- `exists` rules, lookups and Resources use `public_id`: `Rule::exists('addresses', 'public_id')`, `'id' => $this->public_id`.

