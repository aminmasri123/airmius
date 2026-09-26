<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubDepartment;
use App\Models\ClubPermissionDelegation;
use App\Models\ClubRoleAssignment;
use App\Models\ClubRoleDefinition;
use App\Models\ClubSubscription;
use App\Models\SubscriptionPlan;
use App\Models\Team;
use App\Models\User;
use App\Support\ClubPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubPermissionDelegationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_owner_can_grant_and_revoke_time_limited_permissions(): void
    {
        Carbon::setTestNow('2026-09-25 12:00:00');
        [$club, $owner, $member] = $this->clubWithMember();
        Sanctum::actingAs($owner);

        $response = $this->postJson("/api/v1/clubs/{$club->id}/permission-delegations", [
            'grantee_user_id' => $member->id,
            'permissions' => [ClubPermissions::FINANCE_VIEW],
            'ends_at' => now()->addDays(7)->toIso8601String(),
        ])->assertCreated()->assertJsonPath('data.status', 'active');

        $delegationId = $response->json('data.id');
        $this->assertTrue(ClubPermissions::allows($club, $member, ClubPermissions::FINANCE_VIEW));
        $this->assertDatabaseHas('activities', [
            'club_id' => $club->id,
            'type' => 'club.permission_delegation.created',
        ]);

        $this->postJson("/api/v1/clubs/{$club->id}/permission-delegations/{$delegationId}/revoke")
            ->assertOk()->assertJsonPath('data.status', 'revoked');
        $this->assertFalse(ClubPermissions::allows($club, $member, ClubPermissions::FINANCE_VIEW));
        $this->assertDatabaseHas('activities', [
            'club_id' => $club->id,
            'type' => 'club.permission_delegation.revoked',
        ]);
    }

    public function test_scheduled_and_expired_delegations_change_effective_permissions_only_inside_period(): void
    {
        Carbon::setTestNow('2026-09-25 12:00:00');
        [$club, $owner, $member] = $this->clubWithMember();
        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/clubs/{$club->id}/permission-delegations", [
            'grantee_user_id' => $member->id,
            'permissions' => [ClubPermissions::FINANCE_VIEW],
            'starts_at' => now()->addHour()->toIso8601String(),
            'ends_at' => now()->addDays(2)->toIso8601String(),
        ])->assertCreated()->assertJsonPath('data.status', 'scheduled');

        $this->assertFalse(ClubPermissions::allows($club, $member, ClubPermissions::FINANCE_VIEW));
        Carbon::setTestNow('2026-09-25 14:00:00');
        $this->assertTrue(ClubPermissions::allows($club, $member, ClubPermissions::FINANCE_VIEW));
        $club->users()->updateExistingPivot($member->id, ['membership_status' => 'former']);
        $this->assertFalse(ClubPermissions::allows($club, $member, ClubPermissions::FINANCE_VIEW));
        $club->users()->updateExistingPivot($member->id, ['membership_status' => 'active']);
        Carbon::setTestNow('2026-09-28 12:00:00');
        $this->assertFalse(ClubPermissions::allows($club, $member, ClubPermissions::FINANCE_VIEW));
    }

    public function test_delegation_rejects_privilege_escalation_self_grants_and_excessive_periods(): void
    {
        Carbon::setTestNow('2026-09-25 12:00:00');
        [$club, $owner, $member] = $this->clubWithMember();
        Sanctum::actingAs($owner);

        $base = [
            'grantee_user_id' => $member->id,
            'permissions' => [ClubPermissions::MEMBERS_ROLES],
            'ends_at' => now()->addDay()->toIso8601String(),
        ];
        $this->postJson("/api/v1/clubs/{$club->id}/permission-delegations", $base)
            ->assertUnprocessable()->assertJsonValidationErrors('permissions');

        $this->postJson("/api/v1/clubs/{$club->id}/permission-delegations", array_merge($base, [
            'grantee_user_id' => $owner->id,
            'permissions' => [ClubPermissions::FINANCE_VIEW],
        ]))->assertUnprocessable();

        $this->postJson("/api/v1/clubs/{$club->id}/permission-delegations", array_merge($base, [
            'permissions' => [ClubPermissions::FINANCE_VIEW],
            'ends_at' => now()->addDays(91)->toIso8601String(),
        ]))->assertUnprocessable()->assertJsonValidationErrors('ends_at');
        $this->assertDatabaseCount('club_permission_delegations', 0);
    }

    public function test_delegation_is_tenant_bound_and_requires_active_membership(): void
    {
        Carbon::setTestNow('2026-09-25 12:00:00');
        [$club, $owner, $member] = $this->clubWithMember();
        $foreignOwner = User::factory()->create();
        $foreignClub = Club::factory()->create(['owner_id' => $foreignOwner->id]);
        Sanctum::actingAs($owner);

        $payload = [
            'grantee_user_id' => $foreignOwner->id,
            'permissions' => [ClubPermissions::FINANCE_VIEW],
            'ends_at' => now()->addDay()->toIso8601String(),
        ];
        $this->postJson("/api/v1/clubs/{$club->id}/permission-delegations", $payload)->assertUnprocessable();

        $delegation = ClubPermissionDelegation::query()->create([
            'club_id' => $foreignClub->id,
            'grantor_user_id' => $foreignOwner->id,
            'grantee_user_id' => $member->id,
            'permissions' => [ClubPermissions::FINANCE_VIEW],
            'starts_at' => now(),
            'ends_at' => now()->addDay(),
        ]);
        $this->postJson("/api/v1/clubs/{$club->id}/permission-delegations/{$delegation->id}/revoke")->assertNotFound();
        $this->assertTrue($delegation->fresh()->revoked_at === null);
    }

    public function test_non_role_manager_cannot_list_or_create_delegations(): void
    {
        [$club, , $member] = $this->clubWithMember();
        Sanctum::actingAs($member);

        $this->getJson("/api/v1/clubs/{$club->id}/permission-delegations")->assertForbidden();
        $this->postJson("/api/v1/clubs/{$club->id}/permission-delegations", [])->assertForbidden();
    }

    public function test_delegations_can_be_limited_to_a_department_or_team(): void
    {
        Carbon::setTestNow('2026-09-25 12:00:00');
        [$club, $owner, $member] = $this->clubWithMember();
        $unlimitedPlan = SubscriptionPlan::query()->whereNull('team_limit')->firstOrFail();
        ClubSubscription::query()->updateOrCreate(
            ['club_id' => $club->id],
            ['subscription_plan_id' => $unlimitedPlan->id, 'status' => 'active'],
        );
        $department = ClubDepartment::query()->create([
            'club_id' => $club->id, 'name' => 'Jugend', 'is_public' => false,
        ]);
        $otherDepartment = ClubDepartment::query()->create([
            'club_id' => $club->id, 'name' => 'Senioren', 'is_public' => false,
        ]);
        $departmentTeam = Team::factory()->create([
            'club_id' => $club->id, 'club_department_id' => $department->id,
        ]);
        $team = Team::factory()->create([
            'club_id' => $club->id, 'club_department_id' => $otherDepartment->id,
        ]);
        $otherTeam = Team::factory()->create([
            'club_id' => $club->id, 'club_department_id' => $otherDepartment->id,
        ]);
        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/clubs/{$club->id}/permission-delegations", [
            'grantee_user_id' => $member->id,
            'permissions' => [
                ClubPermissions::FILES_EDIT,
                ClubPermissions::ORGANIZATION_EDIT,
                ClubPermissions::TEAMS_EDIT,
            ],
            'scope_type' => 'department',
            'scope_id' => $department->id,
            'ends_at' => now()->addWeek()->toIso8601String(),
        ])->assertCreated()
            ->assertJsonPath('data.scope_type', 'department')
            ->assertJsonPath('data.scope_id', $department->id);

        $this->assertFalse(ClubPermissions::allows($club, $member, ClubPermissions::FILES_EDIT));
        $this->assertTrue(ClubPermissions::allowsInScope($club, $member, ClubPermissions::FILES_EDIT, 'department', $department->id));
        $this->assertTrue(ClubPermissions::allowsAnyScope($club, $member, ClubPermissions::FILES_EDIT));
        $this->assertTrue(ClubPermissions::allowsAnyDepartmentScope($club, $member, ClubPermissions::FILES_EDIT));
        $this->assertFalse(ClubPermissions::allowsInScope($club, $member, ClubPermissions::FILES_EDIT, 'department', $otherDepartment->id));
        $this->assertTrue(ClubPermissions::allowsForTeam($departmentTeam, $member, ClubPermissions::FILES_EDIT));
        $this->assertFalse(ClubPermissions::allowsForTeam($team, $member, ClubPermissions::FILES_EDIT));

        Sanctum::actingAs($member);
        $this->getJson("/api/v1/clubs/{$club->id}/organization")
            ->assertOk()
            ->assertJsonPath('data.can_edit', true)
            ->assertJsonPath('data.can_create_departments', false)
            ->assertJsonPath('data.can_create_training_groups', true);
        $this->getJson("/api/v1/clubs/{$club->id}")
            ->assertOk()
            ->assertJsonPath('data.can_edit_teams', true)
            ->assertJsonPath('data.can_create_teams_globally', false)
            ->assertJsonPath('data.team_creation_departments.0.id', $department->id);
        $teamStoreHeaders = [
            'Authorization' => 'Bearer '.$member->createToken('delegated-team-create')->plainTextToken,
        ];
        $this->postJson('/api/v1/teams', [
            'club_id' => $club->id,
            'club_department_id' => $department->id,
            'name' => 'Delegierte Jugendmannschaft',
        ], $teamStoreHeaders)->assertCreated();
        $this->postJson('/api/v1/teams', [
            'club_id' => $club->id,
            'club_department_id' => $otherDepartment->id,
            'name' => 'Delegierte Fremdmannschaft',
        ], $teamStoreHeaders)->assertForbidden();

        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/clubs/{$club->id}/permission-delegations", [
            'grantee_user_id' => $member->id,
            'permissions' => [ClubPermissions::EVENTS_EDIT],
            'scope_type' => 'team',
            'scope_id' => $team->id,
            'ends_at' => now()->addWeek()->toIso8601String(),
        ])->assertCreated()->assertJsonPath('data.scope_type', 'team');

        $this->assertTrue(ClubPermissions::allowsForTeam($team, $member, ClubPermissions::EVENTS_EDIT));
        $this->assertTrue(ClubPermissions::allowsAnyScope($club, $member, ClubPermissions::EVENTS_EDIT));
        $this->assertFalse(ClubPermissions::allowsAnyDepartmentScope($club, $member, ClubPermissions::EVENTS_EDIT));
        $this->assertFalse(ClubPermissions::allowsForTeam($otherTeam, $member, ClubPermissions::EVENTS_EDIT));
    }

    public function test_scoped_delegation_rejects_foreign_or_malformed_scope(): void
    {
        Carbon::setTestNow('2026-09-25 12:00:00');
        [$club, $owner, $member] = $this->clubWithMember();
        $foreignClub = Club::factory()->create(['owner_id' => User::factory()]);
        $foreignTeam = Team::factory()->create(['club_id' => $foreignClub->id]);
        Sanctum::actingAs($owner);
        $payload = [
            'grantee_user_id' => $member->id,
            'permissions' => [ClubPermissions::EVENTS_EDIT],
            'ends_at' => now()->addDay()->toIso8601String(),
        ];

        $this->postJson("/api/v1/clubs/{$club->id}/permission-delegations", [
            ...$payload, 'scope_type' => 'team', 'scope_id' => $foreignTeam->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('scope_id');
        $this->postJson("/api/v1/clubs/{$club->id}/permission-delegations", [
            ...$payload, 'scope_type' => 'club', 'scope_id' => $foreignTeam->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('scope_id');
        $this->assertDatabaseCount('club_permission_delegations', 0);
    }

    public function test_delegator_cannot_forward_a_permission_outside_their_own_scope(): void
    {
        Carbon::setTestNow('2026-09-25 12:00:00');
        [$club, $owner, $grantee] = $this->clubWithMember();
        $delegator = User::factory()->create();
        $club->users()->attach($delegator->id, [
            'role' => 'financial_controller', 'roles' => ['financial_controller'], 'membership_status' => 'active',
        ]);
        $department = ClubDepartment::query()->create([
            'club_id' => $club->id, 'name' => 'Jugend', 'is_public' => false,
        ]);
        $otherDepartment = ClubDepartment::query()->create([
            'club_id' => $club->id, 'name' => 'Senioren', 'is_public' => false,
        ]);
        $roleManager = ClubRoleDefinition::query()->create([
            'club_id' => $club->id, 'key' => 'role_manager', 'name' => 'Rollenverwaltung',
            'permissions' => [ClubPermissions::MEMBERS_ROLES], 'is_active' => true,
        ]);
        $fileEditor = ClubRoleDefinition::query()->create([
            'club_id' => $club->id, 'key' => 'department_file_editor', 'name' => 'Dateipflege',
            'permissions' => [ClubPermissions::FILES_EDIT], 'is_active' => true,
        ]);
        foreach ([
            [$roleManager, 'club', null, 'club'],
            [$fileEditor, 'department', $department->id, 'department:'.$department->id],
        ] as [$role, $scopeType, $scopeId, $scopeKey]) {
            ClubRoleAssignment::query()->create([
                'club_id' => $club->id,
                'club_role_definition_id' => $role->id,
                'user_id' => $delegator->id,
                'scope_type' => $scopeType,
                'scope_id' => $scopeId,
                'scope_key' => $scopeKey,
                'assigned_by' => $owner->id,
            ]);
        }
        Sanctum::actingAs($delegator);
        $payload = [
            'grantee_user_id' => $grantee->id,
            'permissions' => [ClubPermissions::FILES_EDIT],
            'scope_type' => 'department',
            'ends_at' => now()->addDay()->toIso8601String(),
        ];

        $this->postJson("/api/v1/clubs/{$club->id}/permission-delegations", [
            ...$payload, 'scope_id' => $department->id,
        ])->assertCreated();
        $this->postJson("/api/v1/clubs/{$club->id}/permission-delegations", [
            ...$payload, 'scope_id' => $otherDepartment->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('permissions');

        $this->assertTrue(ClubPermissions::allowsInScope(
            $club, $grantee, ClubPermissions::FILES_EDIT, 'department', $department->id,
        ));
        $this->assertFalse(ClubPermissions::allowsInScope(
            $club, $grantee, ClubPermissions::FILES_EDIT, 'department', $otherDepartment->id,
        ));
    }

    public function test_scoped_delegation_migration_is_reversible(): void
    {
        $migration = require database_path('migrations/2026_09_25_000006_scope_club_permission_delegations.php');

        $migration->down();
        $this->assertFalse(Schema::hasColumn('club_permission_delegations', 'scope_type'));
        $this->assertFalse(Schema::hasColumn('club_permission_delegations', 'scope_id'));
        $this->assertFalse(Schema::hasColumn('club_permission_delegations', 'scope_key'));

        $migration->up();
        $this->assertTrue(Schema::hasColumn('club_permission_delegations', 'scope_type'));
        $this->assertTrue(Schema::hasColumn('club_permission_delegations', 'scope_id'));
        $this->assertTrue(Schema::hasColumn('club_permission_delegations', 'scope_key'));
    }

    private function clubWithMember(): array
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($member->id, [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'active',
        ]);

        return [$club, $owner, $member];
    }
}
