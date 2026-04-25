<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class EventPolicy extends BasePolicy
{
    public function viewAny(User $user)
    {
        return $user->teams()->exists();
    }

    public function view(User $user, Event $event)
    {
        return $this->inClub($user, $event->team->club);
    }

    public function create(User $user)
    {
        return $this->isCoach($user) || $this->isClubAdmin($user);
    }

    public function update(User $user, Event $event)
    {
        return $this->isCoach($user) || $this->isClubAdmin($user);
    }

    public function delete(User $user, Event $event)
    {
        return $this->isClubAdmin($user);
    }

    public function join(User $user)
    {
        return $this->isPlayer($user);
    }
}
