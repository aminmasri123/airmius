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
        return Event::query()->visibleTo($user)->whereKey($event->id)->exists();
    }

    public function create(User $user)
    {
        if (
            $user->can('event.create')
            || $this->isCoach($user)
            || $this->isClubAdmin($user)
            || $this->hasActivePaidSubscription($user)
            || $this->freeEventsRemainingThisMonth($user) > 0
        ) {
            return Response::allow();
        }

        return Response::deny('Im kostenlosen Konto kannst du 2 Events pro Monat erstellen. Dein Monatslimit ist erreicht.');
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

    public function cancel(User $user, Event $event)
    {
        return $this->update($user, $event);
    }

    public function join(User $user, ?Event $event = null)
    {
        if ($event && $event->status === 'cancelled') {
            return false;
        }

        if ($event && $this->view($user, $event)) {
            return true;
        }

        return $user->can('event.join')
            || $this->isPlayer($user);
    }

    private function freeEventsRemainingThisMonth(User $user): int
    {
        $used = Event::query()
            ->where('user_id', $user->id)
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();

        return max(0, 2 - $used);
    }

    private function hasActivePaidSubscription(User $user): bool
    {
        return $user->subscriptions()
            ->grantingAccess()
            ->whereHas('plan', fn ($query) => $query->where('slug', '!=', 'free'))
            ->exists();
    }
}
