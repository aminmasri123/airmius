<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Club;
use App\Models\ClubRoleAssignment;
use App\Models\ClubRoleDefinition;
use App\Models\ClubYearPeriod;
use App\Models\User;
use App\Support\ClubPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubYearPeriodTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_defines_business_contribution_and_sport_years_independently(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        Sanctum::actingAs($owner);

        foreach ([
            ['type' => 'business', 'name' => 'Geschäftsjahr 2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31'],
            ['type' => 'contribution', 'name' => 'Beitragsjahr 2026/27', 'starts_on' => '2026-04-01', 'ends_on' => '2027-03-31'],
            ['type' => 'sport', 'name' => 'Saison 2026/27', 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30'],
        ] as $payload) {
            $this->postJson("/api/v1/clubs/{$club->id}/year-periods", $payload)
                ->assertCreated()
                ->assertJsonPath('data.name', $payload['name']);
        }

        $this->getJson("/api/v1/clubs/{$club->id}/year-periods")
            ->assertOk()
            ->assertJsonCount(3, 'data.periods')
            ->assertJsonPath('data.periods.0.type', 'business')
            ->assertJsonPath('data.periods.1.type', 'contribution')
            ->assertJsonPath('data.periods.2.type', 'sport')
            ->assertJsonPath('data.can_manage', true)
            ->assertJsonPath('data.can_edit', true)
            ->assertJsonPath('data.can_delete', true)
            ->assertJsonPath('data.can_view_reports', true);

        $activity = Activity::query()->where('type', 'club.year_period.created')->latest('id')->firstOrFail();
        $this->assertSame(['entity_type' => 'year_period'], $activity->data);
        $this->assertStringNotContainsString('Saison', json_encode($activity->data));
    }

    public function test_periods_of_the_same_type_cannot_overlap(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        ClubYearPeriod::query()->create([
            'club_id' => $club->id, 'type' => 'business', 'name' => '2026',
            'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31',
        ]);
        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/clubs/{$club->id}/year-periods", [
            'type' => 'business', 'name' => 'Überschneidung',
            'starts_on' => '2026-12-31', 'ends_on' => '2027-12-30',
        ])->assertUnprocessable()->assertJsonValidationErrors('starts_on');

        $this->postJson("/api/v1/clubs/{$club->id}/year-periods", [
            'type' => 'business', 'name' => '2027',
            'starts_on' => '2027-01-01', 'ends_on' => '2027-12-31',
        ])->assertCreated();
    }

    public function test_member_can_read_but_cannot_manage_and_outsider_cannot_read(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id, 'is_listed' => true]);
        $club->users()->attach($member->id, ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active']);
        ClubYearPeriod::query()->create([
            'club_id' => $club->id, 'type' => 'sport', 'name' => 'Saison',
            'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30',
        ]);

        Sanctum::actingAs($member);
        $this->getJson("/api/v1/clubs/{$club->id}/year-periods")
            ->assertOk()->assertJsonCount(1, 'data.periods')->assertJsonPath('data.can_manage', false)
            ->assertJsonPath('data.can_edit', false)
            ->assertJsonPath('data.can_delete', false)
            ->assertJsonPath('data.can_view_reports', false);
        $this->postJson("/api/v1/clubs/{$club->id}/year-periods", [
            'type' => 'business', 'name' => 'Nicht erlaubt',
            'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31',
        ])->assertForbidden();

        Sanctum::actingAs($outsider);
        $this->getJson("/api/v1/clubs/{$club->id}/year-periods")->assertForbidden();
    }

    public function test_cross_club_period_is_rejected_while_own_period_can_be_updated_and_deleted(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $foreignClub = Club::factory()->create(['owner_id' => User::factory()]);
        $period = ClubYearPeriod::query()->create([
            'club_id' => $club->id, 'type' => 'contribution', 'name' => 'Beiträge 2026',
            'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31',
        ]);
        $foreignPeriod = ClubYearPeriod::query()->create([
            'club_id' => $foreignClub->id, 'type' => 'sport', 'name' => 'Fremd',
            'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31',
        ]);
        Sanctum::actingAs($owner);

        $payload = ['type' => 'contribution', 'name' => 'Beiträge 2026/27', 'starts_on' => '2026-04-01', 'ends_on' => '2027-03-31'];
        $this->putJson("/api/v1/clubs/{$club->id}/year-periods/{$foreignPeriod->id}", $payload)->assertNotFound();
        $this->putJson("/api/v1/clubs/{$club->id}/year-periods/{$period->id}", $payload)
            ->assertOk()->assertJsonPath('data.name', 'Beiträge 2026/27');
        $this->deleteJson("/api/v1/clubs/{$club->id}/year-periods/{$period->id}")->assertOk();
        $this->assertDatabaseMissing('club_year_periods', ['id' => $period->id]);
    }

    public function test_year_period_edit_and_delete_permissions_are_separated(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $deleter = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        foreach ([$editor, $deleter] as $member) {
            $club->users()->attach($member->id, [
                'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
            ]);
        }
        $editRole = $this->yearPeriodRole($club, 'year_editor', [ClubPermissions::YEAR_PERIODS_EDIT]);
        $deleteRole = $this->yearPeriodRole($club, 'year_deleter', [ClubPermissions::YEAR_PERIODS_DELETE]);
        $this->assignRole($club, $editor, $editRole, $owner);
        $this->assignRole($club, $deleter, $deleteRole, $owner);
        $period = ClubYearPeriod::query()->create([
            'club_id' => $club->id, 'type' => 'sport', 'name' => 'Saison 2026',
            'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31',
        ]);

        Sanctum::actingAs($editor);
        $this->getJson("/api/v1/clubs/{$club->id}/year-periods")
            ->assertOk()
            ->assertJsonPath('data.can_manage', true)
            ->assertJsonPath('data.can_edit', true)
            ->assertJsonPath('data.can_delete', false);
        $this->putJson("/api/v1/clubs/{$club->id}/year-periods/{$period->id}", [
            'type' => 'sport', 'name' => 'Saison aktualisiert',
            'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31',
        ])->assertOk();
        $this->deleteJson("/api/v1/clubs/{$club->id}/year-periods/{$period->id}")->assertForbidden();

        Sanctum::actingAs($deleter);
        $this->getJson("/api/v1/clubs/{$club->id}/year-periods")
            ->assertOk()
            ->assertJsonPath('data.can_edit', false)
            ->assertJsonPath('data.can_delete', true);
        $this->postJson("/api/v1/clubs/{$club->id}/year-periods", [
            'type' => 'business', 'name' => 'Nicht erlaubt',
            'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31',
        ])->assertForbidden();
        $this->deleteJson("/api/v1/clubs/{$club->id}/year-periods/{$period->id}")->assertOk();
    }

    private function yearPeriodRole(Club $club, string $key, array $permissions): ClubRoleDefinition
    {
        return ClubRoleDefinition::query()->create([
            'club_id' => $club->id,
            'key' => $key,
            'name' => str_replace('_', ' ', $key),
            'permissions' => $permissions,
            'is_active' => true,
        ]);
    }

    private function assignRole(Club $club, User $member, ClubRoleDefinition $role, User $owner): void
    {
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
