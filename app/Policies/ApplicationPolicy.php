<?php

namespace App\Policies;

use App\Models\Application;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/** Applications are private to their owner; anyone else gets a 404, so ids reveal nothing. */
class ApplicationPolicy
{
    public function view(User $user, Application $application): Response
    {
        return $this->owns($user, $application);
    }

    public function update(User $user, Application $application): Response
    {
        return $this->owns($user, $application);
    }

    public function delete(User $user, Application $application): Response
    {
        return $this->owns($user, $application);
    }

    /** Starting a generation: owner only. Business preconditions (master CV, credential, nothing running) are checked by the Action. */
    public function generate(User $user, Application $application): Response
    {
        return $this->owns($user, $application);
    }

    private function owns(User $user, Application $application): Response
    {
        return $application->user_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }
}
