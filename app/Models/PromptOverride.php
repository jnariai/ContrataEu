<?php

namespace App\Models;

use App\Casts\LookupCast;
use App\Enums\PromptKey;
use Carbon\CarbonImmutable;
use Database\Factories\PromptOverrideFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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
