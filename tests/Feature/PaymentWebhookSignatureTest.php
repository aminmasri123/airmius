<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentWebhookSignatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_stripe_webhooks_require_valid_hmac_signature(): void
    {
        config([
            'services.stripe.webhook_secret' => 'whsec_test_secret',
            'services.stripe.webhook_tolerance' => 300,
        ]);

        $payload = json_encode([
            'id' => 'evt_test',
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => 'pi_test']],
        ]);

        foreach (['webhooks.stripe', 'webhooks.commerce.stripe'] as $route) {
            $this
                ->postRawJson($route, $payload, [
                    'Stripe-Signature' => $this->stripeSignature($payload),
                ])
                ->assertOk();

            $this
                ->postRawJson($route, $payload, [
                    'Stripe-Signature' => 't='.now()->timestamp.',v1=invalid',
                ])
                ->assertStatus(400);

            $this
                ->postRawJson($route, $payload)
                ->assertStatus(400);
        }
    }

    public function test_stripe_webhooks_reject_expired_signatures(): void
    {
        config([
            'services.stripe.webhook_secret' => 'whsec_test_secret',
            'services.stripe.webhook_tolerance' => 300,
        ]);

        $payload = json_encode([
            'id' => 'evt_expired',
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => 'pi_expired']],
        ]);
        $timestamp = now()->subMinutes(10)->timestamp;

        $this
            ->postRawJson('webhooks.stripe', $payload, [
                'Stripe-Signature' => $this->stripeSignature($payload, $timestamp),
            ])
            ->assertStatus(400);
    }

    public function test_paypal_webhooks_require_provider_signature_verification(): void
    {
        $this->configurePayPalWebhookIds();
        $this->fakePayPalVerification('SUCCESS');

        $payload = [
            'event_type' => 'BILLING.SUBSCRIPTION.ACTIVATED',
            'resource' => ['id' => 'I-SUBSCRIPTION'],
        ];

        foreach (['webhooks.paypal', 'webhooks.commerce.paypal', 'webhooks.outfit-subscriptions.paypal'] as $route) {
            $this
                ->withHeaders($this->paypalWebhookHeaders())
                ->postJson(route($route), $payload)
                ->assertOk();
        }

        Http::assertSent(fn ($request) => str_contains(
            (string) $request->url(),
            '/v1/notifications/verify-webhook-signature'
        ));
    }

    public function test_paypal_webhooks_reject_failed_provider_signature_verification(): void
    {
        $this->configurePayPalWebhookIds();
        $this->fakePayPalVerification('FAILURE');

        $this
            ->withHeaders($this->paypalWebhookHeaders())
            ->postJson(route('webhooks.paypal'), [
                'event_type' => 'BILLING.SUBSCRIPTION.ACTIVATED',
                'resource' => ['id' => 'I-SUBSCRIPTION'],
            ])
            ->assertStatus(400);
    }

    public function test_paypal_webhooks_reject_missing_signature_headers(): void
    {
        $this->configurePayPalWebhookIds();

        foreach (['webhooks.paypal', 'webhooks.commerce.paypal', 'webhooks.outfit-subscriptions.paypal'] as $route) {
            $this
                ->postJson(route($route), [
                    'event_type' => 'BILLING.SUBSCRIPTION.ACTIVATED',
                    'resource' => ['id' => 'I-SUBSCRIPTION'],
                ])
                ->assertStatus(400);
        }
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

    private function configurePayPalWebhookIds(): void
    {
        config([
            'services.paypal.mode' => 'sandbox',
            'services.paypal.client_id' => 'paypal-client-id',
            'services.paypal.client_secret' => 'paypal-client-secret',
            'services.paypal.webhook_id' => 'subscription-webhook-id',
            'services.paypal.commerce_webhook_id' => 'commerce-webhook-id',
            'services.paypal.outfit_webhook_id' => 'outfit-webhook-id',
        ]);
    }

    private function fakePayPalVerification(string $status): void
    {
        Http::fake([
            'https://api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
                'access_token' => 'paypal-access-token',
            ]),
            'https://api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature' => Http::response([
                'verification_status' => $status,
            ]),
        ]);
    }

    private function paypalWebhookHeaders(): array
    {
        return [
            'PAYPAL-AUTH-ALGO' => 'SHA256withRSA',
            'PAYPAL-CERT-URL' => 'https://api-m.sandbox.paypal.com/certs/test.pem',
            'PAYPAL-TRANSMISSION-ID' => 'transmission-id',
            'PAYPAL-TRANSMISSION-SIG' => 'signature',
            'PAYPAL-TRANSMISSION-TIME' => now()->toIso8601String(),
        ];
    }
}
