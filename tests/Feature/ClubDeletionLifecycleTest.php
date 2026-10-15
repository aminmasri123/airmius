<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsurePlatformAdminTwoFactor;
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
use App\Models\Role;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPlan;
use App\Models\SupportTicket;
use App\Models\Team;
use App\Models\User;
use App\Notifications\ClubDeletionChanged;
use App\Notifications\ClubDeletionCodeRequested;
use App\Services\ClubDeletionService;
use App\Services\ClubService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
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
        Cache::put("club_deletion_code:{$club->id}:{$club->owner_id}", Hash::make('123456'), now()->addMinutes(15));

        return $this->deleteJson('/api/v1/clubs/'.$club->id, [
            'confirmation' => 'Ja, ich bin mir sicher', 'code' => '123456',
        ]);
    }

    public function test_confirmation_is_required_and_only_owner_can_request_or_cancel(): void
    {
        $club = $this->ownedClub();
        $this->deleteJson('/api/v1/clubs/'.$club->id)->assertUnprocessable();
        $this->deleteJson('/api/v1/clubs/'.$club->id, ['confirmation' => 'delete', 'code' => '123456'])->assertUnprocessable();
        $manager = User::factory()->create();
        $club->users()->attach($manager, ['role' => 'admin', 'roles' => ['admin']]);
        Sanctum::actingAs($manager);
        $this->requestDeletion($club)->assertForbidden();
        $this->deleteJson('/api/v1/clubs/'.$club->id.'/deletion')->assertForbidden();
        $this->getJson('/api/v1/clubs/'.$club->id.'/deletion')->assertForbidden();
        $this->assertNull($club->fresh()->deletion_scheduled_at);
    }

    public function test_password_email_code_and_replay_protection(): void
    {
        $club = $this->ownedClub();
        $this->postJson('/api/v1/clubs/'.$club->id.'/deletion-code', ['identity' => 'wrong'])
            ->assertUnprocessable();
        $this->postJson('/api/v1/clubs/'.$club->id.'/deletion-code', ['identity' => 'password'])
            ->assertOk();
        Notification::assertSentTo($club->owner, ClubDeletionCodeRequested::class);
        $code = Notification::sent($club->owner, ClubDeletionCodeRequested::class)->first()->code;
        $this->deleteJson('/api/v1/clubs/'.$club->id, [
            'confirmation' => __('club_deletion.confirmation'), 'code' => '000000',
        ])->assertUnprocessable();
        $this->deleteJson('/api/v1/clubs/'.$club->id, [
            'confirmation' => __('club_deletion.confirmation'), 'code' => $code,
        ])->assertAccepted();
        $this->deleteJson('/api/v1/clubs/'.$club->id.'/deletion')->assertOk();
        $this->deleteJson('/api/v1/clubs/'.$club->id, [
            'confirmation' => __('club_deletion.confirmation'), 'code' => $code,
        ])->assertUnprocessable();
    }

    public function test_financial_blocker_allows_owner_only_idempotent_support_review_without_deletion(): void
    {
        $club = $this->ownedClub();
        ClubFinanceEntry::create([
            'club_id' => $club->id, 'booked_on' => today(), 'type' => 'income',
            'category' => 'other', 'title' => 'Mitgliedsbeitrag',
            'amount' => 25, 'account' => 'cash',
        ]);
        $url = '/api/v1/clubs/'.$club->id.'/deletion-review';

        $first = $this->postJson($url)->assertOk();
        $ticketId = $first->json('ticket_id');
        $this->assertIsInt($ticketId);
        $this->assertSame($ticketId, $first->json('data.review_ticket_id'));
        $this->postJson($url)->assertOk()->assertJsonPath('ticket_id', $ticketId);
        $this->assertSame(1, SupportTicket::query()->where('report_source', 'club_deletion_review')->count());
        $this->assertNull($club->fresh()->deletion_scheduled_at);
        $this->assertNull(SupportTicket::query()->findOrFail($ticketId)->club_id);

        $manager = User::factory()->create();
        $club->users()->attach($manager, ['role' => 'admin', 'roles' => ['admin']]);
        Sanctum::actingAs($manager);
        $this->postJson($url)->assertForbidden();
        $this->assertSame(1, SupportTicket::query()->where('report_source', 'club_deletion_review')->count());
    }

    public function test_financial_club_requires_export_external_archive_and_support_approval_before_purge(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $club = $this->ownedClub();
        $other = Club::factory()->create(['owner_id' => User::factory()->create()->id]);
        $entry = ClubFinanceEntry::create([
            'club_id' => $club->id, 'booked_on' => today(), 'type' => 'income',
            'category' => 'other', 'title' => 'Fee', 'amount' => 25, 'account' => 'cash',
        ]);
        $foreign = ClubFinanceEntry::create([
            'club_id' => $other->id, 'booked_on' => today(), 'type' => 'income',
            'category' => 'other', 'title' => 'Other fee', 'amount' => 20, 'account' => 'cash',
        ]);
        $invoiceId = DB::table('invoices')->insertGetId([
            'club_id' => $club->id, 'number' => 'TEST-CLUB-1', 'amount' => 25, 'due_date' => now(),
        ]);
        $batchId = DB::table('club_sepa_batches')->insertGetId([
            'club_id' => $club->id, 'reference' => 'TEST-SEPA-1',
            'collection_date' => today(), 'notice_days' => 14, 'creditor_snapshot' => '{}',
        ]);
        $itemId = DB::table('club_sepa_batch_items')->insertGetId([
            'club_sepa_batch_id' => $batchId, 'invoice_id' => $invoiceId,
            'amount_cents' => 2500, 'debtor_snapshot' => '{}',
        ]);
        $noticeId = DB::table('club_sepa_notices')->insertGetId([
            'club_sepa_batch_id' => $batchId, 'club_sepa_batch_item_id' => $itemId, 'content' => 'Notice',
        ]);
        DB::table('club_sepa_notice_provider_events')->insert([
            'club_sepa_notice_id' => $noticeId, 'provider' => 'test',
            'event_key' => 'TEST-SEPA-EVENT-1', 'event_type' => 'delivered', 'occurred_at' => now(),
        ]);
        $plan = SubscriptionPlan::query()->where('slug', 'starter')->firstOrFail();
        $platformInvoice = SubscriptionInvoice::create([
            'user_id' => $club->owner_id, 'club_id' => $club->id,
            'subscription_plan_id' => $plan->id,
            'number' => 'PLATFORM-1', 'title' => 'Airmius Starter',
            'amount_cents' => 900, 'currency' => 'EUR', 'status' => 'paid', 'issued_at' => now(),
        ]);
        $this->requestDeletion($club)->assertUnprocessable();
        $this->postJson('/api/v1/clubs/'.$club->id.'/deletion-archive-confirmation', ['archive_confirmed' => true])
            ->assertUnprocessable();
        $this->get('/api/v1/clubs/'.$club->id.'/deletion-financial-export')->assertOk();
        $this->postJson('/api/v1/clubs/'.$club->id.'/deletion-archive-confirmation', ['archive_confirmed' => true])
            ->assertOk()->assertJsonPath('data.can_request', false);
        $this->postJson('/api/v1/clubs/'.$club->id.'/deletion-review')->assertOk()
            ->assertJsonPath('data.can_request', true);
        $this->requestDeletion($club)->assertAccepted();
        $this->travel(30)->days();
        app(ClubDeletionService::class)->process($club->id);
        $this->assertDatabaseHas('clubs', ['id' => $club->id]);
        $this->assertNotNull($club->fresh()->deletion_blocked_at);

        $manager = User::factory()->create();
        Sanctum::actingAs($manager);
        $reviewUrl = '/api/v1/admin/clubs/'.$club->id.'/deletion-finance-review';
        $this->getJson($reviewUrl)->assertForbidden();
        $this->postJson($reviewUrl, [
            'decision' => 'approve', 'note' => 'Not authorized to approve deletion.',
            'reviewed_contract_and_retention' => true,
        ])->assertForbidden();

        $admin = User::factory()->create();
        Role::findOrCreate('super_admin', 'web');
        $admin->assignRole('super_admin');
        Sanctum::actingAs($admin);
        $this->postJson($reviewUrl, [
            'decision' => 'approve', 'note' => 'Export and external archive checked by support.',
            'reviewed_contract_and_retention' => true,
        ])->assertForbidden();
        $this->withoutMiddleware(EnsurePlatformAdminTwoFactor::class);
        $this->postJson($reviewUrl, [
            'decision' => 'approve', 'note' => 'Export and external archive checked by support.',
            'reviewed_contract_and_retention' => true,
        ])->assertUnprocessable();
        $this->postJson($reviewUrl, [
            'decision' => 'approve', 'note' => 'Export, authority and external archive checked; no active provider contract.',
            'reviewed_contract_and_retention' => true, 'reviewed_authority' => true,
        ])->assertOk();
        app(ClubDeletionService::class)->process($club->id);
        $this->assertDatabaseMissing('clubs', ['id' => $club->id]);
        $this->assertDatabaseMissing('club_finance_entries', ['id' => $entry->id]);
        $this->assertDatabaseMissing('club_sepa_batches', ['id' => $batchId]);
        $this->assertDatabaseMissing('club_sepa_batch_items', ['id' => $itemId]);
        $this->assertDatabaseMissing('club_sepa_notices', ['id' => $noticeId]);
        $this->assertDatabaseMissing('invoices', ['id' => $invoiceId]);
        $this->assertDatabaseHas('subscription_invoices', ['id' => $platformInvoice->id, 'club_id' => null]);
        $this->assertSame($club->name, $platformInvoice->fresh()->recipient_snapshot['club_name']);
        $this->assertDatabaseHas('club_finance_entries', ['id' => $foreign->id]);
        $this->assertDatabaseHas('users', ['id' => $club->owner_id]);
        $this->assertDatabaseHas('club_deletion_financial_reviews', [
            'club_id' => null, 'club_id_snapshot' => $club->id, 'approved_by' => $admin->id,
        ]);
        foreach (Schema::getTableListing(schemaQualified: false) as $table) {
            if (in_array('club_id', Schema::getColumnListing($table), true)) {
                $this->assertFalse(DB::table($table)->where('club_id', $club->id)->exists(), $table.' still references the deleted club');
            }
        }
    }

    public function test_legacy_airmius_invoice_keeps_club_recipient_after_club_deletion(): void
    {
        $club = $this->ownedClub();
        $owner = User::findOrFail($club->owner_id);
        $plan = SubscriptionPlan::query()->where('slug', 'starter')->firstOrFail();
        $legacyInvoice = SubscriptionInvoice::create([
            'user_id' => $owner->id, 'club_id' => $club->id,
            'subscription_plan_id' => $plan->id,
            'number' => 'PLATFORM-LEGACY-1', 'title' => 'Airmius Starter',
            'amount_cents' => 900, 'currency' => 'EUR', 'status' => 'paid', 'issued_at' => now(),
        ]);
        DB::table('subscription_invoices')->where('id', $legacyInvoice->id)->update(['recipient_snapshot' => null]);
        $partialInvoice = SubscriptionInvoice::create([
            'user_id' => $owner->id, 'club_id' => $club->id,
            'subscription_plan_id' => $plan->id,
            'number' => 'PLATFORM-LEGACY-2', 'title' => 'Airmius Starter',
            'amount_cents' => 900, 'currency' => 'EUR', 'status' => 'paid', 'issued_at' => now(),
        ]);
        $partialInvoice->update(['recipient_snapshot' => [
            'name' => 'Historischer Empfänger', 'email' => 'historic@example.test',
        ]]);

        $this->requestDeletion($club)->assertAccepted();
        $this->travel(30)->days();
        app(ClubDeletionService::class)->process($club->id);

        $this->assertDatabaseMissing('clubs', ['id' => $club->id]);
        $this->assertDatabaseHas('subscription_invoices', ['id' => $legacyInvoice->id, 'club_id' => null]);
        $snapshot = $legacyInvoice->fresh()->recipient_snapshot;
        $this->assertSame($club->name, $snapshot['club_name']);
        $this->assertSame($owner->name, $snapshot['name']);
        $this->assertSame($owner->email, $snapshot['email']);
        $partialSnapshot = $partialInvoice->fresh()->recipient_snapshot;
        $this->assertSame($club->name, $partialSnapshot['club_name']);
        $this->assertSame('Historischer Empfänger', $partialSnapshot['name']);
        $this->assertSame('historic@example.test', $partialSnapshot['email']);
    }

    public function test_financial_review_is_invalidated_when_a_new_record_is_added(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $club = $this->ownedClub();
        ClubFinanceEntry::create([
            'club_id' => $club->id, 'booked_on' => today(), 'type' => 'income',
            'category' => 'other', 'title' => 'Fee', 'amount' => 25, 'account' => 'cash',
        ]);
        $this->get('/api/v1/clubs/'.$club->id.'/deletion-financial-export')->assertOk();
        $this->postJson('/api/v1/clubs/'.$club->id.'/deletion-archive-confirmation', ['archive_confirmed' => true])->assertOk();
        $this->postJson('/api/v1/clubs/'.$club->id.'/deletion-review')->assertOk();
        $admin = User::factory()->create();
        Role::findOrCreate('super_admin', 'web');
        $admin->assignRole('super_admin');
        app(ClubDeletionService::class)->decideFinancialReview($club, $admin, true, 'Current records and external archive verified by support.');
        ClubFinanceEntry::create([
            'club_id' => $club->id, 'booked_on' => today(), 'type' => 'expense',
            'category' => 'other', 'title' => 'Late expense', 'amount' => 5, 'account' => 'cash',
        ]);
        $this->getJson('/api/v1/clubs/'.$club->id.'/deletion')->assertJsonPath('data.can_request', false);
        $this->requestDeletion($club)->assertUnprocessable();
        $this->assertNotNull(app(ClubDeletionService::class)->blocker($club));
    }

    public function test_cancellation_revokes_a_financial_approval_for_future_requests(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $club = $this->ownedClub();
        ClubFinanceEntry::create([
            'club_id' => $club->id, 'booked_on' => today(), 'type' => 'income',
            'category' => 'other', 'title' => 'Fee', 'amount' => 25, 'account' => 'cash',
        ]);
        $this->get('/api/v1/clubs/'.$club->id.'/deletion-financial-export')->assertOk();
        $this->postJson('/api/v1/clubs/'.$club->id.'/deletion-archive-confirmation', ['archive_confirmed' => true])->assertOk();
        $this->postJson('/api/v1/clubs/'.$club->id.'/deletion-review')->assertOk();
        $admin = User::factory()->create();
        Role::findOrCreate('super_admin', 'web');
        $admin->assignRole('super_admin');
        app(ClubDeletionService::class)->decideFinancialReview($club, $admin, true, 'Current records and external archive verified by support.');
        $this->requestDeletion($club)->assertAccepted();
        $this->deleteJson('/api/v1/clubs/'.$club->id.'/deletion')->assertOk()
            ->assertJsonPath('data.financial_revoked_at', now()->toIso8601String())
            ->assertJsonPath('data.can_request', false);
        $this->requestDeletion($club)->assertUnprocessable();
        $this->assertDatabaseHas('clubs', ['id' => $club->id]);
    }

    public function test_web_settings_request_uses_the_same_owner_and_code_checks(): void
    {
        $owner = User::factory()->create(['language' => 'de']);
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $stranger = User::factory()->create();
        Sanctum::actingAs($stranger);
        $this->postJson(route('auth.clubs.deletion.request', $club), [
            'confirmation' => 'Ja, ich bin mir sicher', 'code' => '123456',
        ])->assertForbidden();

        Cache::put("club_deletion_code:{$club->id}:{$owner->id}", Hash::make('123456'), now()->addMinutes(15));
        Sanctum::actingAs($owner);
        $this->postJson(route('auth.clubs.deletion.request', $club), [
            'confirmation' => 'Ja, ich bin mir sicher', 'code' => '123456',
        ])->assertAccepted()->assertJsonPath('data.grace_days', 30);
        $this->assertNotNull($club->fresh()->deletion_scheduled_at);
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
        Notification::assertSentTo($president, ClubDeletionChanged::class, function (ClubDeletionChanged $notice) use ($club): bool {
            return $notice->event === 'requested'
                && str_contains($notice->body, $club->name)
                && str_contains($notice->body, $club->fresh()->deletion_scheduled_at->utc()->format('Y-m-d H:i').' UTC')
                && $notice->url === route('auth.clubs.show', $club->id);
        });
        $this->travel(1)->days();
        $this->requestDeletion($club)->assertStatus(202)->assertJsonPath('data.scheduled_at', $due);
        Notification::assertSentTimes(ClubDeletionChanged::class, 2);
        $this->assertDatabaseHas('clubs', ['id' => $club->id]);
        $this->assertDatabaseHas('activities', [
            'club_id' => $club->id,
            'type' => 'club.deletion.requested',
        ]);
    }

    public function test_cancel_keeps_data_after_deadline_and_new_request_gets_full_period(): void
    {
        $club = $this->ownedClub();
        $this->requestDeletion($club)->assertStatus(202);
        $this->travel(29)->days();
        $this->deleteJson('/api/v1/clubs/'.$club->id.'/deletion')->assertOk()->assertJsonPath('data.scheduled_at', null);
        $this->assertDatabaseHas('activities', [
            'club_id' => $club->id,
            'type' => 'club.deletion.cancelled',
        ]);
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
        $meetingId = DB::table('club_governance_meetings')->insertGetId([
            'club_id' => $club->id, 'type' => 'board',
            'title' => 'Vorstandssitzung', 'participant_scope' => 'board',
        ]);
        $volunteerId = DB::table('club_volunteer_profiles')->insertGetId([
            'club_id' => $club->id, 'user_id' => $club->owner_id,
        ]);
        $competitionId = DB::table('competitions')->insertGetId([
            'club_id' => $club->id, 'name' => 'Vereinswettkampf',
        ]);
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
        $this->assertDatabaseMissing('club_governance_meetings', ['id' => $meetingId]);
        $this->assertDatabaseMissing('club_volunteer_profiles', ['id' => $volunteerId]);
        $this->assertDatabaseMissing('competitions', ['id' => $competitionId]);
        $this->assertDatabaseHas('files', ['id' => $otherFile->id]);
        $this->assertDatabaseHas('clubs', ['id' => $other->id]);
        $this->assertDatabaseHas('teams', ['id' => $otherTeam->id]);
        $this->assertDatabaseHas('users', ['id' => $ownerId]);
        $this->getJson('/api/v1/clubs/'.$club->id)->assertNotFound();
        Sanctum::actingAs($other->owner);
        $this->getJson('/api/v1/clubs/'.$other->id)->assertOk();
        Storage::disk('public')->assertMissing('events/test.pdf');
        Storage::disk('public')->assertExists('shared.pdf');
        $this->assertDatabaseCount('club_deletion_file_cleanups', 0);
    }

    public function test_service_hour_period_does_not_require_a_financial_archive_review(): void
    {
        $club = $this->ownedClub();
        $periodId = DB::table('club_year_periods')->insertGetId([
            'club_id' => $club->id, 'type' => 'calendar_year', 'name' => '2026',
            'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31',
        ]);
        $hoursId = DB::table('club_service_hour_records')->insertGetId([
            'club_id' => $club->id, 'club_year_period_id' => $periodId,
            'user_id' => $club->owner_id, 'recorded_by' => $club->owner_id,
            'served_on' => '2026-10-15', 'minutes' => 120, 'title' => 'Aufbau',
        ]);

        $this->requestDeletion($club)->assertStatus(202);
        $this->travel(30)->days();
        $this->artisan('airmius:process-club-deletions')->assertSuccessful();

        $this->assertDatabaseMissing('clubs', ['id' => $club->id]);
        $this->assertDatabaseMissing('club_year_periods', ['id' => $periodId]);
        $this->assertDatabaseMissing('club_service_hour_records', ['id' => $hoursId]);
        $this->assertDatabaseHas('users', ['id' => $club->owner_id]);
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
        $this->assertDatabaseHas('clubs', ['id' => $club->id]);
        $this->getJson('/api/v1/clubs/'.$club->id.'/deletion')
            ->assertOk()
            ->assertJsonPath('data.blocked', true)
            ->assertJsonPath('data.blocker', __('club_deletion.subscription'));
        Notification::assertSentTimes(ClubDeletionChanged::class, 3);
        $this->deleteJson('/api/v1/clubs/'.$club->id.'/deletion')->assertOk();
    }

    public function test_web_delete_uses_same_confirmation_and_grace_period(): void
    {
        $club = $this->ownedClub();
        Cache::put("club_deletion_code:{$club->id}:{$club->owner_id}", Hash::make('123456'), now()->addMinutes(15));
        $this->actingAs($club->owner)->from('/dashboard')->delete(route('auth.clubs.destroy', $club), [
            'confirmation' => 'Ja, ich bin mir sicher',
            'code' => '123456',
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
        $this->assertDatabaseHas('clubs', ['id' => $club->id]);
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

    public function test_new_accounting_record_blocks_execution_after_request(): void
    {
        $club = $this->ownedClub();
        $this->requestDeletion($club)->assertStatus(202);
        ClubFinanceEntry::create([
            'club_id' => $club->id, 'booked_on' => today(), 'type' => 'income',
            'category' => 'other', 'title' => 'Beleg', 'amount' => 25, 'account' => 'cash',
        ]);

        $this->travel(30)->days();
        app(ClubDeletionService::class)->process($club->id);

        $this->assertDatabaseHas('clubs', ['id' => $club->id]);
        $this->getJson('/api/v1/clubs/'.$club->id.'/deletion')
            ->assertOk()
            ->assertJsonPath('data.blocked', true)
            ->assertJsonPath('data.blocker', __('club_deletion.finance_retention'));
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
        $this->assertNotNull($club->fresh()->deletion_scheduled_at);
        app()->forgetInstance(ClubService::class);
        $this->artisan('airmius:process-club-deletions')->assertSuccessful();
        $this->assertDatabaseMissing('clubs', ['id' => $club->id]);
    }

    public function test_due_deletion_removes_private_media_from_the_local_disk(): void
    {
        Storage::fake('local');
        $club = $this->ownedClub();
        $post = Post::factory()->create([
            'club_id' => $club->id, 'user_id' => $club->owner_id,
            'image' => 'private-post-media/audit/image.jpg', 'visibility' => 'private',
        ]);
        Storage::disk('local')->put($post->image, 'private');
        Storage::disk('public')->put($post->image, 'unrelated file on another disk');
        $this->requestDeletion($club)->assertStatus(202);
        $this->travel(31)->days();

        $this->artisan('airmius:process-club-deletions')->assertSuccessful();

        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
        Storage::disk('local')->assertMissing($post->image);
        Storage::disk('public')->assertExists($post->image);
        $this->assertDatabaseCount('club_deletion_file_cleanups', 0);
    }
}
