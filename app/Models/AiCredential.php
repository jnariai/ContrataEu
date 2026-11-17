<?php

namespace App\Models;

use App\Casts\LookupCast;
use App\Enums\AiProvider;
use App\Models\Concerns\HasPublicId;
use App\ValueObjects\MaskedApiKey;
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

    public static function add(User $user, AiProvider $provider, string $apiKey, string $model, bool $isDefault): self
    {
        $credential = new self([
            'provider' => $provider,
            'api_key' => trim($apiKey),
            'key_hint' => MaskedApiKey::fromPlain($apiKey)->hint,
            'model' => trim($model),
            'is_default' => $isDefault,
        ]);
        $credential->user()->associate($user);
        $credential->save();

        return $credential;
    }

    public function maskedKey(): MaskedApiKey
    {
        return MaskedApiKey::fromHint($this->key_hint);
    }

    /** A different model may not work with this key, so the old "Verified" no longer applies. */
    public function changeModel(string $model): void
    {
        $model = trim($model);

        if ($model === $this->model) {
            return;
        }

        $this->update(['model' => $model, 'last_verified_at' => null]);
    }

    public function markVerified(CarbonImmutable $at): void
    {
        $this->update(['last_verified_at' => $at]);
    }

    public function makeDefault(): void
    {
        $this->update(['is_default' => true]);
    }

    public function clearDefault(): void
    {
        $this->update(['is_default' => false]);
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
            'provider' => LookupCast::of(AiProvider::class, 'ai_providers', 'provider_id'),
            'api_key' => 'encrypted',
            'is_default' => 'boolean',
            'last_verified_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
