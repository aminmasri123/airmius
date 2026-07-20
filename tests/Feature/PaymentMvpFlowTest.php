<?php

namespace Tests\Feature;

use App\Models\PaymentCheckout;
use App\Models\Setting;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentMvpFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_bank_transfer_checkout_creates_invoice_and_can_be_cancelled(): void
    {
        $user = User::factory()->create(['country' => 'DE']);
        $plan = $this->createPublicPaidPlan();
        $this->configureBankTransferSettings();

        Sanctum::actingAs($user);

        $checkoutResponse = $this->postJson("/api/v1/subscription-plans/{$plan->id}/checkout", [
            'provider' => 'bank_transfer',
            'billing_interval' => 'monthly',
            'accepted_terms' => true,
        ]);

        $checkoutResponse
            ->assertCreated()
            ->assertJsonPath('data.provider', 'bank_transfer')
            ->assertJsonPath('data.billing_interval', 'monthly')
            ->assertJsonPath('data.amount_cents', 1299)
            ->assertJsonPath('data.currency', 'EUR')
            ->assertJsonPath('data.status', 'awaiting_transfer')
            ->assertJsonPath('data.payment_action.type', 'bank_transfer')
            ->assertJsonPath('data.bank_transfer.iban', 'DE89370400440532013000')
            ->assertJsonPath('data.invoice.status', 'awaiting_transfer')
            ->assertJsonPath('data.invoice.amount_cents', 1299)
            ->assertJsonPath('data.invoice.payment_method', 'bank_transfer');

        $checkout = PaymentCheckout::query()
            ->with('invoice')
            ->findOrFail($checkoutResponse->json('data.id'));

        $this->assertSame('awaiting_transfer', $checkout->status);
        $this->assertNotNull($checkout->payment_reference);
        $this->assertSame($checkout->payment_reference, $checkout->invoice->payment_reference);

        $this->getJson('/api/v1/billing/invoices')
            ->assertOk()
            ->assertJsonPath('meta.summary.total_count', 1)
            ->assertJsonPath('meta.summary.open_count', 1)
            ->assertJsonPath('meta.summary.open_amount', 12.99)
            ->assertJsonPath('data.0.kind', 'subscription_invoice')
            ->assertJsonPath('data.0.status', 'awaiting_transfer')
            ->assertJsonPath('data.0.payment_reference', $checkout->payment_reference);

        $this->postJson("/api/v1/subscription-checkouts/{$checkout->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.id', $checkout->id)
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.invoice.status', 'cancelled');

        $this->assertDatabaseHas('payment_checkouts', [
            'id' => $checkout->id,
            'status' => 'cancelled',
        ]);
        $this->assertDatabaseHas('subscription_invoices', [
            'payment_checkout_id' => $checkout->id,
            'status' => 'cancelled',
        ]);

        $this->getJson('/api/v1/billing/invoices')
            ->assertOk()
            ->assertJsonPath('meta.summary.total_count', 1)
            ->assertJsonPath('meta.summary.open_count', 0)
            ->assertJsonPath('meta.summary.cancelled_count', 1)
            ->assertJsonPath('data.0.status', 'cancelled')
            ->assertJsonPath('data.0.status_label', 'Storniert');
    }

    private function createPublicPaidPlan(): SubscriptionPlan
    {
        return SubscriptionPlan::query()->create([
            'slug' => 'mvp-payment-athlete-pro',
            'target_actor' => 'sportler',
            'name' => 'MVP Payment Athlete Pro',
            'description' => 'Paid plan for checkout MVP coverage.',
            'monthly_price_cents' => 1299,
            'yearly_price_cents' => 12900,
            'currency' => 'EUR',
            'features' => ['feed', 'chat', 'billing'],
            'sort_order' => 900,
            'is_public' => true,
            'is_active' => true,
        ]);
    }

    private function configureBankTransferSettings(): void
    {
        Setting::setValue('billing_bank_account_holder', 'Airmius GmbH');
        Setting::setValue('billing_bank_name', 'Test Bank');
        Setting::setValue('billing_iban', 'DE89370400440532013000');
        Setting::setValue('billing_bic', 'COBADEFFXXX');
        Setting::setValue('billing_payment_terms_days', 14);
    }
}
