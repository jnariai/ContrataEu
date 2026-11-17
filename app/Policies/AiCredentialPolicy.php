<?php

namespace App\Policies;

use App\Models\AiCredential;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class AiCredentialPolicy
{
    public function update(User $user, AiCredential $credential): Response
    {
        return $this->owns($user, $credential);
    }

    public function delete(User $user, AiCredential $credential): Response
    {
        return $this->owns($user, $credential);
    }

    private function owns(User $user, AiCredential $credential): Response
    {
        return $credential->user_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }
}
