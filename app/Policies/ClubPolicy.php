<?php

namespace App\Policies;

use App\Models\Club;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ClubPolicy
{

    public function viewAny(User $user)
    {
        return $user->clubs()->exists() || $this->isSystem($user);
    }

    public function view(User $user, Club $club)
    {
        return $this->isSystem($user) || $this->inClub($user, $club);
    }

    public function create(User $user)
    {
        return $this->isSystem($user);
    }

    public function update(User $user, Club $club)
    {
        return $this->isSystem($user)
            || ($this->isClubAdmin($user) && $this->inClub($user, $club));
    }

    public function delete(User $user, Club $club)
    {
        return $this->isSystem($user) || $user->hasRole('club_owner');
    }
}
