<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Club;
use App\Models\ClubBudget;
use App\Models\ClubDepartment;
use App\Models\ClubRoleAssignment;
use App\Models\ClubRoleDefinition;
use App\Models\ClubYearPeriod;
use App\Models\Team;
use App\Models\User;
use App\Support\ClubPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubBudgetHierarchyTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_editor_creates_versioned_budget_hierarchy_for_club_department_team_and_project(): void
    {
        $owner = User::factory()->create();
        $responsible = User::factory()->create(['name' => 'Budget Lead']);
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($responsible->id, ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active']);
        $period = $this->businessPeriod($club);
        $department = ClubDepartment::query()->create(['club_id' => $club->id, 'name' => 'Jugend']);
        $team = Team::factory()->create(['club_id' => $club->id, 'club_department_id' => $department->id]);

        Sanctum::actingAs($owner);
        $clubBudgetId = $this->postJson("/api/v1/clubs/{$club->id}/budgets", $this->payload($period, [
            'scope_type' => 'club',
            'name' => 'Vereinsbudget',
            'responsible_user_id' => $responsible->id,
            'planned_income_cents' => 150000,
            'planned_expense_cents' => 120000,
        ]))->assertCreated()->assertJsonPath('data.version', 1)->json('data.id');

        $departmentBudgetId = $this->postJson("/api/v1/clubs/{$club->id}/budgets", $this->payload($period, [
            'parent_id' => $clubBudgetId,
            'scope_type' => 'department',
            'club_department_id' => $department->id,
            'name' => 'Jugendbudget',
        ]))->assertCreated()->assertJsonPath('data.department_name', 'Jugend')->json('data.id');

        $this->postJson("/api/v1/clubs/{$club->id}/budgets", $this->payload($period, [
            'parent_id' => $departmentBudgetId,
            'scope_type' => 'team',
            'team_id' => $team->id,
            'name' => 'U17 Team',
        ]))->assertCreated()->assertJsonPath('data.team_name', $team->name);

        $this->postJson("/api/v1/clubs/{$club->id}/budgets", $this->payload($period, [
            'parent_id' => $clubBudgetId,
            'scope_type' => 'project',
            'project_name' => 'Sommercamp',
            'name' => 'Sommercamp 2026',
            'version' => 2,
            'approval_status' => 'submitted',
        ]))->assertCreated()->assertJsonPath('data.project_name', 'Sommercamp');

        $this->getJson("/api/v1/clubs/{$club->id}/budgets")
            ->assertOk()
            ->assertJsonCount(4, 'data.budgets')
            ->assertJsonPath('data.can_manage', true)
            ->assertJsonPath('data.can_approve', true)
            ->assertJsonPath('data.budgets.0.name', 'Vereinsbudget')
            ->assertJsonPath('data.budgets.0.responsible_name', 'Budget Lead');

        $activity = Activity::query()->where('type', 'club.budget.created')->latest('id')->firstOrFail();
        $this->assertSame('budget', $activity->data['entity_type']);
        $this->assertSame('project', $activity->data['scope_type']);
        $this->assertArrayNotHasKey('name', $activity->data);
    }

    public function test_budget_scope_rejects_foreign_period_department_team_parent_and_responsible(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $foreignClub = Club::factory()->create(['owner_id' => User::factory()]);
        $period = $this->businessPeriod($club);
        $foreignPeriod = $this->businessPeriod($foreignClub);
        $foreignDepartment = ClubDepartment::query()->create(['club_id' => $foreignClub->id, 'name' => 'Extern']);
        $foreignTeam = Team::factory()->create(['club_id' => $foreignClub->id]);
        $foreignResponsible = User::factory()->create();
        $foreignBudget = ClubBudget::query()->create($this->payload($foreignPeriod, [
            'club_id' => $foreignClub->id,
            'scope_type' => 'club',
            'name' => 'Foreign',
        ]));

        Sanctum::actingAs($owner);

        foreach ([
            ['club_year_period_id' => $foreignPeriod->id],
            ['scope_type' => 'department', 'club_department_id' => $foreignDepartment->id],
            ['scope_type' => 'team', 'team_id' => $foreignTeam->id],
            ['parent_id' => $foreignBudget->id],
            ['responsible_user_id' => $foreignResponsible->id],
        ] as $override) {
            $this->postJson("/api/v1/clubs/{$club->id}/budgets", $this->payload($period, $override))
                ->assertUnprocessable();
        }
    }

    public function test_finance_approval_permission_is_separate_from_edit_permission(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $approver = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        foreach ([$editor, $approver] as $member) {
            $club->users()->attach($member->id, ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active']);
        }
        $this->assignRole($club, $editor, [ClubPermissions::FINANCE_VIEW, ClubPermissions::FINANCE_EDIT], $owner);
        $this->assignRole($club, $approver, [ClubPermissions::FINANCE_VIEW, ClubPermissions::FINANCE_APPROVE], $owner);
        $period = $this->businessPeriod($club);

        Sanctum::actingAs($editor);
        $budgetId = $this->postJson("/api/v1/clubs/{$club->id}/budgets", $this->payload($period, [
            'scope_type' => 'project',
            'project_name' => 'Turnier',
            'name' => 'Turnierplanung',
            'approval_status' => 'submitted',
        ]))->assertCreated()->json('data.id');
        $this->putJson("/api/v1/clubs/{$club->id}/budgets/{$budgetId}/approval", ['approval_status' => 'approved'])->assertForbidden();

        Sanctum::actingAs($approver);
        $this->getJson("/api/v1/clubs/{$club->id}/budgets")
            ->assertOk()
            ->assertJsonPath('data.can_manage', false)
            ->assertJsonPath('data.can_approve', true);
        $this->putJson("/api/v1/clubs/{$club->id}/budgets/{$budgetId}/approval", ['approval_status' => 'approved'])
            ->assertOk()
            ->assertJsonPath('data.approval_status', 'approved');

        $this->assertNotNull(ClubBudget::findOrFail($budgetId)->approved_at);
    }

    private function payload(ClubYearPeriod $period, array $overrides = []): array
    {
        return array_replace([
            'club_year_period_id' => $period->id,
            'scope_type' => 'club',
            'name' => 'Budget',
            'version' => 1,
            'approval_status' => 'draft',
            'planned_income_cents' => 10000,
            'planned_expense_cents' => 9000,
        ], $overrides);
    }

    private function businessPeriod(Club $club): ClubYearPeriod
    {
        return ClubYearPeriod::query()->create([
            'club_id' => $club->id,
            'type' => 'business',
            'name' => 'Geschäftsjahr '.$club->id,
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
        ]);
    }

    private function assignRole(Club $club, User $member, array $permissions, User $owner): void
    {
        static $roleIndex = 0;
        $roleIndex++;

        $role = ClubRoleDefinition::query()->create([
            'club_id' => $club->id,
            'key' => 'budget_'.$roleIndex,
            'name' => 'Budget role',
            'permissions' => $permissions,
            'is_active' => true,
        ]);

        ClubRoleAssignment::query()->create([
            'club_id' => $club->id,
            'club_role_definition_id' => $role->id,
            'user_id' => $member->id,
            'scope_type' => 'club',
            'scope_id' => null,
            'scope_key' => 'club',
            'assigned_by' => $owner->id,
        ]);
    }
}
