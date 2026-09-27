<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AccountWarning;
use App\Models\Badge;
use App\Models\Club;
use App\Models\ContentReport;
use App\Models\GamificationRule;
use App\Models\Message;
use App\Models\ModerationFlag;
use App\Models\Permission;
use App\Models\Post;
use App\Models\Role;
use App\Models\Sport;
use App\Models\Team;
use App\Models\User;
use App\Models\UserSport;
use App\Notifications\AccountSuspendedNotification;
use App\Notifications\ClubVerificationStatusUpdated;
use App\Services\AdminCreatedUserProvisioner;
use App\Support\ModerationAuditLog;
use App\Support\Roles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

class PlatformAdminController extends Controller
{
    public function storeUser(Request $request, AdminCreatedUserProvisioner $provisioner)
    {
        $this->ensureSystemManager($request);
        abort_unless($request->user()->can('users.create'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'generate_password' => ['sometimes', 'boolean'],
            'send_credentials' => ['sometimes', 'boolean'],
            'password' => ['nullable', 'string', 'min:8', Rule::requiredIf(! $request->boolean('generate_password'))],
            'password_confirmation' => ['nullable', 'same:password', Rule::requiredIf(! $request->boolean('generate_password'))],
            'profile_visibility' => ['nullable', Rule::in(['public', 'private'])],
        ]);

        $result = $provisioner->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'] ?? null,
            'profile_visibility' => $data['profile_visibility'] ?? 'public',
            'generate_password' => $request->boolean('generate_password'),
            'send_credentials' => $request->boolean('send_credentials'),
        ]);

        /** @var User $user */
        $user = $result['user'];

        return response()->json([
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'profile_visibility' => $user->profile_visibility,
                ],
                'credentials_sent' => $result['credentials_sent'],
                'generated_password_available' => $request->boolean('generate_password') && ! $result['credentials_sent'],
                'generated_password' => $request->boolean('generate_password') && ! $result['credentials_sent']
                    ? $result['plain_password']
                    : null,
            ],
        ], 201);
    }

    public function dashboard(Request $request)
    {
        $this->ensureSystemManager($request);

        $users = User::query()
            ->with('roles:id,name')
            ->latest('id')
            ->limit(50)
            ->get([
                'id',
                'name',
                'email',
                'profile_visibility',
                'account_status',
                'suspended_until',
                'suspension_reason',
                'email_verified_at',
                'two_factor_confirmed_at',
                'last_login_at',
                'last_seen_at',
                'created_at',
            ])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'profile_visibility' => $user->profile_visibility,
                'account_status' => $user->account_status ?: 'active',
                'suspended_until' => $user->suspended_until?->toJSON(),
                'suspension_reason' => $user->suspension_reason,
                'email_verified' => $user->email_verified_at !== null,
                'two_factor_enabled' => $user->two_factor_confirmed_at !== null,
                'last_login_at' => $user->last_login_at?->toJSON(),
                'last_seen_at' => $user->last_seen_at?->toJSON(),
                'created_at' => $user->created_at?->toJSON(),
                'roles' => $user->roles->pluck('name')->values(),
                'can_change_status' => ! $request->user()->is($user)
                    && (! $user->hasRole('super_admin') || $request->user()->hasRole('super_admin')),
            ]);

        $clubs = Club::query()
            ->with('owner:id,name,email')
            ->latest('verification_requested_at')
            ->latest('id')
            ->limit(100)
            ->get([
                'id',
                'name',
                'sport_type',
                'country',
                'city',
                'owner_id',
                'is_official',
                'official_club_number',
                'requested_official_club_number',
                'verification_status',
                'verification_notes',
                'verification_requested_at',
                'verified_at',
                'rejected_at',
            ])
            ->map(fn (Club $club) => [
                'id' => $club->id,
                'name' => $club->name,
                'sport_type' => $club->sport_type,
                'country' => $club->country,
                'city' => $club->city,
                'is_official' => (bool) $club->is_official,
                'official_club_number' => $club->official_club_number,
                'requested_official_club_number' => $club->requested_official_club_number,
                'verification_status' => $club->verification_status,
                'verification_notes' => $club->verification_notes,
                'verification_requested_at' => $club->verification_requested_at?->toJSON(),
                'verified_at' => $club->verified_at?->toJSON(),
                'rejected_at' => $club->rejected_at?->toJSON(),
                'owner' => $club->owner ? [
                    'id' => $club->owner->id,
                    'name' => $club->owner->name,
                    'email' => $club->owner->email,
                ] : null,
            ]);

        $sports = $this->sportPayloads();
        $badges = Badge::query()
            ->withCount('users')
            ->orderBy('actor_type')
            ->orderBy('trigger')
            ->orderBy('threshold')
            ->get()
            ->map(fn (Badge $badge) => [
                'id' => $badge->id,
                'key' => $badge->key,
                'name' => $badge->name,
                'description' => $badge->description,
                'icon' => $badge->icon,
                'actor_type' => $badge->actor_type,
                'trigger' => $badge->trigger,
                'threshold' => $badge->threshold,
                'meta' => $badge->meta,
                'users_count' => $badge->users_count,
            ]);
        $canManageRoles = $request->user()->can('users.assign_roles')
            || $request->user()->can('user.manage');
        $roleUserCounts = $canManageRoles
            ? DB::table('model_has_roles')
                ->where('model_type', User::class)
                ->select('role_id', DB::raw('count(*) as aggregate'))
                ->groupBy('role_id')
                ->pluck('aggregate', 'role_id')
            : collect();
        $roles = $canManageRoles
            ? Role::query()
                ->with('permissions:id,name,description')
                ->orderBy('name')
                ->get()
                ->map(fn (Role $role) => $this->rolePayload(
                    $role,
                    $request,
                    (int) ($roleUserCounts[$role->id] ?? 0)
                ))
            : collect();
        $permissionGroups = $canManageRoles
            ? Permission::query()
                ->orderBy('name')
                ->get(['id', 'name', 'description'])
                ->groupBy(fn (Permission $permission) => str($permission->name)->before('.')->toString())
                ->map(fn ($items, string $group) => [
                    'group' => $group,
                    'items' => $items->values(),
                ])
                ->values()
            : collect();
        $moderationFlags = ModerationFlag::query()
            ->with(['user:id,name', 'flaggable'])
            ->latest()
            ->limit(80)
            ->get()
            ->map(fn (ModerationFlag $flag) => $this->moderationFlagPayload($flag));
        $moderationReports = ContentReport::query()
            ->with(['reporter:id,name', 'reportable'])
            ->latest()
            ->limit(80)
            ->get()
            ->map(fn (ContentReport $report) => $this->moderationReportPayload($report));
        $warnings = AccountWarning::query()
            ->with('user:id,name,email,account_status')
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (AccountWarning $warning) => [
                'id' => $warning->id,
                'severity' => $warning->severity,
                'points' => $warning->points,
                'reason' => $warning->reason,
                'created_at' => $warning->created_at?->toJSON(),
                'user' => $warning->user ? [
                    'id' => $warning->user->id,
                    'name' => $warning->user->name,
                    'email' => $warning->user->email,
                    'account_status' => $warning->user->account_status,
                ] : null,
            ]);
        $gamificationRules = GamificationRule::query()
            ->orderBy('actor_type')
            ->orderBy('category')
            ->orderBy('is_penalty')
            ->orderBy('label')
            ->get();

        return response()->json([
            'data' => [
                'summary' => [
                    'users' => User::query()->count(),
                    'users_suspended' => User::query()->where('account_status', 'suspended')->count(),
                    'clubs_pending' => Club::query()->whereIn('verification_status', ['pending', 'pending_verification'])->count(),
                    'sports' => $sports->count(),
                    'sports_active' => $sports->where('is_active', true)->count(),
                    'badges' => $badges->count(),
                    'roles' => $roles->count(),
                    'moderation_open' => $moderationFlags->where('status', 'open')->count()
                        + $moderationReports->where('status', 'open')->count(),
                    'appeals_pending' => $moderationReports->where('appeal_status', 'pending')->count(),
                    'warnings_90_days' => AccountWarning::query()
                        ->where('created_at', '>=', now()->subDays(90))
                        ->count(),
                    'gamification_rules' => $gamificationRules->count(),
                    'gamification_active' => $gamificationRules->where('is_active', true)->count(),
                ],
                'users' => $users,
                'clubs' => $clubs,
                'sports' => $sports,
                'badges' => $badges,
                'roles' => $roles,
                'permission_groups' => $permissionGroups,
                'moderation' => [
                    'flags' => $moderationFlags,
                    'reports' => $moderationReports,
                    'warnings' => $warnings,
                ],
                'gamification_rules' => $gamificationRules,
                'abilities' => [
                    'users_view' => (bool) $request->user()->can('users.view'),
                    'users_edit' => (bool) $request->user()->can('users.edit'),
                    'roles_assign' => $canManageRoles,
                    'permissions_create' => $request->user()->hasRole('super_admin'),
                    'system_manage' => true,
                ],
            ],
        ]);
    }

    public function updateUserStatus(Request $request, User $user)
    {
        $this->ensureSystemManager($request);
        abort_unless($request->user()->can('users.edit'), 403);
        abort_if($request->user()->is($user), 422, 'Das eigene Administratorkonto kann hier nicht gesperrt werden.');
        abort_if(
            $user->hasRole('super_admin') && ! $request->user()->hasRole('super_admin'),
            403,
            'Nur Super-Admins können den Status eines Super-Admins ändern.'
        );

        $data = $request->validate([
            'action' => ['required', Rule::in(['suspend', 'lift'])],
            'days' => [Rule::requiredIf($request->input('action') === 'suspend'), 'nullable', 'integer', 'min:1', 'max:90'],
            'reason' => [Rule::requiredIf($request->input('action') === 'suspend'), 'nullable', 'string', 'max:500'],
        ]);

        if ($data['action'] === 'lift') {
            $user->forceFill([
                'account_status' => 'active',
                'suspended_until' => null,
                'suspension_reason' => null,
            ])->save();
        } else {
            $until = now()->addDays((int) $data['days']);
            $user->forceFill([
                'account_status' => 'suspended',
                'suspended_until' => $until,
                'suspension_reason' => $data['reason'],
            ])->save();

            try {
                $user->notify(new AccountSuspendedNotification($data['reason'], $until->format('d.m.Y H:i')));
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return response()->json([
            'data' => [
                'id' => $user->id,
                'account_status' => $user->account_status,
                'suspended_until' => $user->suspended_until?->toJSON(),
                'suspension_reason' => $user->suspension_reason,
            ],
        ]);
    }

    public function approveClub(Request $request, Club $club)
    {
        $this->ensureSystemManager($request);
        abort_if((int) $club->owner_id === (int) $request->user()->id, 422, __('validation.approval_second_person'));
        $data = $request->validate([
            'official_club_number' => ['nullable', 'string', 'max:120'],
            'verification_notes' => ['nullable', 'string', 'max:2000'],
            'mark_official' => ['boolean'],
        ]);

        $number = $data['official_club_number'] ?? $club->requested_official_club_number;
        $markOfficial = (bool) ($data['mark_official'] ?? filled($number));

        $club->forceFill([
            'verification_status' => 'verified',
            'verification_notes' => $data['verification_notes'] ?? null,
            'is_official' => $markOfficial,
            'official_club_number' => $markOfficial ? $number : null,
            'verified_at' => now(),
            'rejected_at' => null,
            'verified_by' => $request->user()->id,
        ])->save();
        $club->owner?->notify(new ClubVerificationStatusUpdated($club));

        return response()->json(['data' => ['id' => $club->id, 'verification_status' => 'verified']]);
    }

    public function rejectClub(Request $request, Club $club)
    {
        $this->ensureSystemManager($request);
        $data = $request->validate([
            'verification_notes' => ['required', 'string', 'max:2000'],
        ]);

        $club->forceFill([
            'verification_status' => 'rejected',
            'verification_notes' => $data['verification_notes'],
            'is_official' => false,
            'official_club_number' => null,
            'verified_at' => null,
            'rejected_at' => now(),
            'verified_by' => $request->user()->id,
        ])->save();
        $club->owner?->notify(new ClubVerificationStatusUpdated($club));

        return response()->json(['data' => ['id' => $club->id, 'verification_status' => 'rejected']]);
    }

    public function storeSport(Request $request)
    {
        $this->ensureSystemManager($request);
        $request->merge(['slug' => $request->input('slug') ?: Str::slug((string) $request->input('name'))]);
        $sport = Sport::query()->create($this->validatedSport($request));

        return response()->json(['data' => $sport], 201);
    }

    public function updateSport(Request $request, Sport $sport)
    {
        $this->ensureSystemManager($request);
        $request->merge(['slug' => $request->input('slug') ?: Str::slug((string) $request->input('name'))]);
        $data = $this->validatedSport($request, $sport);

        DB::transaction(function () use ($sport, $data) {
            $oldSlug = $sport->slug;
            $oldName = $sport->name;
            $sport->update($data);
            if ($oldSlug !== $sport->slug) {
                Team::query()->whereIn('sport_type', [$oldSlug, $oldName])->update(['sport_type' => $sport->slug]);
                Club::query()->whereIn('sport_type', [$oldSlug, $oldName])->update(['sport_type' => $sport->slug]);
            }
        });

        return response()->json(['data' => $sport->refresh()]);
    }

    public function destroySport(Request $request, Sport $sport)
    {
        $this->ensureSystemManager($request);
        $request->validate(['confirmation' => ['required', 'in:delete']]);

        $usage = Team::query()->whereIn('sport_type', [$sport->slug, $sport->name])->count()
            + Club::query()->whereIn('sport_type', [$sport->slug, $sport->name])->count()
            + UserSport::query()->where('sport_id', $sport->id)->count()
            + Post::query()->where('sport_id', $sport->id)->count();
        abort_if($usage > 0, 422, 'Diese Sportart wird bereits verwendet. Bitte deaktivieren statt löschen.');
        $sport->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function storeBadge(Request $request)
    {
        $this->ensureSystemManager($request);
        $badge = Badge::query()->create($this->validatedBadge($request));

        return response()->json(['data' => $badge], 201);
    }

    public function updateBadge(Request $request, Badge $badge)
    {
        $this->ensureSystemManager($request);
        $badge->update($this->validatedBadge($request, $badge));

        return response()->json(['data' => $badge->refresh()]);
    }

    public function destroyBadge(Request $request, Badge $badge)
    {
        $this->ensureSystemManager($request);
        abort_if($badge->users()->exists(), 422, 'Badge wurde bereits vergeben und kann nicht gelöscht werden.');
        $badge->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function storeRole(Request $request)
    {
        $this->ensureRoleManager($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'alpha_dash:ascii', Rule::unique('roles', 'name')],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);
        $this->assertAllowedPermissionSelection($request, $data['permissions'] ?? []);

        $role = DB::transaction(function () use ($data) {
            $role = Role::query()->create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'guard_name' => 'web',
            ]);
            $role->syncPermissions($data['permissions'] ?? []);

            return $role;
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json([
            'data' => $this->rolePayload(
                $role->load('permissions'),
                $request,
                0
            ),
        ], 201);
    }

    public function updateRole(Request $request, Role $role)
    {
        $this->ensureRoleManager($request);
        $this->ensureSystemRoleMutationAllowed($request, $role);
        $data = $request->validate([
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);
        $this->assertAllowedPermissionSelection($request, $data['permissions'] ?? []);

        DB::transaction(function () use ($role, $data) {
            $role->update(['description' => $data['description'] ?? null]);
            $role->syncPermissions($data['permissions'] ?? []);
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json([
            'data' => $this->rolePayload(
                $role->refresh()->load('permissions'),
                $request,
                (int) DB::table('model_has_roles')
                    ->where('model_type', User::class)
                    ->where('role_id', $role->id)
                    ->count()
            ),
        ]);
    }

    public function destroyRole(Request $request, Role $role)
    {
        $this->ensureRoleManager($request);
        abort_if(
            in_array($role->name, Roles::PROTECTED_PLATFORM, true),
            422,
            'Systemrollen können nicht gelöscht werden.'
        );
        abort_if($role->users()->exists(), 422, 'Rolle ist noch Nutzern zugewiesen.');
        $role->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function storePermission(Request $request)
    {
        $this->ensureRoleManager($request);
        abort_unless(
            $request->user()->hasRole('super_admin'),
            403,
            'Nur Super-Admins können neue Berechtigungen erstellen.'
        );
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9_.-]+$/', Rule::unique('permissions', 'name')],
            'description' => ['nullable', 'string', 'max:255'],
        ]);
        $permission = Permission::query()->create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'guard_name' => 'web',
        ]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json(['data' => $permission], 201);
    }

    public function updateModerationFlag(
        Request $request,
        ModerationFlag $flag
    ) {
        $this->ensureSystemManager($request);
        $data = $this->validatedModerationDecision($request);
        $previousStatus = $flag->status;
        $actionTaken = $data['status'];

        if ($data['remove_content'] ?? false) {
            $this->removeModeratedContent($flag->flaggable);
            $data['status'] = 'actioned';
            $actionTaken = 'content_removed';
        }
        $flag->update([
            'status' => $data['status'],
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'decision_reason' => $data['decision_reason'] ?? null,
            'action_taken' => $actionTaken,
        ]);
        ModerationAuditLog::record(
            $flag,
            'decision',
            $request->user(),
            $previousStatus,
            $flag->status,
            $data['decision_reason'] ?? null,
            [
                ...ModerationAuditLog::contentMetadata($flag->flaggable),
                'action_taken' => $actionTaken,
            ]
        );

        return response()->json([
            'data' => $this->moderationFlagPayload(
                $flag->refresh()->load(['user:id,name', 'flaggable'])
            ),
        ]);
    }

    public function updateModerationReport(
        Request $request,
        ContentReport $report
    ) {
        $this->ensureSystemManager($request);
        $data = $this->validatedModerationDecision($request);
        $previousStatus = $report->status;
        $actionTaken = $data['status'];

        if ($data['remove_content'] ?? false) {
            $this->removeModeratedContent($report->reportable);
            $data['status'] = 'actioned';
            $actionTaken = 'content_removed';
        }
        $report->update([
            'status' => $data['status'],
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'decision_reason' => $data['decision_reason'] ?? null,
            'action_taken' => $actionTaken,
        ]);
        ModerationAuditLog::record(
            $report,
            'decision',
            $request->user(),
            $previousStatus,
            $report->status,
            $data['decision_reason'] ?? null,
            [
                ...ModerationAuditLog::contentMetadata($report->reportable),
                'action_taken' => $actionTaken,
            ]
        );

        return response()->json([
            'data' => $this->moderationReportPayload(
                $report->refresh()->load(['reporter:id,name', 'reportable'])
            ),
        ]);
    }

    public function decideModerationAppeal(
        Request $request,
        ContentReport $report
    ) {
        $this->ensureSystemManager($request);
        abort_unless(
            $report->appeal_status === 'pending',
            422,
            'Zu dieser Meldung ist keine offene Beschwerde vorhanden.'
        );
        $data = $request->validate([
            'appeal_status' => ['required', Rule::in(['accepted', 'rejected'])],
            'appeal_decision' => ['required', 'string', 'min:10', 'max:2000'],
        ]);
        $previousStatus = $report->appeal_status;
        $updates = [
            'appeal_status' => $data['appeal_status'],
            'appeal_decision' => $data['appeal_decision'],
            'appeal_decided_by' => $request->user()->id,
            'appeal_decided_at' => now(),
        ];
        if (
            $data['appeal_status'] === 'accepted'
            && $report->status === 'dismissed'
        ) {
            $updates['status'] = 'open';
        }
        $report->forceFill($updates)->save();
        ModerationAuditLog::record(
            $report,
            'appeal_decided',
            $request->user(),
            $previousStatus,
            $report->appeal_status,
            $data['appeal_decision'],
            [
                ...ModerationAuditLog::contentMetadata($report->reportable),
                'report_status' => $report->status,
            ]
        );

        return response()->json([
            'data' => $this->moderationReportPayload(
                $report->refresh()->load(['reporter:id,name', 'reportable'])
            ),
        ]);
    }

    public function updateGamificationRule(
        Request $request,
        GamificationRule $gamificationRule
    ) {
        $this->ensureSystemManager($request);
        $data = $request->validate([
            'label' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'xp_amount' => ['required', 'integer', 'min:-500', 'max:500'],
            'daily_limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'trust_delta' => ['required', 'integer', 'min:-50', 'max:50'],
            'is_active' => ['required', 'boolean'],
            'actor_type' => ['required', Rule::in(['sportler', 'trainer', 'verein', 'team'])],
        ]);
        if ($gamificationRule->actor_type !== $data['actor_type']) {
            throw ValidationException::withMessages([
                'actor_type' => 'Actor-Type und Regel passen nicht zusammen.',
            ]);
        }
        $xpAmount = (int) $data['xp_amount'];
        if ($gamificationRule->is_penalty && $xpAmount > 0) {
            throw ValidationException::withMessages([
                'xp_amount' => 'Strafregeln dürfen keine positiven XP vergeben.',
            ]);
        }
        if (! $gamificationRule->is_penalty && $xpAmount < 0) {
            throw ValidationException::withMessages([
                'xp_amount' => 'Positive Regeln dürfen keine XP abziehen.',
            ]);
        }
        if (
            $gamificationRule->is_penalty
            && ($data['daily_limit'] ?? null) !== null
        ) {
            throw ValidationException::withMessages([
                'daily_limit' => 'Daily Limits sind nur für positive Regeln vorgesehen.',
            ]);
        }
        $gamificationRule->update([
            'label' => $data['label'],
            'description' => $data['description'] ?? null,
            'xp_amount' => $data['xp_amount'],
            'daily_limit' => $data['daily_limit'] ?? null,
            'trust_delta' => $data['trust_delta'],
            'is_active' => $data['is_active'],
        ]);

        return response()->json(['data' => $gamificationRule->refresh()]);
    }

    private function ensureSystemManager(Request $request): void
    {
        abort_unless($request->user()?->can('system.manage'), 403);
    }

    private function ensureRoleManager(Request $request): void
    {
        $this->ensureSystemManager($request);
        abort_unless(
            $request->user()->can('users.assign_roles')
                || $request->user()->can('user.manage'),
            403
        );
    }

    private function ensureSystemRoleMutationAllowed(
        Request $request,
        Role $role
    ): void {
        if (! in_array($role->name, Roles::PROTECTED_PLATFORM, true)) {
            return;
        }
        abort_unless(
            $request->user()->hasRole('super_admin'),
            403,
            'Systemrollen dürfen nur von Super-Admins bearbeitet werden.'
        );
    }

    private function assertAllowedPermissionSelection(
        Request $request,
        array $permissions
    ): void {
        if ($request->user()->hasRole('super_admin')) {
            return;
        }
        $restricted = [
            'user.manage',
            'users.assign_roles',
            'system.manage',
            'security.manage',
            'logs.view',
            'api.manage',
            'billing.manage',
            'subscriptions.manage',
            'finance.edit',
            'outfit-subscriptions.manage',
        ];
        $selected = array_values(array_intersect($permissions, $restricted));
        abort_if(
            $selected !== [],
            403,
            'Diese Hochrisiko-Berechtigungen dürfen nur Super-Admins vergeben: '
                .implode(', ', $selected)
        );
    }

    private function rolePayload(
        Role $role,
        Request $request,
        int $usersCount
    ): array {
        $isSystem = in_array(
            $role->name,
            Roles::PROTECTED_PLATFORM,
            true
        );

        return [
            'id' => $role->id,
            'name' => $role->name,
            'description' => $role->description,
            'permissions' => $role->permissions
                ->sortBy('name')
                ->values()
                ->map(fn (Permission $permission) => [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'description' => $permission->description,
                ]),
            'users_count' => $usersCount,
            'is_system' => $isSystem,
            'can_edit' => ! $isSystem || $request->user()->hasRole('super_admin'),
            'can_delete' => ! $isSystem && $usersCount === 0,
        ];
    }

    private function validatedModerationDecision(Request $request): array
    {
        return $request->validate([
            'status' => ['required', Rule::in(['open', 'dismissed', 'actioned'])],
            'remove_content' => ['nullable', 'boolean'],
            'decision_reason' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function removeModeratedContent(?Model $model): void
    {
        if (! $model) {
            return;
        }
        if ($model->isFillable('moderation_status')) {
            $model->forceFill(['moderation_status' => 'removed'])->save();
        }
        if ($model instanceof Message) {
            $model->delete();
        }
    }

    private function moderationFlagPayload(ModerationFlag $flag): array
    {
        return [
            'id' => $flag->id,
            'source' => $flag->source,
            'severity' => $flag->severity,
            'status' => $flag->status,
            'categories' => $flag->categories ?: [],
            'matched_terms' => $flag->matched_terms ?: [],
            'decision_reason' => $flag->decision_reason,
            'action_taken' => $flag->action_taken,
            'created_at' => $flag->created_at?->toJSON(),
            'user' => $flag->user ? [
                'id' => $flag->user->id,
                'name' => $flag->user->name,
            ] : null,
            'content' => $this->moderatedContentPayload($flag->flaggable),
        ];
    }

    private function moderationReportPayload(ContentReport $report): array
    {
        return [
            'id' => $report->id,
            'reason' => $report->reason,
            'details' => $report->details,
            'status' => $report->status,
            'decision_reason' => $report->decision_reason,
            'action_taken' => $report->action_taken,
            'appeal_reason' => $report->appeal_reason,
            'appeal_status' => $report->appeal_status,
            'appeal_decision' => $report->appeal_decision,
            'created_at' => $report->created_at?->toJSON(),
            'reporter' => $report->reporter ? [
                'id' => $report->reporter->id,
                'name' => $report->reporter->name,
            ] : null,
            'content' => $this->moderatedContentPayload($report->reportable),
        ];
    }

    private function moderatedContentPayload(?Model $model): ?array
    {
        if (! $model) {
            return null;
        }
        if (method_exists($model, 'user')) {
            $model->loadMissing('user:id,name');
        }
        if (method_exists($model, 'sender')) {
            $model->loadMissing('sender:id,name');
        }

        return [
            'type' => class_basename($model),
            'id' => $model->getKey(),
            'text' => str(
                $model->content
                    ?? $model->message
                    ?? $model->caption
                    ?? 'Datei oder gelöschter Inhalt'
            )->limit(500)->toString(),
            'moderation_status' => $model->moderation_status ?? null,
            'author' => $model->user?->name ?? $model->sender?->name ?? null,
        ];
    }

    private function validatedSport(Request $request, ?Sport $sport = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('sports', 'name')->ignore($sport)],
            'slug' => ['nullable', 'string', 'max:140', Rule::unique('sports', 'slug')->ignore($sport)],
            'category' => ['nullable', 'string', 'max:120'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['boolean'],
        ]);
    }

    private function validatedBadge(Request $request, ?Badge $badge = null): array
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9][a-z0-9_-]*$/', Rule::unique('badges', 'key')->ignore($badge)],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'icon' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9\\s_-]+$/i'],
            'actor_type' => ['required', Rule::in(['sportler', 'trainer', 'verein', 'team'])],
            'trigger' => ['required', Rule::in(['xp', 'level', 'streak', 'reason'])],
            'threshold' => ['required', 'integer', 'min:0', 'max:1000000'],
            'meta' => ['nullable', 'array'],
            'meta.reason' => [Rule::requiredIf($request->input('trigger') === 'reason'), 'nullable', 'string', 'max:120', 'regex:/^[a-z0-9][a-z0-9_-]*$/'],
        ]);
        $data['meta'] = $data['trigger'] === 'reason'
            ? ['reason' => $data['meta']['reason']]
            : null;

        return $data;
    }

    private function sportPayloads()
    {
        $teamCounts = Team::query()
            ->select('sport_type', DB::raw('count(*) as aggregate'))
            ->whereNotNull('sport_type')
            ->groupBy('sport_type')
            ->pluck('aggregate', 'sport_type');
        $clubCounts = Club::query()
            ->select('sport_type', DB::raw('count(*) as aggregate'))
            ->whereNotNull('sport_type')
            ->groupBy('sport_type')
            ->pluck('aggregate', 'sport_type');
        $profileCounts = UserSport::query()
            ->select('sport_id', DB::raw('count(*) as aggregate'))
            ->groupBy('sport_id')
            ->pluck('aggregate', 'sport_id');
        $postCounts = Post::query()
            ->select('sport_id', DB::raw('count(*) as aggregate'))
            ->whereNotNull('sport_id')
            ->groupBy('sport_id')
            ->pluck('aggregate', 'sport_id');

        return Sport::query()
            ->withCount('skills')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function (Sport $sport) use ($teamCounts, $clubCounts, $profileCounts, $postCounts) {
                $teams = (int) ($teamCounts[$sport->slug] ?? 0) + (int) ($teamCounts[$sport->name] ?? 0);
                $clubs = (int) ($clubCounts[$sport->slug] ?? 0) + (int) ($clubCounts[$sport->name] ?? 0);
                $profiles = (int) ($profileCounts[$sport->id] ?? 0);
                $posts = (int) ($postCounts[$sport->id] ?? 0);

                return [
                    'id' => $sport->id,
                    'name' => $sport->name,
                    'slug' => $sport->slug,
                    'category' => $sport->category,
                    'is_active' => (bool) $sport->is_active,
                    'sort_order' => $sport->sort_order,
                    'skills_count' => $sport->skills_count,
                    'teams_count' => $teams,
                    'clubs_count' => $clubs,
                    'profiles_count' => $profiles,
                    'posts_count' => $posts,
                    'usage_count' => $teams + $clubs + $profiles + $posts,
                ];
            })
            ->values();
    }
}
