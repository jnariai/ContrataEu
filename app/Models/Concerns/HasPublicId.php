<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LogicException;

/**
 * Keeps the auto-increment primary key internal and exposes "{prefix}{Base58 ULID}" as the public identifier.
 * The model declares: public const string PUBLIC_ID_PREFIX = 'app_';
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
     */
    protected function isValidUniqueId(mixed $value): bool
    {
        return is_string($value)
            && preg_match('/^'.preg_quote(static::PUBLIC_ID_PREFIX, '/').'[1-9A-HJ-NP-Za-km-z]{22}$/', $value) === 1;
    }
}
