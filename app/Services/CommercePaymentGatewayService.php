<?php

namespace App\Services;

use App\Models\CommerceOrder;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CommercePaymentGatewayService
{
    public function createProviderCheckout(CommerceOrder $order): string
    {
        $url = $order->provider === 'stripe'
            ? $this->createStripeCheckout($order)
            : $this->createPayPalCheckout($order);

        $order->update(['checkout_url' => $url]);

        return $url;
    }

    public function syncStripeOrder(CommerceOrder $order, ?string $sessionId, callable $activate): void
    {
        $sessionId = $sessionId ?: $order->provider_checkout_id;

        if (! $sessionId || blank(config('services.stripe.secret'))) {
            return;
        }

        $response = Http::withToken(config('services.stripe.secret'))
            ->get('https://api.stripe.com/v1/checkout/sessions/'.$sessionId);

        if ($response->ok() && $response->json('payment_status') === 'paid') {
            $order->update(['payload' => $response->json()]);
            $activate($order);
        }
    }

    public function capturePayPalOrder(CommerceOrder $order, callable $activate): void
    {
        $response = Http::withToken($this->paypalAccessToken())
            ->withHeaders(['PayPal-Request-Id' => (string) Str::uuid()])
            ->withBody('{}', 'application/json')
            ->post($this->paypalBaseUrl().'/v2/checkout/orders/'.$order->provider_checkout_id.'/capture');

        if ($response->ok() && in_array($response->json('status'), ['COMPLETED', 'APPROVED'], true)) {
            $order->update(['payload' => $response->json()]);
            $activate($order);
        }
    }

    public function prepareBankTransfer(CommerceOrder $order): void
    {
        $bank = $this->bankTransferSettings();
        abort_if(blank($bank['iban']), 422, 'Bankverbindung für Überweisung ist noch nicht konfiguriert.');

        $order->update([
            'status' => 'awaiting_transfer',
            'payment_reference' => 'AIR-COM-'.$order->created_at->format('Y').'-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT),
            'due_at' => now()->addDays((int) $bank['payment_terms_days'])->endOfDay(),
            'payload' => [...($order->payload ?: []), 'bank_transfer' => $bank],
        ]);
    }

    public function bankTransferSettings(): array
    {
        return [
            'bank_account_holder' => Setting::valueFor('billing_bank_account_holder', 'Airmius'),
            'bank_name' => Setting::valueFor('billing_bank_name', ''),
            'iban' => Setting::valueFor('billing_iban', ''),
            'bic' => Setting::valueFor('billing_bic', ''),
            'payment_terms_days' => (int) Setting::valueFor('billing_payment_terms_days', 14),
        ];
    }

    public function buyerEmail(CommerceOrder $order): ?string
    {
        return $order->user?->email ?: $order->guest_email;
    }

    public function orderRoute(CommerceOrder $order, string $type): string
    {
        if ($order->access_token) {
            return match ($type) {
                'success' => route('commerce-checkout.guest.success', [$order, $order->access_token]),
                'cancel' => route('commerce-checkout.guest.cancel', [$order, $order->access_token]),
                'bank-transfer' => route('commerce-checkout.guest.bank-transfer.show', [$order, $order->access_token]),
            };
        }

        return match ($type) {
            'success' => route('commerce-checkout.success', $order),
            'cancel' => route('commerce-checkout.cancel', $order),
            'bank-transfer' => route('commerce-checkout.bank-transfer.show', $order),
        };
    }

    public function isValidStripeSignature(string $payload, ?string $signature): bool
    {
        $secret = config('services.stripe.webhook_secret');

        if (blank($secret)) {
            return true;
        }

        if (! $signature) {
            return false;
        }

        $parts = collect(explode(',', $signature))->mapWithKeys(function ($part) {
            [$key, $value] = array_pad(explode('=', $part, 2), 2, null);

            return [$key => $value];
        });

        return hash_equals(hash_hmac('sha256', $parts->get('t').'.'.$payload, $secret), (string) $parts->get('v1'));
    }

    public function isValidPayPalWebhook(Request $request): bool
    {
        $webhookId = config('services.paypal.commerce_webhook_id') ?: config('services.paypal.webhook_id');

        if (blank($webhookId)) {
            return true;
        }

        try {
            $response = Http::withToken($this->paypalAccessToken())
                ->post($this->paypalBaseUrl().'/v1/notifications/verify-webhook-signature', [
                    'auth_algo' => $request->header('PAYPAL-AUTH-ALGO'),
                    'cert_url' => $request->header('PAYPAL-CERT-URL'),
                    'transmission_id' => $request->header('PAYPAL-TRANSMISSION-ID'),
                    'transmission_sig' => $request->header('PAYPAL-TRANSMISSION-SIG'),
                    'transmission_time' => $request->header('PAYPAL-TRANSMISSION-TIME'),
                    'webhook_id' => $webhookId,
                    'webhook_event' => $request->all(),
                ]);

            return $response->ok() && $response->json('verification_status') === 'SUCCESS';
        } catch (\Throwable) {
            return false;
        }
    }

    private function createStripeCheckout(CommerceOrder $order): string
    {
        abort_if(blank(config('services.stripe.secret')), 422, 'Stripe ist noch nicht konfiguriert.');

        $response = Http::asForm()
            ->withToken(config('services.stripe.secret'))
            ->post('https://api.stripe.com/v1/checkout/sessions', [
                'mode' => 'payment',
                'success_url' => $this->orderRoute($order, 'success').'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $this->orderRoute($order, 'cancel'),
                'client_reference_id' => (string) $order->id,
                'customer_email' => $this->buyerEmail($order),
                'line_items[0][price_data][currency]' => strtolower($order->currency),
                'line_items[0][price_data][product_data][name]' => 'Airmius '.($order->orderable?->name ?? $order->orderable?->title ?? 'Bestellung'),
                'line_items[0][price_data][unit_amount]' => $order->amount_cents,
                'line_items[0][quantity]' => 1,
                'metadata[commerce_order_id]' => (string) $order->id,
            ]);

        if ($response->failed()) {
            Log::warning('Commerce Stripe checkout failed', ['body' => $response->json()]);
            abort(422, 'Stripe Checkout konnte nicht gestartet werden.');
        }

        $order->update([
            'provider_checkout_id' => $response->json('id'),
            'payload' => $response->json(),
        ]);

        return $response->json('url');
    }

    private function createPayPalCheckout(CommerceOrder $order): string
    {
        $response = Http::withToken($this->paypalAccessToken())->post($this->paypalBaseUrl().'/v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => 'airmius-commerce-'.$order->id,
                'description' => 'Airmius Commerce '.$order->id,
                'amount' => [
                    'currency_code' => $order->currency,
                    'value' => number_format($order->amount_cents / 100, 2, '.', ''),
                ],
            ]],
            'application_context' => [
                'brand_name' => 'Airmius',
                'user_action' => 'PAY_NOW',
                'return_url' => $this->orderRoute($order, 'success'),
                'cancel_url' => $this->orderRoute($order, 'cancel'),
            ],
        ]);

        if ($response->failed()) {
            abort(422, 'PayPal Checkout konnte nicht gestartet werden.');
        }

        $order->update([
            'provider_checkout_id' => $response->json('id'),
            'payload' => $response->json(),
        ]);

        $approveLink = collect($response->json('links') ?? [])->firstWhere('rel', 'approve');
        abort_if(blank($approveLink['href'] ?? null), 422, 'PayPal Genehmigungslink fehlt.');

        return $approveLink['href'];
    }

    private function paypalAccessToken(): string
    {
        $response = Http::asForm()
            ->withBasicAuth(config('services.paypal.client_id'), config('services.paypal.client_secret'))
            ->post($this->paypalBaseUrl().'/v1/oauth2/token', ['grant_type' => 'client_credentials']);

        if ($response->failed()) {
            abort(422, 'PayPal Token konnte nicht erzeugt werden.');
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
