<?php

namespace App\Actions;

use App\Enums\AiProvider;
use App\Models\AiCredential;
use App\Models\User;
use Illuminate\Database\DatabaseManager;

/** Adds a key for the user's own AI provider. The first key becomes the default one. */
class StoreAiCredential
{
    public function __construct(private DatabaseManager $db) {}

    public function handle(User $user, AiProvider $provider, string $apiKey, ?string $model): AiCredential
    {
        return $this->db->transaction(function () use ($user, $provider, $apiKey, $model): AiCredential {
            // Lock the owner so two first keys saved at once can't both become the default.
            User::query()->whereKey($user->id)->lockForUpdate()->first();

            $isFirst = ! $user->aiCredentials()->where('is_default', true)->exists();

            return AiCredential::add($user, $provider, $apiKey, filled($model) ? $model : $provider->defaultModel(), $isFirst);
        });
    }
}
