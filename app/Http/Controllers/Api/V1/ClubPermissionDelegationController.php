<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\ClubDepartment;
use App\Models\ClubPermissionDelegation;
use App\Models\Team;
use App\Models\User;
use App\Support\ClubAuditLog;
use App\Support\ClubPermissions;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClubPermissionDelegationController extends Controller
{
    private const MAX_DAYS = 90;

    public function index(Request $request, Club $club)
    {
        $this->authorizeManage($request, $club);

        return response()->json(['data' => [
            'delegations' => ClubPermissionDelegation::query()->where('club_id', $club->id)->latest('id')->limit(200)->get()
                ->map(fn (ClubPermissionDelegation $delegation) => $this->payload($delegation)),
            'delegable_permissions' => $this->delegablePermissions($club, $request->user()),
            'maximum_days' => self::MAX_DAYS,
        ]]);
    }

    public function store(Request $request, Club $club)
    {
        $this->authorizeManage($request, $club);
        $data = $request->validate([
            'grantee_user_id' => ['required', 'integer'],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['required', 'string', 'distinct', Rule::in(ClubPermissions::ALL)],
            'scope_type' => ['sometimes', Rule::in(['club', 'department', 'team'])],
            'scope_id' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['required', 'date'],
        ]);
        $actor = $request->user();
        abort_if((int) $data['grantee_user_id'] === (int) $actor->id, 422, __('validation.delegation_self'));

        $scope = $this->normalizeScope($club, $data['scope_type'] ?? 'club', $data['scope_id'] ?? null);

        $permissions = collect($data['permissions'])->unique()->values()->all();
        if (array_diff($permissions, $this->delegablePermissions($club, $actor, $scope)) !== []) {
            throw ValidationException::withMessages(['permissions' => __('validation.delegation_permission')]);
        }

        $startsAt = isset($data['starts_at']) ? CarbonImmutable::parse($data['starts_at']) : CarbonImmutable::now();
        $endsAt = CarbonImmutable::parse($data['ends_at']);
        if ($startsAt->lt(now()->subMinute()) || $endsAt->lte($startsAt) || $endsAt->gt($startsAt->addDays(self::MAX_DAYS))) {
            throw ValidationException::withMessages(['ends_at' => __('validation.delegation_period')]);
        }

        $delegation = DB::transaction(function () use ($club, $actor, $data, $permissions, $scope, $startsAt, $endsAt) {
            $member = DB::table('club_user')->where('club_id', $club->id)->where('user_id', $data['grantee_user_id'])
                ->where(fn ($query) => $query->whereNull('membership_status')->orWhere('membership_status', 'active'))
                ->lockForUpdate()->first();
            abort_unless($member, 422, __('validation.delegation_grantee'));

            return ClubPermissionDelegation::query()->create([
                'club_id' => $club->id,
                'grantor_user_id' => $actor->id,
                'grantee_user_id' => $data['grantee_user_id'],
                'permissions' => $permissions,
                'scope_type' => $scope['type'],
                'scope_id' => $scope['id'],
                'scope_key' => $scope['key'],
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
            ]);
        });

        ClubAuditLog::record($club, $actor, 'club.permission_delegation.created', $delegation, [
            'permissions' => $permissions,
            'scope_type' => $scope['type'],
            'scope_id' => $scope['id'],
            'starts_at' => $startsAt->toIso8601String(),
            'ends_at' => $endsAt->toIso8601String(),
        ]);

        return response()->json(['data' => $this->payload($delegation)], 201);
    }

    public function revoke(Request $request, Club $club, ClubPermissionDelegation $delegation)
    {
        $this->authorizeManage($request, $club);
        abort_unless((int) $delegation->club_id === (int) $club->id, 404);

        if (! $delegation->revoked_at) {
            $delegation->forceFill(['revoked_at' => now(), 'revoked_by' => $request->user()->id])->save();
            ClubAuditLog::record($club, $request->user(), 'club.permission_delegation.revoked', $delegation, [
                'permissions' => $delegation->permissions,
            ]);
        }

        return response()->json(['data' => $this->payload($delegation->refresh())]);
    }

    private function authorizeManage(Request $request, Club $club): void
    {
        abort_unless($request->user() instanceof User && ClubPermissions::editableBy($club, $request->user()), 403);
    }

    /** @param array{type: string, id: ?int, key: string}|null $scope */
    private function delegablePermissions(Club $club, User $actor, ?array $scope = null): array
    {
        return collect(ClubPermissions::ALL)
            ->reject(fn (string $permission) => $permission === ClubPermissions::MEMBERS_ROLES)
            ->filter(fn (string $permission) => $this->allowsInTargetScope($club, $actor, $permission, $scope))
            ->values()->all();
    }

    /** @param array{type: string, id: ?int, key: string}|null $scope */
    private function allowsInTargetScope(Club $club, User $actor, string $permission, ?array $scope): bool
    {
        if (! $scope || $scope['type'] === 'club') {
            return ClubPermissions::allows($club, $actor, $permission);
        }

        if ($scope['type'] === 'team') {
            $team = Team::query()->where('club_id', $club->id)->find($scope['id']);

            return $team && ClubPermissions::allowsForTeam($team, $actor, $permission);
        }

        return ClubPermissions::allowsInScope($club, $actor, $permission, 'department', (int) $scope['id']);
    }

    /** @return array{type: string, id: ?int, key: string} */
    private function normalizeScope(Club $club, string $type, mixed $id): array
    {
        if ($type === 'club') {
            if ($id !== null) {
                throw ValidationException::withMessages(['scope_id' => __('validation.role_definition_scope')]);
            }

            return ['type' => 'club', 'id' => null, 'key' => 'club'];
        }

        $scopeId = (int) $id;
        $exists = $type === 'department'
            ? ClubDepartment::query()->where('club_id', $club->id)->whereKey($scopeId)->exists()
            : Team::query()->where('club_id', $club->id)->whereKey($scopeId)->exists();
        if ($scopeId < 1 || ! $exists) {
            throw ValidationException::withMessages(['scope_id' => __('validation.role_definition_scope')]);
        }

        return ['type' => $type, 'id' => $scopeId, 'key' => $type.':'.$scopeId];
    }

    private function payload(ClubPermissionDelegation $delegation): array
    {
        $status = $delegation->revoked_at ? 'revoked'
            : ($delegation->starts_at->isFuture() ? 'scheduled' : ($delegation->ends_at->isPast() ? 'expired' : 'active'));

        return [
            'id' => $delegation->id,
            'grantor_user_id' => $delegation->grantor_user_id,
            'grantee_user_id' => $delegation->grantee_user_id,
            'permissions' => $delegation->permissions,
            'scope_type' => $delegation->scope_type,
            'scope_id' => $delegation->scope_id,
            'starts_at' => $delegation->starts_at->toIso8601String(),
            'ends_at' => $delegation->ends_at->toIso8601String(),
            'status' => $status,
            'revoked_at' => $delegation->revoked_at?->toIso8601String(),
        ];
    }
}
