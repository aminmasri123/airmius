<?php

namespace App\Policies;

use App\Models\Club;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ClubPolicy extends BasePolicy
{

    public function viewAny(User $user)
    {
        return $user->clubs()->exists()
            || $user->can('org.manage')
            || $this->isSystem($user);
    }

    public function view(User $user, Club $club)
    {
        return $this->isSystem($user) || $this->inClub($user, $club);
    }

    public function create(User $user)
    {
        return $user->can('org.create')
            || $this->isSystem($user);
    }

    public function update(User $user, Club $club)
    {
        return ($user->can('org.manage') && $this->inClub($user, $club))
            || $this->isSystem($user)
            || ($this->isClubAdmin($user) && $this->inClub($user, $club));
    }

    public function delete(User $user, Club $club)
    {
        return ($user->can('org.manage') && $this->inClub($user, $club))
            || $this->isSystem($user)
            || ($user->hasRole('club_owner') && $this->inClub($user, $club));
    }
}
