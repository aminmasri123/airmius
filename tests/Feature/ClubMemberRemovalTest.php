<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubExternalMember;
use App\Models\ClubMemberRemoval;
use App\Models\ClubMembershipRequest;
use App\Models\User;
use App\Services\ClubMemberRemovalService;
use App\Services\ClubMembershipLifecycleService;
use App\Support\ClubActiveMembership;
use App\Support\ClubPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClubMemberRemovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_notification_allows_one_owned_objection_without_new_deadline(): void
    {
        [$club, $owner, $member] = $this->fixture();
        $club->users()->detach($member->id);
        $notification = \App\Models\Notification::create(['user_id' => $member->id, 'type' => 'club.member_removed', 'data' => ['club_id' => $club->id, 'reason' => 'Test reason']]);
        $url = '/api/v1/member-removal-notifications/'.$notification->id;
        $this->actingAs($owner)->getJson($url)->assertNotFound();
        $this->postJson($url, ['message' => 'Foreign'])->assertNotFound();
        $this->actingAs($member)->getJson($url)->assertOk()->assertJsonPath('data.status', 'legacy_removed')->assertJsonPath('data.club_name', $club->name);
        $this->postJson($url, ['message' => 'Bitte prüfen'])->assertOk()->assertJsonPath('data.status', 'legacy_objected');
        $this->postJson($url, ['message' => 'Bitte prüfen'])->assertOk();
        $this->assertDatabaseCount('club_membership_requests', 1);
        $this->assertDatabaseCount('club_member_removals', 0);
        $this->assertFalse($club->users()->whereKey($member->id)->exists());
    }

    public function test_old_notification_cannot_object_to_a_new_removal(): void
    {
        [$club, $owner, $member] = $this->fixture();
        $notification = \App\Models\Notification::create(['user_id' => $member->id, 'type' => 'club.member_removed', 'data' => ['club_id' => $club->id, 'reason' => 'Old reason']]);
        $removal = app(ClubMemberRemovalService::class)->request($club, $member, $owner, 'New reason');
        $url = '/api/v1/member-removal-notifications/'.$notification->id;
        $this->actingAs($member)->getJson($url)->assertOk()->assertJsonPath('data.status', 'legacy_superseded');
        $this->postJson($url, ['message' => 'Old link'])->assertUnprocessable();
        $this->assertSame('pending', $removal->fresh()->status);
    }

    public function test_removal_retains_member_until_confirmed_and_rejects_foreign_answers(): void
    {
        [$club, $owner, $member] = $this->fixture();
        $this->actingAs($owner)->deleteJson('/api/v1/clubs/'.$club->id.'/members/'.$member->id, ['reason' => 'Test removal'])->assertSuccessful();
        $removal = ClubMemberRemoval::sole();
        $this->assertTrue($club->users()->whereKey($member->id)->exists());
        $this->assertSame('removal_pending', $club->users()->whereKey($member->id)->first()->pivot->membership_status);
        $this->actingAs($owner)->getJson('/api/v1/member-removals/'.$removal->id)->assertNotFound();
        $this->postJson('/api/v1/member-removals/'.$removal->id, ['decision' => 'confirm'])->assertNotFound();
        $this->actingAs($member)->getJson('/api/v1/member-removals/'.$removal->id)->assertOk();
        $this->postJson('/api/v1/member-removals/'.$removal->id, ['decision' => 'confirm'])->assertOk();
        $this->assertFalse($club->users()->whereKey($member->id)->exists());
        $this->assertSame('completed', $removal->fresh()->status);
        $this->postJson('/api/v1/member-removals/'.$removal->id, ['decision' => 'confirm'])->assertOk();
    }

    public function test_objection_stops_expiry_and_cannot_be_reset_by_another_removal(): void
    {
        [$club, $owner, $member] = $this->fixture();
        $service = app(ClubMemberRemovalService::class);
        $removal = $service->request($club, $member, $owner, 'Test reason');
        $this->travel(29)->days();
        $this->assertSame(0, $service->expire());
        $this->actingAs($member)->postJson('/api/v1/member-removals/'.$removal->id, ['decision' => 'object', 'message' => 'Bitte prüfen'])->assertOk();
        $service->request($club, $member, $owner, 'Another reason');
        $this->travel(40)->days();
        $this->assertSame(0, $service->expire());
        $this->assertSame('objected', $removal->fresh()->status);
        $this->assertTrue($club->users()->whereKey($member->id)->exists());
        $this->assertDatabaseCount('club_member_removals', 1);
        $this->assertDatabaseHas('club_membership_requests', ['user_id' => $member->id, 'type' => 'removal_objection', 'status' => 'pending']);
    }

    public function test_silence_expires_after_thirty_days_only_once(): void
    {
        [$club, $owner, $member] = $this->fixture();
        $service = app(ClubMemberRemovalService::class);
        $service->request($club, $member, $owner, 'Test reason');
        $this->travel(29)->days();
        $this->assertSame(0, $service->expire());
        $service->request($club, $member, $owner, 'Repeated request');
        $this->travel(1)->days();
        $this->assertSame(1, $service->expire());
        $this->assertSame(0, $service->expire());
        $this->assertFalse($club->users()->whereKey($member->id)->exists());
    }

    public function test_accepted_objection_restores_membership_and_cancels_removal(): void
    {
        [$club, $owner, $member] = $this->fixture();
        $service = app(ClubMemberRemovalService::class);
        $removal = $service->request($club, $member, $owner, 'Test reason');
        $service->respond($removal, $member, 'object', 'Bitte prüfen');
        $request = ClubMembershipRequest::where('user_id', $member->id)->sole();
        app(ClubMembershipLifecycleService::class)->approve($club, $request, $owner);
        $this->assertSame('cancelled', $removal->fresh()->status);
        $this->assertSame('active', $club->users()->whereKey($member->id)->first()->pivot->membership_status);
        $this->travel(31)->days();
        $this->assertSame(0, $service->expire());
    }

    public function test_rejected_objection_does_not_restart_the_timer(): void
    {
        [$club, $owner, $member] = $this->fixture();
        $service = app(ClubMemberRemovalService::class);
        $removal = $service->request($club, $member, $owner, 'Test reason');
        $service->respond($removal, $member, 'object', null);
        $request = ClubMembershipRequest::where('user_id', $member->id)->sole();
        app(ClubMembershipLifecycleService::class)->decline($club, $request, $owner, 'Prüfung');
        $this->travel(31)->days();
        $this->assertSame(0, $service->expire());
        $this->assertTrue($club->users()->whereKey($member->id)->exists());
    }

    public function test_external_member_without_account_is_removed_immediately(): void
    {
        [$club, $owner] = $this->fixture();
        $external = ClubExternalMember::create(['club_id' => $club->id, 'created_by' => $owner->id, 'name' => 'External test', 'email' => 'external@example.test', 'membership_status' => 'active']);
        $this->actingAs($owner)->deleteJson('/api/v1/clubs/'.$club->id.'/external-members/'.$external->id, ['reason' => 'Test removal'])->assertSuccessful();
        $this->assertDatabaseMissing('club_external_members', ['id' => $external->id]);
        $this->assertDatabaseCount('club_member_removals', 0);
    }

    public function test_pending_removal_denies_active_membership_and_status_override(): void
    {
        [$club, $owner, $member] = $this->fixture();
        app(ClubMemberRemovalService::class)->request($club, $member, $owner, 'Test reason');
        $this->assertFalse(ClubActiveMembership::allows($club, $member));
        $this->assertFalse(ClubPermissions::allows($club, $member, ClubPermissions::MEMBERS_EDIT));
        $this->actingAs($owner)->putJson('/api/v1/clubs/'.$club->id.'/members/'.$member->id, ['role' => 'member', 'membership_status' => 'active'])->assertUnprocessable();
    }

    private function fixture(): array
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['language' => 'de']);
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($member->id, ['role' => 'member', 'membership_status' => 'active']);

        return [$club, $owner, $member];
    }
}
