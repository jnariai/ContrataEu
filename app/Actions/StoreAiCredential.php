<?php

namespace App\Actions;

use App\Enums\AiProvider;
use App\Models\AiCredential;
use App\Models\User;
use Illuminate\Database\DatabaseManager;

/** Adds a key for the user's own AI provider. The first key is always the active one; later ones when asked. */
class StoreAiCredential
{
    public function __construct(private DatabaseManager $db) {}

    public function handle(User $user, AiProvider $provider, string $apiKey, ?string $model, ?string $name, bool $activate): AiCredential
    {
        return $this->db->transaction(function () use ($user, $provider, $apiKey, $model, $name, $activate): AiCredential {
            // Lock the owner so two keys saved at once can't both become the active one.
            User::query()->whereKey($user->id)->lockForUpdate()->first();

            $active = $user->aiCredentials()->where('is_default', true)->first();

            if ($active !== null && $activate) {
                $active->clearDefault();
            }

            return AiCredential::add($user, $provider, $apiKey, filled($model) ? $model : $provider->defaultModel(), $name, $active === null || $activate);
        });
    }
}
