<?php

namespace App\Policies;

use App\Models\Ride;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class RidePolicy extends BasePolicy
{
    public function viewAny(User $user)
    {
        return $this->isPlayer($user);
    }

    public function create(User $user)
    {
        return $this->isPlayer($user);
    }

    public function update(User $user, Ride $ride)
    {
        return $ride->driver_id === $user->id;
    }

    public function delete(User $user, Ride $ride)
    {
        return $ride->driver_id === $user->id
            || $this->isClubAdmin($user);
    }

    public function join(User $user, Ride $ride)
    {
        return $this->isPlayer($user)
            && $this->canSeeRide($user, $ride)
            && $ride->users()->count() < (int) $ride->seats;
    }

    private function canSeeRide(User $user, Ride $ride): bool
    {
        if ((int) $ride->driver_id === (int) $user->id || $ride->users()->where('users.id', $user->id)->exists()) {
            return true;
        }

        return match ($ride->visibility) {
            'public' => true,
            'friends' => $ride->driver?->isFriendsWith($user) || $user->isFriendsWith($ride->driver),
            'club' => $ride->club_id && $user->clubs()->where('clubs.id', $ride->club_id)->exists(),
            'team' => $ride->team_id && $user->teams()->where('teams.id', $ride->team_id)->exists(),
            default => false,
        };
    }
}
