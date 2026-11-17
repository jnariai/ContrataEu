<?php

namespace App\Policies;

use App\Models\GeneratedDocument;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class GeneratedDocumentPolicy
{
    public function view(User $user, GeneratedDocument $document): Response
    {
        return $this->owns($user, $document);
    }

    public function update(User $user, GeneratedDocument $document): Response
    {
        return $this->owns($user, $document);
    }

    private function owns(User $user, GeneratedDocument $document): Response
    {
        $document->loadMissing('generation.application');

        return $document->generation->application->user_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }
}
