<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class EventPolicy extends BasePolicy
{
    public function viewAny(User $user)
    {
        return $user->teams()->exists()
            || $user->clubs()->exists()
            || $user->can('event.join')
            || $user->can('event.create')
            || $user->can('event.update');
    }

    public function view(User $user, Event $event)
    {
        if ($event->visibility === 'public') {
            return true;
        }

        if ($event->visibility === 'private' && $event->team_id) {
            return $event->team->users()->where('users.id', $user->id)->exists();
        }

        $club = $event->resolvedClub();

        return $club && $this->inClub($user, $club);
    }

    public function create(User $user)
    {
        return $user->can('event.create')
            || $this->isCoach($user)
            || $this->isClubAdmin($user);
    }

    public function update(User $user, Event $event)
    {
        $club = $event->resolvedClub();

        return ($club && $user->can('event.update') && $this->inClub($user, $club))
            || $this->isCoach($user)
            || $this->isClubAdmin($user);
    }

    public function delete(User $user, Event $event)
    {
        $club = $event->resolvedClub();

        return ($club && $user->can('event.delete') && $this->inClub($user, $club))
            || $this->isClubAdmin($user);
    }

    public function join(User $user, ?Event $event = null)
    {
        return $user->can('event.join')
            || $this->isPlayer($user);
    }
}
