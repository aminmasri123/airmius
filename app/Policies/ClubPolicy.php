<?php

namespace App\Policies;

use App\Models\Club;
use App\Models\User;
use App\Support\Roles;

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
        return Club::query()->visibleTo($user)->whereKey($club->id)->exists();
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
            || (int) $club->owner_id === (int) $user->id;
    }
}
