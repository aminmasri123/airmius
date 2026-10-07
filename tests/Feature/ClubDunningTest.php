<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubDunningEvent;
use App\Models\ClubDunningRule;
use App\Models\Invoice;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Support\ClubPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubDunningTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_editor_versions_rules_and_records_idempotent_dunning_stage_with_delivery_evidence(): void
    {
        $owner = User::factory()->create();
        $finance = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($finance->id, [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'active',
            'permission_overrides' => [ClubPermissions::FINANCE_EDIT => true],
        ]);
        $invoice = $this->invoice($club);

        Sanctum::actingAs($finance);

        $firstRule = $this->postJson("/api/v1/clubs/{$club->id}/dunning-rules", [
            'name' => 'Standard Mahnlauf',
            'stages' => [
                ['stage' => 1, 'days_after_due' => 7, 'fee_cents' => 250, 'channel' => 'email'],
                ['stage' => 2, 'days_after_due' => 21, 'fee_cents' => 500, 'channel' => 'letter', 'blocks_service' => true],
            ],
            'exceptions' => ['hardship_pause_days' => 30],
            'channel_requirements' => ['letter' => ['proof_required' => true]],
        ])->assertCreated()->assertJsonPath('data.version', 1)->json('data.id');

        $this->postJson("/api/v1/clubs/{$club->id}/dunning-rules", [
            'name' => 'Standard Mahnlauf v2',
            'stages' => [
                ['stage' => 1, 'days_after_due' => 5, 'fee_cents' => 300, 'channel' => 'email'],
            ],
        ])->assertCreated()->assertJsonPath('data.version', 2);

        $this->assertFalse(ClubDunningRule::query()->findOrFail($firstRule)->is_active);

        $payload = [
            'rule_id' => $firstRule,
            'stage' => 2,
            'channel' => 'letter',
            'delivery_status' => 'delivered',
            'delivered_at' => '2026-09-26T10:00:00Z',
            'evidence_reference' => 'Einschreiben RR-42',
            'idempotency_key' => 'invoice-'.$invoice->id.'-stage-2-letter',
        ];

        $first = $this->postJson("/api/v1/clubs/{$club->id}/membership-invoices/{$invoice->id}/dunning-events", $payload)
            ->assertOk()
            ->assertJsonPath('data.rule_version', 1)
            ->assertJsonPath('data.stage', 2)
            ->assertJsonPath('data.fee_cents', 500)
            ->assertJsonPath('data.blocks_service', true)
            ->assertJsonPath('data.delivery_status', 'delivered')
            ->json('data.id');

        $second = $this->postJson("/api/v1/clubs/{$club->id}/membership-invoices/{$invoice->id}/dunning-events", [
            ...$payload,
            'fee_cents' => 999,
        ])->assertOk()->json('data.id');

        $this->assertSame($first, $second);
        $this->assertSame(1, ClubDunningEvent::query()->where('invoice_id', $invoice->id)->count());
        $this->assertSame('overdue', $invoice->fresh()->status);
        $this->assertSame('overdue', $invoice->fresh()->claim_status);
    }

    public function test_dunning_rules_and_events_require_finance_edit_permission(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($member->id, ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active']);
        $invoice = $this->invoice($club);

        Sanctum::actingAs($member);

        $this->postJson("/api/v1/clubs/{$club->id}/dunning-rules", [
            'name' => 'Nicht erlaubt',
            'stages' => [['stage' => 1]],
        ])->assertForbidden();

        $this->postJson("/api/v1/clubs/{$club->id}/membership-invoices/{$invoice->id}/dunning-events", [
            'stage' => 1,
        ])->assertForbidden();
    }

    public function test_dunning_stage_cannot_run_early_and_automatic_run_is_idempotent(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $this->activatePlan($club);
        $invoice = $this->invoice($club);
        $invoice->update(['due_date' => '2026-10-01']);

        Sanctum::actingAs($owner);
        $ruleId = $this->postJson("/api/v1/clubs/{$club->id}/dunning-rules", [
            'name' => 'Automatik',
            'stages' => [['stage' => 1, 'days_after_due' => 7, 'channel' => 'email']],
        ])->assertCreated()->json('data.id');

        $this->travelTo('2026-10-05');
        $this->postJson("/api/v1/clubs/{$club->id}/membership-invoices/{$invoice->id}/dunning-events", [
            'rule_id' => $ruleId,
            'stage' => 1,
        ])->assertUnprocessable();

        $this->artisan('airmius:process-club-dunning', ['--date' => '2026-10-08'])->assertSuccessful();
        $this->artisan('airmius:process-club-dunning', ['--date' => '2026-10-08'])->assertSuccessful();

        $this->assertSame(1, ClubDunningEvent::query()->where('invoice_id', $invoice->id)->count());
        $this->travelBack();
    }

    private function invoice(Club $club): Invoice
    {
        return Invoice::query()->create([
            'club_id' => $club->id,
            'user_id' => $club->owner_id,
            'number' => 'DUN-001',
            'title' => 'Mitgliedsbeitrag',
            'amount' => '25.00',
            'status' => 'open',
            'claim_status' => 'open',
            'source' => 'manual',
            'due_date' => now()->subDays(30),
            'issued_at' => now()->subMonth(),
        ]);
    }

    private function activatePlan(Club $club): void
    {
        $plan = SubscriptionPlan::query()->firstOrCreate(['slug' => 'pro'], [
            'target_actor' => 'verein',
            'name' => 'Pro',
            'monthly_price_cents' => 2990,
            'yearly_price_cents' => 29900,
            'currency' => 'EUR',
            'features' => [],
            'sort_order' => 1,
            'is_public' => true,
            'is_active' => true,
        ]);
        $club->currentSubscription()->updateOrCreate([], [
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
            'billing_interval' => 'monthly',
        ]);
        $club->unsetRelation('currentSubscription');
    }
}
