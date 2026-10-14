<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubExternalMember;
use App\Models\ClubMemberQualification;
use App\Models\ClubStaffAssignment;
use App\Models\Event;
use App\Models\Team;
use App\Models\User;
use App\Support\ClubPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubStaffSchedulingTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_assignment_checks_availability_calendar_and_qualifications(): void
    {
        [$manager, $club, $coach, $team] = $this->clubWithCoach();
        Sanctum::actingAs($manager);

        $this->postJson("/api/v1/clubs/{$club->id}/staff-scheduling/availabilities", [
            'user_id' => $coach->id,
            'starts_at' => '2026-10-01T09:00:00Z',
            'ends_at' => '2026-10-01T12:00:00Z',
            'status' => 'unavailable',
            'note' => 'Fortbildung extern',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'unavailable');

        $this->postJson("/api/v1/clubs/{$club->id}/staff-scheduling/assignments", [
            'team_id' => $team->id,
            'user_id' => $coach->id,
            'starts_at' => '2026-10-01T10:00:00Z',
            'ends_at' => '2026-10-01T11:00:00Z',
            'role' => 'head_coach',
            'required_qualifications' => ['Trainer C'],
            'status' => 'planned',
        ])->assertStatus(409)
            ->assertJsonValidationErrors('conflicts');

        $this->postJson("/api/v1/clubs/{$club->id}/staff-scheduling/conflicts", [
            'user_id' => $coach->id,
            'starts_at' => '2026-10-01T10:00:00Z',
            'ends_at' => '2026-10-01T11:00:00Z',
            'required_qualifications' => ['Trainer C'],
        ])->assertOk()
            ->assertJsonPath('data.has_blocking_conflicts', true)
            ->assertJsonFragment(['type' => 'availability'])
            ->assertJsonFragment(['type' => 'qualification_missing']);

        ClubMemberQualification::query()->create([
            'club_id' => $club->id,
            'user_id' => $coach->id,
            'created_by' => $manager->id,
            'type' => 'license',
            'title' => 'Trainer C',
            'valid_from' => '2026-01-01',
            'valid_until' => '2026-12-31',
            'proof_status' => 'verified',
            'visibility' => 'membership_admins',
        ]);

        $assignment = $this->postJson("/api/v1/clubs/{$club->id}/staff-scheduling/assignments", [
            'team_id' => $team->id,
            'user_id' => $coach->id,
            'starts_at' => '2026-10-02T10:00:00Z',
            'ends_at' => '2026-10-02T11:30:00Z',
            'role' => 'head_coach',
            'required_qualifications' => ['Trainer C'],
            'status' => 'confirmed',
        ])->assertCreated()
            ->assertJsonPath('data.role', 'head_coach')
            ->assertJsonPath('data.required_qualifications.0', 'Trainer C')
            ->json('data.id');

        $this->assertDatabaseHas('club_staff_assignments', [
            'id' => $assignment,
            'club_id' => $club->id,
            'user_id' => $coach->id,
            'status' => 'confirmed',
        ]);
    }

    public function test_calendar_conflicts_include_existing_event_and_assignment_windows(): void
    {
        [$manager, $club, $coach, $team] = $this->clubWithCoach();
        Sanctum::actingAs($manager);

        $event = Event::query()->create([
            'club_id' => $club->id,
            'team_id' => $team->id,
            'user_id' => $manager->id,
            'title' => 'Ligaspiel',
            'type' => 'match',
            'visibility' => 'organization',
            'status' => 'scheduled',
            'start_time' => '2026-10-03T14:00:00Z',
            'end_time' => '2026-10-03T16:00:00Z',
            'event_timezone' => 'UTC',
        ]);
        $event->participants()->attach($coach->id, ['status' => 'yes']);

        ClubStaffAssignment::query()->create([
            'club_id' => $club->id,
            'team_id' => $team->id,
            'user_id' => $coach->id,
            'assigned_by' => $manager->id,
            'starts_at' => '2026-10-03T15:00:00Z',
            'ends_at' => '2026-10-03T17:00:00Z',
            'role' => 'assistant_coach',
            'required_qualifications' => [],
            'status' => 'planned',
        ]);

        $this->postJson("/api/v1/clubs/{$club->id}/staff-scheduling/conflicts", [
            'user_id' => $coach->id,
            'starts_at' => '2026-10-03T15:30:00Z',
            'ends_at' => '2026-10-03T16:30:00Z',
        ])->assertOk()
            ->assertJsonPath('data.has_blocking_conflicts', true)
            ->assertJsonFragment(['type' => 'assignment_overlap'])
            ->assertJsonFragment(['type' => 'event_overlap']);

        $this->getJson("/api/v1/clubs/{$club->id}/staff-scheduling?from=2026-10-03T00:00:00Z&to=2026-10-04T00:00:00Z")
            ->assertOk()
            ->assertJsonPath('data.assignments.0.team_id', $team->id)
            ->assertJsonPath('data.can_manage', true);
    }

    public function test_open_staff_shift_signup_waitlist_release_swap_and_substitute_lifecycle(): void
    {
        [$manager, $club, $coach, $team] = $this->clubWithCoach();
        $second = User::factory()->create();
        $third = User::factory()->create();
        $club->users()->syncWithoutDetaching([
            $second->id => ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active'],
            $third->id => ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active'],
        ]);

        Sanctum::actingAs($manager);
        $shiftId = $this->postJson("/api/v1/clubs/{$club->id}/staff-scheduling/assignments", [
            'team_id' => $team->id,
            'starts_at' => '2026-10-04T08:00:00Z',
            'ends_at' => '2026-10-04T10:00:00Z',
            'role' => 'gate',
            'status' => 'open',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'open')
            ->json('data.id');

        Sanctum::actingAs($coach);
        $this->postJson("/api/v1/clubs/{$club->id}/staff-scheduling/assignments/{$shiftId}/signup")
            ->assertOk()
            ->assertJsonPath('data.user_id', $coach->id)
            ->assertJsonPath('data.status', 'confirmed');

        Sanctum::actingAs($second);
        $waitlistId = $this->postJson("/api/v1/clubs/{$club->id}/staff-scheduling/assignments/{$shiftId}/signup")
            ->assertCreated()
            ->assertJsonPath('data.user_id', $second->id)
            ->assertJsonPath('data.status', 'waitlisted')
            ->json('data.id');

        Sanctum::actingAs($coach);
        $this->postJson("/api/v1/clubs/{$club->id}/staff-scheduling/assignments/{$shiftId}/release")
            ->assertOk()
            ->assertJsonPath('data.promoted_assignment.user_id', $second->id)
            ->assertJsonPath('data.promoted_assignment.status', 'confirmed');

        $this->assertDatabaseHas('club_staff_assignments', [
            'id' => $waitlistId,
            'status' => 'cancelled',
        ]);

        Sanctum::actingAs($manager);
        $swapRequestId = $this->postJson("/api/v1/clubs/{$club->id}/staff-scheduling/assignments/{$shiftId}/swap", [
            'target_user_id' => $third->id,
        ])->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->json('data.id');

        $this->postJson("/api/v1/clubs/{$club->id}/staff-scheduling/assignments/{$shiftId}/substitute", [
            'substitute_user_id' => $coach->id,
        ])->assertConflict();

        Sanctum::actingAs($third);
        $this->postJson("/api/v1/clubs/{$club->id}/staff-scheduling/change-requests/{$swapRequestId}/respond", [
            'decision' => 'accept',
        ])->assertOk()
            ->assertJsonPath('data.request.status', 'accepted')
            ->assertJsonPath('data.assignment.user_id', $third->id)
            ->assertJsonPath('data.assignment.substitute_user_id', $second->id);

        Sanctum::actingAs($manager);
        $substituteRequestId = $this->postJson("/api/v1/clubs/{$club->id}/staff-scheduling/assignments/{$shiftId}/substitute", [
            'substitute_user_id' => $coach->id,
        ])->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->json('data.id');

        Sanctum::actingAs($coach);
        $this->postJson("/api/v1/clubs/{$club->id}/staff-scheduling/change-requests/{$substituteRequestId}/respond", [
            'decision' => 'accept',
        ])->assertOk()
            ->assertJsonPath('data.request.status', 'accepted')
            ->assertJsonPath('data.assignment.user_id', $third->id)
            ->assertJsonPath('data.assignment.substitute_user_id', $coach->id);

        Sanctum::actingAs($manager);
        $this->getJson("/api/v1/clubs/{$club->id}/staff-scheduling?from=2026-10-04T00:00:00Z&to=2026-10-05T00:00:00Z")
            ->assertOk()
            ->assertJsonPath('data.change_requests.0.status', 'accepted')
            ->assertJsonPath('data.unfilled_assignments', []);
    }

    public function test_capacity_creates_distinct_slots_and_third_member_waitlists_without_duplicate_signup(): void
    {
        [$manager, $club, $first, $team] = $this->clubWithCoach();
        $second = User::factory()->create();
        $third = User::factory()->create();
        $club->users()->syncWithoutDetaching([
            $second->id => ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active'],
            $third->id => ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active'],
        ]);

        Sanctum::actingAs($manager);
        $created = $this->postJson("/api/v1/clubs/{$club->id}/staff-scheduling/assignments", [
            'team_id' => $team->id,
            'starts_at' => '2026-10-25T08:00:00Z',
            'ends_at' => '2026-10-25T11:00:00Z',
            'role' => 'Sommerfest Aufbau',
            'capacity' => 2,
            'status' => 'open',
        ])->assertCreated()
            ->assertJsonPath('data.capacity', 2)
            ->assertJsonCount(2, 'data.slot_ids');

        [$firstSlot, $secondSlot] = $created->json('data.slot_ids');

        Sanctum::actingAs($first);
        $this->postJson("/api/v1/clubs/{$club->id}/staff-scheduling/assignments/{$firstSlot}/signup")
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        Sanctum::actingAs($second);
        $this->postJson("/api/v1/clubs/{$club->id}/staff-scheduling/assignments/{$secondSlot}/signup")
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        Sanctum::actingAs($third);
        $this->postJson("/api/v1/clubs/{$club->id}/staff-scheduling/assignments/{$firstSlot}/signup")
            ->assertCreated()
            ->assertJsonPath('data.status', 'waitlisted');

        $this->postJson("/api/v1/clubs/{$club->id}/staff-scheduling/assignments/{$firstSlot}/signup")
            ->assertOk()
            ->assertJsonPath('data.status', 'waitlisted');

        $this->assertDatabaseCount('club_staff_assignments', 3);
    }

    public function test_inactive_member_cannot_view_or_join_staff_shifts(): void
    {
        [$manager, $club, $member, $team] = $this->clubWithCoach();

        Sanctum::actingAs($manager);
        $shiftId = $this->postJson("/api/v1/clubs/{$club->id}/staff-scheduling/assignments", [
            'team_id' => $team->id,
            'starts_at' => '2026-10-25T08:00:00Z',
            'ends_at' => '2026-10-25T11:00:00Z',
            'role' => 'Sommerfest Aufbau',
            'status' => 'open',
        ])->assertCreated()->json('data.id');

        $club->users()->updateExistingPivot($member->id, ['membership_status' => 'paused']);

        Sanctum::actingAs($member);
        $this->getJson("/api/v1/clubs/{$club->id}/staff-scheduling")
            ->assertForbidden();
        $this->postJson("/api/v1/clubs/{$club->id}/staff-scheduling/assignments/{$shiftId}/signup")
            ->assertForbidden();
    }

    public function test_staff_lifecycle_enforces_roles_and_conflicts_for_substitutes(): void
    {
        [$manager, $club, $coach, $team] = $this->clubWithCoach();
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        $club->users()->syncWithoutDetaching([
            $member->id => ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active'],
        ]);

        Sanctum::actingAs($manager);
        $shiftId = $this->postJson("/api/v1/clubs/{$club->id}/staff-scheduling/assignments", [
            'team_id' => $team->id,
            'user_id' => $coach->id,
            'starts_at' => '2026-10-05T08:00:00Z',
            'ends_at' => '2026-10-05T10:00:00Z',
            'role' => 'gate',
            'status' => 'confirmed',
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($outsider);
        $this->postJson("/api/v1/clubs/{$club->id}/staff-scheduling/assignments/{$shiftId}/release")
            ->assertForbidden();

        Sanctum::actingAs($manager);
        $this->postJson("/api/v1/clubs/{$club->id}/staff-scheduling/availabilities", [
            'user_id' => $member->id,
            'starts_at' => '2026-10-05T08:30:00Z',
            'ends_at' => '2026-10-05T09:30:00Z',
            'status' => 'unavailable',
        ])->assertCreated();

        $this->postJson("/api/v1/clubs/{$club->id}/staff-scheduling/assignments/{$shiftId}/substitute", [
            'substitute_user_id' => $member->id,
        ])->assertStatus(409)
            ->assertJsonValidationErrors('conflicts')
            ->assertJsonFragment(['type' => 'availability']);
    }

    public function test_shift_planning_supports_audiences_details_comments_external_helpers_and_confirmed_alternatives(): void
    {
        [$manager, $club, $first, $team] = $this->clubWithCoach();
        $second = User::factory()->create();
        $unrelated = User::factory()->create();
        $club->users()->syncWithoutDetaching([
            $second->id => ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active'],
            $unrelated->id => ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active'],
        ]);
        $team->users()->syncWithoutDetaching([$second->id => ['role' => 'player']]);
        $external = ClubExternalMember::query()->create([
            'club_id' => $club->id,
            'created_by' => $manager->id,
            'name' => 'Externe Helferin',
            'role' => 'member',
            'membership_status' => 'active',
        ]);

        Sanctum::actingAs($manager);
        $drinks = $this->postJson("/api/v1/clubs/{$club->id}/staff-scheduling/assignments", [
            'starts_at' => '2026-10-25T12:00:00Z',
            'ends_at' => '2026-10-25T14:00:00Z',
            'role' => 'Getränkeverkauf',
            'location' => 'Theke Nord',
            'instructions' => 'Kasse übernehmen und Getränke ausgeben.',
            'requirements' => 'Geschlossene Schuhe tragen.',
            'contact_name' => 'Festleitung',
            'contact_details' => '+49 123 456',
            'visibility_scope' => 'teams',
            'audience_team_ids' => [$team->id],
            'notify_audience' => true,
            'capacity' => 1,
            'status' => 'open',
        ])->assertCreated()
            ->assertJsonPath('data.location', 'Theke Nord');
        $drinksId = $drinks->json('data.id');

        Sanctum::actingAs($unrelated);
        $this->getJson("/api/v1/clubs/{$club->id}/staff-scheduling?from=2026-10-25T00:00:00Z&to=2026-10-26T00:00:00Z")
            ->assertOk()
            ->assertJsonCount(0, 'data.assignments');

        Sanctum::actingAs($first);
        $this->postJson("/api/v1/clubs/{$club->id}/staff-scheduling/assignments/{$drinksId}/signup")
            ->assertOk();
        $this->postJson("/api/v1/clubs/{$club->id}/staff-scheduling/assignments/{$drinksId}/comments", [
            'message' => 'Wo befindet sich der Schlüssel?',
        ])->assertCreated();

        Sanctum::actingAs($second);
        $waitlistId = $this->postJson("/api/v1/clubs/{$club->id}/staff-scheduling/assignments/{$drinksId}/signup")
            ->assertCreated()
            ->assertJsonPath('data.status', 'waitlisted')
            ->json('data.id');

        Sanctum::actingAs($manager);
        $cake = $this->postJson("/api/v1/clubs/{$club->id}/staff-scheduling/assignments", [
            'starts_at' => '2026-10-25T12:00:00Z',
            'ends_at' => '2026-10-25T14:00:00Z',
            'role' => 'Kuchenverkauf',
            'visibility_scope' => 'teams',
            'audience_team_ids' => [$team->id],
            'capacity' => 2,
            'status' => 'open',
        ])->assertCreated();
        [$cakeSlot, $externalSlot] = $cake->json('data.slot_ids');
        $this->postJson("/api/v1/clubs/{$club->id}/staff-scheduling/assignments/{$externalSlot}/external-member", [
            'external_member_id' => $external->id,
        ])->assertOk()
            ->assertJsonPath('data.external_member_name', 'Externe Helferin');

        $offerId = $this->postJson("/api/v1/clubs/{$club->id}/staff-scheduling/assignments/{$cakeSlot}/alternative", [
            'target_user_id' => $second->id,
            'source_assignment_id' => $waitlistId,
            'note' => 'Gleiche Zeit, freier Platz.',
        ])->assertCreated()
            ->assertJsonPath('data.type', 'alternative')
            ->json('data.id');

        Sanctum::actingAs($second);
        $this->postJson("/api/v1/clubs/{$club->id}/staff-scheduling/change-requests/{$offerId}/respond", [
            'decision' => 'accept',
        ])->assertOk()
            ->assertJsonPath('data.assignment.user_id', $second->id);
        $this->assertDatabaseHas('club_staff_assignments', ['id' => $waitlistId, 'status' => 'cancelled']);

        Sanctum::actingAs($manager);
        $this->putJson("/api/v1/clubs/{$club->id}/staff-scheduling/assignments/{$cakeSlot}", [
            'starts_at' => '2026-10-25T12:00:00Z',
            'ends_at' => '2026-10-25T14:00:00Z',
            'role' => 'Kuchen- und Kaffeeverkauf',
            'visibility_scope' => 'club',
            'capacity' => 2,
            'status' => 'open',
        ])->assertOk()
            ->assertJsonPath('data.role', 'Kuchen- und Kaffeeverkauf');
        $this->deleteJson("/api/v1/clubs/{$club->id}/staff-scheduling/assignments/{$cakeSlot}")
            ->assertOk();
        $this->assertDatabaseMissing('club_staff_assignments', ['shift_key' => $cake->json('data.shift_key')]);
    }

    private function clubWithCoach(): array
    {
        $manager = User::factory()->create();
        $coach = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $manager->id]);
        $team = Team::factory()->create(['club_id' => $club->id]);
        $club->users()->syncWithoutDetaching([
            $manager->id => ['role' => 'owner', 'roles' => ['owner'], 'membership_status' => 'active'],
            $coach->id => ['role' => 'trainer', 'roles' => ['trainer'], 'membership_status' => 'active'],
        ]);
        $club->users()->updateExistingPivot($manager->id, [
            'permission_overrides' => [
                ClubPermissions::EVENTS_MANAGE => true,
                ClubPermissions::TRAINING_SESSIONS_EDIT => true,
            ],
        ]);
        $team->users()->attach($coach->id, ['role' => 'coach']);

        return [$manager, $club, $coach, $team];
    }
}
