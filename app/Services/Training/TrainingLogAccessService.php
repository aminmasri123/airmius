<?php

namespace App\Services\Training;

use App\Models\Team;
use App\Models\TrainingLog;
use App\Models\User;
use App\Support\ClubPermissions;
use App\Support\Roles;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TrainingLogAccessService
{
    /** @var array<int, Collection<int, User>> */
    private array $manageableAthletesCache = [];

    public function canView(User $user, TrainingLog $log): bool
    {
        if ((int) $log->user_id === (int) $user->id || (int) $log->created_by === (int) $user->id) {
            return true;
        }

        if ($user->hasAnyRole(Roles::FULL_ACCESS)) {
            return true;
        }

        $privacyScope = $log->metrics['privacy_scope'] ?? 'trainer';

        if ($privacyScope === 'private') {
            return false;
        }

        if ($privacyScope === 'team' && $log->team_id) {
            return $user->teams()->where('teams.id', $log->team_id)->exists();
        }

        return $this->manageableAthleteIds($user)->contains((int) $log->user_id);
    }

    public function feedbackRole(User $user, TrainingLog $log): string
    {
        abort_unless($this->canViewProtectedCaseFile($user, $log), 403);

        if ((int) $log->user_id === (int) $user->id) {
            return 'athlete';
        }

        if ((int) $log->trainer_id === (int) $user->id || (int) $log->created_by === (int) $user->id) {
            return 'trainer';
        }

        if ($user->hasAnyRole(Roles::FULL_ACCESS)) {
            return 'admin';
        }

        return $this->manageableAthleteIds($user)->contains((int) $log->user_id)
            ? 'trainer'
            : 'team_staff';
    }

    public function canViewProtectedCaseFile(User $user, TrainingLog $log): bool
    {
        if (! $this->canView($user, $log)) {
            return false;
        }

        if (in_array((int) $user->id, array_filter([
            (int) $log->user_id,
            (int) $log->created_by,
            (int) $log->trainer_id,
        ]), true)) {
            return true;
        }

        if ($user->hasAnyRole(Roles::FULL_ACCESS)) {
            return true;
        }

        return $this->manageableAthleteIds($user)->contains((int) $log->user_id);
    }

    public function visibleQuery(User $user): Builder
    {
        $query = TrainingLog::query();

        if ($user->hasAnyRole(Roles::FULL_ACCESS)) {
            return $query;
        }

        $teamIds = $user->teams()->pluck('teams.id');
        $manageableAthleteIds = $this->manageableAthleteIds($user);

        return $query->where(function (Builder $visible) use ($user, $teamIds, $manageableAthleteIds) {
            $visible
                ->where('user_id', $user->id)
                ->orWhere('created_by', $user->id)
                ->when($teamIds->isNotEmpty(), fn (Builder $teamVisible) => $teamVisible->orWhere(function (Builder $teamScope) use ($teamIds) {
                    $teamScope
                        ->whereIn('team_id', $teamIds)
                        ->where('metrics->privacy_scope', 'team');
                }))
                ->when($manageableAthleteIds->isNotEmpty(), fn (Builder $managedVisible) => $managedVisible->orWhere(function (Builder $managedScope) use ($manageableAthleteIds) {
                    $managedScope
                        ->whereIn('user_id', $manageableAthleteIds)
                        ->where(function (Builder $privacy) {
                            $privacy
                                ->whereNull('metrics->privacy_scope')
                                ->orWhere('metrics->privacy_scope', '!=', 'private');
                        });
                }));
        });
    }

    public function manageableAthletes(User $user): Collection
    {
        $userId = (int) $user->id;

        if (array_key_exists($userId, $this->manageableAthletesCache)) {
            return $this->manageableAthletesCache[$userId];
        }

        $managedTeamIds = $this->managedTeamIds($user);

        if ($managedTeamIds->isEmpty()) {
            return $this->manageableAthletesCache[$userId] = collect();
        }

        return $this->manageableAthletesCache[$userId] = User::query()
            ->whereKeyNot($user->id)
            ->whereHas('teams', fn ($query) => $query->whereIn('teams.id', $managedTeamIds))
            ->orderBy('name')
            ->get(['id', 'name', 'first_name', 'last_name', 'email']);
    }

    public function manageableAthleteIds(User $user): Collection
    {
        return $this->manageableAthletes($user)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();
    }

    private function managedTeamIds(User $user): Collection
    {
        if ($user->hasAnyRole(Roles::FULL_ACCESS)) {
            return Team::query()->pluck('id');
        }

        $managedTeamIds = $user->teams()
            ->with('club')
            ->wherePivotIn('role', ['Coach', 'coach', 'Trainer', 'trainer', 'ClubPresident', 'club_president', 'Captain', 'captain', 'Admin', 'admin', 'Manager', 'manager'])
            ->get()
            ->filter(fn (Team $team) => ! $team->club
                || ! ClubPermissions::explicitlyDenies(
                    $team->club,
                    $user,
                    ClubPermissions::TRAINER_COCKPIT_VIEW,
                ))
            ->pluck('id');

        $clubIds = $user->clubs()->pluck('clubs.id');

        if ($clubIds->isNotEmpty()) {
            $managedTeamIds = $managedTeamIds->merge(
                Team::query()
                    ->with('club')
                    ->whereIn('club_id', $clubIds)
                    ->get()
                    ->filter(fn (Team $team) => ClubPermissions::allowsForTeam(
                        $team,
                        $user,
                        ClubPermissions::TRAINER_COCKPIT_VIEW,
                    ))
                    ->pluck('id')
            );
        }

        return $managedTeamIds->unique()->values();
    }
}
