<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubInventoryItem;
use App\Models\ClubInventoryLoan;
use App\Models\File;
use App\Models\Folder;
use App\Models\OrganizationJob;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPlan;
use App\Models\Team;
use App\Models\TrainingPlan;
use App\Models\TrainingSession;
use App\Models\TrainingSessionVersion;
use App\Models\User;
use App\Services\ClubDataErasureService;
use App\Support\EmailTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Jetstream\Contracts\DeletesUsers;
use Laravel\Jetstream\Features;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeleteAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_accounts_can_be_deleted(): void
    {
        if (! Features::hasAccountDeletionFeatures()) {
            $this->markTestSkipped('Account deletion is not enabled.');
        }

        $this->actingAs($user = User::factory()->create());
        Notification::fake();

        $this
            ->withSession([
                'account_deletion_confirmation' => [
                    'user_id' => $user->id,
                    'code_hash' => Hash::make('123456'),
                    'expires_at' => now()->addMinutes(15)->timestamp,
                ],
            ])
            ->delete('/user', [
                'code' => '123456',
            ]);

        $this->assertNull($user->fresh());
    }

    public function test_correct_code_must_be_provided_before_account_can_be_deleted(): void
    {
        if (! Features::hasAccountDeletionFeatures()) {
            $this->markTestSkipped('Account deletion is not enabled.');
        }

        $this->actingAs($user = User::factory()->create());
        Notification::fake();

        $this
            ->withSession([
                'account_deletion_confirmation' => [
                    'user_id' => $user->id,
                    'code_hash' => Hash::make('123456'),
                    'expires_at' => now()->addMinutes(15)->timestamp,
                ],
            ])
            ->delete('/user', [
                'code' => 'wrong-code',
            ]);

        $this->assertNotNull($user->fresh());
    }

    public function test_club_owner_cannot_delete_account_even_with_valid_code(): void
    {
        $user = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $user->id]);
        $this->actingAs($user);
        Notification::fake();

        $this->withSession([
            'account_deletion_confirmation' => [
                'user_id' => $user->id,
                'code_hash' => Hash::make('123456'),
                'expires_at' => now()->addMinutes(15)->timestamp,
            ],
        ])->delete('/user', ['code' => '123456'])
            ->assertSessionHasErrors('account');

        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('clubs', ['id' => $club->id, 'owner_id' => $user->id]);
    }

    public function test_rejected_mobile_owner_deletion_keeps_session_and_confirmation_code(): void
    {
        $user = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $user->id]);
        $token = $user->createToken('deletion-test');
        $key = "mobile_account_deletion_confirmation:{$user->id}";
        Cache::put($key, [
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(15)->timestamp,
        ], now()->addMinutes(15));

        $this->withToken($token->plainTextToken)
            ->deleteJson('/api/v1/account', ['code' => '123456'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('account');

        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('clubs', ['id' => $club->id, 'owner_id' => $user->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $token->accessToken->id]);
        $this->assertNotNull(Cache::get($key));
        $this->getJson('/api/v1/me')->assertOk();
    }

    public function test_account_deletion_retains_club_payment_and_airmius_invoice_with_recipient_snapshot(): void
    {
        $user = User::factory()->create(['name' => 'Max Mustermann', 'email' => 'max@example.test']);
        $club = Club::factory()->create(['owner_id' => User::factory()->create()->id]);
        $payment = Payment::create([
            'club_id' => $club->id, 'user_id' => $user->id,
            'amount' => 25, 'status' => 'paid',
        ]);
        $plan = SubscriptionPlan::query()->where('slug', 'starter')->firstOrFail();
        $invoice = SubscriptionInvoice::create([
            'user_id' => $user->id, 'subscription_plan_id' => $plan->id,
            'number' => 'TEST-ACCOUNT-DELETION-1', 'title' => 'Starter',
            'amount_cents' => 900, 'currency' => 'EUR', 'status' => 'paid',
            'issued_at' => now(),
        ]);

        app(DeletesUsers::class)->delete($user);

        $this->assertNull($user->fresh());
        $this->assertSame($club->id, $payment->fresh()->club_id);
        $this->assertNull($payment->fresh()->user_id);
        $this->assertSame('Max Mustermann', $payment->fresh()->payer_snapshot['name']);
        $this->assertNull($invoice->fresh()->user_id);
        $this->assertSame('max@example.test', $invoice->fresh()->recipient_snapshot['email']);
        $this->assertSame('TEST-ACCOUNT-DELETION-1', $invoice->fresh()->number);
    }

    public function test_account_deletion_preserves_club_content_but_removes_private_file_records(): void
    {
        config()->set('filesystems.uploads_disk', 'public');
        Storage::fake('public');
        Storage::disk('public')->put('clubs/test.pdf', 'club');
        Storage::disk('public')->put('private/test.pdf', 'private');
        $user = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => User::factory()->create()->id]);
        $clubFile = File::create([
            'club_id' => $club->id, 'user_id' => $user->id,
            'path' => 'clubs/test.pdf', 'type' => 'application/pdf',
        ]);
        $privateFile = File::create([
            'user_id' => $user->id, 'path' => 'private/test.pdf', 'type' => 'application/pdf',
        ]);
        $folder = Folder::create([
            'club_id' => $club->id, 'user_id' => $user->id, 'name' => 'Vereinsordner',
        ]);
        $nestedFolder = Folder::create([
            'parent_id' => $folder->id, 'user_id' => $user->id, 'name' => 'Unterordner',
        ]);
        $nestedFile = File::create([
            'folder_id' => $nestedFolder->id, 'user_id' => $user->id,
            'path' => 'clubs/nested.pdf', 'type' => 'application/pdf',
        ]);
        $job = OrganizationJob::create([
            'club_id' => $club->id, 'created_by' => $user->id,
            'title' => 'Trainer gesucht', 'description' => 'Training',
        ]);

        app(DeletesUsers::class)->delete($user);

        $this->assertNull($user->fresh());
        $this->assertNull($clubFile->fresh()->user_id);
        $this->assertNull($folder->fresh()->user_id);
        $this->assertNull($nestedFolder->fresh()->user_id);
        $this->assertNull($nestedFile->fresh()->user_id);
        $this->assertNull($job->fresh()->created_by);
        $this->assertNull($privateFile->fresh());
        app(ClubDataErasureService::class)->cleanupFiles();
        Storage::disk('public')->assertExists('clubs/test.pdf');
        Storage::disk('public')->assertMissing('private/test.pdf');
    }

    public function test_account_deletion_preserves_club_training_but_removes_private_training(): void
    {
        $user = User::factory()->create();
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $team = Team::factory()->create(['club_id' => $club->id]);
        $clubPlan = TrainingPlan::create([
            'created_by' => $user->id, 'team_id' => $team->id, 'title' => 'Teamtraining',
        ]);
        $privatePlan = TrainingPlan::create([
            'created_by' => $user->id, 'title' => 'Privates Training',
        ]);
        $clubSession = TrainingSession::create([
            'created_by' => $user->id, 'club_id' => $club->id, 'team_id' => $team->id,
            'title' => 'Vereinseinheit',
        ]);
        $clubVersion = TrainingSessionVersion::create([
            'training_session_id' => $clubSession->id, 'created_by' => $user->id,
            'revision' => 1, 'snapshot' => ['title' => 'Vereinseinheit'],
        ]);
        $privateSession = TrainingSession::create([
            'created_by' => $user->id, 'title' => 'Private Einheit',
        ]);

        app(DeletesUsers::class)->delete($user);

        $this->assertNull($user->fresh());
        $this->assertNull($clubPlan->fresh()->created_by);
        $this->assertNull($clubSession->fresh()->created_by);
        $this->assertNull($clubVersion->fresh()->created_by);
        $this->assertNull($privatePlan->fresh());
        $this->assertNull($privateSession->fresh());
        $this->assertNotNull($club->fresh());
    }

    public function test_account_deletion_keeps_club_procurement_and_detaches_person(): void
    {
        $user = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => User::factory()->create()->id]);
        $requestId = DB::table('club_procurement_requests')->insertGetId([
            'club_id' => $club->id, 'requested_by' => $user->id,
            'title' => 'Trikots kaufen',
        ]);
        $receiptId = DB::table('club_procurement_receipts')->insertGetId([
            'club_id' => $club->id, 'club_procurement_request_id' => $requestId,
            'received_by' => $user->id, 'received_on' => today(),
        ]);

        app(DeletesUsers::class)->delete($user);

        $this->assertNull($user->fresh());
        $this->assertDatabaseHas('club_procurement_requests', [
            'id' => $requestId, 'club_id' => $club->id, 'requested_by' => null,
        ]);
        $this->assertDatabaseHas('club_procurement_receipts', [
            'id' => $receiptId, 'club_id' => $club->id, 'received_by' => null,
        ]);
    }

    public function test_account_deletion_keeps_an_open_club_inventory_loan_with_a_minimal_borrower_snapshot(): void
    {
        $owner = User::factory()->create();
        $borrower = User::factory()->create(['name' => 'Max Mustermann']);
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $item = ClubInventoryItem::query()->create([
            'club_id' => $club->id,
            'name' => 'Vereinsball',
            'quantity_total' => 2,
            'quantity_available' => 1,
            'condition' => 'good',
            'status' => 'active',
            'is_loanable' => true,
        ]);
        $loan = ClubInventoryLoan::query()->create([
            'club_id' => $club->id,
            'club_inventory_item_id' => $item->id,
            'borrower_id' => $borrower->id,
            'quantity' => 1,
            'status' => 'active',
            'due_at' => now()->addWeek(),
        ]);

        app(DeletesUsers::class)->delete($borrower);

        $this->assertNull($borrower->fresh());
        $this->assertDatabaseHas('club_inventory_loans', [
            'id' => $loan->id,
            'club_id' => $club->id,
            'borrower_id' => null,
            'borrower_name_snapshot' => 'Max Mustermann',
            'status' => 'active',
        ]);
        $this->assertSame(1, $item->fresh()->quantity_available);

        Sanctum::actingAs($owner);
        $this->getJson("/api/v1/clubs/{$club->id}/inventory")
            ->assertOk()
            ->assertJsonPath('data.loans.0.borrower_name_snapshot', 'Max Mustermann');

        $member = User::factory()->create();
        $club->users()->attach($member->id, ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active']);
        Sanctum::actingAs($member);
        $this->getJson("/api/v1/clubs/{$club->id}/inventory")
            ->assertOk()
            ->assertJsonCount(0, 'data.loans');
    }

    public function test_account_deletion_cancels_pending_inventory_requests_without_retaining_a_name(): void
    {
        $borrower = User::factory()->create(['name' => 'Max Mustermann']);
        $club = Club::factory()->create(['owner_id' => User::factory()->create()->id]);
        $item = ClubInventoryItem::query()->create([
            'club_id' => $club->id,
            'name' => 'Vereinsball',
            'quantity_total' => 2,
            'quantity_available' => 2,
            'condition' => 'good',
            'status' => 'active',
            'is_loanable' => true,
        ]);
        $loan = ClubInventoryLoan::query()->create([
            'club_id' => $club->id,
            'club_inventory_item_id' => $item->id,
            'borrower_id' => $borrower->id,
            'quantity' => 1,
            'status' => 'pending',
        ]);

        app(DeletesUsers::class)->delete($borrower);

        $this->assertDatabaseHas('club_inventory_loans', [
            'id' => $loan->id,
            'club_id' => $club->id,
            'borrower_id' => null,
            'borrower_name_snapshot' => null,
            'status' => 'cancelled',
        ]);
        $this->assertSame(2, $item->fresh()->quantity_available);
    }

    public function test_confirmed_return_removes_borrower_snapshot_only_when_no_claim_remains(): void
    {
        $owner = User::factory()->create();
        $borrower = User::factory()->create(['name' => 'Max Mustermann']);
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $item = ClubInventoryItem::query()->create([
            'club_id' => $club->id,
            'name' => 'Vereinsball',
            'quantity_total' => 3,
            'quantity_available' => 0,
            'condition' => 'good',
            'status' => 'active',
            'is_loanable' => true,
        ]);
        $loans = collect([0, 0, 500])->map(fn (int $deposit) => ClubInventoryLoan::query()->create([
            'club_id' => $club->id,
            'club_inventory_item_id' => $item->id,
            'borrower_id' => $borrower->id,
            'quantity' => 1,
            'status' => 'active',
            'rental_deposit_cents' => $deposit,
        ]));

        app(DeletesUsers::class)->delete($borrower);
        $member = User::factory()->create();
        $club->users()->attach($member->id, ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active']);
        Sanctum::actingAs($member);
        $this->postJson("/api/v1/clubs/{$club->id}/inventory/loans/{$loans[0]->id}/return", [
            'return_condition' => 'good',
        ])->assertForbidden();

        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/clubs/{$club->id}/inventory/loans/{$loans[0]->id}/return", [
            'return_condition' => 'good',
        ])->assertOk()->assertJsonPath('data.borrower_name_snapshot', null);
        $this->postJson("/api/v1/clubs/{$club->id}/inventory/loans/{$loans[1]->id}/return", [
            'return_condition' => 'damaged',
            'damage_claim_cents' => 200,
        ])->assertOk()->assertJsonPath('data.borrower_name_snapshot', 'Max Mustermann');
        $this->postJson("/api/v1/clubs/{$club->id}/inventory/loans/{$loans[2]->id}/return", [
            'return_condition' => 'good',
            'deposit_refunded_cents' => 0,
        ])->assertOk()->assertJsonPath('data.borrower_name_snapshot', 'Max Mustermann');

        $this->assertDatabaseHas('club_inventory_loans', ['id' => $loans[0]->id, 'status' => 'returned', 'borrower_name_snapshot' => null]);
        $this->assertDatabaseHas('club_inventory_loans', ['id' => $loans[1]->id, 'status' => 'returned', 'borrower_name_snapshot' => 'Max Mustermann']);
        $this->assertDatabaseHas('club_inventory_loans', ['id' => $loans[2]->id, 'status' => 'returned', 'borrower_name_snapshot' => 'Max Mustermann']);
    }

    public function test_inventory_loan_foreign_key_keeps_the_record_if_a_user_row_is_deleted_directly(): void
    {
        $borrower = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => User::factory()->create()->id]);
        $item = ClubInventoryItem::query()->create([
            'club_id' => $club->id,
            'name' => 'Vereinsball',
            'quantity_total' => 2,
            'quantity_available' => 1,
            'condition' => 'good',
            'status' => 'active',
            'is_loanable' => true,
        ]);
        $loan = ClubInventoryLoan::query()->create([
            'club_id' => $club->id,
            'club_inventory_item_id' => $item->id,
            'borrower_id' => $borrower->id,
            'quantity' => 1,
            'status' => 'active',
        ]);

        DB::table('users')->where('id', $borrower->id)->delete();

        $this->assertDatabaseHas('club_inventory_loans', [
            'id' => $loan->id,
            'club_id' => $club->id,
            'borrower_id' => null,
            'status' => 'active',
        ]);
    }

    public function test_account_deletion_keeps_club_service_hour_history_for_other_members(): void
    {
        $recorder = User::factory()->create();
        $member = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => User::factory()->create()->id]);
        $periodId = DB::table('club_year_periods')->insertGetId([
            'club_id' => $club->id, 'type' => 'calendar_year', 'name' => '2026',
            'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31',
        ]);
        $recordId = DB::table('club_service_hour_records')->insertGetId([
            'club_id' => $club->id, 'club_year_period_id' => $periodId,
            'user_id' => $member->id, 'recorded_by' => $recorder->id,
            'served_on' => '2026-10-14', 'minutes' => 120, 'title' => 'Aufbau',
        ]);
        $correctionId = DB::table('club_service_hour_corrections')->insertGetId([
            'club_service_hour_record_id' => $recordId, 'club_id' => $club->id,
            'club_year_period_id' => $periodId, 'user_id' => $member->id,
            'corrected_by' => $recorder->id, 'minutes_delta' => 30,
            'previous_total_minutes' => 120, 'corrected_total_minutes' => 150,
            'reason' => 'Nachtrag',
        ]);
        $exemptionId = DB::table('club_service_hour_exemptions')->insertGetId([
            'club_id' => $club->id, 'club_year_period_id' => $periodId,
            'user_id' => $member->id, 'approved_by' => $recorder->id,
            'minutes' => 60, 'reason' => 'Freistellung',
        ]);

        app(DeletesUsers::class)->delete($recorder);

        $this->assertDatabaseHas('club_service_hour_records', [
            'id' => $recordId, 'user_id' => $member->id, 'recorded_by' => null,
        ]);
        $this->assertDatabaseHas('club_service_hour_corrections', [
            'id' => $correctionId, 'user_id' => $member->id, 'corrected_by' => null,
        ]);
        $this->assertDatabaseHas('club_service_hour_exemptions', [
            'id' => $exemptionId, 'user_id' => $member->id, 'approved_by' => null,
        ]);
    }

    public function test_account_deletion_does_not_remove_a_recorded_club_vote(): void
    {
        $voter = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => User::factory()->create()->id]);
        $meetingId = DB::table('club_governance_meetings')->insertGetId([
            'club_id' => $club->id, 'type' => 'board',
            'title' => 'Vorstandssitzung', 'participant_scope' => 'board',
        ]);
        $recipientId = DB::table('club_governance_meeting_recipients')->insertGetId([
            'club_id' => $club->id, 'club_governance_meeting_id' => $meetingId,
            'user_id' => $voter->id, 'voting_eligible' => true,
        ]);
        $decisionId = DB::table('club_governance_meeting_decisions')->insertGetId([
            'club_id' => $club->id, 'club_governance_meeting_id' => $meetingId,
            'meeting_version' => 1, 'type' => 'resolution', 'voting_mode' => 'open',
            'title' => 'Trikots kaufen', 'status' => 'closed',
        ]);
        $voteId = DB::table('club_governance_meeting_decision_votes')->insertGetId([
            'club_id' => $club->id,
            'club_governance_meeting_decision_id' => $decisionId,
            'club_governance_meeting_recipient_id' => $recipientId,
            'user_id' => $voter->id, 'choice' => 'yes',
            'person_name' => $voter->name,
        ]);

        app(DeletesUsers::class)->delete($voter);

        $this->assertDatabaseHas('club_governance_meeting_recipients', [
            'id' => $recipientId, 'user_id' => null,
        ]);
        $this->assertDatabaseHas('club_governance_meeting_decision_votes', [
            'id' => $voteId, 'user_id' => null, 'choice' => 'yes',
        ]);
        $this->assertDatabaseHas('club_governance_meeting_decisions', [
            'id' => $decisionId, 'status' => 'closed',
        ]);
    }

    public function test_legacy_account_deletion_email_template_is_repaired_with_german_umlauts(): void
    {
        $templates = EmailTemplate::defaultsForValidation();
        $templates['account_deletion_code'] = [
            'subject' => 'Bestaetigungscode zur Kontoloeschung',
            'greeting' => 'Hallo,',
            'body' => 'Du kannst dein Konto loeschen. Dein Bestaetigungscode ist 778503 und 15 Minuten gueltig.',
            'action_label' => '',
        ];

        Setting::setValue(EmailTemplate::SETTINGS_KEY, json_encode($templates));

        $migration = require base_path('database/migrations/2026_08_02_000004_fix_legacy_german_account_deletion_email_template.php');
        $migration->up();

        $content = EmailTemplate::content('account_deletion_code', ['code' => '778503'], 'de');

        $this->assertSame('Bestätigungscode zur Kontolöschung', $content['subject']);
        $this->assertStringContainsString('Bestätigungscode lautet: 778503', $content['body']);
        $this->assertStringContainsString('15 Minuten gültig', $content['body']);
        $this->assertStringNotContainsString('Bestaetigung', $content['subject'].$content['body']);
        $this->assertStringNotContainsString('loesch', $content['body']);
        $this->assertStringNotContainsString('guelt', $content['body']);
    }
}
