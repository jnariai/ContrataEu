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
 * A user can keep many keys; exactly one is active (`is_default`), and generations and CV imports use that one.
 *
 * @property int $id
 * @property string $public_id
 * @property int $user_id
 * @property int $provider_id
 * @property AiProvider $provider
 * @property string|null $name optional, so several keys for the same provider can be told apart
 * @property string $api_key
 * @property string|null $base_url only for an OpenAI-compatible key: the server's address, such as https://api.orcarouter.ai/v1
 * @property string $key_hint
 * @property string $model
 * @property bool $is_default
 * @property CarbonImmutable|null $last_verified_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read User $user
 */
#[Fillable(['provider', 'name', 'api_key', 'base_url', 'key_hint', 'model', 'is_default', 'last_verified_at'])]
#[Hidden(['api_key'])]
class AiCredential extends Model
{
    /** @use HasFactory<AiCredentialFactory> */
    use HasFactory;

    use HasPublicId;

    public const string PUBLIC_ID_PREFIX = 'key_';

    public static function add(User $user, AiProvider $provider, string $apiKey, ?string $baseUrl, string $model, ?string $name, bool $isDefault): self
    {
        $credential = new self([
            'provider' => $provider,
            'name' => self::cleanName($name),
            'api_key' => trim($apiKey),
            'base_url' => $provider->needsBaseUrl() ? self::cleanBaseUrl((string) $baseUrl) : null,
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

    /** Another server may not accept this key or model, so the old "Verified" no longer applies. */
    public function changeBaseUrl(string $baseUrl): void
    {
        $baseUrl = self::cleanBaseUrl($baseUrl);

        if (! $this->provider->needsBaseUrl() || $baseUrl === $this->base_url) {
            return;
        }

        $this->update(['base_url' => $baseUrl, 'last_verified_at' => null]);
    }

    /**
     * The address up to the API version, as providers document it. A pasted endpoint ("…/v1/chat/completions") is
     * cut back to it, because the calls add that path themselves.
     */
    public static function cleanBaseUrl(string $baseUrl): string
    {
        $baseUrl = rtrim(trim($baseUrl), '/');

        return rtrim((string) preg_replace('#/chat/completions$#i', '', $baseUrl), '/');
    }

    public function rename(?string $name): void
    {
        $this->update(['name' => self::cleanName($name)]);
    }

    /**
     * The key's name, or when it has none its provider (an OpenAI-compatible key's server host, which says more than
     * "OpenAI-compatible"): how the key is shown in lists and switchers.
     */
    public function displayName(): string
    {
        return $this->name ?? $this->host() ?? $this->provider->label();
    }

    /** The server an OpenAI-compatible key calls, such as "api.orcarouter.ai". */
    public function host(): ?string
    {
        $host = $this->base_url === null ? null : parse_url($this->base_url, PHP_URL_HOST);

        return is_string($host) ? $host : null;
    }

    private static function cleanName(?string $name): ?string
    {
        $name = trim((string) $name);

        return $name === '' ? null : $name;
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
