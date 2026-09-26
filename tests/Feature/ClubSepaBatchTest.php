<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubSepaBatch;
use App\Models\Invoice;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\ClubInvoicePaymentService;
use App\Services\ClubService;
use App\Support\ClubPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ClubSepaBatchTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private User $owner;

    private User $reviewer;

    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 9, 23)->setTime(12, 0));
        $this->owner = User::factory()->create();
        $this->reviewer = User::factory()->create();
        $member = User::factory()->create();
        $this->club = Club::factory()->create([
            'owner_id' => $this->owner->id, 'sepa_creditor_id' => 'DE98ZZZ09999999999',
            'sepa_iban' => 'DE02120300000000202051',
        ]);
        $this->club->users()->attach($this->reviewer->id, ['role' => 'financial_controller', 'roles' => ['financial_controller']]);
        $this->club->users()->attach($member->id, [
            'role' => 'member', 'roles' => ['member'], 'sepa_iban' => 'DE12500105170648489890',
            'sepa_mandate_reference' => 'MANDATE-1', 'sepa_mandate_signed_on' => '2026-09-01', 'sepa_mandate_active' => true,
        ]);
        $plan = SubscriptionPlan::firstOrCreate(['slug' => 'pro'], ['name' => 'Pro', 'target_actor' => 'verein', 'is_active' => true]);
        $this->club->currentSubscription()->updateOrCreate([], ['subscription_plan_id' => $plan->id, 'status' => 'active']);
        $this->invoice = Invoice::create([
            'club_id' => $this->club->id, 'user_id' => $member->id, 'number' => 'SEP-1', 'title' => 'Beitrag',
            'amount' => 100, 'status' => 'open', 'due_date' => '2026-10-10',
        ]);
        Sanctum::actingAs($this->owner);
    }

    private function base(): string
    {
        return "/api/v1/clubs/{$this->club->id}/sepa-batches";
    }

    private function prepare(): int
    {
        return $this->postJson($this->base(), [
            'invoice_ids' => [$this->invoice->id], 'collection_date' => '2026-10-10', 'notice_days' => 14,
        ])->assertCreated()->assertJsonPath('data.status', 'draft')->json('data.id');
    }

    private function approve(int $id): void
    {
        Sanctum::actingAs($this->reviewer);
        $this->postJson($this->base()."/{$id}/approve")->assertOk()->assertJsonPath('data.status', 'approved');
    }

    private function notice(int $id): void
    {
        $this->postJson($this->base()."/{$id}/notice", [
            'sent_on' => '2026-09-23', 'channel' => 'email', 'reference' => 'Versandarchiv 23.09.', 'confirmed' => true,
        ])->assertOk()->assertJsonPath('data.status', 'notified');
    }

    public function test_approval_and_export_can_be_separated_into_distinct_finance_actions(): void
    {
        $approver = User::factory()->create();
        $exporter = User::factory()->create();
        $this->club->users()->attach([
            $approver->id => [
                'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
                'permission_overrides' => [ClubPermissions::FINANCE_APPROVE => true],
            ],
            $exporter->id => [
                'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
                'permission_overrides' => [ClubPermissions::FINANCE_EXPORT => true],
            ],
        ]);
        $id = $this->prepare();

        Sanctum::actingAs($approver);
        $this->postJson($this->base()."/{$id}/approve")->assertOk()->assertJsonPath('data.status', 'approved');
        Sanctum::actingAs($this->owner);
        $this->notice($id);
        Sanctum::actingAs($approver);
        $this->postJson($this->base()."/{$id}/export")->assertForbidden();
        Sanctum::actingAs($exporter);
        $this->postJson($this->base()."/{$id}/export")->assertOk();
    }

    public function test_prepare_second_approval_notice_and_repeatable_export_preserve_the_instruction(): void
    {
        app(ClubInvoicePaymentService::class)->record($this->invoice, ['amount' => 20], $this->owner);
        $id = $this->prepare();
        $this->getJson($this->base())->assertOk()->assertJsonPath('data.data.0.total_cents', 8000)
            ->assertJsonPath('data.data.0.items.0.iban_last4', '9890')->assertDontSee('DE12500105170648489890');
        $this->postJson($this->base()."/{$id}/approve")->assertUnprocessable();
        $this->approve($id);
        $this->postJson($this->base()."/{$id}/export")->assertUnprocessable();
        $this->notice($id);
        $first = $this->postJson($this->base()."/{$id}/export")->assertOk()
            ->assertSee('<ReqdColltnDt>2026-10-10</ReqdColltnDt>', false)
            ->assertSee('<InstdAmt Ccy="EUR">80.00</InstdAmt>', false)->getContent();
        $this->travel(1)->days();
        $this->assertSame($first, $this->postJson($this->base()."/{$id}/export")->assertOk()->getContent());
        $this->assertSame('open', $this->invoice->fresh()->status);
        $this->assertNotNull($this->invoice->fresh()->sepa_exported_at);
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame(1, DB::table('activities')->where('type', 'club.sepa.exported')->count());
        $this->assertStringNotContainsString('DE02120300000000202051', DB::table('club_sepa_batches')->value('creditor_snapshot'));
        $this->assertStringNotContainsString('DE12500105170648489890', DB::table('club_sepa_batches')->value('export_xml'));
    }

    public function test_active_reservation_blocks_new_run_and_legacy_export_but_cancellation_releases_it(): void
    {
        $id = $this->prepare();
        $data = ['invoice_ids' => [$this->invoice->id], 'collection_date' => '2026-10-10', 'notice_days' => 14];
        $this->postJson($this->base(), $data)->assertUnprocessable();
        $this->assertDatabaseCount('club_sepa_batches', 1);
        $this->getJson("/api/v1/clubs/{$this->club->id}/membership/sepa-export")->assertUnprocessable();
        $this->postJson($this->base()."/{$id}/cancel", ['reason' => 'Neu planen'])->assertOk();
        $this->get("/api/v1/clubs/{$this->club->id}/membership/sepa-export")->assertOk();
        $this->postJson($this->base(), $data)->assertCreated();
        $this->assertDatabaseCount('club_sepa_batch_items', 2);
        $this->assertSame(1, DB::table('club_sepa_batch_items')->whereNotNull('reserved_invoice_id')->count());
    }

    public function test_payment_change_after_notice_prevents_stale_export(): void
    {
        $id = $this->prepare();
        $this->approve($id);
        $this->notice($id);
        app(ClubInvoicePaymentService::class)->record($this->invoice, ['amount' => 10], $this->owner);
        $this->postJson($this->base()."/{$id}/export")->assertUnprocessable();
        $this->assertNull(ClubSepaBatch::findOrFail($id)->export_xml);
        $this->assertNull($this->invoice->fresh()->sepa_exported_at);
    }

    public function test_mandate_revocation_prevents_approval_and_creditor_change_prevents_export(): void
    {
        $id = $this->prepare();
        $this->club->users()->updateExistingPivot($this->invoice->user_id, ['sepa_mandate_active' => false]);
        Sanctum::actingAs($this->reviewer);
        $this->postJson($this->base()."/{$id}/approve")->assertUnprocessable();
        $this->club->users()->updateExistingPivot($this->invoice->user_id, ['sepa_mandate_active' => true]);
        $this->approve($id);
        $this->notice($id);
        $this->club->update(['sepa_creditor_id' => 'DIFFERENT']);
        $this->postJson($this->base()."/{$id}/export")->assertUnprocessable();
    }

    public function test_preparation_is_all_or_nothing_for_foreign_or_invalid_invoices(): void
    {
        $other = Club::factory()->create(['owner_id' => $this->owner->id]);
        $foreign = Invoice::create(['club_id' => $other->id, 'user_id' => $this->owner->id, 'number' => 'OTHER', 'due_date' => '2026-10-10', 'title' => 'Other', 'amount' => 10, 'status' => 'open']);
        $data = ['invoice_ids' => [$this->invoice->id, $foreign->id], 'collection_date' => '2026-10-10', 'notice_days' => 14];
        $this->postJson($this->base(), $data)->assertUnprocessable();
        $data['invoice_ids'] = [$this->invoice->id, $this->invoice->id];
        $this->postJson($this->base(), $data)->assertUnprocessable();
        $data['invoice_ids'] = [$this->invoice->id];
        $this->invoice->update(['status' => 'cancelled']);
        $this->postJson($this->base(), $data)->assertUnprocessable();
        $this->assertDatabaseCount('club_sepa_batches', 0);
        $this->assertDatabaseCount('club_sepa_batch_items', 0);
    }

    public function test_notice_requires_confirmation_evidence_and_agreed_lead_time(): void
    {
        $this->postJson($this->base(), [
            'invoice_ids' => [$this->invoice->id], 'collection_date' => '2026-09-24', 'notice_days' => 14,
        ])->assertUnprocessable();
        $id = $this->prepare();
        $this->approve($id);
        $data = ['sent_on' => '2026-09-23', 'channel' => 'email', 'reference' => 'archive'];
        $this->postJson($this->base()."/{$id}/notice", $data)->assertUnprocessable();
        $data['confirmed'] = true;
        $data['reference'] = '   ';
        $this->postJson($this->base()."/{$id}/notice", $data)->assertUnprocessable();
        $data['reference'] = 'archive';
        $data['sent_on'] = '2026-09-22';
        $this->postJson($this->base()."/{$id}/notice", $data)->assertUnprocessable();
        $this->travel(10)->days();
        $data['sent_on'] = today()->toDateString();
        $this->postJson($this->base()."/{$id}/notice", $data)->assertUnprocessable();
    }

    public function test_exported_run_cannot_release_reservations_by_cancel_or_new_run(): void
    {
        $id = $this->prepare();
        $this->approve($id);
        $this->notice($id);
        $this->postJson($this->base()."/{$id}/export")->assertOk();
        $this->postJson($this->base()."/{$id}/cancel", ['reason' => 'retry'])->assertUnprocessable();
        Sanctum::actingAs($this->owner);
        $this->getJson("/api/v1/clubs/{$this->club->id}/membership/sepa-export")->assertUnprocessable();
    }

    public function test_member_read_only_finance_and_cross_club_boundaries_are_enforced(): void
    {
        $id = $this->prepare();
        Sanctum::actingAs($this->invoice->user);
        $this->getJson($this->base())->assertForbidden();
        $this->postJson($this->base()."/{$id}/approve")->assertForbidden();
        $this->club->users()->updateExistingPivot($this->invoice->user_id, ['permission_overrides' => ['finance.view' => true]]);
        $this->getJson($this->base())->assertOk()->assertJsonPath('can_manage', false);
        $this->postJson($this->base()."/{$id}/export")->assertForbidden();
        Sanctum::actingAs($this->owner);
        $this->getJson($this->base())->assertOk()->assertJsonPath('can_manage', true);
        $other = Club::factory()->create(['owner_id' => $this->owner->id]);
        $this->postJson("/api/v1/clubs/{$other->id}/sepa-batches/{$id}/approve")->assertNotFound();
    }

    public function test_club_deletion_preserves_saved_financial_history_with_a_clear_error(): void
    {
        $this->prepare();
        try {
            app(ClubService::class)->delete($this->club);
            $this->fail('A club with saved debit history must not be deleted.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
        $this->assertDatabaseHas('clubs', ['id' => $this->club->id]);
        $this->assertDatabaseHas('invoices', ['id' => $this->invoice->id]);
        $this->assertDatabaseCount('club_sepa_batches', 1);
    }
}
