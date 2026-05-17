<?php

namespace App\Policies;

use App\Models\Ride;
use App\Models\User;

class RidePolicy extends BasePolicy
{
    public function viewAny(User $user)
    {
        return $this->canUseRides($user);
    }

    public function create(User $user)
    {
        return $this->canUseRides($user);
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
        return $this->canUseRides($user)
            && $this->canSeeRide($user, $ride)
            && (int) $ride->driver_id !== (int) $user->id
            && ! $ride->users()->where('users.id', $user->id)->wherePivotIn('status', [Ride::MEMBER_STATUS_REQUESTED, Ride::MEMBER_STATUS_ACCEPTED])->exists()
            && (! $ride->departure_time || now()->lte($ride->departure_time))
            && $ride->acceptedUsers()->count() < (int) $ride->seats;
    }

    private function canSeeRide(User $user, Ride $ride): bool
    {
        if ((int) $ride->driver_id === (int) $user->id
            || $ride->users()->where('users.id', $user->id)->wherePivotIn('status', [Ride::MEMBER_STATUS_REQUESTED, Ride::MEMBER_STATUS_ACCEPTED])->exists()) {
            return true;
        }

        return match ($ride->visibility) {
            'public' => true,
            'friends' => $ride->driver?->isFriendsWith($user) || $user->isFriendsWith($ride->driver),
            'club' => $ride->club_id && $this->relatedClubIds($user)->contains((int) $ride->club_id),
            'team' => $ride->team_id && $this->relatedTeamIds($user)->contains((int) $ride->team_id),
            default => false,
        };
    }

    private function canUseRides(User $user): bool
    {
        return $this->isPlayer($user)
            || $user->hasAnyRole(['parent', 'guardian'])
            || $user->can('guardians.children.view');
    }

    private function relatedClubIds(User $user)
    {
        return $user->clubs()
            ->pluck('clubs.id')
            ->merge($this->managedChildren($user)
                ->with(['clubs:id', 'teams:id,club_id'])
                ->get()
                ->flatMap(fn (User $child) => $child->clubs->pluck('id')
                    ->merge($child->teams->pluck('club_id')->filter())))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    private function relatedTeamIds(User $user)
    {
        return $user->teams()
            ->pluck('teams.id')
            ->merge($this->managedChildren($user)
                ->with('teams:id')
                ->get()
                ->flatMap(fn (User $child) => $child->teams->pluck('id')))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    private function managedChildren(User $user)
    {
        return User::query()
            ->whereDate('birth_date', '>', now()->subYears(16)->toDateString())
            ->where(function ($query) use ($user) {
                $query->where('guardian_user_id', $user->id)
                    ->orWhere('guardian_email', mb_strtolower((string) $user->email));
            });
    }
}
