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

    public function join(User $user)
    {
        return $this->isPlayer($user);
    }
}
