<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TeamPolicy extends BasePolicy
{
    public function viewAny(User $user)
    {
        return $user->teams()->exists()
            || $this->isCoach($user)
            || $this->isClubAdmin($user);
    }

    public function view(User $user, Team $team)
    {
        return $this->inClub($user, $team->club);
    }

    public function create(User $user)
    {
        return $user->can('team.create')
            || $this->isClubAdmin($user)
            || $this->isCoach($user);
    }

    public function update(User $user, Team $team)
    {
        return ($user->can('team.update') && $this->inClub($user, $team->club))
            || $this->isClubAdmin($user)
            || $this->isCoach($user)
            || $user->hasRole('team_manager');
    }

    public function delete(User $user, Team $team)
    {
        return ($user->can('team.delete') && $this->inClub($user, $team->club))
            || $this->isClubAdmin($user);
    }
}
