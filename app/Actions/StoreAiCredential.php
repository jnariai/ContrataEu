<?php

namespace App\Actions;

use App\Enums\AiProvider;
use App\Models\AiCredential;
use App\Models\User;
use Illuminate\Database\DatabaseManager;
use InvalidArgumentException;

/** Adds a key for the user's own AI provider. The first key is always the active one; later ones when asked. */
class StoreAiCredential
{
    public function __construct(private DatabaseManager $db) {}

    public function handle(User $user, AiProvider $provider, string $apiKey, ?string $baseUrl, ?string $model, ?string $name, bool $activate): AiCredential
    {
        return $this->db->transaction(function () use ($user, $provider, $apiKey, $baseUrl, $model, $name, $activate): AiCredential {
            // Lock the owner so two keys saved at once can't both become the active one.
            User::query()->whereKey($user->id)->lockForUpdate()->first();

            $active = $user->aiCredentials()->where('is_default', true)->first();

            if ($active !== null && $activate) {
                $active->clearDefault();
            }

            $model = filled($model) ? $model : $provider->defaultModel();

            // The request requires a model when the provider has no suggestion; this keeps a direct caller honest.
            if ($model === null) {
                throw new InvalidArgumentException("{$provider->label()} keys need a model.");
            }

            return AiCredential::add($user, $provider, $apiKey, $baseUrl, $model, $name, $active === null || $activate);
        });
    }
}
