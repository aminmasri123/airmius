<?php

namespace Tests\Feature;

use App\Jobs\SendClubSepaNotice;
use App\Models\Club;
use App\Models\ClubSepaBatch;
use App\Models\ClubSepaNotice;
use App\Models\Invoice;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\ClubInvoicePaymentService;
use App\Services\ClubSepaNoticeMailer;
use App\Services\ClubSepaNoticeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\Mailer\Bridge\Postmark\Transport\PostmarkApiTransport;
use Tests\TestCase;

class ClubSepaNoticeTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private ClubSepaBatch $batch;

    private User $reviewer;

    private User $member;

    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->travelTo(now()->setDate(2026, 9, 23)->setTime(12, 0));
        $owner = User::factory()->create();
        $this->reviewer = User::factory()->create();
        $this->member = User::factory()->create(['language' => 'de']);
        $this->club = Club::factory()->create(['owner_id' => $owner->id, 'sepa_creditor_id' => 'DE98ZZZ09999999999', 'sepa_iban' => 'DE02120300000000202051']);
        $this->club->users()->attach($this->reviewer->id, ['role' => 'financial_controller', 'roles' => ['financial_controller']]);
        $this->club->users()->attach($this->member->id, [
            'role' => 'member', 'roles' => ['member'], 'sepa_iban' => 'DE12500105170648489890',
            'sepa_mandate_reference' => 'MANDATE-1', 'sepa_mandate_signed_on' => '2026-09-01', 'sepa_mandate_active' => true,
        ]);
        $plan = SubscriptionPlan::firstOrCreate(['slug' => 'pro'], ['name' => 'Pro', 'target_actor' => 'verein', 'is_active' => true]);
        $this->club->currentSubscription()->updateOrCreate([], ['subscription_plan_id' => $plan->id, 'status' => 'active']);
        $this->invoice = Invoice::create(['club_id' => $this->club->id, 'user_id' => $this->member->id, 'number' => 'N-1', 'title' => 'Beitrag', 'amount' => 100, 'status' => 'open', 'due_date' => '2026-10-10']);
        app(ClubInvoicePaymentService::class)->record($this->invoice, ['amount' => 20], $owner);
        Sanctum::actingAs($owner);
        $id = $this->postJson($this->base(), ['invoice_ids' => [$this->invoice->id], 'collection_date' => '2026-10-10', 'notice_days' => 14])->assertCreated()->json('data.id');
        $this->batch = ClubSepaBatch::findOrFail($id);
        Sanctum::actingAs($this->reviewer);
        $this->postJson($this->path('/approve'))->assertOk();
    }

    private function base(): string
    {
        return "/api/v1/clubs/{$this->club->id}/sepa-batches";
    }

    private function path(string $suffix): string
    {
        return $this->base()."/{$this->batch->id}{$suffix}";
    }

    private function prepare(): ClubSepaNotice
    {
        $this->postJson($this->path('/notices'))->assertOk();

        return $this->batch->notices()->firstOrFail();
    }

    private function mailer(bool $fail = false): void
    {
        $mock = $this->mock(ClubSepaNoticeMailer::class);
        $mock->shouldReceive('transport')->andReturn(['mailer' => 'test']);
        if ($fail) {
            $mock->shouldReceive('send')->once()->andThrow(new \RuntimeException('Ambiguous transport failure'));
        } else {
            $mock->shouldReceive('send')->once()->andReturn('provider-message-123');
        }
    }

    private function enqueue(): void
    {
        $this->postJson($this->path('/notices/send'), ['confirmed' => true])->assertAccepted();
    }

    private function acceptedNotice(): ClubSepaNotice
    {
        $notice = $this->prepare();
        $this->mailer();
        $this->enqueue();
        app(ClubSepaNoticeService::class)->deliver($notice->id);

        return $notice->fresh();
    }

    private function postmark(array $payload)
    {
        config([
            'services.postmark.webhook_username' => 'postmark',
            'services.postmark.webhook_password' => 'webhook-secret',
            'services.postmark.webhook_ips' => [],
        ]);

        return $this->withBasicAuth('postmark', 'webhook-secret')
            ->postJson('/webhooks/mail/postmark', $payload);
    }

    public function test_preview_is_localized_frozen_encrypted_and_does_not_send(): void
    {
        $notice = $this->prepare();
        $content = $notice->content;
        $this->assertStringContainsString('80,00', $content['body']);
        $this->assertStringContainsString('10.10.2026', $content['body']);
        $this->assertStringContainsString('MANDATE-1', $content['body']);
        $this->assertStringContainsString('DE98ZZZ09999999999', $content['body']);
        $this->assertStringNotContainsString('DE12500105170648489890', $content['body']);
        $this->assertStringNotContainsString($this->member->email, DB::table('club_sepa_notices')->value('content'));
        $this->member->update(['language' => 'en']);
        $this->prepare();
        $this->assertSame($content, $notice->fresh()->content);
        $this->assertDatabaseCount('club_sepa_notices', 1);
        Queue::assertNothingPushed();
        $this->getJson($this->base())->assertOk()->assertJsonPath('data.data.0.notices.0.content.email', $this->member->email);
    }

    public function test_confirmed_queue_dispatch_is_claimed_once_and_unlocks_export_only_after_acceptance(): void
    {
        $notice = $this->prepare();
        $this->mailer();
        $this->postJson($this->path('/notices/send'), ['confirmed' => false])->assertUnprocessable();
        $this->enqueue();
        $this->enqueue();
        Queue::assertPushed(SendClubSepaNotice::class, 2);
        $this->assertSame('approved', $this->batch->fresh()->status);
        $this->postJson($this->path('/export'))->assertUnprocessable();
        $service = app(ClubSepaNoticeService::class);
        $service->deliver($notice->id);
        $service->deliver($notice->id);
        $this->assertSame('sent', $notice->fresh()->status);
        $this->assertSame('notified', $this->batch->fresh()->status);
        $this->assertDatabaseCount('mail_deliveries', 1);
        $this->assertDatabaseHas('mail_deliveries', ['mail_type' => 'club_sepa_notice', 'status' => 'sent']);
        $this->postJson($this->path('/export'))->assertOk()->assertSee('80.00');
        $this->assertSame('open', $this->invoice->fresh()->status);
    }

    public function test_ambiguous_failure_never_retries_or_counts_as_notice(): void
    {
        $notice = $this->prepare();
        $this->mailer(true);
        $this->enqueue();
        app(ClubSepaNoticeService::class)->deliver($notice->id);
        $this->assertSame('uncertain', $notice->fresh()->status);
        $this->enqueue();
        app(ClubSepaNoticeService::class)->deliver($notice->id);
        Queue::assertPushed(SendClubSepaNotice::class, 1);
        $this->assertSame('approved', $this->batch->fresh()->status);
        $this->postJson($this->path('/export'))->assertUnprocessable();
        $this->assertDatabaseCount('mail_deliveries', 0);
    }

    public function test_payment_or_recipient_changes_block_queued_delivery(): void
    {
        $notice = $this->prepare();
        $mock = $this->mock(ClubSepaNoticeMailer::class);
        $mock->shouldReceive('transport')->andReturn(['mailer' => 'test']);
        $mock->shouldNotReceive('send');
        $this->enqueue();
        $this->member->update(['email' => 'changed@example.test']);
        app(ClubSepaNoticeService::class)->deliver($notice->id);
        $this->assertSame('blocked', $notice->fresh()->status);
        $this->postJson($this->path('/notices/send'), ['confirmed' => true])->assertUnprocessable();
        $this->member->update(['email' => $notice->content['email']]);
        $this->enqueue();
        app(ClubInvoicePaymentService::class)->record($this->invoice, ['amount' => 10], $this->reviewer);
        app(ClubSepaNoticeService::class)->deliver($notice->id);
        $this->assertSame('blocked', $notice->fresh()->status);
    }

    public function test_cancellation_and_manual_notice_stop_waiting_jobs(): void
    {
        $notice = $this->prepare();
        $notice->update(['status' => 'queued']);
        $this->postJson($this->path('/notice'), ['confirmed' => true, 'sent_on' => '2026-09-23', 'channel' => 'letter', 'reference' => 'Postal dispatch 123'])->assertOk();
        $this->assertSame('cancelled', $notice->fresh()->status);
        $mock = $this->mock(ClubSepaNoticeMailer::class);
        $mock->shouldNotReceive('send');
        app(ClubSepaNoticeService::class)->deliver($notice->id);
        $this->postJson($this->path('/cancel'), ['reason' => 'Replan'])->assertOk();
        $this->assertSame('cancelled', $this->batch->fresh()->status);
    }

    public function test_inflight_notice_blocks_cancel_but_interrupted_claim_is_never_resent(): void
    {
        $notice = $this->prepare();
        $notice->update(['status' => 'sending', 'started_at' => now()]);
        $this->postJson($this->path('/cancel'), ['reason' => 'Replan'])->assertUnprocessable();
        $this->travel(11)->minutes();
        $this->postJson($this->path('/cancel'), ['reason' => 'Check bank and mail status'])->assertOk();
        $this->assertSame('uncertain', $notice->fresh()->status);
    }

    public function test_expired_notice_window_and_revoked_permissions_stop_workers(): void
    {
        $notice = $this->prepare();
        $notice->update(['status' => 'queued', 'requested_by' => $this->reviewer->id]);
        $this->club->users()->detach($this->reviewer->id);
        app(ClubSepaNoticeService::class)->deliver($notice->id);
        $this->assertSame('blocked', $notice->fresh()->status);
        $this->club->users()->attach($this->reviewer->id, ['role' => 'financial_controller', 'roles' => ['financial_controller']]);
        $notice->update(['status' => 'queued']);
        $this->travel(5)->days();
        app(ClubSepaNoticeService::class)->deliver($notice->id);
        $this->assertSame('blocked', $notice->fresh()->status);
        $this->assertSame('approved', $this->batch->fresh()->status);
    }

    public function test_read_only_access_hides_email_and_body_and_cannot_prepare_or_send(): void
    {
        $this->prepare();
        $this->club->users()->updateExistingPivot($this->member->id, ['permission_overrides' => ['finance.view' => true]]);
        Sanctum::actingAs($this->member);
        $this->getJson($this->base())->assertOk()->assertJsonMissingPath('data.data.0.notices.0.content');
        $this->postJson($this->path('/notices'))->assertForbidden();
        $this->postJson($this->path('/notices/send'), ['confirmed' => true])->assertForbidden();
    }

    public function test_non_delivering_mail_transport_is_not_treated_as_real_dispatch(): void
    {
        $this->prepare();
        $this->postJson($this->path('/notices/send'), ['confirmed' => true])->assertUnprocessable();
        Queue::assertNothingPushed();
        $this->assertSame('prepared', $this->batch->notices()->first()->status);
    }

    public function test_actual_mail_adapter_passes_frozen_content_to_laravel_transport(): void
    {
        $notice = $this->prepare();
        // Only this isolated test substitutes an in-memory transport. Production
        // transport validation remains covered separately and rejects array/log.
        $mailer = \Mockery::mock(ClubSepaNoticeMailer::class)->makePartial();
        $mailer->shouldReceive('transport')->andReturn(['mailer' => 'array', 'address' => 'club@example.test', 'name' => 'Club']);
        $id = $mailer->send($notice);
        $messages = Mail::mailer('array')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $sent = $messages->first();
        $this->assertSame($id, $sent->getMessageId());
        $message = $sent->getOriginalMessage();
        $this->assertSame($notice->content['body'], $message->getTextBody());
        $this->assertSame($notice->content['subject'], $message->getSubject());
        $this->assertSame($this->member->email, $message->getTo()[0]->getAddress());
    }

    public function test_postmark_transport_bridge_is_available_for_production_configuration(): void
    {
        config([
            'mail.mailers.postmark' => ['transport' => 'postmark'],
            'services.postmark.key' => 'test-server-token',
        ]);

        $this->assertInstanceOf(
            PostmarkApiTransport::class,
            Mail::mailer('postmark')->getSymfonyTransport(),
        );
    }

    public function test_every_position_must_be_accepted_before_batch_is_notified(): void
    {
        $second = $this->invoice->replicate();
        $second->number = 'N-2';
        $second->save();
        $this->batch->items()->create([
            'invoice_id' => $second->id, 'reserved_invoice_id' => $second->id,
            'amount_cents' => 10000, 'debtor_snapshot' => array_replace($this->batch->items()->first()->debtor_snapshot, ['number' => 'N-2']),
        ]);
        $this->prepare();
        $notices = $this->batch->notices()->orderBy('id')->get();
        $mock = $this->mock(ClubSepaNoticeMailer::class);
        $mock->shouldReceive('transport')->andReturn(['mailer' => 'test']);
        $mock->shouldReceive('send')->twice()->andReturn('mail-one', 'mail-two');
        $this->enqueue();
        $service = app(ClubSepaNoticeService::class);
        $service->deliver($notices[0]->id);
        $this->assertSame('approved', $this->batch->fresh()->status);
        $this->postJson($this->path('/export'))->assertUnprocessable();
        $service->deliver($notices[1]->id);
        $this->assertSame('notified', $this->batch->fresh()->status);
        $this->assertDatabaseCount('mail_deliveries', 2);
    }

    public function test_postmark_delivery_feedback_is_authenticated_idempotent_and_separate_from_transport_acceptance(): void
    {
        $notice = $this->acceptedNotice();
        $this->assertSame('pending', $notice->provider_status);

        $payload = [
            'RecordType' => 'Delivery',
            'MessageID' => '<provider-message-123>',
            'Recipient' => $this->member->email,
            'DeliveredAt' => '2026-09-23T12:05:00Z',
        ];
        $this->postmark($payload)->assertAccepted()->assertJsonPath('status', 'processed');
        $this->postmark($payload)->assertAccepted()->assertJsonPath('status', 'duplicate');

        $notice = $notice->fresh();
        $this->assertSame('sent', $notice->status);
        $this->assertSame('delivered', $notice->provider_status);
        $this->assertSame('2026-09-23T12:05:00.000000Z', $notice->delivered_at->toJSON());
        $this->assertDatabaseCount('club_sepa_notice_provider_events', 1);
        $this->assertDatabaseHas('activities', [
            'type' => 'club.sepa.notice_delivery',
            'subject_id' => $this->batch->id,
        ]);
        $delivery = DB::table('mail_deliveries')->where('dedupe_key', 'club-sepa-notice:'.$notice->id)->first();
        $this->assertSame('delivered', json_decode($delivery->context, true)['provider_status']);
    }

    public function test_postmark_bounce_feedback_wins_over_delivery_without_storing_recipient_payload(): void
    {
        $notice = $this->acceptedNotice();
        $this->postmark([
            'RecordType' => 'Delivery',
            'MessageID' => 'provider-message-123',
            'Recipient' => $this->member->email,
            'DeliveredAt' => '2026-09-23T12:05:00Z',
        ])->assertAccepted();
        $this->postmark([
            'RecordType' => 'Bounce',
            'ID' => 9876,
            'MessageID' => 'provider-message-123',
            'Email' => $this->member->email,
            'BouncedAt' => '2026-09-23T12:06:00Z',
            'Type' => 'HardBounce',
            'TypeCode' => 1,
            'Inactive' => true,
            'Description' => 'Sensitive remote response',
        ])->assertAccepted()->assertJsonPath('status', 'processed');

        $notice = $notice->fresh();
        $this->assertSame('bounced', $notice->provider_status);
        $this->assertSame('HardBounce', $notice->bounce_type);
        $this->assertSame('notified', $this->batch->fresh()->status);
        $stored = DB::table('club_sepa_notice_provider_events')->where('event_type', 'bounce')->value('metadata');
        $this->assertStringNotContainsString($this->member->email, $stored);
        $this->assertStringNotContainsString('Sensitive remote response', $stored);
        $this->assertDatabaseHas('activities', ['type' => 'club.sepa.notice_bounce']);
    }

    public function test_postmark_webhook_rejects_missing_credentials_and_ignores_unmatched_feedback(): void
    {
        $notice = $this->acceptedNotice();
        config([
            'services.postmark.webhook_username' => 'postmark',
            'services.postmark.webhook_password' => 'webhook-secret',
            'services.postmark.webhook_ips' => [],
        ]);
        $payload = [
            'RecordType' => 'Delivery',
            'MessageID' => 'provider-message-123',
            'Recipient' => $this->member->email,
            'DeliveredAt' => '2026-09-23T12:05:00Z',
        ];
        $this->postJson('/webhooks/mail/postmark', $payload)->assertUnauthorized();
        config(['services.postmark.webhook_ips' => ['203.0.113.10']]);
        $this->withBasicAuth('postmark', 'webhook-secret')
            ->postJson('/webhooks/mail/postmark', $payload)
            ->assertForbidden();
        config(['services.postmark.webhook_ips' => []]);
        $payload['Recipient'] = 'someone-else@example.test';
        $this->postmark($payload)->assertAccepted()->assertJsonPath('status', 'ignored');
        $this->assertSame('pending', $notice->fresh()->provider_status);
        $this->assertDatabaseCount('club_sepa_notice_provider_events', 0);
    }
}
