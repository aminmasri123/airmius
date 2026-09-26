<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubRoleAssignment;
use App\Models\ClubRoleDefinition;
use App\Models\Team;
use App\Models\TeamFee;
use App\Models\TeamPenaltyRule;
use App\Models\User;
use App\Support\ClubPermissions;
use App\Support\TeamRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileTeamPenaltyApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_staff_can_manage_rules_and_fees(): void
    {
        [$team, $coach, $player] = $this->teamWithMembers();
        Sanctum::actingAs($coach);

        $this->getJson('/api/v1/teams/'.$team->id.'/penalties')
            ->assertOk()
            ->assertJsonPath('data.can_manage', true)
            ->assertJsonPath('data.rules', []);

        $rule = $this->postJson('/api/v1/teams/'.$team->id.'/penalty-rules', [
            'title' => 'Verspätung',
            'trigger' => 'late',
            'calculation_type' => 'fixed',
            'amount' => 5,
            'currency' => 'EUR',
            'is_active' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Verspätung')
            ->json('data.id');

        $fee = $this->postJson('/api/v1/teams/'.$team->id.'/penalty-fees', [
            'user_id' => $player->id,
            'penalty_rule_id' => $rule,
            'amount' => 5,
            'note' => 'Training',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'open')
            ->json('data.id');

        $this->getJson('/api/v1/teams/'.$team->id.'/penalties')
            ->assertOk()
            ->assertJsonPath('data.rules.0.id', $rule)
            ->assertJsonPath('data.fees.0.id', $fee)
            ->assertJsonPath('data.summary.open_count', 1);

        $this->postJson('/api/v1/teams/'.$team->id.'/penalty-fees/'.$fee.'/paid')
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');

        $this->assertDatabaseHas('team_fees', [
            'id' => $fee,
            'team_id' => $team->id,
            'status' => 'paid',
        ]);
    }

    public function test_members_can_only_read_their_own_fees_and_cannot_manage_cashbox(): void
    {
        [$team, $coach, $player] = $this->teamWithMembers();
        $rule = TeamPenaltyRule::query()->create([
            'team_id' => $team->id,
            'title' => 'Ausrüstung',
            'trigger' => 'forgotten_equipment',
            'calculation_type' => 'fixed',
            'amount' => 8,
            'currency' => 'EUR',
            'is_active' => true,
        ]);
        TeamFee::query()->create([
            'team_id' => $team->id,
            'user_id' => $player->id,
            'collector_id' => $coach->id,
            'penalty_rule_id' => $rule->id,
            'category' => 'penalty',
            'amount' => 8,
            'currency' => 'EUR',
            'status' => 'open',
        ]);
        TeamFee::query()->create([
            'team_id' => $team->id,
            'user_id' => $coach->id,
            'collector_id' => $coach->id,
            'penalty_rule_id' => $rule->id,
            'category' => 'penalty',
            'amount' => 4,
            'currency' => 'EUR',
            'status' => 'open',
        ]);

        Sanctum::actingAs($player);

        $this->getJson('/api/v1/teams/'.$team->id.'/penalties')
            ->assertOk()
            ->assertJsonPath('data.can_manage', false)
            ->assertJsonCount(1, 'data.fees')
            ->assertJsonPath('data.fees.0.user_id', $player->id);

        $this->postJson('/api/v1/teams/'.$team->id.'/penalty-rules', [
            'title' => 'Unbefugte Regel',
            'amount' => 10,
        ])->assertForbidden();

        $this->postJson('/api/v1/teams/'.$team->id.'/penalty-fees', [
            'user_id' => $player->id,
            'amount' => 10,
        ])->assertForbidden();
    }

    public function test_scoped_cashbox_role_is_limited_to_its_team_and_explicit_denial_blocks_team_staff(): void
    {
        $owner = User::factory()->create();
        $cashier = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $assignedTeam = Team::factory()->create(['club_id' => $club->id]);
        $otherTeam = Team::factory()->create(['club_id' => $club->id]);
        $club->users()->attach($cashier->id, [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'active',
        ]);
        $assignedTeam->users()->attach($cashier->id, ['role' => TeamRoles::PLAYER]);
        $otherTeam->users()->attach($cashier->id, ['role' => TeamRoles::PLAYER]);
        $role = ClubRoleDefinition::query()->create([
            'club_id' => $club->id,
            'key' => 'team_cashier',
            'name' => 'Mannschaftskasse',
            'permissions' => [ClubPermissions::TEAM_CASHBOX_MANAGE],
            'is_active' => true,
        ]);
        ClubRoleAssignment::query()->create([
            'club_id' => $club->id,
            'club_role_definition_id' => $role->id,
            'user_id' => $cashier->id,
            'scope_type' => 'team',
            'scope_id' => $assignedTeam->id,
            'scope_key' => 'team:'.$assignedTeam->id,
            'assigned_by' => $owner->id,
        ]);

        Sanctum::actingAs($cashier);
        $this->getJson('/api/v1/teams/'.$assignedTeam->id.'/penalties')
            ->assertOk()
            ->assertJsonPath('data.can_manage', true);
        $this->getJson('/api/v1/teams/'.$assignedTeam->id.'/daily-life')
            ->assertOk()
            ->assertJsonPath('data.access.can_manage_operations', false)
            ->assertJsonPath('data.access.can_manage_cash_box', true)
            ->assertJsonPath('data.cash_box.can_manage', true)
            ->assertJsonPath('data.attendance.missing_responses', [])
            ->assertJsonPath('data.guardian_mode.visible', false);
        $this->postJson('/api/v1/teams/'.$assignedTeam->id.'/penalty-rules', [
            'title' => 'Material vergessen',
            'amount' => 3,
        ])->assertCreated();
        $this->getJson('/api/v1/teams/'.$otherTeam->id.'/penalties')
            ->assertOk()
            ->assertJsonPath('data.can_manage', false);
        $this->postJson('/api/v1/teams/'.$otherTeam->id.'/penalty-rules', [
            'title' => 'Nicht erlaubt',
            'amount' => 9,
        ])->assertForbidden();

        $assignedTeam->users()->updateExistingPivot($cashier->id, ['role' => TeamRoles::COACH]);
        $club->users()->updateExistingPivot($cashier->id, [
            'permission_overrides' => [ClubPermissions::TEAM_CASHBOX_MANAGE => false],
        ]);

        $this->getJson('/api/v1/teams/'.$assignedTeam->id.'/penalties')
            ->assertOk()
            ->assertJsonPath('data.can_manage', false);
        $this->getJson('/api/v1/teams/'.$assignedTeam->id.'/daily-life')
            ->assertOk()
            ->assertJsonPath('data.access.can_manage_cash_box', false)
            ->assertJsonPath('data.cash_box.can_manage', false);
        $this->postJson('/api/v1/teams/'.$assignedTeam->id.'/penalty-rules', [
            'title' => 'Trotz Sperre',
            'amount' => 4,
        ])->assertForbidden();
    }

    /** @return array{0: Team, 1: User, 2: User} */
    private function teamWithMembers(): array
    {
        $owner = User::factory()->create();
        $coach = User::factory()->create();
        $player = User::factory()->create();
        $club = Club::query()->create([
            'owner_id' => $owner->id,
            'name' => 'Penalty Club',
        ]);
        $team = Team::factory()->create(['club_id' => $club->id]);
        $team->users()->attach($coach->id, ['role' => TeamRoles::COACH]);
        $team->users()->attach($player->id, ['role' => TeamRoles::PLAYER]);

        return [$team, $coach, $player];
    }
}
