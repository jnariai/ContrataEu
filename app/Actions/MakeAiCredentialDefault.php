<?php

namespace App\Actions;

use App\Models\AiCredential;
use Illuminate\Database\DatabaseManager;

/** Generations use the default key. Exactly one key per user is the default. */
class MakeAiCredentialDefault
{
    public function __construct(private DatabaseManager $db) {}

    public function handle(AiCredential $credential): void
    {
        $this->db->transaction(function () use ($credential): void {
            $keys = AiCredential::query()->where('user_id', $credential->user_id)->lockForUpdate()->get();

            $keys->where('is_default', true)->reject(fn (AiCredential $key): bool => $key->is($credential))
                ->each(fn (AiCredential $key) => $key->clearDefault());

            $keys->firstOrFail(fn (AiCredential $key): bool => $key->is($credential))->makeDefault();
        });
    }
}
