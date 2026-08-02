<?php

namespace App\Policies;

use App\Models\Club;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Auth\Access\Response;

class ClubPolicy extends BasePolicy
{

    public function viewAny(User $user)
    {
        return $user->clubs()->exists()
            || $user->can('clubs.view')
            || $user->can('teams.view')
            || $user->can('org.manage')
            || $this->hasFullAccess($user);
    }

    public function view(User $user, Club $club)
    {
        return $this->hasFullAccess($user)
            || $user->can('clubs.view')
            || $user->can('teams.view')
            || ($club->verification_status === 'verified' && $club->is_listed)
            || $this->inClub($user, $club);
    }

    public function create(User $user)
    {
        return $user->can('org.create')
            || $user->can('clubs.create')
            || $user->hasAnyRole(Roles::PLAYER)
            || $this->hasFullAccess($user);
    }

    public function update(User $user, Club $club)
    {
        return $this->managesClub($user, $club);
    }

    public function delete(User $user, Club $club)
    {
        return $this->hasFullAccess($user)
            || $club->owner_id === $user->id
            || $club->users()
                ->where('users.id', $user->id)
                ->wherePivot('role', 'owner')
                ->exists()
            || ($user->can('clubs.delete') && $this->managesClub($user, $club));
    }
}
