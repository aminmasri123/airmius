<?php

namespace App\Policies;

use App\Models\Club;
use App\Models\Event;
use App\Models\Team;
use App\Models\User;
use App\Support\ClubPermissions;
use App\Support\EventCreationPermissions;
use Illuminate\Auth\Access\Response;

class EventPolicy extends BasePolicy
{
    public function viewAny(User $user)
    {
        return true;
    }

    public function view(User $user, Event $event)
    {
        if (Event::query()->visibleTo($user)->whereKey($event->id)->exists()) {
            return true;
        }

        if ($event->team) {
            return ClubPermissions::allowsForTeam($event->team, $user, ClubPermissions::EVENTS_EDIT)
                || ClubPermissions::allowsForTeam($event->team, $user, ClubPermissions::EVENTS_DELETE);
        }

        $club = $event->resolvedClub();

        return $club && (
            ClubPermissions::allows($club, $user, ClubPermissions::EVENTS_EDIT)
            || ClubPermissions::allows($club, $user, ClubPermissions::EVENTS_DELETE)
        );
    }

    public function create(User $user, ?Club $club = null, ?Team $team = null)
    {
        if (
            EventCreationPermissions::hasUnlimitedAccess($user, $club, $team)
            || $this->freeEventsRemainingThisMonth($user) > 0
        ) {
            return Response::allow();
        }

        return Response::deny('Im kostenlosen Konto kannst du 2 Events pro Monat erstellen. Dein Monatslimit ist erreicht.');
    }

    public function update(User $user, Event $event)
    {
        if ((int) $event->user_id === (int) $user->id && ! $event->club_id && ! $event->team_id) {
            return true;
        }

        $club = $event->resolvedClub();

        if ($event->team && ClubPermissions::allowsForTeam($event->team, $user, ClubPermissions::EVENTS_EDIT)) {
            return true;
        }

        return ($club && ClubPermissions::allows($club, $user, ClubPermissions::EVENTS_EDIT))
            || ($event->team
                && ! ClubPermissions::explicitlyDenies($event->team->club, $user, ClubPermissions::EVENTS_EDIT)
                && $user->can('event.update')
                && $this->managesTeam($user, $event->team));
    }

    public function delete(User $user, Event $event)
    {
        if ((int) $event->user_id === (int) $user->id && ! $event->club_id && ! $event->team_id) {
            return true;
        }

        $club = $event->resolvedClub();

        if ($event->team && ClubPermissions::allowsForTeam($event->team, $user, ClubPermissions::EVENTS_DELETE)) {
            return true;
        }

        return ($club && ClubPermissions::allows($club, $user, ClubPermissions::EVENTS_DELETE))
            || ($event->team
                && ! ClubPermissions::explicitlyDenies($event->team->club, $user, ClubPermissions::EVENTS_DELETE)
                && $user->can('event.delete')
                && $this->managesTeam($user, $event->team));
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
}
