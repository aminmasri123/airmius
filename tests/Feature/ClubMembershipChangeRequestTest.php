<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Club;
use App\Models\ClubContributionRule;
use App\Models\ClubDepartment;
use App\Models\ClubMembershipRequest;
use App\Models\ClubMembershipType;
use App\Models\Notification;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubMembershipChangeRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_requests_a_type_change_and_second_person_approval_updates_the_membership(): void
    {
        $owner = User::factory()->create(['language' => 'de']);
        $member = User::factory()->create(['language' => 'en']);
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $currentType = $this->membershipType($club, 'Basis');
        $targetType = $this->membershipType($club, 'Premium');
        ClubContributionRule::query()->create([
            'club_id' => $club->id,
            'club_membership_type_id' => $targetType->id,
            'name' => 'Premium monthly',
            'valid_from' => now()->subDay()->toDateString(),
            'billing_interval' => 'monthly',
            'amount' => 29.90,
            'is_active' => true,
        ]);
        $club->users()->updateExistingPivot($owner->id, [
            'role' => 'owner',
            'roles' => ['owner'],
            'membership_status' => 'active',
        ]);
        $club->users()->attach($member->id, [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'active',
            'club_membership_type_id' => $currentType->id,
            'contribution_amount' => 10,
            'contribution_interval' => 'monthly',
        ]);

        Sanctum::actingAs($member);
        $this->postJson("/api/v1/clubs/{$club->id}/membership-change-requests", [
            'club_membership_type_id' => $targetType->id,
            'message' => 'Please change my plan.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'membership_change')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.membership_type.id', $targetType->id)
            ->assertJsonPath('data.preview_amount', '29.90')
            ->assertJsonPath('data.preview_interval', 'monthly');

        $membershipRequest = ClubMembershipRequest::query()
            ->where('type', 'membership_change')
            ->firstOrFail();
        $this->getJson("/api/v1/clubs/{$club->id}")
            ->assertOk()
            ->assertJsonPath('data.membership.club_membership_type_id', $currentType->id)
            ->assertJsonPath('data.membership.change_requested', true);
        $this->actingAs($member)
            ->get(route('auth.clubs.show', $club))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('viewer.membership_type_id', $currentType->id)
                ->where('viewer.has_pending_membership_change_request', true));
        $this->assertDatabaseHas('club_user', [
            'club_id' => $club->id,
            'user_id' => $member->id,
            'club_membership_type_id' => $currentType->id,
        ]);
        $this->assertDatabaseHas('activities', [
            'club_id' => $club->id,
            'user_id' => $member->id,
            'type' => 'club.membership_change.requested',
            'subject_id' => $membershipRequest->id,
        ]);

        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/clubs/{$club->id}/membership-requests/{$membershipRequest->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('club_user', [
            'club_id' => $club->id,
            'user_id' => $member->id,
            'club_membership_type_id' => $targetType->id,
            'contribution_amount' => 29.90,
            'contribution_interval' => 'monthly',
        ]);
        $notification = Notification::query()
            ->where('user_id', $member->id)
            ->where('type', 'club.membership_request_approved')
            ->latest('id')
            ->firstOrFail();
        $this->assertSame('organization.notifications.membership_change_confirmed_title', $notification->data['i18n']['title_key']);
        $this->assertSame('organization.notifications.membership_change_confirmed_body', $notification->data['i18n']['body_key']);
        $this->assertSame('membership_change_confirmed', $notification->data['lifecycle_event']);
        $this->assertSame($targetType->id, $notification->data['club_membership_type_id']);
        $this->assertSame('Premium', $notification->data['membership_type_name']);
        $this->assertSame(1, Activity::query()
            ->where('type', 'club.membership_request.approved')
            ->where('subject_id', $membershipRequest->id)
            ->count());
    }

    public function test_type_change_rejects_same_private_foreign_and_non_member_targets(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $nonMember = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $otherClub = Club::factory()->create(['owner_id' => User::factory()->create()->id]);
        $currentType = $this->membershipType($club, 'Current');
        $privateType = $this->membershipType($club, 'Private', false);
        $foreignType = $this->membershipType($otherClub, 'Foreign');
        $club->users()->attach($member->id, [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'active',
            'club_membership_type_id' => $currentType->id,
        ]);

        Sanctum::actingAs($member);
        $this->postJson("/api/v1/clubs/{$club->id}/membership-change-requests", [
            'club_membership_type_id' => $currentType->id,
        ])->assertUnprocessable();
        foreach ([$privateType, $foreignType] as $invalidType) {
            $this->postJson("/api/v1/clubs/{$club->id}/membership-change-requests", [
                'club_membership_type_id' => $invalidType->id,
            ])->assertJsonValidationErrors('club_membership_type_id');
        }

        Sanctum::actingAs($nonMember);
        $this->postJson("/api/v1/clubs/{$club->id}/membership-change-requests", [
            'club_membership_type_id' => $currentType->id,
        ])->assertUnprocessable();
        $this->assertDatabaseMissing('club_membership_requests', [
            'club_id' => $club->id,
            'type' => 'membership_change',
        ]);
    }

    public function test_member_requests_a_department_change_and_approval_confirms_without_reassigning_teams(): void
    {
        $owner = User::factory()->create(['language' => 'de']);
        $member = User::factory()->create(['language' => 'en']);
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $currentType = $this->membershipType($club, 'Basis');
        $sourceDepartment = ClubDepartment::query()->create([
            'club_id' => $club->id,
            'name' => 'Fußball',
            'is_public' => true,
        ]);
        $targetDepartment = ClubDepartment::query()->create([
            'club_id' => $club->id,
            'name' => 'Leichtathletik',
            'is_public' => true,
        ]);
        $team = Team::query()->create([
            'club_id' => $club->id,
            'club_department_id' => $sourceDepartment->id,
            'name' => 'U15',
            'sport_type' => 'football',
        ]);
        $club->users()->attach($member->id, [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'active',
            'club_membership_type_id' => $currentType->id,
            'club_department_id' => $sourceDepartment->id,
        ]);
        $team->users()->attach($member->id, ['role' => 'athlete']);

        Sanctum::actingAs($member);
        $this->postJson("/api/v1/clubs/{$club->id}/membership-change-requests", [
            'club_department_id' => $sourceDepartment->id,
        ])->assertUnprocessable();

        $this->postJson("/api/v1/clubs/{$club->id}/membership-change-requests", [
            'club_department_id' => $targetDepartment->id,
            'message' => 'Ich möchte in die neue Abteilung wechseln.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'membership_change')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.club_department_id', $targetDepartment->id)
            ->assertJsonPath('data.club_membership_type_id', null)
            ->assertJsonPath('data.department.id', $targetDepartment->id);

        $membershipRequest = ClubMembershipRequest::query()
            ->where('type', 'membership_change')
            ->firstOrFail();
        $this->assertSame($targetDepartment->id, $membershipRequest->club_department_id);
        $this->assertNull($membershipRequest->club_membership_type_id);
        $this->assertDatabaseHas('team_user', [
            'team_id' => $team->id,
            'user_id' => $member->id,
        ]);
        $this->assertDatabaseHas('club_user', [
            'club_id' => $club->id,
            'user_id' => $member->id,
            'club_membership_type_id' => $currentType->id,
            'club_department_id' => $sourceDepartment->id,
        ]);
        $this->getJson("/api/v1/clubs/{$club->id}")
            ->assertOk()
            ->assertJsonPath('data.membership.club_department_id', $sourceDepartment->id);
        $this->assertDatabaseHas('activities', [
            'club_id' => $club->id,
            'user_id' => $member->id,
            'type' => 'club.membership_change.requested',
            'subject_id' => $membershipRequest->id,
        ]);

        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/clubs/{$club->id}/membership-requests/{$membershipRequest->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('team_user', [
            'team_id' => $team->id,
            'user_id' => $member->id,
        ]);
        $this->assertDatabaseHas('club_user', [
            'club_id' => $club->id,
            'user_id' => $member->id,
            'club_membership_type_id' => $currentType->id,
            'club_department_id' => $targetDepartment->id,
        ]);
        Sanctum::actingAs($member);
        $this->getJson("/api/v1/clubs/{$club->id}")
            ->assertOk()
            ->assertJsonPath('data.membership.club_department_id', $targetDepartment->id);
        $this->actingAs($member)
            ->get(route('auth.clubs.show', $club))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('viewer.club_department_id', $targetDepartment->id));
        $notification = Notification::query()
            ->where('user_id', $member->id)
            ->where('type', 'club.membership_request_approved')
            ->latest('id')
            ->firstOrFail();
        $this->assertSame('membership_change_confirmed', $notification->data['lifecycle_event']);
        $this->assertSame($targetDepartment->id, $notification->data['club_department_id']);
        $this->assertSame('Leichtathletik', $notification->data['department_name']);
        $this->assertNull($notification->data['club_membership_type_id']);
        $this->assertNull($notification->data['membership_type_name']);
    }

    public function test_future_type_change_is_applied_once_on_its_effective_date(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $currentType = $this->membershipType($club, 'Basis');
        $targetType = $this->membershipType($club, 'Premium');
        ClubContributionRule::query()->create([
            'club_id' => $club->id,
            'club_membership_type_id' => $targetType->id,
            'name' => 'Premium',
            'valid_from' => '2026-01-01',
            'billing_interval' => 'monthly',
            'amount' => 30,
            'is_active' => true,
        ]);
        $club->users()->attach($member->id, [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'active',
            'club_membership_type_id' => $currentType->id,
            'contribution_amount' => 20,
            'contribution_interval' => 'monthly',
        ]);

        Sanctum::actingAs($member);
        $requestId = $this->postJson("/api/v1/clubs/{$club->id}/membership-change-requests", [
            'club_membership_type_id' => $targetType->id,
            'effective_on' => '2026-11-01',
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/clubs/{$club->id}/membership-requests/{$requestId}/approve")
            ->assertOk();
        $this->assertDatabaseHas('club_user', [
            'club_id' => $club->id,
            'user_id' => $member->id,
            'club_membership_type_id' => $currentType->id,
            'contribution_amount' => 20,
        ]);

        $this->artisan('airmius:process-scheduled-membership-transitions', ['--date' => '2026-10-31'])
            ->assertSuccessful();
        $this->artisan('airmius:process-scheduled-membership-transitions', ['--date' => '2026-11-01'])
            ->assertSuccessful();
        $this->artisan('airmius:process-scheduled-membership-transitions', ['--date' => '2026-11-01'])
            ->assertSuccessful();

        $this->assertDatabaseHas('club_user', [
            'club_id' => $club->id,
            'user_id' => $member->id,
            'club_membership_type_id' => $targetType->id,
            'contribution_amount' => 30,
        ]);
        $this->assertNotNull(ClubMembershipRequest::query()->findOrFail($requestId)->applied_at);
    }

    private function membershipType(Club $club, string $name, bool $public = true): ClubMembershipType
    {
        return ClubMembershipType::query()->create([
            'club_id' => $club->id,
            'name' => $name,
            'is_active' => true,
            'is_public' => $public,
        ]);
    }
}
