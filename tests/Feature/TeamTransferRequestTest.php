<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Club;
use App\Models\Notification;
use App\Models\Team;
use App\Models\TeamTransferRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TeamTransferRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_requests_dated_team_transfer_and_second_person_approval_applies_history_and_notifications(): void
    {
        $owner = User::factory()->create(['language' => 'de']);
        $member = User::factory()->create(['language' => 'en']);
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $sourceTeam = Team::factory()->create(['club_id' => $club->id, 'name' => 'U15']);
        $targetTeam = Team::factory()->create(['club_id' => $club->id, 'name' => 'U17']);
        $club->users()->attach($member->id, ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active']);
        $sourceTeam->users()->attach($member->id, ['role' => 'Player']);

        Sanctum::actingAs($member);
        $this->postJson("/api/v1/teams/{$targetTeam->id}/transfer-requests", [
            'source_team_id' => $sourceTeam->id,
            'role' => 'Captain',
            'effective_on' => '2026-10-01',
            'message' => 'Private family context must not enter audit.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.origin', 'member_request')
            ->assertJsonPath('data.source_team_id', $sourceTeam->id)
            ->assertJsonPath('data.target_team_id', $targetTeam->id)
            ->assertJsonPath('data.effective_on', '2026-10-01');

        $transfer = TeamTransferRequest::query()->firstOrFail();
        $this->assertSame('Private family context must not enter audit.', $transfer->message);
        $this->assertDatabaseHas('activities', [
            'club_id' => $club->id,
            'user_id' => $member->id,
            'type' => 'club.team_transfer.requested',
            'subject_id' => $transfer->id,
        ]);
        $this->assertStringNotContainsString(
            'Private family context',
            json_encode(Activity::query()->where('type', 'club.team_transfer.requested')->firstOrFail()->data)
        );

        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/team-transfer-requests/{$transfer->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'applied')
            ->assertJsonPath('data.decided_by', $owner->id);

        $this->assertDatabaseMissing('team_user', [
            'team_id' => $sourceTeam->id,
            'user_id' => $member->id,
        ]);
        $this->assertDatabaseHas('team_user', [
            'team_id' => $targetTeam->id,
            'user_id' => $member->id,
            'role' => 'Captain',
        ]);
        $timelineEntry = DB::table('club_member_timeline_entries')
            ->where('club_id', $club->id)
            ->where('subject_type', 'member')
            ->where('subject_id', $member->id)
            ->where('type', 'team_transfer')
            ->first();
        $this->assertNotNull($timelineEntry);
        $this->assertStringStartsWith('2026-10-01', (string) $timelineEntry->occurred_on);
        $this->assertSame('U15', $timelineEntry->from_value);
        $this->assertSame('U17', $timelineEntry->to_value);
        $notification = Notification::query()
            ->where('user_id', $member->id)
            ->where('type', 'team.transfer_applied')
            ->firstOrFail();
        $this->assertSame('organization.notifications.team_transfer_applied_title', $notification->data['i18n']['title_key']);
        $this->assertSame($sourceTeam->id, $notification->data['source_team_id']);
        $this->assertSame($targetTeam->id, $notification->data['target_team_id']);
        $this->assertSame(1, Activity::query()->where('type', 'club.team_transfer.applied')->where('subject_id', $transfer->id)->count());
        $this->assertSame(1, DB::table('club_member_timeline_entries')->where('type', 'team_transfer')->count());
    }

    public function test_administrative_transfer_applies_without_member_request(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $sourceTeam = Team::factory()->create(['club_id' => $club->id]);
        $targetTeam = Team::factory()->create(['club_id' => $club->id]);
        $club->users()->attach($member->id, ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active']);
        $sourceTeam->users()->attach($member->id, ['role' => 'Player']);

        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/teams/{$targetTeam->id}/transfers", [
            'user_id' => $member->id,
            'source_team_id' => $sourceTeam->id,
            'role' => 'Player',
            'effective_on' => '2026-10-15',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'applied')
            ->assertJsonPath('data.origin', 'administrative');

        $this->assertDatabaseMissing('team_user', ['team_id' => $sourceTeam->id, 'user_id' => $member->id]);
        $this->assertDatabaseHas('team_user', ['team_id' => $targetTeam->id, 'user_id' => $member->id]);
        $this->assertDatabaseHas('activities', [
            'club_id' => $club->id,
            'user_id' => $owner->id,
            'type' => 'club.team_transfer.administrative',
        ]);
    }

    public function test_transfer_rejects_foreign_source_self_approval_duplicates_and_same_team(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $otherClub = Club::factory()->create(['owner_id' => User::factory()->create()->id]);
        $sourceTeam = Team::factory()->create(['club_id' => $club->id]);
        $targetTeam = Team::factory()->create(['club_id' => $club->id]);
        $foreignTeam = Team::factory()->create(['club_id' => $otherClub->id]);
        $club->users()->attach($member->id, ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active']);
        $sourceTeam->users()->attach($member->id, ['role' => 'Player']);

        Sanctum::actingAs($member);
        $this->postJson("/api/v1/teams/{$targetTeam->id}/transfer-requests", [
            'source_team_id' => $foreignTeam->id,
        ])->assertJsonValidationErrors('source_team_id');

        $this->postJson("/api/v1/teams/{$sourceTeam->id}/transfer-requests", [
            'source_team_id' => $sourceTeam->id,
        ])->assertStatus(422);

        $this->postJson("/api/v1/teams/{$targetTeam->id}/transfer-requests", [
            'source_team_id' => $sourceTeam->id,
        ])->assertCreated();
        $this->postJson("/api/v1/teams/{$targetTeam->id}/transfer-requests", [
            'source_team_id' => $sourceTeam->id,
        ])->assertStatus(422);

        $selfApprovalSource = Team::factory()->create(['club_id' => $club->id]);
        $selfApprovalTarget = Team::factory()->create(['club_id' => $club->id]);
        $selfApprovalSource->users()->attach($owner->id, ['role' => 'Player']);

        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/teams/{$selfApprovalTarget->id}/transfer-requests", [
            'source_team_id' => $selfApprovalSource->id,
        ])->assertCreated();

        $transfer = TeamTransferRequest::query()
            ->where('user_id', $owner->id)
            ->firstOrFail();
        $this->postJson("/api/v1/team-transfer-requests/{$transfer->id}/approve")
            ->assertStatus(422);
    }
}
