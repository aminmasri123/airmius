<?php

namespace App\Policies;

use App\Models\File;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class FilePolicy extends BasePolicy
{
    public function viewAny(User $user)
    {
        return $this->isClubAdmin($user) || $this->isCoach($user);
    }

    public function view(User $user, File $file)
    {
        return $this->inClub($user, $file->club);
    }

    public function upload(User $user)
    {
        return $this->isClubAdmin($user)
            || $this->isCoach($user)
            || $this->hasRole($user, ['media_manager']);
    }

    public function delete(User $user, File $file)
    {
        return $file->user_id === $user->id
            || $this->isClubAdmin($user);
    }
}
