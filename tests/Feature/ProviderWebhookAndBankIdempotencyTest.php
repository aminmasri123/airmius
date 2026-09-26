<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\CommerceOrder;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentCheckout;
use App\Models\ProviderWebhookEvent;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\ClubInvoicePaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;

class ProviderWebhookAndBankIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_stripe_subscription_webhook_replay_is_audited_and_does_not_activate_twice(): void
    {
        Notification::fake();
        config([
            'services.stripe.webhook_secret' => 'whsec_replay_test',
            'services.stripe.webhook_tolerance' => 300,
        ]);

        $checkout = $this->paymentCheckout('stripe', [
            'provider_checkout_id' => 'cs_test_replay',
        ]);
        $payload = json_encode([
            'id' => 'evt_subscription_replay',
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'id' => 'cs_test_replay',
                'customer' => 'cus_replay',
                'subscription' => 'sub_replay',
            ]],
        ]);

        $this->postRawJson('webhooks.stripe', $payload, [
            'Stripe-Signature' => $this->stripeSignature($payload),
        ])->assertOk();
        $this->postRawJson('webhooks.stripe', $payload, [
            'Stripe-Signature' => $this->stripeSignature($payload),
        ])->assertOk();

        $this->assertSame('completed', $checkout->fresh()->status);
        $this->assertDatabaseCount('provider_webhook_events', 1);
        $this->assertSame(1, ProviderWebhookEvent::query()
            ->where('provider', 'stripe')
            ->where('scope', 'subscriptions')
            ->where('event_key', 'evt_subscription_replay')
            ->where('status', 'processed')
            ->count());
    }

    public function test_commerce_webhook_replay_completes_order_once(): void
    {
        Notification::fake();
        config([
            'services.stripe.webhook_secret' => 'whsec_commerce_replay',
            'services.stripe.webhook_tolerance' => 300,
        ]);

        $order = CommerceOrder::query()->create([
            'user_id' => User::factory()->create()->id,
            'type' => 'service',
            'provider' => 'stripe',
            'amount_cents' => 4900,
            'currency' => 'EUR',
            'status' => 'pending',
            'provider_checkout_id' => 'cs_commerce_replay',
            'payload' => [],
        ]);
        $payload = json_encode([
            'id' => 'evt_commerce_replay',
            'type' => 'checkout.session.completed',
            'data' => ['object' => ['id' => 'cs_commerce_replay']],
        ]);

        $this->postRawJson('webhooks.commerce.stripe', $payload, [
            'Stripe-Signature' => $this->stripeSignature($payload),
        ])->assertOk();
        $invoiceNumber = $order->fresh()->invoice_number;
        $this->postRawJson('webhooks.commerce.stripe', $payload, [
            'Stripe-Signature' => $this->stripeSignature($payload),
        ])->assertOk();

        $order->refresh();
        $this->assertSame('completed', $order->status);
        $this->assertSame($invoiceNumber, $order->invoice_number);
        $this->assertDatabaseCount('provider_webhook_events', 1);
    }

    public function test_paypal_transport_verification_failure_does_not_store_or_mutate_local_state(): void
    {
        Notification::fake();
        config([
            'services.paypal.mode' => 'sandbox',
            'services.paypal.client_id' => 'paypal-client-id',
            'services.paypal.client_secret' => 'paypal-client-secret',
            'services.paypal.webhook_id' => 'subscription-webhook-id',
        ]);
        Http::fake([
            'https://api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
                'access_token' => 'paypal-access-token',
            ]),
            'https://api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature' => Http::response([
                'verification_status' => 'FAILURE',
            ]),
        ]);

        $checkout = $this->paymentCheckout('paypal', [
            'provider_subscription_id' => 'I-TRANSPORT-FAIL',
        ]);

        $this->withHeaders($this->paypalWebhookHeaders())
            ->postJson(route('webhooks.paypal'), [
                'id' => 'WH-TRANSPORT-FAIL',
                'event_type' => 'BILLING.SUBSCRIPTION.ACTIVATED',
                'resource' => ['id' => 'I-TRANSPORT-FAIL'],
            ])
            ->assertStatus(400);

        $this->assertSame('pending', $checkout->fresh()->status);
        $this->assertDatabaseCount('provider_webhook_events', 0);
    }

    public function test_bank_payment_idempotency_key_prevents_duplicate_booking_and_cross_invoice_reuse(): void
    {
        $club = Club::factory()->create(['owner_id' => User::factory()->create()->id]);
        $member = User::factory()->create();
        $invoice = $this->invoice($club, $member, 'BANK-IDEMP-1', 42.00);

        $first = app(ClubInvoicePaymentService::class)->record($invoice, [
            'amount' => 42.00,
            'method' => 'bank_import',
            'reference' => 'BANK-REF-1',
            'idempotency_key' => 'bank:hash-123:invoice:'.$invoice->id,
        ]);
        $second = app(ClubInvoicePaymentService::class)->record($invoice->fresh(), [
            'amount' => 42.00,
            'method' => 'bank_import',
            'reference' => 'BANK-REF-1',
            'idempotency_key' => 'bank:hash-123:invoice:'.$invoice->id,
        ]);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Payment::query()->where('invoice_id', $invoice->id)->count());

        $otherInvoice = $this->invoice($club, $member, 'BANK-IDEMP-2', 13.00);
        try {
            app(ClubInvoicePaymentService::class)->record($otherInvoice, [
                'amount' => 13.00,
                'method' => 'bank_import',
                'idempotency_key' => 'bank:hash-123:invoice:'.$invoice->id,
            ]);
            $this->fail('Expected cross-invoice idempotency reuse to be rejected.');
        } catch (HttpExceptionInterface $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
    }

    private function paymentCheckout(string $provider, array $attributes = []): PaymentCheckout
    {
        $user = User::factory()->create(['country' => 'DE']);
        $plan = SubscriptionPlan::query()->create([
            'slug' => 'provider-webhook-'.$provider.'-'.Str::lower(Str::random(8)),
            'target_actor' => 'sportler',
            'name' => 'Provider Webhook Plan',
            'monthly_price_cents' => 990,
            'yearly_price_cents' => 9900,
            'currency' => 'EUR',
            'features' => [],
            'sort_order' => 999,
            'is_public' => true,
            'is_active' => true,
        ]);

        return PaymentCheckout::query()->create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'provider' => $provider,
            'billing_interval' => 'monthly',
            'amount_cents' => 990,
            'currency' => 'EUR',
            'status' => 'pending',
            'payload' => [],
            ...$attributes,
        ]);
    }

    private function invoice(Club $club, User $member, string $number, float $amount): Invoice
    {
        return Invoice::query()->create([
            'club_id' => $club->id,
            'user_id' => $member->id,
            'number' => $number,
            'title' => 'Bank idempotency invoice',
            'amount' => $amount,
            'status' => 'open',
            'source' => 'manual',
            'issued_at' => now(),
            'due_date' => now()->addDays(14),
        ]);
    }

    private function postRawJson(string $route, string $payload, array $headers = [])
    {
        $server = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ];

        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return $this->call('POST', route($route), [], [], [], $server, $payload);
    }

    private function stripeSignature(string $payload, ?int $timestamp = null): string
    {
        $timestamp ??= now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, config('services.stripe.webhook_secret'));

        return "t={$timestamp},v1={$signature}";
    }

    private function paypalWebhookHeaders(): array
    {
        return [
            'PAYPAL-AUTH-ALGO' => 'SHA256withRSA',
            'PAYPAL-CERT-URL' => 'https://api-m.sandbox.paypal.com/certs/test.pem',
            'PAYPAL-TRANSMISSION-ID' => 'transmission-id-failure',
            'PAYPAL-TRANSMISSION-SIG' => 'signature',
            'PAYPAL-TRANSMISSION-TIME' => now()->toIso8601String(),
        ];
    }
}
