<?php

namespace App\Policies;

use App\Models\Generation;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class GenerationPolicy
{
    public function view(User $user, Generation $generation): Response
    {
        $generation->loadMissing('application');

        return $generation->application->user_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }
}
