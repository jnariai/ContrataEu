<?php

namespace App\Casts;

use BackedEnum;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Maps an enum attribute to the integer id of its lookup table. Code only ever sees the enum.
 *
 *     'status' => LookupCast::of(ApplicationStatus::class, 'application_statuses', 'status_id'),
 *
 * The DB facade is used because Eloquent builds casts itself, so nothing can be injected.
 *
 * @implements CastsAttributes<BackedEnum|null, BackedEnum|null>
 */
class LookupCast implements CastsAttributes
{
    /** @var array<string, array<string, int>> value => id, per table */
    private static array $ids = [];

    /** @param class-string<BackedEnum> $enum */
    public function __construct(
        private readonly string $enum,
        private readonly string $table,
        private readonly string $column,
    ) {}

    /** @param class-string<BackedEnum> $enum */
    public static function of(string $enum, string $table, string $column): string
    {
        return self::class.':'.$enum.','.$table.','.$column;
    }

    public static function idOf(string $table, BackedEnum $case): int
    {
        return self::ids($table)[(string) $case->value]
            ?? throw new InvalidArgumentException("[{$case->value}] has no row in [{$table}]: add it in a migration.");
    }

    /**
     * @template T of BackedEnum
     *
     * @param  class-string<T>  $enum
     * @return T
     */
    public static function caseOf(string $enum, string $table, int $id): BackedEnum
    {
        $value = array_search($id, self::ids($table), true);

        if ($value === false) {
            throw new InvalidArgumentException("Id [{$id}] doesn't exist in [{$table}].");
        }

        return $enum::from((string) $value);
    }

    /** @param array<string, mixed> $attributes */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?BackedEnum
    {
        $id = $attributes[$this->column] ?? null;

        return $id === null ? null : self::caseOf($this->enum, $this->table, (int) $id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, int|null>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value !== null && ! $value instanceof $this->enum) {
            throw new InvalidArgumentException("[{$key}] must be set with a {$this->enum} case.");
        }

        return [$this->column => $value === null ? null : self::idOf($this->table, $value)];
    }

    /** @return array<string, int> */
    private static function ids(string $table): array
    {
        return self::$ids[$table] ??= DB::table($table)->pluck('id', 'value')->map(static fn (mixed $id): int => (int) $id)->all();
    }
}
