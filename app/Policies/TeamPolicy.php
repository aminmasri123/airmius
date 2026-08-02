<?php

namespace App\Policies;

use App\Models\Club;
use App\Models\Team;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TeamPolicy extends BasePolicy
{
    public function viewAny(User $user)
    {
        return $user->teams()->exists()
            || $user->clubs()->exists()
            || $user->teamInvitations()->where('status', 'pending')->exists()
            || $user->can('create', Club::class)
            || $user->can('teams.view')
            || $user->can('clubs.view')
            || $this->hasFullAccess($user);
    }

    public function view(User $user, Team $team)
    {
        return $this->hasFullAccess($user)
            || $user->can('teams.view')
            || $team->users()->where('users.id', $user->id)->exists()
            || $this->inClub($user, $team->club);
    }

    public function create(User $user)
    {
        return $user->can('team.create')
            || $user->can('teams.create');
    }

    public function update(User $user, Team $team)
    {
        return $this->managesTeam($user, $team);
    }

    public function invite(User $user, Team $team)
    {
        return $this->managesClub($user, $team->club)
            || $this->hasElevatedTeamRole($user, $team)
            || (($user->can('team.invite') || $user->can('teams.manage_players'))
                && $this->managesTeam($user, $team));
    }

    public function delete(User $user, Team $team)
    {
        return $this->managesClub($user, $team->club);
    }
}
