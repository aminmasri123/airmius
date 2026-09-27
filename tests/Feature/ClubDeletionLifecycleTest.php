<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubFinanceEntry;
use App\Models\ClubGovernanceAssignment;
use App\Models\ClubGovernanceBody;
use App\Models\ClubPolicyDocument;
use App\Models\ClubSubscription;
use App\Models\ClubTask;
use App\Models\Event;
use App\Models\File;
use App\Models\Post;
use App\Models\SubscriptionPlan;
use App\Models\Team;
use App\Models\User;
use App\Notifications\ClubDeletionChanged;
use App\Services\ClubDeletionService;
use App\Services\ClubService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubDeletionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Storage::fake('public');
        config(['filesystems.uploads_disk' => 'public']);
        $this->travelTo(now()->startOfSecond());
    }

    private function ownedClub(): Club
    {
        $owner = User::factory()->create(['language' => 'de']);
        Sanctum::actingAs($owner);

        return Club::factory()->create(['owner_id' => $owner->id]);
    }

    private function requestDeletion(Club $club)
    {
        return $this->deleteJson('/api/v1/clubs/'.$club->id, ['confirmation' => 'Ja, ich bin mir sicher']);
    }

    public function test_confirmation_is_required_and_only_owner_can_request_or_cancel(): void
    {
        $club = $this->ownedClub();
        $this->deleteJson('/api/v1/clubs/'.$club->id)->assertUnprocessable();
        $this->deleteJson('/api/v1/clubs/'.$club->id, ['confirmation' => 'delete'])->assertUnprocessable();
        $manager = User::factory()->create();
        $club->users()->attach($manager, ['role' => 'admin', 'roles' => ['admin']]);
        Sanctum::actingAs($manager);
        $this->requestDeletion($club)->assertForbidden();
        $this->deleteJson('/api/v1/clubs/'.$club->id.'/deletion')->assertForbidden();
        $this->getJson('/api/v1/clubs/'.$club->id.'/deletion')->assertForbidden();
        $this->assertNull($club->fresh()->deletion_scheduled_at);
    }

    public function test_request_notifies_owner_and_board_and_retry_does_not_extend_deadline(): void
    {
        $club = $this->ownedClub();
        $president = User::factory()->create();
        $board = ClubGovernanceBody::create(['club_id' => $club->id, 'type' => 'board', 'name' => 'Vorstand']);
        ClubGovernanceAssignment::create([
            'club_id' => $club->id, 'club_governance_body_id' => $board->id,
            'user_id' => $president->id, 'position_title' => 'Präsident',
        ]);
        $due = now()->addDays(30)->toIso8601String();
        $this->requestDeletion($club)->assertStatus(202)->assertJsonPath('data.scheduled_at', $due);
        Notification::assertSentTo($club->owner, ClubDeletionChanged::class);
        Notification::assertSentTo($president, ClubDeletionChanged::class);
        $this->travel(1)->days();
        $this->requestDeletion($club)->assertStatus(202)->assertJsonPath('data.scheduled_at', $due);
        Notification::assertSentTimes(ClubDeletionChanged::class, 2);
        $this->assertDatabaseHas('clubs', ['id' => $club->id]);
    }

    public function test_cancel_keeps_data_after_deadline_and_new_request_gets_full_period(): void
    {
        $club = $this->ownedClub();
        $this->requestDeletion($club)->assertStatus(202);
        $this->travel(29)->days();
        $this->deleteJson('/api/v1/clubs/'.$club->id.'/deletion')->assertOk()->assertJsonPath('data.scheduled_at', null);
        $this->travel(2)->days();
        $this->artisan('airmius:process-club-deletions')->assertSuccessful();
        $this->assertDatabaseHas('clubs', ['id' => $club->id]);
        $this->requestDeletion($club)->assertStatus(202)->assertJsonPath('data.scheduled_at', now()->addDays(30)->toIso8601String());
    }

    public function test_due_deletion_erases_owned_data_and_uploads_but_preserves_other_club_and_accounts(): void
    {
        $club = $this->ownedClub();
        $other = Club::factory()->create(['owner_id' => User::factory()->create()->id]);
        $team = Team::factory()->create(['club_id' => $club->id]);
        $otherTeam = Team::factory()->create(['club_id' => $other->id]);
        $event = Event::create(['club_id' => null, 'team_id' => $team->id, 'title' => 'Training', 'type' => 'training', 'visibility' => 'private', 'start_time' => now()]);
        $task = ClubTask::create(['club_id' => $club->id, 'title' => 'Task', 'created_by' => $club->owner_id]);
        $post = Post::factory()->create(['club_id' => null, 'team_id' => $team->id, 'user_id' => $club->owner_id]);
        $file = File::create(['club_id' => null, 'event_id' => $event->id, 'user_id' => $club->owner_id, 'path' => 'events/test.pdf', 'type' => 'application/pdf', 'size' => 3]);
        $shared = File::create(['club_id' => $club->id, 'user_id' => $club->owner_id, 'path' => 'shared.pdf', 'type' => 'application/pdf', 'size' => 3]);
        $otherFile = File::create(['club_id' => $other->id, 'user_id' => $other->owner_id, 'path' => 'shared.pdf', 'type' => 'application/pdf', 'size' => 3]);
        Storage::disk('public')->put('events/test.pdf', 'test');
        Storage::disk('public')->put('shared.pdf', 'test');
        $ownerId = $club->owner_id;
        $this->requestDeletion($club)->assertStatus(202);
        $this->travel(30)->days();
        $this->travel(-1)->seconds();
        $this->artisan('airmius:process-club-deletions')->assertSuccessful();
        $this->assertDatabaseHas('clubs', ['id' => $club->id]);
        Storage::disk('public')->assertExists('events/test.pdf');
        $this->travel(1)->seconds();
        $this->artisan('airmius:process-club-deletions')->assertSuccessful();
        foreach (['clubs' => $club, 'teams' => $team, 'events' => $event, 'club_tasks' => $task, 'posts' => $post, 'files' => $file] as $table => $model) {
            $this->assertDatabaseMissing($table, ['id' => $model->id]);
        }
        $this->assertDatabaseMissing('files', ['id' => $shared->id]);
        $this->assertDatabaseHas('files', ['id' => $otherFile->id]);
        $this->assertDatabaseHas('clubs', ['id' => $other->id]);
        $this->assertDatabaseHas('teams', ['id' => $otherTeam->id]);
        $this->assertDatabaseHas('users', ['id' => $ownerId]);
        Storage::disk('public')->assertMissing('events/test.pdf');
        Storage::disk('public')->assertExists('shared.pdf');
        $this->assertDatabaseCount('club_deletion_file_cleanups', 0);
    }

    public function test_reminder_sent_once_and_late_subscription_blocks_deletion(): void
    {
        $club = $this->ownedClub();
        $this->requestDeletion($club)->assertStatus(202);
        $this->travel(23)->days();
        $service = app(ClubDeletionService::class);
        $service->process($club->id);
        $service->process($club->id);
        Notification::assertSentTimes(ClubDeletionChanged::class, 2);
        $plan = SubscriptionPlan::create(['slug' => 'test-plan', 'name' => 'Plan']);
        $subscription = ClubSubscription::updateOrCreate(['club_id' => $club->id], ['subscription_plan_id' => $plan->id, 'status' => 'active']);
        $subscription->update(['provider_subscription_id' => 'sub_test', 'status' => 'active']);
        $this->travel(7)->days();
        $service->process($club->id);
        $service->process($club->id);
        $this->assertNotNull($club->fresh()->deletion_blocked_at);
        Notification::assertSentTimes(ClubDeletionChanged::class, 3);
        $this->deleteJson('/api/v1/clubs/'.$club->id.'/deletion')->assertOk();
    }

    public function test_web_delete_uses_same_confirmation_and_grace_period(): void
    {
        $club = $this->ownedClub();
        $this->actingAs($club->owner)->from('/dashboard')->delete(route('auth.clubs.destroy', $club), [
            'confirmation' => 'Ja, ich bin mir sicher',
        ])->assertRedirect();
        $this->assertNotNull($club->fresh()->deletion_scheduled_at);
        $this->deleteJson(route('auth.clubs.deletion.cancel', $club))->assertOk();
    }

    public function test_owner_change_cancels_previous_owners_request(): void
    {
        $club = $this->ownedClub();
        $this->requestDeletion($club)->assertStatus(202);
        $club->update(['owner_id' => User::factory()->create()->id]);
        $this->travel(30)->days();
        app(ClubDeletionService::class)->process($club->id);
        $this->assertNull($club->fresh()->deletion_scheduled_at);
    }

    public function test_accounting_records_block_scheduling_and_cannot_be_bypassed(): void
    {
        $club = $this->ownedClub();
        ClubFinanceEntry::create([
            'club_id' => $club->id, 'booked_on' => today(), 'type' => 'income',
            'category' => 'other', 'title' => 'Beleg', 'amount' => 25, 'account' => 'cash',
        ]);
        $this->requestDeletion($club)->assertUnprocessable();
        $this->assertNull($club->fresh()->deletion_scheduled_at);
    }

    public function test_restricted_document_links_are_deleted_in_dependency_order(): void
    {
        $club = $this->ownedClub();
        $file = File::create(['club_id' => $club->id, 'user_id' => $club->owner_id, 'path' => 'policy.pdf', 'type' => 'application/pdf', 'size' => 3]);
        ClubPolicyDocument::create([
            'club_id' => $club->id, 'file_id' => $file->id, 'type' => 'statutes',
            'title' => 'Satzung', 'version_label' => '1', 'valid_from' => today(),
        ]);
        $this->requestDeletion($club)->assertStatus(202);
        $this->travel(30)->days();
        app(ClubDeletionService::class)->process($club->id);
        $this->assertDatabaseMissing('clubs', ['id' => $club->id]);
        $this->assertDatabaseMissing('files', ['id' => $file->id]);
    }

    public function test_failed_erasure_rolls_back_database_and_keeps_stored_files_for_retry(): void
    {
        $club = $this->ownedClub();
        $this->requestDeletion($club)->assertStatus(202);
        $this->travel(30)->days();
        $this->mock(ClubService::class, function ($mock) {
            $mock->shouldReceive('delete')->once()->andThrow(new \RuntimeException('Injected failure'));
        });
        $this->artisan('airmius:process-club-deletions')->assertFailed();
        $this->assertDatabaseHas('clubs', ['id' => $club->id]);
        $this->assertDatabaseCount('club_deletion_file_cleanups', 0);
    }
}
