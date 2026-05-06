<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class EventPolicy extends BasePolicy
{
    public function viewAny(User $user)
    {
        return true;
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
        $allowed = $user->can('event.create')
            || $this->isCoach($user)
            || $this->isClubAdmin($user);

        return $allowed
            ? Response::allow()
            : Response::deny('Um Events zu erstellen, brauchst du ein Paket oder eine Rolle mit Event-Erstellung. Bitte fuehre ein Upgrade durch oder bitte deinen Verein/Admin, dir die passende Berechtigung zu geben.');
    }

    public function update(User $user, Event $event)
    {
        if ((int) $event->user_id === (int) $user->id) {
            return true;
        }

        $club = $event->resolvedClub();

        return ($club && $user->can('event.update') && $this->managesClub($user, $club))
            || ($event->team && $user->can('event.update') && $this->managesTeam($user, $event->team));
    }

    public function delete(User $user, Event $event)
    {
        if ((int) $event->user_id === (int) $user->id) {
            return true;
        }

        $club = $event->resolvedClub();

        return ($club && $user->can('event.delete') && $this->managesClub($user, $club))
            || ($event->team && $user->can('event.delete') && $this->managesTeam($user, $event->team));
    }

    public function join(User $user, ?Event $event = null)
    {
        if ($event && $this->view($user, $event)) {
            return true;
        }

        return $user->can('event.join')
            || $this->isPlayer($user);
    }
}
