<?php

namespace App\Policies;

use App\Models\StoredFile;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class StoredFilePolicy
{
    public function view(User $user, StoredFile $file): Response
    {
        return $file->user_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }
}
