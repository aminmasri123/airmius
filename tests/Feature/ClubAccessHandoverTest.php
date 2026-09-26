<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubRoleAssignment;
use App\Models\ClubRoleDefinition;
use App\Models\User;
use App\Services\ClubAccessHandoverService;
use App\Support\ClubPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubAccessHandoverTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_people_approve_successor_and_scheduler_applies_unchanged_snapshot(): void
    {
        [$club, $owner, $proposer, $approver, $departing, $successor, $role] = $this->context();
        $dueOn = now()->addDays(7)->toDateString();
        $club->users()->updateExistingPivot($departing->id, ['membership_ends_on' => $dueOn]);
        $review = app(ClubAccessHandoverService::class)->ensure($club, $departing, $dueOn);
        $this->assertNotNull($review);

        Sanctum::actingAs($proposer);
        $this->postJson("/api/v1/clubs/{$club->id}/access-handover-reviews/{$review->id}/propose", [
            'decision' => 'assign_successor',
            'successor_user_id' => $successor->id,
            'note' => 'Geordnete Übergabe',
        ])->assertOk()
            ->assertJsonPath('data.status', 'proposed')
            ->assertJsonPath('data.successor.id', $successor->id)
            ->assertJsonPath('data.assignment_count', 1);

        $this->postJson("/api/v1/clubs/{$club->id}/access-handover-reviews/{$review->id}/approve")
            ->assertUnprocessable();

        Sanctum::actingAs($approver);
        $this->postJson("/api/v1/clubs/{$club->id}/access-handover-reviews/{$review->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.approver.id', $approver->id);

        $this->assertDatabaseMissing('club_role_assignments', [
            'club_id' => $club->id,
            'user_id' => $successor->id,
            'club_role_definition_id' => $role->id,
        ]);

        $this->artisan('airmius:process-membership-terminations', ['--date' => $dueOn])
            ->assertExitCode(0);

        $this->assertDatabaseMissing('club_role_assignments', [
            'club_id' => $club->id,
            'user_id' => $departing->id,
        ]);
        $this->assertDatabaseHas('club_role_assignments', [
            'club_id' => $club->id,
            'user_id' => $successor->id,
            'club_role_definition_id' => $role->id,
            'scope_key' => 'club',
            'assigned_by' => $approver->id,
        ]);
        $this->assertDatabaseHas('club_access_handover_reviews', [
            'id' => $review->id,
            'status' => 'applied',
            'proposed_by' => $proposer->id,
            'approved_by' => $approver->id,
        ]);
        $this->assertDatabaseHas('activities', [
            'club_id' => $club->id,
            'type' => 'club.role_access_handover.applied',
        ]);
    }

    public function test_changed_roles_require_a_fresh_proposal_before_approval(): void
    {
        [$club, $owner, $proposer, $approver, $departing, $successor] = $this->context();
        $review = app(ClubAccessHandoverService::class)->ensure($club, $departing, now()->addWeek());
        Sanctum::actingAs($proposer);

        $foreignClub = Club::factory()->create(['owner_id' => User::factory()]);
        $foreign = User::factory()->create();
        $foreignClub->users()->attach($foreign->id, [
            'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
        ]);
        $this->postJson("/api/v1/clubs/{$club->id}/access-handover-reviews/{$review->id}/propose", [
            'decision' => 'assign_successor', 'successor_user_id' => $foreign->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('successor_user_id');

        $club->users()->updateExistingPivot($successor->id, ['membership_status' => 'former']);
        $this->postJson("/api/v1/clubs/{$club->id}/access-handover-reviews/{$review->id}/propose", [
            'decision' => 'assign_successor', 'successor_user_id' => $successor->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('successor_user_id');
        $club->users()->updateExistingPivot($successor->id, ['membership_status' => 'active']);

        $this->postJson("/api/v1/clubs/{$club->id}/access-handover-reviews/{$review->id}/propose", [
            'decision' => 'assign_successor', 'successor_user_id' => $successor->id,
        ])->assertOk();
        $extra = ClubRoleDefinition::query()->create([
            'club_id' => $club->id,
            'key' => 'extra_reader',
            'name' => 'Extra reader',
            'permissions' => [ClubPermissions::MEMBERS_VIEW],
            'is_active' => true,
        ]);
        ClubRoleAssignment::query()->create([
            'club_id' => $club->id,
            'club_role_definition_id' => $extra->id,
            'user_id' => $departing->id,
            'scope_type' => 'club',
            'scope_key' => 'club',
            'assigned_by' => $owner->id,
        ]);

        Sanctum::actingAs($approver);
        $this->postJson("/api/v1/clubs/{$club->id}/access-handover-reviews/{$review->id}/approve")
            ->assertUnprocessable()->assertJsonValidationErrors('review');
        $this->assertDatabaseHas('club_access_handover_reviews', ['id' => $review->id, 'status' => 'proposed']);

        Sanctum::actingAs($proposer);
        $this->postJson("/api/v1/clubs/{$club->id}/access-handover-reviews/{$review->id}/propose", [
            'decision' => 'assign_successor', 'successor_user_id' => $successor->id,
        ])->assertOk()->assertJsonPath('data.assignment_count', 2);
        Sanctum::actingAs($approver);
        $this->postJson("/api/v1/clubs/{$club->id}/access-handover-reviews/{$review->id}/approve")
            ->assertOk()->assertJsonPath('data.status', 'approved');
    }

    public function test_state_change_after_approval_fails_closed_at_membership_end(): void
    {
        [$club, $owner, $proposer, $approver, $departing, $successor] = $this->context();
        $dueOn = now()->addDays(5)->toDateString();
        $club->users()->updateExistingPivot($departing->id, ['membership_ends_on' => $dueOn]);
        $review = app(ClubAccessHandoverService::class)->ensure($club, $departing, $dueOn);

        Sanctum::actingAs($proposer);
        $this->postJson("/api/v1/clubs/{$club->id}/access-handover-reviews/{$review->id}/propose", [
            'decision' => 'assign_successor', 'successor_user_id' => $successor->id,
        ])->assertOk();
        Sanctum::actingAs($approver);
        $this->postJson("/api/v1/clubs/{$club->id}/access-handover-reviews/{$review->id}/approve")->assertOk();

        $club->users()->updateExistingPivot($successor->id, ['membership_status' => 'former']);
        $this->artisan('airmius:process-membership-terminations', ['--date' => $dueOn])->assertExitCode(0);

        $this->assertDatabaseHas('club_access_handover_reviews', ['id' => $review->id, 'status' => 'stale']);
        $this->assertDatabaseMissing('club_role_assignments', ['club_id' => $club->id, 'user_id' => $successor->id]);
        $this->assertDatabaseMissing('club_role_assignments', ['club_id' => $club->id, 'user_id' => $departing->id]);
    }

    public function test_role_definition_change_after_approval_invalidates_the_handover_snapshot(): void
    {
        [$club, $owner, $proposer, $approver, $departing, $successor, $role] = $this->context();
        $dueOn = now()->addDays(6)->toDateString();
        $club->users()->updateExistingPivot($departing->id, ['membership_ends_on' => $dueOn]);
        $review = app(ClubAccessHandoverService::class)->ensure($club, $departing, $dueOn);

        Sanctum::actingAs($proposer);
        $this->postJson("/api/v1/clubs/{$club->id}/access-handover-reviews/{$review->id}/propose", [
            'decision' => 'assign_successor', 'successor_user_id' => $successor->id,
        ])->assertOk();
        Sanctum::actingAs($approver);
        $this->postJson("/api/v1/clubs/{$club->id}/access-handover-reviews/{$review->id}/approve")->assertOk();

        $role->update(['permissions' => [ClubPermissions::FINANCE_VIEW]]);
        $this->artisan('airmius:process-membership-terminations', ['--date' => $dueOn])->assertExitCode(0);

        $this->assertDatabaseHas('club_access_handover_reviews', ['id' => $review->id, 'status' => 'stale']);
        $this->assertDatabaseMissing('club_role_assignments', [
            'club_id' => $club->id,
            'user_id' => $successor->id,
            'club_role_definition_id' => $role->id,
        ]);
        $this->assertDatabaseMissing('club_role_assignments', ['club_id' => $club->id, 'user_id' => $departing->id]);
    }

    public function test_remove_decision_and_tenant_bound_listing_require_role_management(): void
    {
        [$club, $owner, $proposer, $approver, $departing] = $this->context();
        $review = app(ClubAccessHandoverService::class)->ensure($club, $departing, now()->addWeek());
        $plain = User::factory()->create();
        $club->users()->attach($plain->id, [
            'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
        ]);
        Sanctum::actingAs($plain);
        $this->getJson("/api/v1/clubs/{$club->id}/access-handover-reviews")->assertForbidden();

        Sanctum::actingAs($proposer);
        $this->getJson("/api/v1/clubs/{$club->id}/access-handover-reviews")
            ->assertOk()->assertJsonCount(1, 'data.reviews');
        $this->postJson("/api/v1/clubs/{$club->id}/access-handover-reviews/{$review->id}/propose", [
            'decision' => 'remove',
        ])->assertOk()->assertJsonPath('data.successor', null);

        $foreignClub = Club::factory()->create(['owner_id' => User::factory()]);
        Sanctum::actingAs($foreignClub->owner);
        $this->postJson("/api/v1/clubs/{$foreignClub->id}/access-handover-reviews/{$review->id}/approve")
            ->assertNotFound();

        Sanctum::actingAs($approver);
        $this->postJson("/api/v1/clubs/{$club->id}/access-handover-reviews/{$review->id}/approve")
            ->assertOk()->assertJsonPath('data.status', 'approved');
    }

    public function test_handover_migration_is_reversible(): void
    {
        $migration = require database_path('migrations/2026_09_25_000007_create_club_access_handover_reviews.php');
        $migration->down();
        $this->assertFalse(Schema::hasTable('club_access_handover_reviews'));
        $migration->up();
        $this->assertTrue(Schema::hasTable('club_access_handover_reviews'));
    }

    /** @return array{Club, User, User, User, User, User, ClubRoleDefinition} */
    private function context(): array
    {
        $owner = User::factory()->create();
        $proposer = User::factory()->create();
        $approver = User::factory()->create();
        $departing = User::factory()->create();
        $successor = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        foreach ([$proposer, $approver, $departing, $successor] as $member) {
            $club->users()->attach($member->id, [
                'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
            ]);
        }
        foreach ([$proposer, $approver] as $manager) {
            $club->users()->updateExistingPivot($manager->id, [
                'permission_overrides' => [
                    ClubPermissions::MEMBERS_ROLES => true,
                    ClubPermissions::MEMBERS_VIEW => true,
                ],
            ]);
        }
        $role = ClubRoleDefinition::query()->create([
            'club_id' => $club->id,
            'key' => 'handover_reader',
            'name' => 'Handover reader',
            'permissions' => [ClubPermissions::MEMBERS_VIEW],
            'is_active' => true,
        ]);
        ClubRoleAssignment::query()->create([
            'club_id' => $club->id,
            'club_role_definition_id' => $role->id,
            'user_id' => $departing->id,
            'scope_type' => 'club',
            'scope_key' => 'club',
            'assigned_by' => $owner->id,
        ]);

        return [$club, $owner, $proposer, $approver, $departing, $successor, $role];
    }
}
