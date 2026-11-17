<?php

namespace App\Actions;

use App\Models\AiCredential;
use Illuminate\Database\DatabaseManager;

/** Deleting the default key hands the default to the most recently added remaining key. */
class DeleteAiCredential
{
    public function __construct(private DatabaseManager $db) {}

    public function handle(AiCredential $credential): void
    {
        $this->db->transaction(function () use ($credential): void {
            $keys = AiCredential::query()->where('user_id', $credential->user_id)->lockForUpdate()->latest('id')->get();
            $deleted = $keys->firstOrFail(fn (AiCredential $key): bool => $key->is($credential));

            $deleted->delete();

            if ($deleted->is_default) {
                $keys->first(fn (AiCredential $key): bool => ! $key->is($deleted))?->makeDefault();
            }
        });
    }
}
