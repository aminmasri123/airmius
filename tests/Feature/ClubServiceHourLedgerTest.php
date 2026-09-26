<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubRoleAssignment;
use App\Models\ClubRoleDefinition;
use App\Models\ClubServiceHourRecord;
use App\Models\ClubYearPeriod;
use App\Models\User;
use App\Support\ClubPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubServiceHourLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_hours_are_confirmed_corrected_exempted_and_summarized_per_role_and_period(): void
    {
        [$club, $owner, $member, $manager, $period, $role] = $this->fixture();
        $this->assign($club, $member, $role, $owner);
        Sanctum::actingAs($manager);

        $this->postJson("/api/v1/clubs/{$club->id}/service-hour-requirements", [
            'period_id' => $period->id,
            'role_definition_id' => $role->id,
            'required_minutes' => 600,
            'replacement_rate_cents' => 1500,
        ])->assertCreated()
            ->assertJsonPath('data.required_minutes', 600)
            ->assertJsonPath('data.replacement_rate_cents', 1500);

        $recordId = $this->postJson("/api/v1/clubs/{$club->id}/service-hour-records", [
            'period_id' => $period->id,
            'user_id' => $member->id,
            'served_on' => '2026-04-12',
            'minutes' => 240,
            'title' => 'Aufbau Heimturnier',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->json('data.id');

        $this->postJson("/api/v1/clubs/{$club->id}/service-hour-records/{$recordId}/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.confirmation_snapshot.minutes', 240);

        $this->postJson("/api/v1/clubs/{$club->id}/service-hour-records/{$recordId}/corrections", [
            'minutes_delta' => -30,
            'reason' => 'Pause versehentlich mitgezählt',
        ])->assertCreated()
            ->assertJsonPath('data.previous_total_minutes', 240)
            ->assertJsonPath('data.corrected_total_minutes', 210);

        $this->postJson("/api/v1/clubs/{$club->id}/service-hour-exemptions", [
            'period_id' => $period->id,
            'user_id' => $member->id,
            'minutes' => 120,
            'reason' => 'Vorstandsfreigabe wegen Krankheit',
        ])->assertCreated();

        $this->getJson("/api/v1/clubs/{$club->id}/service-hours?period_id={$period->id}")
            ->assertOk()
            ->assertJsonFragment([
                'user_id' => $member->id,
                'required_minutes' => 600,
                'confirmed_minutes' => 240,
                'correction_minutes' => -30,
                'exempted_minutes' => 120,
                'credited_minutes' => 330,
                'remaining_minutes' => 270,
            ]);

        $this->assertDatabaseHas('club_service_hour_records', ['id' => $recordId, 'minutes' => 240, 'status' => 'confirmed']);
        $this->assertDatabaseCount('club_service_hour_corrections', 1);
    }

    public function test_replacement_hours_credit_helper_and_keep_replaced_member_visible(): void
    {
        [$club, $owner, $member, $manager, $period, $role] = $this->fixture();
        $helper = User::factory()->create(['name' => 'Ersatz Helfer']);
        $club->users()->attach($helper->id, ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active']);
        $this->assign($club, $helper, $role, $owner);
        Sanctum::actingAs($manager);

        $this->postJson("/api/v1/clubs/{$club->id}/service-hour-requirements", [
            'period_id' => $period->id,
            'role_definition_id' => $role->id,
            'required_minutes' => 300,
        ])->assertCreated();

        $recordId = $this->postJson("/api/v1/clubs/{$club->id}/service-hour-records", [
            'period_id' => $period->id,
            'user_id' => $helper->id,
            'replacement_for_user_id' => $member->id,
            'served_on' => '2026-05-01',
            'minutes' => 180,
            'title' => 'Ersatzdienst Catering',
        ])->assertCreated()
            ->assertJsonPath('data.kind', 'replacement')
            ->assertJsonPath('data.replacement_for_user_id', $member->id)
            ->json('data.id');

        $this->postJson("/api/v1/clubs/{$club->id}/service-hour-records/{$recordId}/confirm")->assertOk();
        $this->getJson("/api/v1/clubs/{$club->id}/service-hours?period_id={$period->id}")
            ->assertOk()
            ->assertJsonFragment(['user_id' => $helper->id, 'confirmed_minutes' => 180, 'remaining_minutes' => 120]);
    }

    public function test_roles_permissions_period_scope_and_locked_period_are_enforced(): void
    {
        [$club, $owner, $member, $manager, $period, $role] = $this->fixture();
        $foreignClub = Club::factory()->create(['owner_id' => User::factory()]);
        $foreignPeriod = ClubYearPeriod::query()->create([
            'club_id' => $foreignClub->id,
            'type' => 'contribution',
            'name' => 'Fremd',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
        ]);
        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/v1/clubs/{$club->id}/service-hours?period_id={$period->id}")->assertForbidden();

        Sanctum::actingAs($manager);
        $this->postJson("/api/v1/clubs/{$club->id}/service-hour-requirements", [
            'period_id' => $foreignPeriod->id,
            'role_definition_id' => $role->id,
            'required_minutes' => 60,
        ])->assertNotFound();

        $requirementId = $this->postJson("/api/v1/clubs/{$club->id}/service-hour-requirements", [
            'period_id' => $period->id,
            'role_definition_id' => $role->id,
            'required_minutes' => 60,
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/v1/clubs/{$club->id}/service-hour-requirements/{$requirementId}/lock")->assertOk();
        $this->postJson("/api/v1/clubs/{$club->id}/service-hour-records", [
            'period_id' => $period->id,
            'user_id' => $member->id,
            'served_on' => '2026-04-12',
            'minutes' => 60,
            'title' => 'Spätdienst',
        ])->assertUnprocessable()->assertJsonValidationErrors('period_id');
    }

    private function fixture(): array
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['name' => 'Mitglied Pflicht']);
        $manager = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($member->id, ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active']);
        $club->users()->attach($manager->id, ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active']);
        $period = ClubYearPeriod::query()->create([
            'club_id' => $club->id,
            'type' => 'contribution',
            'name' => 'Beitragsjahr 2026',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
        ]);
        $role = ClubRoleDefinition::query()->create([
            'club_id' => $club->id,
            'key' => 'active_member',
            'name' => 'Aktives Mitglied',
            'permissions' => [],
            'is_active' => true,
        ]);
        $managerRole = ClubRoleDefinition::query()->create([
            'club_id' => $club->id,
            'key' => 'service_manager',
            'name' => 'Pflichtstundenverwaltung',
            'permissions' => [
                ClubPermissions::MEMBERS_VIEW,
                ClubPermissions::MEMBERS_MANAGE,
            ],
            'is_active' => true,
        ]);
        $this->assign($club, $manager, $managerRole, $owner);

        return [$club, $owner, $member, $manager, $period, $role];
    }

    private function assign(Club $club, User $user, ClubRoleDefinition $role, User $owner): void
    {
        ClubRoleAssignment::query()->create([
            'club_id' => $club->id,
            'club_role_definition_id' => $role->id,
            'user_id' => $user->id,
            'scope_type' => 'club',
            'scope_id' => null,
            'scope_key' => 'club',
            'assigned_by' => $owner->id,
        ]);
    }
}
