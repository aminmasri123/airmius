<?php

namespace App\Services;

use App\Support\PaymentWebhookVerifier;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

final class ProviderSmokeGateway
{
    public function sendSmtpReceipt(string $mailer, string $recipient, string $receiptCode): void
    {
        $fromAddress = (string) config('mail.from.address');
        $fromName = (string) config('mail.from.name', 'Airmius');

        Mail::mailer($mailer)->raw(
            "Airmius staging provider smoke test.\n\nReceipt: {$receiptCode}\n\nNo reply is required.",
            static function ($message) use ($recipient, $receiptCode, $fromAddress, $fromName): void {
                $message->to($recipient)
                    ->from($fromAddress, $fromName)
                    ->subject('Airmius staging smoke '.$receiptCode);
            },
        );
    }

    public function sendFirebaseReceipt(string $deviceToken, string $receiptCode): void
    {
        app(MobilePushDeliveryService::class)->sendSmokeProbe($deviceToken, $receiptCode);
    }

    public function verifyStripeSandbox(): void
    {
        $response = Http::withToken((string) config('services.stripe.secret'))
            ->acceptJson()
            ->timeout(10)
            ->get('https://api.stripe.com/v1/account');

        if (! $response->ok() || ! str_starts_with((string) $response->json('id'), 'acct_')) {
            throw new RuntimeException('Stripe sandbox authentication failed.');
        }

        $payload = '{"id":"evt_airmius_provider_smoke","type":"ping"}';
        $timestamp = now()->timestamp;
        $signature = hash_hmac(
            'sha256',
            $timestamp.'.'.$payload,
            (string) config('services.stripe.webhook_secret'),
        );

        if (! app(PaymentWebhookVerifier::class)->isValidStripeSignature(
            $payload,
            "t={$timestamp},v1={$signature}",
        )) {
            throw new RuntimeException('Stripe webhook verification contract failed.');
        }
    }

    public function verifyPayPalSandbox(): void
    {
        $baseUrl = 'https://api-m.sandbox.paypal.com';
        $tokenResponse = Http::withBasicAuth(
            (string) config('services.paypal.client_id'),
            (string) config('services.paypal.client_secret'),
        )
            ->asForm()
            ->timeout(10)
            ->post($baseUrl.'/v1/oauth2/token', ['grant_type' => 'client_credentials']);
        $accessToken = (string) $tokenResponse->json('access_token');

        if (! $tokenResponse->ok() || $accessToken === '') {
            throw new RuntimeException('PayPal sandbox authentication failed.');
        }

        $webhookIds = collect([
            config('services.paypal.webhook_id'),
            config('services.paypal.commerce_webhook_id'),
            config('services.paypal.outfit_webhook_id'),
        ])->filter()->unique()->values();

        foreach ($webhookIds as $webhookId) {
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->timeout(10)
                ->get($baseUrl.'/v1/notifications/webhooks/'.rawurlencode((string) $webhookId));

            if (! $response->ok() || ! hash_equals((string) $webhookId, (string) $response->json('id'))) {
                throw new RuntimeException('PayPal webhook registration verification failed.');
            }
        }
    }
}
