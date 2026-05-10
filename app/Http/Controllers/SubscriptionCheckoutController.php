<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\PaymentCheckout;
use App\Models\Setting;
use App\Models\SubscriptionCoupon;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPlan;
use App\Notifications\SubscriptionInvoiceAwaitingTransfer;
use App\Notifications\SubscriptionInvoicePaid;
use App\Support\AppNotification;
use App\Support\ClubRoles;
use App\Support\VisitorCountry;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class SubscriptionCheckoutController extends Controller
{
    public function start(Request $request, SubscriptionPlan $subscriptionPlan, VisitorCountry $visitorCountry)
    {
        return $this->store($request, $subscriptionPlan, $visitorCountry);
    }

    public function store(Request $request, SubscriptionPlan $subscriptionPlan, VisitorCountry $visitorCountry)
    {
        $this->ensureSameOriginCheckout($request);

        abort_unless($subscriptionPlan->is_active, 404);
        $subscriptionPlan->loadMissing('countryPrices');

        $data = $request->validate([
            'provider' => ['required', Rule::in(['stripe', 'paypal', 'bank_transfer'])],
            'billing_interval' => ['required', Rule::in(['monthly', 'yearly'])],
            'club_id' => ['nullable', Rule::exists('clubs', 'id')],
            'coupon_code' => ['nullable', 'string', 'max:80'],
            'accepted_terms' => ['accepted'],
        ], [
            'accepted_terms.accepted' => 'Bitte bestaetige AGB und Widerrufshinweise, bevor du das Abo kostenpflichtig bestellst.',
        ]);

        $club = $this->resolveClub($request, $subscriptionPlan, $data['club_id'] ?? null);
        $country = $visitorCountry->resolve($request, $club?->country ?: $request->user()->country);
        $price = $subscriptionPlan->priceForCountry($country['country']);

        if (! $price['available']) {
            $this->checkoutError('Dieser Abo-Plan ist in deinem Land aktuell nicht verfuegbar.');
        }

        $amountCents = $data['billing_interval'] === 'yearly'
            ? (int) $price['yearly_price_cents']
            : (int) $price['monthly_price_cents'];

        if ($amountCents <= 0) {
            $this->checkoutError('Kostenlose Plaene brauchen keinen Checkout.');
        }

        $coupon = $this->resolveCoupon($data['coupon_code'] ?? null);
        $discountCents = $coupon ? $coupon->discountFor($amountCents) : 0;
        $payableCents = max(0, $amountCents - $discountCents);

        if ($payableCents <= 0) {
            $this->checkoutError('Der Rabatt deckt den gesamten Betrag. Kostenlose Aktivierung folgt in einer spaeteren Ausbaustufe.');
        }

        if ($data['provider'] === 'bank_transfer' && blank($this->bankTransferSettings()['iban'])) {
            $this->checkoutError('Bankverbindung fuer Ueberweisung ist noch nicht konfiguriert.');
        }

        $checkout = PaymentCheckout::create([
            'user_id' => $request->user()->id,
            'club_id' => $club?->id,
            'subscription_plan_id' => $subscriptionPlan->id,
            'subscription_coupon_id' => $coupon?->id,
            'provider' => $data['provider'],
            'billing_interval' => $data['billing_interval'],
            'original_amount_cents' => $amountCents,
            'discount_cents' => $discountCents,
            'amount_cents' => $payableCents,
            'currency' => $price['currency'] ?: 'EUR',
            'status' => 'pending',
            'payload' => [
                'pricing_country' => $country['country'],
                'pricing_country_source' => $country['source'],
                'localized_price' => $price['localized'],
                'base_currency' => $subscriptionPlan->currency,
                'base_monthly_price_cents' => $subscriptionPlan->monthly_price_cents,
                'base_yearly_price_cents' => $subscriptionPlan->yearly_price_cents,
            ],
        ]);
        $this->createSubscriptionInvoice($checkout);

        if ($data['provider'] === 'bank_transfer') {
            $this->prepareBankTransferCheckout($checkout);
            $checkout->invoice?->update([
                'status' => 'awaiting_transfer',
                'payment_reference' => $checkout->payment_reference,
                'due_at' => $checkout->due_at,
                'meta' => array_merge($checkout->invoice->meta ?? [], [
                    'bank_transfer' => $checkout->payload,
                ]),
            ]);

            AppNotification::send($checkout->user_id, 'subscription.invoice.awaiting_transfer', [
                'title' => 'Airmius Rechnung wartet auf Ueberweisung',
                'body' => $checkout->invoice->number.' - '.$checkout->payment_reference,
                'subscription_invoice_id' => $checkout->invoice->id,
            ]);
            $this->sendAwaitingTransferEmail($checkout);

            if ($this->expectsCheckoutJson($request)) {
                return response()->json([
                    'redirect_url' => route('subscription-checkout.bank-transfer.show', $checkout),
                ]);
            }

            return redirect()->route('subscription-checkout.bank-transfer.show', $checkout);
        }

        $checkoutUrl = $data['provider'] === 'stripe'
            ? $this->createStripeCheckout($checkout)
            : $this->createPayPalCheckout($checkout);

        $checkout->update(['checkout_url' => $checkoutUrl]);

        if ($this->expectsCheckoutJson($request)) {
            return response()->json([
                'redirect_url' => $checkoutUrl,
            ]);
        }

        return Inertia::location($checkoutUrl);
    }

    private function expectsCheckoutJson(Request $request): bool
    {
        return $request->expectsJson()
            || $request->ajax()
            || $request->header('X-Checkout-Mode') === 'json';
    }

    private function checkoutError(string $message): never
    {
        throw ValidationException::withMessages([
            'checkout' => $message,
        ]);
    }

    private function ensureSameOriginCheckout(Request $request): void
    {
        $allowedHost = $request->getHost();
        $source = $request->headers->get('origin') ?: $request->headers->get('referer');

        if (blank($source)) {
            Log::warning('Subscription checkout blocked because origin is missing', [
                'path' => $request->path(),
                'user_id' => $request->user()?->id,
                'host' => $allowedHost,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            abort(403, 'Checkout konnte aus Sicherheitsgruenden nicht gestartet werden.');
        }

        $sourceHost = parse_url($source, PHP_URL_HOST);

        if (! hash_equals((string) $allowedHost, (string) $sourceHost)) {
            Log::warning('Subscription checkout blocked because origin does not match host', [
                'path' => $request->path(),
                'user_id' => $request->user()?->id,
                'host' => $allowedHost,
                'source' => $source,
                'source_host' => $sourceHost,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            abort(403, 'Checkout konnte aus Sicherheitsgruenden nicht gestartet werden.');
        }
    }

    public function success(Request $request, PaymentCheckout $checkout)
    {
        abort_unless($checkout->user_id === $request->user()->id, 403);

        if ($checkout->provider === 'stripe' && $checkout->status === 'pending') {
            $this->syncStripeCheckout($checkout);
        }

        if ($checkout->provider === 'paypal' && $checkout->status === 'pending') {
            if (! $this->capturePayPalOrder($checkout)) {
                return redirect()
                    ->route('guest.pricing', ['audience' => $checkout->plan?->target_actor ?: 'sportler'])
                    ->with('error', 'PayPal konnte die Zahlung gerade nicht final bestaetigen. Bitte versuche es erneut oder pruefe spaeter deine Abos.');
            }
        }

        return redirect()
            ->route('auth.club-memberships.index')
            ->with('success', 'Checkout wurde verarbeitet. Dein Abo wird aktualisiert, sobald der Zahlungsanbieter die Zahlung bestaetigt.');
    }

    public function cancel(Request $request, PaymentCheckout $checkout)
    {
        abort_unless($checkout->user_id === $request->user()->id, 403);

        $checkout->update(['status' => 'cancelled']);

        return redirect()
            ->route('guest.pricing')
            ->with('success', 'Checkout wurde abgebrochen.');
    }

    public function bankTransfer(Request $request, PaymentCheckout $checkout)
    {
        abort_unless($checkout->user_id === $request->user()->id, 403);
        abort_unless($checkout->provider === 'bank_transfer', 404);

        return inertia('Auth/Dashboard/Subscriptions/BankTransfer', [
            'checkout' => [
                'id' => $checkout->id,
                'status' => $checkout->status,
                'payment_reference' => $checkout->payment_reference,
                'amount' => number_format($checkout->amount_cents / 100, 2, ',', '.').' '.$checkout->currency,
                'amount_cents' => $checkout->amount_cents,
                'currency' => $checkout->currency,
                'due_at' => $checkout->due_at?->toDateString(),
                'billing_interval' => $checkout->billing_interval,
                'plan' => [
                    'name' => $checkout->plan?->name,
                    'description' => $checkout->plan?->description,
                ],
                'invoice' => $checkout->invoice ? [
                    'id' => $checkout->invoice->id,
                    'number' => $checkout->invoice->number,
                ] : null,
                'club' => $checkout->club ? [
                    'id' => $checkout->club->id,
                    'name' => $checkout->club->name,
                ] : null,
            ],
            'bank' => $this->bankTransferSettings(),
        ]);
    }

    public function markBankTransferPaid(Request $request, PaymentCheckout $checkout)
    {
        abort_unless($request->user()->can('subscriptions.manage') || $request->user()->can('system.manage'), 403);
        abort_unless($checkout->provider === 'bank_transfer', 404);

        $checkout->update([
            'payload' => array_merge($checkout->payload ?? [], [
                'marked_paid_by' => $request->user()->id,
                'marked_paid_at' => now()->toISOString(),
            ]),
        ]);

        $this->activateCheckout($checkout);

        return back()->with('success', 'Ueberweisung wurde als bezahlt markiert und das Abo aktiviert.');
    }

    public function stripeWebhook(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        if (! $this->isValidStripeSignature($payload, $signature)) {
            return response('Invalid signature', 400);
        }

        $event = json_decode($payload, true);
        $type = $event['type'] ?? null;
        $object = $event['data']['object'] ?? [];

        if ($type === 'checkout.session.completed') {
            $checkout = PaymentCheckout::query()
                ->where('provider', 'stripe')
                ->where('provider_checkout_id', $object['id'] ?? null)
                ->first();

            if ($checkout) {
                $checkout->update([
                    'provider_customer_id' => $object['customer'] ?? null,
                    'provider_subscription_id' => $object['subscription'] ?? null,
                    'payload' => array_merge($checkout->payload ?? [], ['stripe_webhook' => $event]),
                ]);
                $this->activateCheckout($checkout);
            }
        }

        return response('ok');
    }

    public function paypalWebhook(Request $request)
    {
        if (! $this->isValidPayPalWebhook($request)) {
            return response('Invalid signature', 400);
        }

        $event = $request->all();
        $eventType = $event['event_type'] ?? null;
        $resource = $event['resource'] ?? [];
        $orderId = $resource['supplementary_data']['related_ids']['order_id'] ?? $resource['id'] ?? null;

        if (in_array($eventType, ['CHECKOUT.ORDER.APPROVED', 'PAYMENT.CAPTURE.COMPLETED'], true)) {
            $checkout = PaymentCheckout::query()
                ->where('provider', 'paypal')
                ->where('provider_checkout_id', $orderId)
                ->first();

            if ($checkout) {
                $checkout->update(['payload' => array_merge($checkout->payload ?? [], ['paypal_webhook' => $event])]);
                $this->activateCheckout($checkout);
            }
        }

        return response('ok');
    }

    private function resolveClub(Request $request, SubscriptionPlan $plan, ?int $clubId): ?Club
    {
        if (($plan->target_actor ?? 'verein') !== 'verein') {
            return null;
        }

        $club = Club::query()
            ->where('id', $clubId)
            ->where(function ($query) use ($request) {
                $query->where('owner_id', $request->user()->id)
                    ->orWhereHas('users', function ($memberQuery) use ($request) {
                        $memberQuery->where('users.id', $request->user()->id);
                        ClubRoles::whereAny($memberQuery, ['owner', 'admin', 'manager']);
                    });
            })
            ->first();

        if (! $club) {
            $club = Club::query()->where('owner_id', $request->user()->id)->oldest('id')->first();
        }

        if (! $club) {
            $this->checkoutError('Bitte erst einen Verein erstellen oder auswaehlen.');
        }

        return $club;
    }

    private function createStripeCheckout(PaymentCheckout $checkout): string
    {
        $secret = config('services.stripe.secret');
        if (blank($secret)) {
            $this->checkoutError('Stripe ist noch nicht konfiguriert.');
        }

        $response = Http::asForm()
            ->withToken($secret)
            ->post('https://api.stripe.com/v1/checkout/sessions', [
                'mode' => 'payment',
                'success_url' => route('subscription-checkout.success', $checkout).'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('subscription-checkout.cancel', $checkout),
                'client_reference_id' => (string) $checkout->id,
                'customer_email' => $checkout->user->email,
                'line_items[0][price_data][currency]' => strtolower($checkout->currency),
                'line_items[0][price_data][product_data][name]' => 'Airmius '.$checkout->plan->name,
                'line_items[0][price_data][unit_amount]' => $checkout->amount_cents,
                'line_items[0][quantity]' => 1,
                'metadata[checkout_id]' => (string) $checkout->id,
                'metadata[plan_id]' => (string) $checkout->subscription_plan_id,
                'metadata[club_id]' => (string) ($checkout->club_id ?? ''),
            ]);

        if ($response->failed()) {
            Log::warning('Stripe checkout failed', ['body' => $response->json()]);
            $this->checkoutError('Stripe Checkout konnte nicht gestartet werden.');
        }

        $payload = $response->json();
        $checkout->update([
            'provider_checkout_id' => $payload['id'] ?? null,
            'payload' => array_merge($checkout->payload ?? [], ['stripe' => $payload]),
        ]);

        return $payload['url'];
    }

    private function prepareBankTransferCheckout(PaymentCheckout $checkout): void
    {
        $bank = $this->bankTransferSettings();

        if (blank($bank['iban'])) {
            $this->checkoutError('Bankverbindung fuer Ueberweisung ist noch nicht konfiguriert.');
        }

        $checkout->update([
            'status' => 'awaiting_transfer',
            'payment_reference' => $this->paymentReference($checkout),
            'due_at' => now()->addDays((int) $bank['payment_terms_days'])->endOfDay(),
            'payload' => array_merge($checkout->payload ?? [], [
                'bank_account_holder' => $bank['bank_account_holder'],
                'bank_name' => $bank['bank_name'],
                'iban' => $bank['iban'],
                'bic' => $bank['bic'],
                'payment_terms_days' => $bank['payment_terms_days'],
            ]),
        ]);
    }

    private function syncStripeCheckout(PaymentCheckout $checkout): void
    {
        $sessionId = request('session_id') ?: $checkout->provider_checkout_id;

        if (! $sessionId || ! config('services.stripe.secret')) {
            return;
        }

        $response = Http::withToken(config('services.stripe.secret'))
            ->get('https://api.stripe.com/v1/checkout/sessions/'.$sessionId);

        if ($response->ok() && ($response->json('payment_status') === 'paid')) {
            $checkout->update([
                'provider_checkout_id' => $response->json('id'),
                'provider_customer_id' => $response->json('customer'),
                'provider_subscription_id' => $response->json('subscription'),
                'payload' => array_merge($checkout->payload ?? [], ['stripe_sync' => $response->json()]),
            ]);
            $this->activateCheckout($checkout);
        }
    }

    private function createPayPalCheckout(PaymentCheckout $checkout): string
    {
        $token = $this->paypalAccessToken();
        $baseUrl = $this->paypalBaseUrl();

        $response = Http::withToken($token)->post($baseUrl.'/v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => 'airmius-checkout-'.$checkout->id,
                'description' => 'Airmius '.$checkout->plan->name,
                'amount' => [
                    'currency_code' => $checkout->currency,
                    'value' => number_format($checkout->amount_cents / 100, 2, '.', ''),
                ],
            ]],
            'application_context' => [
                'brand_name' => 'Airmius',
                'user_action' => 'PAY_NOW',
                'return_url' => route('subscription-checkout.success', $checkout),
                'cancel_url' => route('subscription-checkout.cancel', $checkout),
            ],
        ]);

        if ($response->failed()) {
            Log::warning('PayPal checkout failed', ['body' => $response->json()]);
            $this->checkoutError('PayPal Checkout konnte nicht gestartet werden.');
        }

        $payload = $response->json();
        $checkout->update([
            'provider_checkout_id' => $payload['id'] ?? null,
            'payload' => array_merge($checkout->payload ?? [], ['paypal' => $payload]),
        ]);

        $approveLink = collect($payload['links'] ?? [])->firstWhere('rel', 'approve');

        if (! $approveLink || blank($approveLink['href'] ?? null)) {
            $this->checkoutError('PayPal Genehmigungslink fehlt.');
        }

        return $approveLink['href'];
    }

    private function capturePayPalOrder(PaymentCheckout $checkout): bool
    {
        if (! $checkout->provider_checkout_id) {
            return false;
        }

        try {
            $response = Http::withToken($this->paypalAccessToken())
                ->retry(3, 500)
                ->timeout(20)
                ->connectTimeout(10)
                ->withOptions(['version' => 1.1])
                ->withHeaders(['PayPal-Request-Id' => (string) Str::uuid()])
                ->withBody('{}', 'application/json')
                ->post($this->paypalBaseUrl().'/v2/checkout/orders/'.$checkout->provider_checkout_id.'/capture');
        } catch (ConnectionException $exception) {
            Log::warning('PayPal capture connection failed', [
                'checkout_id' => $checkout->id,
                'paypal_order_id' => $checkout->provider_checkout_id,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }

        if ($response->successful() && in_array($response->json('status'), ['COMPLETED', 'APPROVED'], true)) {
            $checkout->update(['payload' => array_merge($checkout->payload ?? [], ['paypal_capture' => $response->json()])]);
            $this->activateCheckout($checkout);

            return true;
        }

        Log::warning('PayPal capture was not completed', [
            'checkout_id' => $checkout->id,
            'paypal_order_id' => $checkout->provider_checkout_id,
            'status' => $response->status(),
            'body' => $response->json(),
        ]);

        return false;
    }

    private function paypalAccessToken(): string
    {
        if (blank(config('services.paypal.client_id')) || blank(config('services.paypal.client_secret'))) {
            $this->checkoutError('PayPal ist noch nicht konfiguriert.');
        }

        $response = Http::asForm()
            ->withBasicAuth(config('services.paypal.client_id'), config('services.paypal.client_secret'))
            ->post($this->paypalBaseUrl().'/v1/oauth2/token', [
                'grant_type' => 'client_credentials',
            ]);

        if ($response->failed()) {
            $this->checkoutError('PayPal Token konnte nicht erzeugt werden.');
        }

        return $response->json('access_token');
    }

    private function paypalBaseUrl(): string
    {
        return config('services.paypal.mode') === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    private function bankTransferSettings(): array
    {
        return [
            'bank_account_holder' => Setting::valueFor('billing_bank_account_holder', 'Airmius'),
            'bank_name' => Setting::valueFor('billing_bank_name', ''),
            'iban' => Setting::valueFor('billing_iban', ''),
            'bic' => Setting::valueFor('billing_bic', ''),
            'payment_terms_days' => (int) Setting::valueFor('billing_payment_terms_days', 14),
        ];
    }

    private function paymentReference(PaymentCheckout $checkout): string
    {
        return 'AIRMIUS-'.$checkout->created_at->format('Y').'-'.str_pad((string) $checkout->id, 6, '0', STR_PAD_LEFT);
    }

    private function activateCheckout(PaymentCheckout $checkout): void
    {
        if ($checkout->status === 'completed') {
            return;
        }

        if ($checkout->coupon) {
            $checkout->coupon->increment('redeemed_count');
        }

        $periodEndsAt = $checkout->billing_interval === 'yearly'
            ? now()->addYear()
            : now()->addMonth();

        if ($checkout->club_id) {
            $checkout->club->currentSubscription()->updateOrCreate(
                ['club_id' => $checkout->club_id],
                [
                    'subscription_plan_id' => $checkout->subscription_plan_id,
                    'status' => 'active',
                    'payment_provider' => $checkout->provider,
                    'provider_subscription_id' => $checkout->provider_subscription_id,
                    'provider_customer_id' => $checkout->provider_customer_id,
                    'trial_ends_at' => null,
                    'current_period_ends_at' => $periodEndsAt,
                ],
            );
        } else {
            $checkout->user->userSubscriptions()->updateOrCreate(
                ['subscription_plan_id' => $checkout->subscription_plan_id],
                [
                    'status' => 'active',
                    'payment_provider' => $checkout->provider,
                    'provider_subscription_id' => $checkout->provider_subscription_id,
                    'provider_customer_id' => $checkout->provider_customer_id,
                    'trial_ends_at' => null,
                    'current_period_ends_at' => $periodEndsAt,
                ],
            );
        }

        $checkout->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $checkout->invoice?->update([
            'status' => 'paid',
            'paid_at' => now(),
            'payment_method' => $checkout->provider,
            'payment_reference' => $checkout->payment_reference ?: $checkout->provider_checkout_id,
            'meta' => array_merge($checkout->invoice->meta ?? [], [
                'provider_checkout_id' => $checkout->provider_checkout_id,
                'provider_subscription_id' => $checkout->provider_subscription_id,
                'provider_customer_id' => $checkout->provider_customer_id,
            ]),
        ]);

        AppNotification::send($checkout->user_id, 'subscription.invoice.paid', [
            'title' => 'Airmius Rechnung bezahlt',
            'body' => ($checkout->invoice?->number ?: 'Abo-Rechnung').' wurde als bezahlt markiert.',
            'subscription_invoice_id' => $checkout->invoice?->id,
        ]);
        $this->sendPaidEmail($checkout);
    }

    private function sendAwaitingTransferEmail(PaymentCheckout $checkout): void
    {
        $invoice = $checkout->invoice;

        if (! $invoice || $invoice->invoice_email_sent_at || ! $checkout->user?->email) {
            return;
        }

        $checkout->user->notify(new SubscriptionInvoiceAwaitingTransfer($invoice));

        $invoice->forceFill(['invoice_email_sent_at' => now()])->save();
    }

    private function sendPaidEmail(PaymentCheckout $checkout): void
    {
        $invoice = $checkout->invoice;

        if (! $invoice || $invoice->payment_confirmation_email_sent_at || ! $checkout->user?->email) {
            return;
        }

        $checkout->user->notify(new SubscriptionInvoicePaid($invoice));

        $invoice->forceFill(['payment_confirmation_email_sent_at' => now()])->save();
    }

    private function createSubscriptionInvoice(PaymentCheckout $checkout): SubscriptionInvoice
    {
        $periodStart = now()->toDateString();
        $periodEnd = $checkout->billing_interval === 'yearly'
            ? now()->addYear()->subDay()->toDateString()
            : now()->addMonth()->subDay()->toDateString();

        return SubscriptionInvoice::query()->firstOrCreate(
            ['payment_checkout_id' => $checkout->id],
            [
                'user_id' => $checkout->user_id,
                'club_id' => $checkout->club_id,
                'subscription_plan_id' => $checkout->subscription_plan_id,
                'number' => $this->nextInvoiceNumber(),
                'title' => 'Airmius '.$checkout->plan->name,
                'description' => 'Airmius Abo '.$checkout->plan->name.' ('.($checkout->billing_interval === 'yearly' ? 'Jahreszahlung' : 'Monatszahlung').')',
                'amount_cents' => $checkout->amount_cents,
                'currency' => $checkout->currency,
                'status' => 'open',
                'payment_method' => $checkout->provider,
                'billing_period_start' => $periodStart,
                'billing_period_end' => $periodEnd,
                'issued_at' => now(),
                'due_at' => now()->addDays((int) Setting::valueFor('billing_payment_terms_days', 14)),
                'meta' => [
                    'checkout_id' => $checkout->id,
                    'billing_interval' => $checkout->billing_interval,
                    'original_amount_cents' => $checkout->original_amount_cents,
                    'discount_cents' => $checkout->discount_cents,
                    'coupon_code' => $checkout->coupon?->code,
                    'pricing_country' => $checkout->payload['pricing_country'] ?? null,
                    'pricing_country_source' => $checkout->payload['pricing_country_source'] ?? null,
                    'localized_price' => $checkout->payload['localized_price'] ?? false,
                ],
            ],
        );
    }

    private function resolveCoupon(?string $code): ?SubscriptionCoupon
    {
        if (blank($code)) {
            return null;
        }

        $coupon = SubscriptionCoupon::query()
            ->where('code', strtoupper(trim($code)))
            ->first();

        if (! $coupon || ! $coupon->isRedeemable()) {
            $this->checkoutError('Der Rabattcode ist ungueltig oder abgelaufen.');
        }

        return $coupon;
    }

    private function nextInvoiceNumber(): string
    {
        $prefix = 'AR-'.now()->format('Y').'-';
        $next = SubscriptionInvoice::query()
            ->where('number', 'like', $prefix.'%')
            ->count() + 1;

        return $prefix.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    private function isValidStripeSignature(string $payload, ?string $signature): bool
    {
        $secret = config('services.stripe.webhook_secret');

        if (blank($secret)) {
            return true;
        }

        if (! $signature) {
            return false;
        }

        $parts = collect(explode(',', $signature))
            ->mapWithKeys(function ($part) {
                [$key, $value] = array_pad(explode('=', $part, 2), 2, null);

                return [$key => $value];
            });
        $timestamp = $parts->get('t');
        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        return hash_equals($expected, (string) $parts->get('v1'));
    }

    private function isValidPayPalWebhook(Request $request): bool
    {
        if (blank(config('services.paypal.webhook_id'))) {
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
                    'webhook_id' => config('services.paypal.webhook_id'),
                    'webhook_event' => $request->all(),
                ]);

            return $response->ok() && $response->json('verification_status') === 'SUCCESS';
        } catch (\Throwable) {
            return false;
        }
    }
}
