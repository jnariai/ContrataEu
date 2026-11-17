<?php

namespace App\Policies;

use App\Models\CvImport;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CvImportPolicy
{
    public function delete(User $user, CvImport $cvImport): Response
    {
        return $cvImport->user_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }
}
