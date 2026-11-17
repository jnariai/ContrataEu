<?php

namespace App\Policies;

use App\Models\GeneratedAnswer;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class GeneratedAnswerPolicy
{
    public function update(User $user, GeneratedAnswer $answer): Response
    {
        $answer->loadMissing('generation.application');

        return $answer->generation->application->user_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }
}
