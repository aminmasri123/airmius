<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Club;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Support\ClubAuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubMembershipInvoiceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_uc25_invoice_payment_and_reminder_are_complete_direct_and_audited(): void
    {
        Notification::fake();
        $owner = User::factory()->create(['name' => 'UC25 Treasurer']);
        $member = User::factory()->create(['name' => 'UC25 Athlete']);
        $club = Club::factory()->create(['owner_id' => $owner->id, 'name' => 'UC25 Test Club']);
        $club->users()->attach($member->id, [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'active',
        ]);
        $plan = SubscriptionPlan::query()->firstOrCreate(
            ['slug' => 'club'],
            [
                'target_actor' => 'verein',
                'name' => 'Club',
                'monthly_price_cents' => 2990,
                'yearly_price_cents' => 29900,
                'currency' => 'EUR',
                'features' => [],
                'sort_order' => 1,
                'is_public' => true,
                'is_active' => true,
            ],
        );
        $club->currentSubscription()->updateOrCreate([], [
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
            'billing_interval' => 'monthly',
        ]);

        Sanctum::actingAs($owner);
        $created = $this->postJson("/api/v1/clubs/{$club->id}/members/{$member->id}/invoices", [
            'title' => 'UC25 Mitgliedsbeitrag August',
            'description' => 'Isolierte Testrechnung',
            'amount' => 31.50,
            'billing_period_start' => '2026-08-01',
            'billing_period_end' => '2026-08-31',
            'due_date' => '2026-09-05',
        ])->assertCreated()
            ->assertJsonPath('data.invoices.0.status', 'open')
            ->assertJsonPath('data.invoices.0.billing_period_start', '2026-08-01')
            ->assertJsonPath('data.invoices.0.billing_period_end', '2026-08-31');

        $invoiceId = (int) $created->json('data.invoices.0.id');
        $this->postJson("/api/v1/clubs/{$club->id}/membership-invoices/{$invoiceId}/payments", [
            'amount' => 31.50,
            'method' => 'bank_transfer',
            'paid_at' => '2026-08-22',
            'reference' => 'UC25-PAY-001',
        ])->assertOk()
            ->assertJsonPath('data.invoices.0.status', 'paid')
            ->assertJsonPath('data.payments.0.method', 'bank_transfer')
            ->assertJsonPath('data.payments.0.reference', 'UC25-PAY-001');

        $this->assertDatabaseHas('payments', [
            'invoice_id' => $invoiceId,
            'amount' => 31.50,
            'method' => 'bank_transfer',
            'reference' => 'UC25-PAY-001',
        ]);
        $this->assertSame('paid', Invoice::query()->findOrFail($invoiceId)->status);
        $this->assertSame(1, Payment::query()->where('invoice_id', $invoiceId)->count());

        $this->postJson("/api/v1/clubs/{$club->id}/membership-invoices/{$invoiceId}/payments", [
            'amount' => 31.50,
            'method' => 'bank_transfer',
        ])->assertUnprocessable();
        $this->assertSame(1, Payment::query()->where('invoice_id', $invoiceId)->count());

        $overdue = Invoice::query()->create([
            'club_id' => $club->id,
            'user_id' => $member->id,
            'number' => 'UC25-REM-001',
            'title' => 'UC25 Testrechnung Erinnerung',
            'amount' => 12.00,
            'status' => 'overdue',
            'source' => 'manual',
            'billing_period_start' => '2026-07-01',
            'billing_period_end' => '2026-07-31',
            'due_date' => '2026-08-01',
            'issued_at' => '2026-07-15',
        ]);

        $this->postJson("/api/v1/clubs/{$club->id}/membership-invoices/{$overdue->id}/reminder")
            ->assertOk()
            ->assertJsonPath('data.invoices.0.id', $overdue->id)
            ->assertJsonPath('data.invoices.0.status', 'overdue')
            ->assertJsonPath('data.invoices.0.reminder_sent_at', fn ($value) => filled($value));

        $this->assertNotNull($overdue->fresh()->reminder_sent_at);
        $this->assertDatabaseHas('activities', [
            'club_id' => $club->id,
            'user_id' => $owner->id,
            'type' => 'club.invoice.reminder_sent',
            'subject_type' => Invoice::class,
            'subject_id' => $overdue->id,
        ]);
        $this->assertSame(
            'Zahlungserinnerung gesendet',
            ClubAuditLog::payload(Activity::query()->latest('id')->firstOrFail())['label'],
        );

        Sanctum::actingAs($member);
        $this->getJson('/api/v1/billing/invoices')
            ->assertOk()
            ->assertJsonFragment(['number' => 'UC25-REM-001'])
            ->assertJsonFragment(['status' => 'paid']);
    }

    public function test_uc25_rejects_invalid_period_and_foreign_finance_access(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($member->id, [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'active',
        ]);
        $plan = SubscriptionPlan::query()->firstOrCreate(
            ['slug' => 'starter'],
            [
                'target_actor' => 'verein',
                'name' => 'Starter',
                'monthly_price_cents' => 990,
                'yearly_price_cents' => 9900,
                'currency' => 'EUR',
                'features' => [],
                'sort_order' => 1,
                'is_public' => true,
                'is_active' => true,
            ],
        );
        $club->currentSubscription()->updateOrCreate([], [
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
            'billing_interval' => 'monthly',
        ]);

        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/clubs/{$club->id}/members/{$member->id}/invoices", [
            'title' => 'Ungültiger Zeitraum',
            'amount' => 10,
            'billing_period_start' => '2026-09-01',
            'billing_period_end' => '2026-08-01',
            'due_date' => '2026-09-05',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('billing_period_end');

        Sanctum::actingAs($outsider);
        $this->postJson("/api/v1/clubs/{$club->id}/members/{$member->id}/invoices", [
            'title' => 'Fremde Rechnung',
            'amount' => 10,
            'due_date' => '2026-09-05',
        ])->assertForbidden();
    }
}
