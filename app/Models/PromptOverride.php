<?php

namespace App\Models;

use App\Casts\LookupCast;
use App\Enums\PromptKey;
use Carbon\CarbonImmutable;
use Database\Factories\PromptOverrideFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The user's replacement for one default prompt section. Absent → the default file is used.
 *
 * @property int $id
 * @property int $user_id
 * @property int $key_id
 * @property PromptKey $key
 * @property string $content
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read User $user
 */
#[Fillable(['key', 'content'])]
class PromptOverride extends Model
{
    /** @use HasFactory<PromptOverrideFactory> */
    use HasFactory;

    public static function saveFor(User $user, PromptKey $key, string $content): self
    {
        $override = self::query()->whereBelongsTo($user)->forKey($key)->first() ?? new self(['key' => $key]);

        $override->fill(['content' => $content]);
        $override->user()->associate($user);
        $override->save();

        return $override;
    }

    /** The text a generation uses for this section: the user's override, or the app default. */
    public static function resolveFor(User $user, PromptKey $key): string
    {
        return self::query()->whereBelongsTo($user)->forKey($key)->value('content') ?? $key->defaultContent();
    }

    /** Back to the app's default: the override simply stops existing. */
    public static function resetFor(User $user, PromptKey $key): void
    {
        self::query()->whereBelongsTo($user)->forKey($key)->delete();
    }

    /** @param Builder<self> $query */
    #[Scope]
    protected function forKey(Builder $query, PromptKey $key): void
    {
        $query->where('key_id', LookupCast::idOf('prompt_keys', $key));
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'key' => LookupCast::of(PromptKey::class, 'prompt_keys', 'key_id'),
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
