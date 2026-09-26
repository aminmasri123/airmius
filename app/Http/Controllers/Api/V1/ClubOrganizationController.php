<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\ClubDepartment;
use App\Models\ClubLocation;
use App\Models\ClubTrainingGroup;
use App\Models\User;
use App\Support\ClubAuditLog;
use App\Support\ClubPermissions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClubOrganizationController extends Controller
{
    public function index(Request $request, Club $club)
    {
        $user = $request->user();
        $canEditGlobally = (bool) $user
            && ClubPermissions::allows($club, $user, ClubPermissions::ORGANIZATION_EDIT);
        $canDeleteGlobally = (bool) $user
            && ClubPermissions::allows($club, $user, ClubPermissions::ORGANIZATION_DELETE);
        $canEdit = $canEditGlobally || ((bool) $user
            && ClubPermissions::allowsAnyScope($club, $user, ClubPermissions::ORGANIZATION_EDIT));
        $canDelete = $canDeleteGlobally || ((bool) $user
            && ClubPermissions::allowsAnyScope($club, $user, ClubPermissions::ORGANIZATION_DELETE));
        $activeMember = (bool) $user && $club->users()
            ->where('users.id', $user->id)
            ->where(fn ($query) => $query->whereNull('club_user.membership_status')
                ->orWhere('club_user.membership_status', 'active'))
            ->exists();
        $canViewAllInternal = $canEditGlobally || $canDeleteGlobally || ($activeMember
            && ClubPermissions::allows($club, $user, ClubPermissions::ORGANIZATION_VIEW));
        $hasScopedAccess = (bool) $user && collect([
            ClubPermissions::ORGANIZATION_VIEW,
            ClubPermissions::ORGANIZATION_EDIT,
            ClubPermissions::ORGANIZATION_DELETE,
            ClubPermissions::TEAMS_EDIT,
        ])->contains(fn (string $permission) => ClubPermissions::allowsAnyScope($club, $user, $permission));
        abort_unless($canViewAllInternal || $hasScopedAccess || $club->is_listed, 404);

        $departments = $club->departments()->orderBy('name')->get();
        $locations = $club->locations()->orderBy('name')->get();
        $trainingGroups = $club->trainingGroups()->with('sportYearPeriod')->orderBy('name')->get();
        $teams = $club->teams()->orderBy('name')->get([
            'id', 'club_id', 'name', 'sport_type', 'club_department_id', 'club_location_id', 'club_training_group_id',
            'sport_year_period_id', 'birth_year_from', 'birth_year_to', 'performance_level', 'capacity',
            'waitlist_enabled', 'valid_from', 'valid_until',
        ]);

        $visibleTeams = $teams->filter(fn ($team) => $canViewAllInternal
            || ($user && $this->canUseTeam($team, $user)));
        $authorizedDepartmentIds = $departments
            ->filter(fn (ClubDepartment $department) => $canViewAllInternal
                || ($user && $this->canUseDepartment($club, $user, $department->id)))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique();
        $visibleDepartmentIds = $authorizedDepartmentIds
            ->merge($departments->where('is_public', true)->pluck('id'))
            ->merge($visibleTeams->pluck('club_department_id')->filter())
            ->map(fn ($id) => (int) $id)
            ->unique();
        $visibleTrainingGroups = $trainingGroups->filter(fn (ClubTrainingGroup $group) => $canViewAllInternal
            || $group->is_public
            || ($group->club_department_id && $authorizedDepartmentIds->contains((int) $group->club_department_id))
            || $visibleTeams->contains(fn ($team) => (int) $team->club_training_group_id === (int) $group->id));
        $visibleLocationIds = $visibleTrainingGroups->pluck('club_location_id')
            ->merge($visibleTeams->pluck('club_location_id'))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique();
        $canEditTeamAssignments = (bool) $user
            && (ClubPermissions::allows($club, $user, ClubPermissions::TEAMS_EDIT)
                || ClubPermissions::allowsAnyScope($club, $user, ClubPermissions::TEAMS_EDIT));

        return response()->json(['data' => [
            'departments' => $departments
                ->filter(fn (ClubDepartment $department) => $canViewAllInternal
                    || $department->is_public
                    || $visibleDepartmentIds->contains((int) $department->id))
                ->map(fn (ClubDepartment $item) => $this->departmentPayload($item, $user))
                ->values(),
            'locations' => $locations
                ->filter(fn (ClubLocation $location) => $canViewAllInternal
                    || $location->is_public
                    || ($hasScopedAccess && $visibleLocationIds->contains((int) $location->id)))
                ->map(fn (ClubLocation $item) => $this->locationPayload($item, $canViewAllInternal, $user))
                ->values(),
            'training_groups' => $visibleTrainingGroups
                ->map(fn (ClubTrainingGroup $item) => $this->trainingGroupPayload($item, $user))
                ->values(),
            'team_assignments' => $visibleTeams
                ->map(fn ($team) => [
                    ...$team->only([
                        'id', 'name', 'sport_type', 'club_department_id', 'club_location_id', 'club_training_group_id',
                        'sport_year_period_id', 'birth_year_from', 'birth_year_to', 'performance_level', 'capacity',
                        'waitlist_enabled',
                    ]),
                    'valid_from' => $team->valid_from?->format('Y-m-d'),
                    'valid_until' => $team->valid_until?->format('Y-m-d'),
                    'can_edit_assignment' => (bool) $user
                        && ClubPermissions::allowsForTeam($team, $user, ClubPermissions::TEAMS_EDIT),
                ])->values(),
            'can_manage' => $canEdit || $canDelete,
            'can_edit' => $canEdit,
            'can_delete' => $canDelete,
            'can_create_departments' => $canEditGlobally,
            'can_create_locations' => $canEditGlobally,
            'can_create_training_groups' => (bool) $user
                && ClubPermissions::allowsAnyDepartmentScope($club, $user, ClubPermissions::ORGANIZATION_EDIT),
            'can_edit_team_assignments' => $canEditTeamAssignments,
            'can_assign_teams_globally' => (bool) $user
                && ClubPermissions::allows($club, $user, ClubPermissions::TEAMS_EDIT),
        ]]);
    }

    public function storeDepartment(Request $request, Club $club)
    {
        $this->authorizeManage($request, $club, ClubPermissions::ORGANIZATION_EDIT);
        $department = $club->departments()->create($this->departmentData($request, $club));
        $this->audit($club, $request, 'club.organization.department.created', $department, 'department');

        return response()->json(['data' => $this->departmentPayload($department, $request->user())], 201);
    }

    public function updateDepartment(Request $request, Club $club, ClubDepartment $department)
    {
        $this->authorizeChild($request, $club, $department, ClubPermissions::ORGANIZATION_EDIT);
        $department->update($this->departmentData($request, $club, $department));
        $this->audit($club, $request, 'club.organization.department.updated', $department, 'department');

        return response()->json(['data' => $this->departmentPayload($department->refresh(), $request->user())]);
    }

    public function destroyDepartment(Request $request, Club $club, ClubDepartment $department)
    {
        $this->authorizeChild($request, $club, $department, ClubPermissions::ORGANIZATION_DELETE);
        if ($department->teams()->exists() || $department->trainingGroups()->exists() || $department->inventoryItems()->exists()) {
            throw ValidationException::withMessages(['department' => __('validation.organization_unit_in_use')]);
        }
        $this->audit($club, $request, 'club.organization.department.deleted', $department, 'department');
        $department->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function storeLocation(Request $request, Club $club)
    {
        $this->authorizeManage($request, $club, ClubPermissions::ORGANIZATION_EDIT);
        $location = $club->locations()->create($this->locationData($request, $club));
        $this->audit($club, $request, 'club.organization.location.created', $location, 'location');

        return response()->json(['data' => $this->locationPayload($location, true, $request->user())], 201);
    }

    public function updateLocation(Request $request, Club $club, ClubLocation $location)
    {
        $this->authorizeChild($request, $club, $location, ClubPermissions::ORGANIZATION_EDIT);
        $location->update($this->locationData($request, $club, $location));
        $this->audit($club, $request, 'club.organization.location.updated', $location, 'location');

        return response()->json(['data' => $this->locationPayload($location->refresh(), true, $request->user())]);
    }

    public function destroyLocation(Request $request, Club $club, ClubLocation $location)
    {
        $this->authorizeChild($request, $club, $location, ClubPermissions::ORGANIZATION_DELETE);
        if ($location->teams()->exists() || $location->trainingGroups()->exists()) {
            throw ValidationException::withMessages(['location' => __('validation.organization_unit_in_use')]);
        }
        $this->audit($club, $request, 'club.organization.location.deleted', $location, 'location');
        $location->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function storeTrainingGroup(Request $request, Club $club)
    {
        $departmentId = $request->filled('club_department_id') ? $request->integer('club_department_id') : null;
        $this->authorizeDepartmentScope($request, $club, ClubPermissions::ORGANIZATION_EDIT, $departmentId);
        $data = $this->trainingGroupData($request, $club);
        $group = $club->trainingGroups()->create($data);
        $this->audit($club, $request, 'club.organization.training_group.created', $group, 'training_group');

        return response()->json(['data' => $this->trainingGroupPayload($group, $request->user())], 201);
    }

    public function updateTrainingGroup(Request $request, Club $club, ClubTrainingGroup $trainingGroup)
    {
        abort_unless((int) $trainingGroup->club_id === (int) $club->id, 404);
        $this->authorizeDepartmentScope($request, $club, ClubPermissions::ORGANIZATION_EDIT, $trainingGroup->club_department_id);
        $data = $this->trainingGroupData($request, $club, $trainingGroup);
        $this->authorizeDepartmentScope($request, $club, ClubPermissions::ORGANIZATION_EDIT, $data['club_department_id'] ?? null);
        $trainingGroup->update($data);
        $this->audit($club, $request, 'club.organization.training_group.updated', $trainingGroup, 'training_group');

        return response()->json(['data' => $this->trainingGroupPayload($trainingGroup->refresh(), $request->user())]);
    }

    public function destroyTrainingGroup(Request $request, Club $club, ClubTrainingGroup $trainingGroup)
    {
        abort_unless((int) $trainingGroup->club_id === (int) $club->id, 404);
        $this->authorizeDepartmentScope($request, $club, ClubPermissions::ORGANIZATION_DELETE, $trainingGroup->club_department_id);
        if ($trainingGroup->teams()->exists()) {
            throw ValidationException::withMessages(['training_group' => __('validation.organization_unit_in_use')]);
        }
        $this->audit($club, $request, 'club.organization.training_group.deleted', $trainingGroup, 'training_group');
        $trainingGroup->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    private function authorizeManage(Request $request, Club $club, string $permission): void
    {
        abort_unless($request->user()
            && ClubPermissions::allows($club, $request->user(), $permission), 403);
    }

    private function authorizeChild(Request $request, Club $club, Model $model, string $permission): void
    {
        abort_unless((int) $model->getAttribute('club_id') === (int) $club->id, 404);
        if ($model instanceof ClubDepartment) {
            $this->authorizeDepartmentScope($request, $club, $permission, (int) $model->id);

            return;
        }

        $this->authorizeManage($request, $club, $permission);
    }

    private function authorizeDepartmentScope(Request $request, Club $club, string $permission, ?int $departmentId): void
    {
        $user = $request->user();
        abort_unless($user && (ClubPermissions::allows($club, $user, $permission)
            || ($departmentId && ClubPermissions::allowsInScope($club, $user, $permission, 'department', $departmentId))), 403);
    }

    private function departmentData(Request $request, Club $club, ?ClubDepartment $department = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160', Rule::unique('club_departments')->where('club_id', $club->id)->ignore($department)],
            'sport_type' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_public' => ['required', 'boolean'],
        ]);

        return $this->trim($data, ['name', 'sport_type', 'description']);
    }

    private function locationData(Request $request, Club $club, ?ClubLocation $location = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160', Rule::unique('club_locations')->where('club_id', $club->id)->ignore($location)],
            'street' => ['nullable', 'string', 'max:255'],
            'house_number' => ['nullable', 'string', 'max:40'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['required', 'string', 'size:2'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_public' => ['required', 'boolean'],
        ]);
        $data = $this->trim($data, ['name', 'street', 'house_number', 'postal_code', 'city', 'notes']);
        $data['country'] = strtoupper($data['country']);

        return $data;
    }

    private function trainingGroupData(Request $request, Club $club, ?ClubTrainingGroup $group = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160', Rule::unique('club_training_groups')->where('club_id', $club->id)->ignore($group)],
            'club_department_id' => ['nullable', Rule::exists('club_departments', 'id')->where('club_id', $club->id)],
            'club_location_id' => ['nullable', Rule::exists('club_locations', 'id')->where('club_id', $club->id)],
            'sport_year_period_id' => ['sometimes', 'nullable', 'integer', Rule::exists('club_year_periods', 'id')->where(fn ($query) => $query
                ->where('club_id', $club->id)
                ->where('type', 'sport'))],
            'sport_type' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_public' => ['required', 'boolean'],
            ...$this->planningRules(),
        ]);

        return $this->trim($data, ['name', 'sport_type', 'description', 'performance_level']);
    }

    private function trim(array $data, array $fields): array
    {
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $value = trim((string) ($data[$field] ?? ''));
                $data[$field] = $value === '' ? null : $value;
            }
        }

        return $data;
    }

    private function departmentPayload(ClubDepartment $item, ?User $user = null): array
    {
        return [
            ...$item->only(['id', 'club_id', 'name', 'sport_type', 'description', 'is_public']),
            'can_edit' => (bool) $user && (ClubPermissions::allows($item->club, $user, ClubPermissions::ORGANIZATION_EDIT)
                || ClubPermissions::allowsInScope($item->club, $user, ClubPermissions::ORGANIZATION_EDIT, 'department', (int) $item->id)),
            'can_delete' => (bool) $user && (ClubPermissions::allows($item->club, $user, ClubPermissions::ORGANIZATION_DELETE)
                || ClubPermissions::allowsInScope($item->club, $user, ClubPermissions::ORGANIZATION_DELETE, 'department', (int) $item->id)),
            'can_assign_teams' => (bool) $user && (ClubPermissions::allows($item->club, $user, ClubPermissions::TEAMS_EDIT)
                || ClubPermissions::allowsInScope($item->club, $user, ClubPermissions::TEAMS_EDIT, 'department', (int) $item->id)),
        ];
    }

    private function locationPayload(ClubLocation $item, bool $includeInternalNotes = true, ?User $user = null): array
    {
        return [
            ...$item->only([
                'id', 'club_id', 'name', 'street', 'house_number', 'postal_code', 'city', 'country',
                ...($includeInternalNotes ? ['notes'] : []),
                'is_public',
            ]),
            'can_edit' => (bool) $user && ClubPermissions::allows($item->club, $user, ClubPermissions::ORGANIZATION_EDIT),
            'can_delete' => (bool) $user && ClubPermissions::allows($item->club, $user, ClubPermissions::ORGANIZATION_DELETE),
        ];
    }

    private function trainingGroupPayload(ClubTrainingGroup $item, ?User $user = null): array
    {
        $canEdit = (bool) $user && (ClubPermissions::allows($item->club, $user, ClubPermissions::ORGANIZATION_EDIT)
            || ($item->club_department_id && ClubPermissions::allowsInScope($item->club, $user, ClubPermissions::ORGANIZATION_EDIT, 'department', (int) $item->club_department_id)));
        $canDelete = (bool) $user && (ClubPermissions::allows($item->club, $user, ClubPermissions::ORGANIZATION_DELETE)
            || ($item->club_department_id && ClubPermissions::allowsInScope($item->club, $user, ClubPermissions::ORGANIZATION_DELETE, 'department', (int) $item->club_department_id)));

        return [
            ...$item->only([
                'id', 'club_id', 'club_department_id', 'club_location_id', 'sport_year_period_id',
                'name', 'sport_type', 'description', 'is_public',
                'birth_year_from', 'birth_year_to', 'performance_level', 'capacity', 'waitlist_enabled',
            ]),
            'sport_year_period' => $item->relationLoaded('sportYearPeriod') && $item->sportYearPeriod ? [
                'id' => $item->sportYearPeriod->id,
                'name' => $item->sportYearPeriod->name,
                'starts_on' => $item->sportYearPeriod->starts_on?->format('Y-m-d'),
                'ends_on' => $item->sportYearPeriod->ends_on?->format('Y-m-d'),
            ] : null,
            'valid_from' => $item->valid_from?->format('Y-m-d'),
            'valid_until' => $item->valid_until?->format('Y-m-d'),
            'can_edit' => $canEdit,
            'can_delete' => $canDelete,
            'can_assign_teams' => (bool) $user && (ClubPermissions::allows($item->club, $user, ClubPermissions::TEAMS_EDIT)
                || ($item->club_department_id && ClubPermissions::allowsInScope($item->club, $user, ClubPermissions::TEAMS_EDIT, 'department', (int) $item->club_department_id))),
        ];
    }

    private function canUseDepartment(Club $club, User $user, int $departmentId): bool
    {
        foreach ([ClubPermissions::ORGANIZATION_VIEW, ClubPermissions::ORGANIZATION_EDIT, ClubPermissions::ORGANIZATION_DELETE] as $permission) {
            if (ClubPermissions::allowsInScope($club, $user, $permission, 'department', $departmentId)) {
                return true;
            }
        }

        return false;
    }

    private function planningRules(): array
    {
        return [
            'birth_year_from' => ['sometimes', 'nullable', 'integer', 'min:1900', 'max:2100'],
            'birth_year_to' => ['sometimes', 'nullable', 'integer', 'min:1900', 'max:2100', 'gte:birth_year_from'],
            'performance_level' => ['sometimes', 'nullable', 'string', 'max:120'],
            'capacity' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100000'],
            'waitlist_enabled' => ['sometimes', 'boolean'],
            'valid_from' => ['sometimes', 'nullable', 'date'],
            'valid_until' => ['sometimes', 'nullable', 'date', 'after_or_equal:valid_from'],
        ];
    }

    private function canUseTeam($team, User $user): bool
    {
        foreach ([ClubPermissions::ORGANIZATION_VIEW, ClubPermissions::ORGANIZATION_EDIT, ClubPermissions::ORGANIZATION_DELETE, ClubPermissions::TEAMS_EDIT] as $permission) {
            if (ClubPermissions::allowsForTeam($team, $user, $permission)) {
                return true;
            }
        }

        return false;
    }

    private function audit(Club $club, Request $request, string $type, Model $subject, string $entityType): void
    {
        ClubAuditLog::record($club, $request->user(), $type, $subject, ['entity_type' => $entityType]);
    }
}
