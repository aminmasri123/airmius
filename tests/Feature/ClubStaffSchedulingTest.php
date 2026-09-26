<?php

namespace Tests\Feature;

use App\Models\Club;
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
        $this->postJson("/api/v1/clubs/{$club->id}/staff-scheduling/assignments/{$shiftId}/swap", [
            'target_user_id' => $third->id,
        ])->assertOk()
            ->assertJsonPath('data.user_id', $third->id)
            ->assertJsonPath('data.substitute_user_id', $second->id);

        $this->postJson("/api/v1/clubs/{$club->id}/staff-scheduling/assignments/{$shiftId}/substitute", [
            'substitute_user_id' => $coach->id,
        ])->assertOk()
            ->assertJsonPath('data.user_id', $third->id)
            ->assertJsonPath('data.substitute_user_id', $coach->id);

        $this->getJson("/api/v1/clubs/{$club->id}/staff-scheduling?from=2026-10-04T00:00:00Z&to=2026-10-05T00:00:00Z")
            ->assertOk()
            ->assertJsonPath('data.unfilled_assignments', []);
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
