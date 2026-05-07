<?php

namespace App\Policies;


use App\Models\User;
use App\Support\Roles;

class BasePolicy
{
    /**
     * Create a new policy instance.
     */

    public function __construct()
    {
        //
    }
    public function before($user, $ability)
    {
        if ($this->hasFullAccess($user)) {
            return true;
        }
    }

    protected function hasRole(User $user, array $roles)
    {
        return $user->hasAnyRole($roles);
    }

    protected function hasFullAccess(User $user)
    {
        return $user->hasAnyRole(Roles::FULL_ACCESS);
    }

    protected function isSystem(User $user)
    {
        return $user->hasAnyRole(Roles::SYSTEM);
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
        if (! $club) {
            return false;
        }

        return $club->users()->where('user_id', $user->id)->exists();
    }

    protected function managesClub(User $user, $club): bool
    {
        if (! $club) {
            return false;
        }

        return $this->hasFullAccess($user)
            || $club->owner_id === $user->id
            || $this->hasElevatedClubRole($user, $club);
    }

    protected function hasElevatedClubRole(User $user, $club): bool
    {
        if (! $club) {
            return false;
        }

        return $club->users()
            ->where('users.id', $user->id)
            ->wherePivotIn('role', ['owner', 'admin', 'manager', 'academy_manager'])
            ->exists();
    }

    protected function hasElevatedTeamRole(User $user, $team): bool
    {
        if (! $team) {
            return false;
        }

        return $team->users()
            ->where('users.id', $user->id)
            ->wherePivotIn('role', ['Coach', 'Captain'])
            ->exists();
    }

    protected function managesTeam(User $user, $team): bool
    {
        if (! $team) {
            return false;
        }

        return $this->managesClub($user, $team->club)
            || ($user->can('team.update') && $this->hasElevatedTeamRole($user, $team));
    }
}
