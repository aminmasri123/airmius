<?php

namespace App\Policies;

use App\Models\Folder;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class FolderPolicy extends BasePolicy
{
    public function viewAny(User $user)
    {
        return $this->isClubAdmin($user) || $this->isCoach($user);
    }

    public function create(User $user)
    {
        return $this->isClubAdmin($user) || $this->isCoach($user);
    }

    public function delete(User $user, Folder $folder)
    {
        return $this->isClubAdmin($user);
    }
}
