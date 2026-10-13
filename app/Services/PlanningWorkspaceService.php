<?php

namespace App\Services;

use App\Models\Club;
use App\Models\ClubYearPeriod;
use App\Models\Team;
use App\Models\User;
use App\Support\ClubPermissions;
use App\Support\Roles;

class PlanningWorkspaceService
{
    public function clubs(User $user)
    {
        return Club::query()->when(! $user->hasAnyRole(Roles::FULL_ACCESS), fn ($query) => $query->linkedToUser($user))
            ->orderBy('name')->get()->filter(fn (Club $club) => $this->canAccessClub($club, $user))
            ->map(function (Club $club) use ($user): array {
                $teams = Team::query()->visibleTo($user)->where('club_id', $club->id)->orderBy('name')->get();
                $edit = ClubPermissions::allows($club, $user, ClubPermissions::EVENTS_EDIT);

                return [
                    'id' => $club->id, 'name' => $club->name,
                    'can_edit_competitions' => $edit,
                    'can_pay_fees' => ClubPermissions::allows($club, $user, ClubPermissions::FINANCE_EDIT),
                    'can_confirm' => ClubPermissions::allows($club, $user, ClubPermissions::FINANCE_APPROVE),
                    'can_delete_competitions' => ClubPermissions::allows($club, $user, ClubPermissions::EVENTS_DELETE),
                    'season_planning_enabled' => app(PlanFeatureService::class)->allows($club, 'season_planning'),
                    'teams' => $teams->map(fn (Team $team) => [
                        'id' => $team->id, 'name' => $team->name, 'sport_type' => $team->sport_type, 'sport_year_period_id' => $team->sport_year_period_id,
                        'can_edit_season' => ClubPermissions::allowsForTeam($team, $user, ClubPermissions::TRAINING_PLANS_EDIT)
                            && app(PlanFeatureService::class)->allows($club, 'season_planning'),
                    ])->values(),
                    'periods' => ClubYearPeriod::query()->where('club_id', $club->id)->where('type', 'sport')
                        ->orderByDesc('starts_on')->get(['id', 'name', 'starts_on', 'ends_on']),
                'members' => $edit ? $club->users()->where(function ($query): void {
                        $query->whereNull('club_user.membership_status')->orWhere('club_user.membership_status', 'active');
                    })->orderBy('name')->get(['users.id', 'users.name']) : [],
                ];
            })->values();
    }

    public function authorizeClub(Club $club, User $user): void
    {
        abort_unless($this->canAccessClub($club, $user), 403);
    }

    private function canAccessClub(Club $club, User $user): bool
    {
        if ($club->owner_id === $user->id || $user->hasAnyRole(Roles::FULL_ACCESS)
            || $club->users()->where('users.id', $user->id)->where(fn ($q) => $q->whereNull('club_user.membership_status')->orWhere('club_user.membership_status', 'active'))->exists()
            || $club->externalMembers()->where('linked_user_id', $user->id)->where('membership_status', 'active')->exists()) {
            return true;
        }

        if (collect([ClubPermissions::EVENTS_EDIT, ClubPermissions::TRAINING_PLANS_EDIT, ClubPermissions::FINANCE_VIEW, ClubPermissions::FINANCE_EDIT, ClubPermissions::FINANCE_APPROVE])
            ->contains(fn ($permission) => ClubPermissions::allows($club, $user, $permission))) {
            return true;
        }

        return $club->teams()->get()->contains(fn (Team $team) => ClubPermissions::allowsForTeam($team, $user, ClubPermissions::TRAINING_PLANS_EDIT));
    }
}
