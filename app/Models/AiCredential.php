<?php

namespace App\Models;

use App\Casts\LookupCast;
use App\Enums\AiProvider;
use App\Models\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\AiCredentialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The user's own AI provider key (BYOK). The key is encrypted at rest; only `key_hint` (last 4 chars) is ever shown.
 *
 * @property int $id
 * @property string $public_id
 * @property int $user_id
 * @property int $provider_id
 * @property AiProvider $provider
 * @property string $api_key
 * @property string $key_hint
 * @property string $model
 * @property bool $is_default
 * @property CarbonImmutable|null $last_verified_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read User $user
 */
#[Fillable(['provider', 'api_key', 'key_hint', 'model', 'is_default', 'last_verified_at'])]
#[Hidden(['api_key'])]
class AiCredential extends Model
{
    /** @use HasFactory<AiCredentialFactory> */
    use HasFactory;

    use HasPublicId;

    public const string PUBLIC_ID_PREFIX = 'key_';

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'provider' => LookupCast::of(AiProvider::class, 'ai_providers', 'provider_id'),
            'api_key' => 'encrypted',
            'is_default' => 'boolean',
            'last_verified_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
