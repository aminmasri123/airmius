<?php

namespace App\Services;

use App\Models\Club;
use App\Models\ClubDepartment;
use App\Models\Team;
use App\Models\User;
use App\Support\ClubPermissions;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class SupportAccessService
{
    /** @return array{global: bool, club_ids: array<int, int>, department_ids: array<int, int>, team_ids: array<int, int>} */
    public function operatorScope(User $user, ?string $permission = null): array
    {
        $global = $user->can('support.tickets') || $user->can('system.manage');

        if ($global) {
            return ['global' => true, 'club_ids' => [], 'department_ids' => [], 'team_ids' => []];
        }

        $permissions = $permission
            ? [$permission]
            : [
                ClubPermissions::SUPPORT_VIEW,
                ClubPermissions::SUPPORT_EDIT,
                ClubPermissions::SUPPORT_ASSIGN,
                ClubPermissions::SUPPORT_RESOLVE,
            ];
        $clubs = $this->linkedClubs($user);
        $clubIds = $clubs
            ->filter(fn (Club $club) => collect($permissions)->contains(
                fn (string $candidate) => ClubPermissions::allows($club, $user, $candidate),
            ))
            ->pluck('id')->map(fn ($id) => (int) $id)->values();
        $scopedClubIds = $clubs->pluck('id')->map(fn ($id) => (int) $id)->values();
        $departmentIds = ClubDepartment::query()
            ->whereIn('club_id', $scopedClubIds)
            ->get(['id', 'club_id'])
            ->filter(function (ClubDepartment $department) use ($clubs, $permissions, $user): bool {
                $club = $clubs->firstWhere('id', $department->club_id);

                return $club && collect($permissions)->contains(fn (string $candidate) => ClubPermissions::allowsInScope(
                    $club, $user, $candidate, 'department', (int) $department->id,
                ));
            })
            ->pluck('id')->map(fn ($id) => (int) $id)->values();
        $teamIds = Team::query()
            ->with('club')
            ->whereIn('club_id', $scopedClubIds)
            ->get(['id', 'club_id', 'club_department_id'])
            ->filter(fn (Team $team) => collect($permissions)->contains(
                fn (string $candidate) => ClubPermissions::allowsForTeam($team, $user, $candidate),
            ))
            ->pluck('id')->map(fn ($id) => (int) $id)->values();

        return [
            'global' => false,
            'club_ids' => $clubIds->all(),
            'department_ids' => $departmentIds->all(),
            'team_ids' => $teamIds->all(),
        ];
    }

    public function canOperate(User $user): bool
    {
        $scope = $this->operatorScope($user);

        return $scope['global'] || $scope['club_ids'] !== [] || $scope['department_ids'] !== [] || $scope['team_ids'] !== [];
    }

    /** @return Collection<int, Club> */
    public function linkedClubs(User $user): Collection
    {
        return Club::query()
            ->linkedToUser($user)
            ->select(['clubs.id', 'clubs.name', 'clubs.owner_id'])
            ->orderBy('clubs.name')
            ->get();
    }

    /** @return Collection<int, Club> */
    public function supportClubs(User $user, ?string $permission = null): Collection
    {
        $permissions = $permission
            ? [$permission]
            : [
                ClubPermissions::SUPPORT_VIEW,
                ClubPermissions::SUPPORT_EDIT,
                ClubPermissions::SUPPORT_ASSIGN,
                ClubPermissions::SUPPORT_RESOLVE,
            ];

        return Club::query()
            ->where(function ($query) use ($user): void {
                $query->where('owner_id', $user->id)
                    ->orWhereHas('users', fn ($members) => $members->where('users.id', $user->id));
            })
            ->select(['clubs.id', 'clubs.name', 'clubs.owner_id'])
            ->orderBy('clubs.name')
            ->get()
            ->filter(fn (Club $club) => collect($permissions)->contains(
                fn (string $candidate) => ClubPermissions::allowsAnyScope($club, $user, $candidate),
            ))
            ->values();
    }

    public function requesterClubId(User $user, mixed $requestedClubId, string $category): ?int
    {
        $linkedClubIds = $this->linkedClubs($user)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($requestedClubId !== null) {
            $clubId = (int) $requestedClubId;
            abort_unless($linkedClubIds->contains($clubId), 403);

            return $clubId;
        }

        return in_array($category, ['club', 'membership'], true) && $linkedClubIds->count() === 1
            ? $linkedClubIds->first()
            : null;
    }

    /** @return array{club_department_id: ?int, team_id: ?int} */
    public function requesterContext(User $user, ?int $clubId, mixed $requestedDepartmentId, mixed $requestedTeamId): array
    {
        if (! $clubId) {
            if ($requestedDepartmentId !== null || $requestedTeamId !== null) {
                throw ValidationException::withMessages(['club_id' => __('validation.support_scope')]);
            }

            return ['club_department_id' => null, 'team_id' => null];
        }

        abort_unless($this->linkedClubs($user)->contains('id', $clubId), 403);

        $departmentId = $requestedDepartmentId !== null ? (int) $requestedDepartmentId : null;
        $teamId = $requestedTeamId !== null ? (int) $requestedTeamId : null;
        if ($teamId) {
            $team = Team::query()->where('club_id', $clubId)->find($teamId);
            if (! $team || ($departmentId && (int) $team->club_department_id !== $departmentId)) {
                throw ValidationException::withMessages(['team_id' => __('validation.support_scope')]);
            }
            $departmentId = $team->club_department_id ? (int) $team->club_department_id : null;
        }
        if ($departmentId && ! ClubDepartment::query()->where('club_id', $clubId)->whereKey($departmentId)->exists()) {
            throw ValidationException::withMessages(['club_department_id' => __('validation.support_scope')]);
        }

        return ['club_department_id' => $departmentId, 'team_id' => $teamId];
    }

    /** @return array{club_department_id: ?int, team_id: ?int} */
    public function publicClubContext(int $clubId, mixed $requestedDepartmentId, mixed $requestedTeamId): array
    {
        $departmentId = $requestedDepartmentId !== null ? (int) $requestedDepartmentId : null;
        $teamId = $requestedTeamId !== null ? (int) $requestedTeamId : null;

        if ($teamId) {
            $team = Team::query()->where('club_id', $clubId)->find($teamId);
            if (! $team || ($departmentId && (int) $team->club_department_id !== $departmentId)) {
                throw ValidationException::withMessages(['team_id' => __('validation.support_scope')]);
            }
            $departmentId = $team->club_department_id ? (int) $team->club_department_id : null;
        }

        if ($departmentId && ! ClubDepartment::query()->where('club_id', $clubId)->whereKey($departmentId)->exists()) {
            throw ValidationException::withMessages(['club_department_id' => __('validation.support_scope')]);
        }

        return ['club_department_id' => $departmentId, 'team_id' => $teamId];
    }
}
