<?php

namespace App\Services;

use App\Models\CommerceOrder;
use App\Models\Setting;
use App\Support\PaymentWebhookVerifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Throwable;

class CommercePaymentGatewayService
{
    public function createProviderCheckout(CommerceOrder $order): string
    {
        try {
            $url = $order->provider === 'stripe'
                ? $this->createStripeCheckout($order)
                : $this->createPayPalCheckout($order);
        } catch (Throwable $exception) {
            $this->markProviderCheckoutFailed($order);

            throw $exception;
        }

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
        abort_if(blank($bank['iban']), 422, __('commerce.validation.bank_not_configured'));

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
                'cancel' => URL::temporarySignedRoute(
                    'commerce-checkout.guest.cancel',
                    now()->addDay(),
                    ['order' => $order, 'token' => $order->access_token],
                ),
                'bank-transfer' => route('commerce-checkout.guest.bank-transfer.show', [$order, $order->access_token]),
            };
        }

        return match ($type) {
            'success' => route('commerce-checkout.success', $order),
            'cancel' => URL::temporarySignedRoute(
                'commerce-checkout.cancel',
                now()->addDay(),
                ['order' => $order],
            ),
            'bank-transfer' => route('commerce-checkout.bank-transfer.show', $order),
        };
    }

    public function isValidStripeSignature(string $payload, ?string $signature): bool
    {
        return app(PaymentWebhookVerifier::class)->isValidStripeSignature($payload, $signature);
    }

    public function isValidPayPalWebhook(Request $request): bool
    {
        $webhookId = config('services.paypal.commerce_webhook_id') ?: config('services.paypal.webhook_id');

        return app(PaymentWebhookVerifier::class)->isValidPayPalWebhook($request, $webhookId);
    }

    private function createStripeCheckout(CommerceOrder $order): string
    {
        abort_if(blank(config('services.stripe.secret')), 422, __('commerce.validation.provider_not_configured', ['provider' => 'Stripe']));

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
            Log::warning('Commerce Stripe checkout failed', [
                'order_id' => $order->id,
                'status' => $response->status(),
            ]);
            abort(422, __('commerce.validation.provider_checkout_failed', ['provider' => 'Stripe']));
        }

        $order->update([
            'provider_checkout_id' => $response->json('id'),
            'payload' => $response->json(),
        ]);

        return $response->json('url');
    }

    private function createPayPalCheckout(CommerceOrder $order): string
    {
        $this->ensurePayPalConfigured();

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
            Log::warning('Commerce PayPal checkout failed', [
                'order_id' => $order->id,
                'status' => $response->status(),
            ]);
            abort(422, __('commerce.validation.provider_checkout_failed', ['provider' => 'PayPal']));
        }

        $order->update([
            'provider_checkout_id' => $response->json('id'),
            'payload' => $response->json(),
        ]);

        $approveLink = collect($response->json('links') ?? [])->firstWhere('rel', 'approve');
        abort_if(blank($approveLink['href'] ?? null), 422, __('commerce.validation.paypal_approval_missing'));

        return $approveLink['href'];
    }

    private function paypalAccessToken(): string
    {
        $this->ensurePayPalConfigured();

        $response = Http::asForm()
            ->withBasicAuth(config('services.paypal.client_id'), config('services.paypal.client_secret'))
            ->post($this->paypalBaseUrl().'/v1/oauth2/token', ['grant_type' => 'client_credentials']);

        if ($response->failed()) {
            abort(422, __('commerce.validation.paypal_token_failed'));
        }

        return $response->json('access_token');
    }

    private function ensurePayPalConfigured(): void
    {
        abort_if(
            blank(config('services.paypal.client_id')) || blank(config('services.paypal.client_secret')),
            422,
            __('commerce.validation.provider_not_configured', ['provider' => 'PayPal']),
        );
    }

    private function markProviderCheckoutFailed(CommerceOrder $order): void
    {
        DB::transaction(function () use ($order) {
            $lockedOrder = CommerceOrder::query()->lockForUpdate()->findOrFail($order->id);

            if ($lockedOrder->status !== 'pending') {
                return;
            }

            $lockedOrder->update([
                'status' => 'failed',
                'checkout_url' => null,
                'payload' => [
                    ...($lockedOrder->payload ?: []),
                    'provider_failed_at' => now()->toISOString(),
                ],
            ]);
        });

        $order->refresh();
    }

    private function paypalBaseUrl(): string
    {
        return config('services.paypal.mode') === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }
}
