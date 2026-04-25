<?php

namespace App\Policies;

use App\Models\User;

class BasePolicy
{
    /**
     * Create a new policy instance.
     */
    public function __construct()
    {
        //
    }


    protected function hasRole(User $user, array $roles)
    {
        return $user->hasAnyRole($roles);
    }

    protected function isSystem(User $user)
    {
        return $this->hasRole($user, [
            'super_admin','admin','system_admin'
        ]);
    }

    protected function isClubAdmin(User $user)
    {
        return $this->hasRole($user, [
            'club_owner','club_admin','club_manager','academy_manager'
        ]);
    }

    protected function isCoach(User $user)
    {
        return $this->hasRole($user, [
            'coach','assistant_coach','performance_coach','fitness_coach'
        ]);
    }

    protected function isPlayer(User $user)
    {
        return $this->hasRole($user, [
            'player','youth_player','guest_player'
        ]);
    }

    protected function inClub(User $user, $club)
    {
        return $club->users()->where('user_id', $user->id)->exists();
    }
}
