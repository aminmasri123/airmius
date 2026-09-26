<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\ClubRoleAssignment;
use App\Models\ClubRoleDefinition;
use App\Models\User;
use App\Support\ClubAuditLog;
use App\Support\ClubPermissions;
use App\Support\ClubRoleTemplates;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClubRoleDefinitionController extends Controller
{
    public function index(Request $request, Club $club)
    {
        $this->authorizeManage($request, $club);

        return response()->json(['data' => [
            'roles' => $club->roleDefinitions()->withCount('assignments')->orderBy('name')->limit(100)->get()->map(fn (ClubRoleDefinition $role) => $this->payload($role)),
            'templates' => ClubRoleTemplates::payload(),
            'permission_catalog' => ClubPermissions::catalog(),
        ]]);
    }

    public function store(Request $request, Club $club)
    {
        $this->authorizeManage($request, $club);
        abort_if($club->roleDefinitions()->count() >= 100, 422, __('validation.role_definition_limit'));
        $data = $this->validated($request, $club);
        $role = $club->roleDefinitions()->create($data);
        $this->audit($club, $request, 'club.role_definition.created', $role);

        return response()->json(['data' => $this->payload($role)], 201);
    }

    public function update(Request $request, Club $club, ClubRoleDefinition $roleDefinition)
    {
        $this->authorizeRole($request, $club, $roleDefinition);
        $roleDefinition->update($this->validated($request, $club, $roleDefinition));
        $this->audit($club, $request, 'club.role_definition.updated', $roleDefinition);

        return response()->json(['data' => $this->payload($roleDefinition->refresh())]);
    }

    public function destroy(Request $request, Club $club, ClubRoleDefinition $roleDefinition)
    {
        $this->authorizeRole($request, $club, $roleDefinition);
        if ($roleDefinition->assignments()->exists()) {
            throw ValidationException::withMessages(['role' => __('validation.role_definition_in_use')]);
        }
        $this->audit($club, $request, 'club.role_definition.deleted', $roleDefinition);
        $roleDefinition->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function memberAssignments(Request $request, Club $club, User $user)
    {
        $this->authorizeMember($request, $club, $user);

        return response()->json(['data' => $this->assignmentPayload($club, $user)]);
    }

    public function updateMemberAssignments(Request $request, Club $club, User $user)
    {
        $this->authorizeMember($request, $club, $user);
        $input = $request->all();
        $hasRoleIds = array_key_exists('role_definition_ids', $input);
        $hasAssignments = array_key_exists('assignments', $input);
        if ($hasRoleIds === $hasAssignments) {
            throw ValidationException::withMessages(['assignments' => __('validation.role_definition_assignment_format')]);
        }
        $data = $request->validate([
            'role_definition_ids' => ['sometimes', 'array'],
            'role_definition_ids.*' => ['required', 'integer', 'distinct'],
            'assignments' => ['sometimes', 'array'],
            'assignments.*.role_definition_id' => ['required', 'integer'],
            'assignments.*.scope_type' => ['required', Rule::in(['club', 'department', 'team'])],
            'assignments.*.scope_id' => ['nullable', 'integer', 'min:1'],
        ]);
        $requested = isset($data['assignments'])
            ? collect($data['assignments'])->map(fn (array $assignment) => $this->normalizeAssignment($club, $assignment))
            : collect($data['role_definition_ids'])->map(fn ($id) => $this->normalizeAssignment($club, [
                'role_definition_id' => $id, 'scope_type' => 'club', 'scope_id' => null,
            ]));
        if ($requested->pluck('key')->unique()->count() !== $requested->count()) {
            throw ValidationException::withMessages(['assignments' => __('validation.role_definition_assignment_duplicate')]);
        }
        $requestedIds = $requested->pluck('role_definition_id')->unique()->values();
        $definitions = $club->roleDefinitions()->whereIn('id', $requestedIds)->where('is_active', true)->get();
        if ($definitions->count() !== $requestedIds->count()) {
            throw ValidationException::withMessages(['role_definition_ids' => __('validation.role_definition_assignment')]);
        }

        $current = ClubRoleAssignment::query()->where('club_id', $club->id)->where('user_id', $user->id)->get();
        $currentKeys = $current->map(fn (ClubRoleAssignment $assignment) => $assignment->club_role_definition_id.'@'.$assignment->scope_key);
        $addedIds = $requested->reject(fn (array $assignment) => $currentKeys->contains($assignment['key']))
            ->pluck('role_definition_id')->unique();
        $effective = ClubPermissions::effectiveFor($club, $request->user());
        $addsUnauthorizedPermission = $definitions->whereIn('id', $addedIds)->contains(
            fn (ClubRoleDefinition $role) => collect($role->permissions)->contains(
                fn (string $permission) => ! ($effective[$permission] ?? false)
            )
        );
        if ($addsUnauthorizedPermission) {
            throw ValidationException::withMessages(['role_definition_ids' => __('validation.role_definition_permission')]);
        }

        DB::transaction(function () use ($club, $request, $user, $requested): void {
            $member = DB::table('club_user')->where('club_id', $club->id)->where('user_id', $user->id)
                ->where(fn ($query) => $query->whereNull('membership_status')->orWhere('membership_status', 'active'))
                ->lockForUpdate()->first();
            abort_unless($member, 422, __('validation.role_definition_member'));

            $requestedKeys = $requested->pluck('key');
            ClubRoleAssignment::query()->where('club_id', $club->id)->where('user_id', $user->id)->get()
                ->reject(fn (ClubRoleAssignment $assignment) => $requestedKeys->contains($assignment->club_role_definition_id.'@'.$assignment->scope_key))
                ->each->delete();
            foreach ($requested as $assignment) {
                ClubRoleAssignment::query()->firstOrCreate([
                    'club_id' => $club->id,
                    'club_role_definition_id' => $assignment['role_definition_id'],
                    'user_id' => $user->id,
                    'scope_key' => $assignment['scope_key'],
                ], [
                    'scope_type' => $assignment['scope_type'],
                    'scope_id' => $assignment['scope_id'],
                    'assigned_by' => $request->user()->id,
                ]);
            }
        });

        $nextKeys = $requested->pluck('key')->sort()->values()->all();
        $previousKeys = $currentKeys->sort()->values()->all();
        if ($nextKeys !== $previousKeys) {
            ClubAuditLog::record($club, $request->user(), 'club.role_assignment.updated', $user, [
                'assignments' => $requested->map(fn (array $assignment) => [
                    'role_definition_id' => $assignment['role_definition_id'],
                    'scope_type' => $assignment['scope_type'],
                    'scope_id' => $assignment['scope_id'],
                ])->values()->all(),
            ]);
        }

        return response()->json(['data' => $this->assignmentPayload($club, $user)]);
    }

    private function normalizeAssignment(Club $club, array $assignment): array
    {
        $type = (string) $assignment['scope_type'];
        $id = filled($assignment['scope_id'] ?? null) ? (int) $assignment['scope_id'] : null;
        if ($type === 'club' && $id !== null) {
            throw ValidationException::withMessages(['assignments' => __('validation.role_definition_scope')]);
        }
        if ($type === 'department' && (! $id || ! $club->departments()->whereKey($id)->exists())) {
            throw ValidationException::withMessages(['assignments' => __('validation.role_definition_scope')]);
        }
        if ($type === 'team' && (! $id || ! $club->teams()->whereKey($id)->exists())) {
            throw ValidationException::withMessages(['assignments' => __('validation.role_definition_scope')]);
        }

        $scopeKey = $type === 'club' ? 'club' : $type.':'.$id;
        $roleId = (int) $assignment['role_definition_id'];

        return [
            'role_definition_id' => $roleId,
            'scope_type' => $type,
            'scope_id' => $id,
            'scope_key' => $scopeKey,
            'key' => $roleId.'@'.$scopeKey,
        ];
    }

    private function validated(Request $request, Club $club, ?ClubRoleDefinition $role = null): array
    {
        $request->merge([
            'name' => trim((string) $request->input('name')),
            'key' => trim((string) ($request->input('key') ?: Str::slug((string) $request->input('name'), '_'))),
        ]);
        $data = $request->validate([
            'key' => ['required', 'string', 'max:80', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('club_role_definitions')->where('club_id', $club->id)->ignore($role)],
            'name' => ['required', 'string', 'max:120'],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['required', 'string', 'distinct', Rule::in(ClubPermissions::ALL)],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $permissions = array_values(array_unique($data['permissions']));
        $effective = ClubPermissions::effectiveFor($club, $request->user());
        if (collect($permissions)->contains(fn (string $permission) => ! ($effective[$permission] ?? false))) {
            throw ValidationException::withMessages(['permissions' => __('validation.role_definition_permission')]);
        }

        return [
            'key' => $data['key'],
            'name' => $data['name'],
            'permissions' => $permissions,
            'is_active' => $data['is_active'] ?? $role?->is_active ?? true,
        ];
    }

    private function authorizeManage(Request $request, Club $club): void
    {
        abort_unless($request->user() instanceof User && ClubPermissions::editableBy($club, $request->user()), 403);
    }

    private function authorizeRole(Request $request, Club $club, ClubRoleDefinition $role): void
    {
        $this->authorizeManage($request, $club);
        abort_unless((int) $role->club_id === (int) $club->id, 404);
    }

    private function authorizeMember(Request $request, Club $club, User $user): void
    {
        $this->authorizeManage($request, $club);
        abort_unless($club->users()->where('users.id', $user->id)->exists(), 404);
    }

    private function payload(ClubRoleDefinition $role, bool $withAssignedCount = true): array
    {
        return [
            'id' => $role->id,
            'key' => $role->key,
            'name' => $role->name,
            'permissions' => $role->permissions,
            'is_active' => $role->is_active,
            'assigned_count' => $withAssignedCount
                ? (isset($role->assignments_count) ? (int) $role->assignments_count : $role->assignments()->count())
                : null,
        ];
    }

    private function assignmentPayload(Club $club, User $user): array
    {
        $assignments = ClubRoleAssignment::query()->where('club_id', $club->id)->where('user_id', $user->id)
            ->with('roleDefinition')->orderBy('id')->get();

        return [
            'member_id' => $user->id,
            'role_definition_ids' => $assignments->where('scope_type', 'club')->pluck('club_role_definition_id')->map(fn ($id) => (int) $id)->values(),
            'assignments' => $assignments->map(fn (ClubRoleAssignment $assignment) => [
                'role_definition_id' => (int) $assignment->club_role_definition_id,
                'scope_type' => $assignment->scope_type,
                'scope_id' => $assignment->scope_id ? (int) $assignment->scope_id : null,
            ])->values(),
            'roles' => $assignments->map(fn (ClubRoleAssignment $assignment) => $this->payload($assignment->roleDefinition, false)),
            'effective_permissions' => ClubPermissions::effectiveFor($club, $user),
        ];
    }

    private function audit(Club $club, Request $request, string $type, ClubRoleDefinition $role): void
    {
        ClubAuditLog::record($club, $request->user(), $type, $role, [
            'role_key' => $role->key,
            'permissions' => $role->permissions,
            'is_active' => $role->is_active,
        ]);
    }
}
