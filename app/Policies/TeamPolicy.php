<?php

namespace App\Policies;

use App\Models\Club;
use App\Models\Team;
use App\Models\User;
use App\Support\ClubPermissions;

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
        return ClubPermissions::allowsForTeam($team, $user, ClubPermissions::TEAMS_EDIT)
            || (! ClubPermissions::explicitlyDenies($team->club, $user, ClubPermissions::TEAMS_EDIT)
                && $user->can('team.update')
                && $this->hasElevatedTeamRole($user, $team));
    }

    public function invite(User $user, Team $team)
    {
        return $this->manageMembers($user, $team);
    }

    public function manageMembers(User $user, Team $team): bool
    {
        return ClubPermissions::allowsForTeam($team, $user, ClubPermissions::MEMBERS_APPROVE)
            || (! ClubPermissions::explicitlyDenies($team->club, $user, ClubPermissions::MEMBERS_APPROVE)
                && ($this->hasElevatedTeamRole($user, $team)
                    || (($user->can('team.invite') || $user->can('teams.manage_players'))
                        && $this->managesTeam($user, $team))));
    }

    public function updateMemberRole(User $user, Team $team): bool
    {
        return ClubPermissions::allowsForTeam($team, $user, ClubPermissions::MEMBERS_ROLES)
            || (! ClubPermissions::explicitlyDenies($team->club, $user, ClubPermissions::MEMBERS_ROLES)
                && $this->managesTeam($user, $team));
    }

    public function removeMember(User $user, Team $team): bool
    {
        return ClubPermissions::allowsForTeam($team, $user, ClubPermissions::MEMBERS_DELETE)
            || (! ClubPermissions::explicitlyDenies($team->club, $user, ClubPermissions::MEMBERS_DELETE)
                && $user->can('team.kick') && $this->managesTeam($user, $team));
    }

    public function delete(User $user, Team $team)
    {
        return ClubPermissions::allowsForTeam($team, $user, ClubPermissions::TEAMS_DELETE);
    }
}
