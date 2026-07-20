<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Throwable;

class PaymentWebhookVerifier
{
    public function isValidStripeSignature(string $payload, ?string $signature): bool
    {
        $secret = config('services.stripe.webhook_secret');

        if (blank($secret) || blank($signature)) {
            return false;
        }

        $parts = $this->stripeSignatureParts($signature);
        $timestamp = (int) ($parts['t'][0] ?? 0);

        if ($timestamp <= 0 || $this->stripeTimestampExpired($timestamp)) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        return collect($parts['v1'] ?? [])
            ->contains(fn (string $signature) => hash_equals($expected, $signature));
    }

    public function isValidPayPalWebhook(Request $request, ?string $webhookId): bool
    {
        if (blank($webhookId) || blank(config('services.paypal.client_id')) || blank(config('services.paypal.client_secret'))) {
            return false;
        }

        $headers = [
            'auth_algo' => $request->header('PAYPAL-AUTH-ALGO'),
            'cert_url' => $request->header('PAYPAL-CERT-URL'),
            'transmission_id' => $request->header('PAYPAL-TRANSMISSION-ID'),
            'transmission_sig' => $request->header('PAYPAL-TRANSMISSION-SIG'),
            'transmission_time' => $request->header('PAYPAL-TRANSMISSION-TIME'),
        ];

        if (collect($headers)->contains(fn ($value) => blank($value))) {
            return false;
        }

        try {
            $response = Http::withToken($this->paypalAccessToken())
                ->post($this->paypalBaseUrl().'/v1/notifications/verify-webhook-signature', [
                    ...$headers,
                    'webhook_id' => $webhookId,
                    'webhook_event' => $request->all(),
                ]);

            return $response->ok() && $response->json('verification_status') === 'SUCCESS';
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array<string,array<int,string>>
     */
    private function stripeSignatureParts(string $signature): array
    {
        return collect(explode(',', $signature))
            ->map(function (string $part) {
                [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);

                return [$key, $value];
            })
            ->filter(fn (array $part) => filled($part[0]) && filled($part[1]))
            ->reduce(function (array $carry, array $part) {
                $carry[$part[0]][] = $part[1];

                return $carry;
            }, []);
    }

    private function stripeTimestampExpired(int $timestamp): bool
    {
        $tolerance = max(1, (int) config('services.stripe.webhook_tolerance', 300));

        return abs(now()->timestamp - $timestamp) > $tolerance;
    }

    private function paypalAccessToken(): string
    {
        $response = Http::withBasicAuth(config('services.paypal.client_id'), config('services.paypal.client_secret'))
            ->asForm()
            ->post($this->paypalBaseUrl().'/v1/oauth2/token', ['grant_type' => 'client_credentials']);

        if (! $response->ok() || blank($response->json('access_token'))) {
            throw new \RuntimeException('PayPal access token could not be created.');
        }

        return $response->json('access_token');
    }

    private function paypalBaseUrl(): string
    {
        return config('services.paypal.mode') === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }
}
