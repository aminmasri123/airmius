<?php

namespace App\Support;

use App\Models\Club;
use App\Models\Event;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;

class EventCreationPermissions
{
    /**
     * Accounts with a platform entitlement may create recurring events in any
     * context they can otherwise select. Club rights are evaluated separately
     * so that a scoped role never unlocks another club or a public event.
     */
    public static function hasGlobalUnlimitedAccess(User $user): bool
    {
        if (
            $user->can('event.create')
            || $user->hasAnyRole([
                'coach', 'assistant_coach', 'performance_coach', 'fitness_coach',
                'club_owner', 'club_admin', 'club_manager', 'academy_manager',
            ])
        ) {
            return true;
        }

        return $user->subscriptions()
            ->grantingAccess()
            ->whereHas('plan', fn ($query) => $query->where('slug', '!=', 'free'))
            ->exists();
    }

    public static function hasUnlimitedAccess(User $user, ?Club $club = null, ?Team $team = null): bool
    {
        if (self::hasGlobalUnlimitedAccess($user)) {
            return true;
        }

        return self::hasScopedAccess($user, $club, $team);
    }

    public static function hasScopedAccess(User $user, ?Club $club = null, ?Team $team = null): bool
    {

        if ($team) {
            return ClubPermissions::allowsForTeam($team, $user, ClubPermissions::EVENTS_EDIT)
                || ($team->club && ClubPermissions::allows($team->club, $user, ClubPermissions::EVENTS_EDIT));
        }

        return $club
            && ClubPermissions::allows($club, $user, ClubPermissions::EVENTS_EDIT);
    }

    /** @return array{0: ?Club, 1: ?Team} */
    public static function context(array $data): array
    {
        $team = filled($data['team_id'] ?? null)
            ? Team::query()->with('club')->find((int) $data['team_id'])
            : null;
        $clubId = $team?->club_id ?: ($data['club_id'] ?? null);
        $club = $team?->club ?: (filled($clubId) ? Club::query()->find((int) $clubId) : null);

        return [$club, $team];
    }

    /**
     * @return array{
     *   is_free_limited: bool,
     *   monthly_limit: ?int,
     *   used_this_month: ?int,
     *   remaining_this_month: ?int,
     *   allows_recurring: bool,
     *   allows_recurring_globally: bool,
     *   recurring_club_ids: array<int>,
     *   recurring_team_ids: array<int>
     * }
     */
    public static function capabilities(User $user): array
    {
        $global = self::hasGlobalUnlimitedAccess($user);
        $clubs = self::memberClubs($user);
        $clubIds = $clubs
            ->filter(fn (Club $club) => ClubPermissions::allows($club, $user, ClubPermissions::EVENTS_EDIT))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();
        $teams = self::availableTeams($user, $clubs);
        $teamIds = $teams
            ->filter(fn (Team $team) => self::hasUnlimitedAccess($user, $team->club, $team))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();
        $used = Event::query()
            ->where('user_id', $user->id)
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();
        $limit = 2;

        return [
            'is_free_limited' => ! $global,
            'monthly_limit' => $global ? null : $limit,
            'used_this_month' => $global ? null : $used,
            'remaining_this_month' => $global ? null : max(0, $limit - $used),
            'allows_recurring' => $global || $clubIds->isNotEmpty() || $teamIds->isNotEmpty(),
            'allows_recurring_globally' => $global,
            'recurring_club_ids' => $clubIds->all(),
            'recurring_team_ids' => $teamIds->all(),
        ];
    }

    /** @return Collection<int, Club> */
    public static function memberClubs(User $user): Collection
    {
        return Club::query()
            ->whereHas('users', fn ($query) => $query->where('users.id', $user->id))
            ->orderBy('name')
            ->get();
    }

    /** @param Collection<int, Club>|null $clubs
     * @return Collection<int, Team>
     */
    public static function availableTeams(User $user, ?Collection $clubs = null): Collection
    {
        $clubs ??= self::memberClubs($user);

        if ($clubs->isEmpty()) {
            return collect();
        }

        return Team::query()
            ->with('club')
            ->whereIn('club_id', $clubs->pluck('id'))
            ->orderBy('name')
            ->get()
            ->filter(function (Team $team) use ($user): bool {
                return $team->users()->where('users.id', $user->id)->exists()
                    || self::hasScopedAccess($user, $team->club, $team);
            })
            ->values();
    }
}
