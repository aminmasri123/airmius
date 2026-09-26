<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\ClubSubscription;
use App\Models\PaymentCheckout;
use App\Models\Setting;
use App\Models\SubscriptionCoupon;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPlan;
use App\Models\UserSubscription;
use App\Notifications\SubscriptionInvoiceAwaitingTransfer;
use App\Notifications\SubscriptionInvoicePaid;
use App\Services\Subscriptions\SubscriptionCheckoutActivationService;
use App\Services\ProviderWebhookEventService;
use App\Services\UserSubscriptionActivationService;
use App\Support\AppNotification;
use App\Support\ClubPermissions;
use App\Support\PaymentWebhookVerifier;
use App\Support\SupportedLocale;
use App\Support\VisitorCountry;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class SubscriptionCheckoutController extends Controller
{
    public function __construct(private readonly SubscriptionCheckoutActivationService $checkoutActivator) {}

    public function store(Request $request, SubscriptionPlan $subscriptionPlan, VisitorCountry $visitorCountry)
    {
        $this->ensureSameOriginCheckout($request);

        abort_unless($subscriptionPlan->is_active && $subscriptionPlan->is_public, 404);
        $subscriptionPlan->loadMissing('countryPrices');

        $data = $request->validate([
            'provider' => ['required', Rule::in(['stripe', 'paypal', 'bank_transfer'])],
            'billing_interval' => ['required', Rule::in(['monthly', 'yearly'])],
            'club_id' => ['nullable', 'integer', 'min:1'],
            'coupon_code' => ['nullable', 'string', 'max:80'],
            'accepted_terms' => ['accepted'],
        ], [
            'accepted_terms.accepted' => __('subscription.validation.accept_terms'),
        ]);

        $club = $this->resolveClub($request, $subscriptionPlan, $data['club_id'] ?? null);
        $country = $visitorCountry->resolve($request, $club?->country ?: $request->user()->country);
        $price = $subscriptionPlan->priceForCountry($country['country']);

        if (! $price['available']) {
            $this->checkoutError(__('subscription.validation.country_unavailable'));
        }

        $amountCents = $data['billing_interval'] === 'yearly'
            ? (int) $price['yearly_price_cents']
            : (int) $price['monthly_price_cents'];

        if ($amountCents <= 0) {
            $this->checkoutError(__('subscription.validation.free_without_checkout'));
        }

        $coupon = $this->resolveCoupon($data['coupon_code'] ?? null);
        $discountCents = $coupon ? $coupon->discountFor($amountCents) : 0;
        $payableCents = max(0, $amountCents - $discountCents);

        if ($payableCents <= 0) {
            $this->checkoutError(__('subscription.validation.discount_full'));
        }

        if ($data['provider'] === 'bank_transfer' && blank($this->bankTransferSettings()['iban'])) {
            $this->checkoutError(__('subscription.validation.bank_not_configured'));
        }
        $this->ensureProviderIsConfigured($data['provider']);

        $checkout = DB::transaction(function () use ($request, $subscriptionPlan, $club, $coupon, $data, $amountCents, $discountCents, $payableCents, $price, $country) {
            $checkout = PaymentCheckout::query()->create([
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

            if ($checkout->provider === 'bank_transfer') {
                $this->prepareBankTransferCheckout($checkout);
            }

            $this->createSubscriptionInvoice($checkout->fresh(['plan', 'user']));

            return $checkout->fresh(['plan', 'user', 'club', 'invoice']);
        });

        Log::info('Subscription checkout created', [
            'checkout_id' => $checkout->id,
            'plan_id' => $subscriptionPlan->id,
            'user_id' => $request->user()->id,
            'provider' => $data['provider'],
            'amount_cents' => $payableCents,
            'currency' => $price['currency'] ?: 'EUR',
        ]);

        if ($data['provider'] === 'bank_transfer') {
            AppNotification::sendLocalized(
                $checkout->user_id,
                'subscription.invoice.awaiting_transfer',
                'subscription.notifications.awaiting_transfer_title',
                'subscription.notifications.awaiting_transfer_body',
                ['invoice' => $checkout->invoice->number, 'reference' => $checkout->payment_reference],
                ['subscription_invoice_id' => $checkout->invoice->id],
                ['dedupe_key' => 'subscription-invoice:'.$checkout->invoice->id.':awaiting-transfer'],
            );
            $this->sendAwaitingTransferEmail($checkout);

            if ($this->expectsCheckoutJson($request)) {
                return response()->json([
                    'redirect_url' => route('subscription-checkout.bank-transfer.show', $checkout),
                ]);
            }

            return redirect()->route('subscription-checkout.bank-transfer.show', $checkout);
        }

        try {
            $checkoutUrl = $data['provider'] === 'stripe'
                ? $this->createStripeCheckout($checkout)
                : $this->createPayPalCheckout($checkout);
        } catch (Throwable $exception) {
            $this->markCheckoutFailed($checkout);

            if ($exception instanceof ValidationException) {
                throw $exception;
            }

            Log::warning('Subscription provider checkout failed unexpectedly', [
                'checkout_id' => $checkout->id,
                'provider' => $checkout->provider,
                'exception' => $exception::class,
            ]);

            $this->checkoutError(__('subscription.validation.provider_checkout_failed', [
                'provider' => $checkout->provider === 'stripe' ? 'Stripe' : 'PayPal',
            ]));
        }

        $checkout->update(['checkout_url' => $checkoutUrl]);

        if ($this->expectsCheckoutJson($request)) {
            Log::info('Subscription checkout redirect URL returned', [
                'checkout_id' => $checkout->id,
                'provider' => $checkout->provider,
                'has_checkout_url' => filled($checkoutUrl),
            ]);

            return response()->json([
                'redirect_url' => $checkoutUrl,
            ]);
        }

        return redirect()->away($checkoutUrl);
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

            abort(403, __('subscription.validation.security_origin'));
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

            abort(403, __('subscription.validation.security_origin'));
        }
    }

    public function success(Request $request, PaymentCheckout $checkout)
    {
        abort_unless($checkout->user_id === $request->user()->id, 403);

        if ($checkout->provider === 'stripe' && $checkout->status === 'pending') {
            $this->syncStripeCheckout($checkout);
        }

        if ($checkout->provider === 'paypal' && $checkout->status === 'pending') {
            if (! $this->syncPayPalSubscription($checkout, $request->query('subscription_id'))) {
                return redirect()
                    ->route('guest.pricing', ['audience' => $checkout->plan?->target_actor ?: 'sportler'])
                    ->with('error', __('subscription.responses.paypal_confirmation_failed'));
            }
        }

        return redirect()
            ->route('auth.club-memberships.index')
            ->with('success', __('subscription.responses.checkout_processing'));
    }

    public function cancel(Request $request, PaymentCheckout $checkout)
    {
        abort_unless($checkout->user_id === $request->user()->id, 403);

        if ($checkout->status !== 'cancelled') {
            abort_unless(in_array($checkout->status, ['pending', 'awaiting_transfer'], true), 422);

            DB::transaction(function () use ($request, $checkout) {
                $cancelledAt = now()->toISOString();
                $checkout->update([
                    'status' => 'cancelled',
                    'checkout_url' => null,
                    'payload' => array_merge($checkout->payload ?? [], [
                        'cancelled_by' => $request->user()->id,
                        'cancelled_at' => $cancelledAt,
                    ]),
                ]);
                $checkout->invoice?->update([
                    'status' => 'cancelled',
                    'meta' => array_merge($checkout->invoice->meta ?? [], [
                        'cancelled_by' => $request->user()->id,
                        'cancelled_at' => $cancelledAt,
                    ]),
                ]);
            });
        }

        return redirect()
            ->route('guest.pricing')
            ->with('success', __('subscription.responses.checkout_cancelled'));
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

        $this->checkoutActivator->activate($checkout);

        return back()->with('success', __('subscription.responses.bank_transfer_paid'));
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

        app(ProviderWebhookEventService::class)->handle('stripe', 'subscriptions', $event['id'] ?? null, $type, function () use ($type, $object, $event) {
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
                $this->checkoutActivator->activate($checkout);
            }
        }

            if (in_array($type, ['customer.subscription.updated', 'customer.subscription.deleted'], true)) {
            $providerSubscriptionId = $object['id'] ?? null;

            if ($providerSubscriptionId) {
                $this->syncLocalSubscriptionFromProvider(
                    'stripe',
                    $providerSubscriptionId,
                    match ($object['status'] ?? null) {
                        'active', 'trialing' => 'active',
                        'past_due', 'unpaid' => 'past_due',
                        'canceled', 'incomplete_expired' => 'cancelled',
                        default => null,
                    },
                    $object['cancel_at_period_end'] ?? false,
                    isset($object['current_period_end']) ? Carbon::createFromTimestamp((int) $object['current_period_end']) : null,
                    $event
                );
            }
        }

            if (in_array($type, ['invoice.payment_succeeded', 'invoice.paid'], true)) {
            $providerSubscriptionId = $object['subscription'] ?? null;

            if ($providerSubscriptionId) {
                $this->syncLocalSubscriptionFromProvider(
                    'stripe',
                    $providerSubscriptionId,
                    'active',
                    false,
                    isset($object['lines']['data'][0]['period']['end'])
                        ? Carbon::createFromTimestamp((int) $object['lines']['data'][0]['period']['end'])
                        : null,
                    $event
                );
            }
        }

            if (in_array($type, ['invoice.payment_failed'], true)) {
            $providerSubscriptionId = $object['subscription'] ?? null;

            if ($providerSubscriptionId) {
                $this->syncLocalSubscriptionFromProvider('stripe', $providerSubscriptionId, 'past_due', false, null, $event);
            }
        }

            return null;
        });

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
        $providerSubscriptionId = $resource['billing_agreement_id']
            ?? $resource['subscription_id']
            ?? $resource['id']
            ?? null;

        app(ProviderWebhookEventService::class)->handle('paypal', 'subscriptions', $event['id'] ?? null, $eventType, function () use ($eventType, $event, $providerSubscriptionId) {
            if (in_array($eventType, ['BILLING.SUBSCRIPTION.ACTIVATED', 'PAYMENT.SALE.COMPLETED'], true)) {
            $checkout = PaymentCheckout::query()
                ->where('provider', 'paypal')
                ->where('provider_subscription_id', $providerSubscriptionId)
                ->first();

            if ($checkout) {
                $checkout->update(['payload' => array_merge($checkout->payload ?? [], ['paypal_webhook' => $event])]);
                $this->checkoutActivator->activate($checkout);
            }
        }

            if (in_array($eventType, ['BILLING.SUBSCRIPTION.CANCELLED', 'BILLING.SUBSCRIPTION.EXPIRED', 'BILLING.SUBSCRIPTION.SUSPENDED'], true)) {
            $this->syncLocalSubscriptionFromProvider(
                'paypal',
                $providerSubscriptionId,
                $eventType === 'BILLING.SUBSCRIPTION.SUSPENDED' ? 'past_due' : 'cancelled',
                false,
                null,
                $event
            );
        }

            return null;
        });

        return response('ok');
    }

    private function resolveClub(Request $request, SubscriptionPlan $plan, ?int $clubId): ?Club
    {
        if (($plan->target_actor ?? 'verein') !== 'verein') {
            return null;
        }

        $clubQuery = Club::query()
            ->where(function ($query) use ($request) {
                $query->where('owner_id', $request->user()->id)
                    ->orWhereHas('users', fn ($members) => $members->where('users.id', $request->user()->id));
            });

        $clubs = $clubQuery
            ->oldest('id')
            ->get()
            ->filter(fn (Club $club) => ClubPermissions::allows(
                $club,
                $request->user(),
                ClubPermissions::SUBSCRIPTIONS_EDIT,
            ));
        $club = $clubId ? $clubs->firstWhere('id', $clubId) : $clubs->first();

        if (! $club) {
            throw ValidationException::withMessages([
                'club_id' => __('subscription.validation.club_required'),
            ]);
        }

        return $club;
    }

    private function ensureProviderIsConfigured(string $provider): void
    {
        if ($provider === 'stripe' && blank(config('services.stripe.secret'))) {
            $this->checkoutError(__('subscription.validation.provider_not_configured', ['provider' => 'Stripe']));
        }

        if ($provider === 'paypal' && (blank(config('services.paypal.client_id')) || blank(config('services.paypal.client_secret')))) {
            $this->checkoutError(__('subscription.validation.provider_not_configured', ['provider' => 'PayPal']));
        }
    }

    private function providerCancelUrl(PaymentCheckout $checkout): string
    {
        return URL::temporarySignedRoute(
            'subscription-checkout.cancel',
            now()->addDay(),
            ['checkout' => $checkout],
        );
    }

    private function markCheckoutFailed(PaymentCheckout $checkout): void
    {
        DB::transaction(function () use ($checkout) {
            $checkout->refresh();

            if ($checkout->status === 'pending') {
                $checkout->update([
                    'status' => 'failed',
                    'checkout_url' => null,
                    'payload' => array_merge($checkout->payload ?? [], [
                        'provider_failed_at' => now()->toISOString(),
                    ]),
                ]);
            }

            $invoice = $checkout->invoice()->first();
            if ($invoice && $invoice->status === 'open') {
                $invoice->update([
                    'status' => 'cancelled',
                    'meta' => array_merge($invoice->meta ?? [], [
                        'provider_failed_at' => now()->toISOString(),
                    ]),
                ]);
            }
        });
    }

    private function createStripeCheckout(PaymentCheckout $checkout): string
    {
        $secret = config('services.stripe.secret');
        if (blank($secret)) {
            $this->checkoutError(__('subscription.validation.provider_not_configured', ['provider' => 'Stripe']));
        }

        $response = Http::asForm()
            ->withToken($secret)
            ->post('https://api.stripe.com/v1/checkout/sessions', [
                'mode' => 'subscription',
                'success_url' => route('subscription-checkout.success', $checkout).'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $this->providerCancelUrl($checkout),
                'client_reference_id' => (string) $checkout->id,
                'customer_email' => $checkout->user->email,
                'line_items[0][price_data][currency]' => strtolower($checkout->currency),
                'line_items[0][price_data][product_data][name]' => 'Airmius '.$checkout->plan->name,
                'line_items[0][price_data][unit_amount]' => $checkout->amount_cents,
                'line_items[0][price_data][recurring][interval]' => $checkout->billing_interval === 'yearly' ? 'year' : 'month',
                'line_items[0][price_data][recurring][interval_count]' => 1,
                'line_items[0][quantity]' => 1,
                'metadata[checkout_id]' => (string) $checkout->id,
                'metadata[plan_id]' => (string) $checkout->subscription_plan_id,
                'metadata[club_id]' => (string) ($checkout->club_id ?? ''),
                'subscription_data[metadata][checkout_id]' => (string) $checkout->id,
                'subscription_data[metadata][plan_id]' => (string) $checkout->subscription_plan_id,
                'subscription_data[metadata][club_id]' => (string) ($checkout->club_id ?? ''),
                'subscription_data[metadata][user_id]' => (string) $checkout->user_id,
            ]);

        if ($response->failed()) {
            Log::warning('Stripe checkout failed', [
                'checkout_id' => $checkout->id,
                'status' => $response->status(),
            ]);
            $this->checkoutError(__('subscription.validation.provider_checkout_failed', ['provider' => 'Stripe']));
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
            $this->checkoutError(__('subscription.validation.bank_not_configured'));
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
            $this->checkoutActivator->activate($checkout);
        }
    }

    private function createPayPalCheckout(PaymentCheckout $checkout): string
    {
        $planId = $this->ensurePayPalPlan($checkout);

        $response = Http::withToken($this->paypalAccessToken())->post($this->paypalBaseUrl().'/v1/billing/subscriptions', [
            'plan_id' => $planId,
            'custom_id' => 'airmius-subscription-checkout-'.$checkout->id,
            'quantity' => '1',
            'subscriber' => [
                'email_address' => $checkout->user?->email,
                'name' => [
                    'given_name' => Str::of((string) $checkout->user?->name)->explode(' ')->first() ?: 'Airmius',
                    'surname' => Str::of((string) $checkout->user?->name)->explode(' ')->slice(1)->implode(' ') ?: 'Kunde',
                ],
            ],
            'application_context' => [
                'brand_name' => 'Airmius',
                'user_action' => 'SUBSCRIBE_NOW',
                'return_url' => route('subscription-checkout.success', $checkout),
                'cancel_url' => $this->providerCancelUrl($checkout),
            ],
        ]);

        if ($response->failed()) {
            Log::warning('PayPal checkout failed', [
                'checkout_id' => $checkout->id,
                'status' => $response->status(),
            ]);
            $this->checkoutError(__('subscription.validation.provider_checkout_failed', ['provider' => 'PayPal']));
        }

        $payload = $response->json();
        $checkout->update([
            'provider_checkout_id' => $payload['id'] ?? null,
            'provider_subscription_id' => $payload['id'] ?? null,
            'payload' => array_merge($checkout->payload ?? [], ['paypal' => $payload]),
        ]);

        $approveLink = collect($payload['links'] ?? [])->firstWhere('rel', 'approve');

        if (! $approveLink || blank($approveLink['href'] ?? null)) {
            Log::warning('PayPal approval link missing', [
                'checkout_id' => $checkout->id,
            ]);

            $this->checkoutError(__('subscription.validation.paypal_approval_missing'));
        }

        return $approveLink['href'];
    }

    private function ensurePayPalPlan(PaymentCheckout $checkout): string
    {
        $checkout->loadMissing('plan');

        $intervalUnit = $checkout->billing_interval === 'yearly' ? 'YEAR' : 'MONTH';
        $signature = hash('sha256', implode('|', [
            config('services.paypal.mode'),
            $checkout->plan->id,
            $checkout->amount_cents,
            strtoupper($checkout->currency),
            $intervalUnit,
            1,
        ]));

        if ($checkout->plan->paypal_plan_id && $checkout->plan->paypal_plan_signature === $signature) {
            return $checkout->plan->paypal_plan_id;
        }

        $productId = $checkout->plan->paypal_product_id ?: $this->createPayPalProduct($checkout->plan);
        $amount = number_format($checkout->amount_cents / 100, 2, '.', '');

        $response = Http::withToken($this->paypalAccessToken())->post($this->paypalBaseUrl().'/v1/billing/plans', [
            'product_id' => $productId,
            'name' => 'Airmius '.$checkout->plan->name.' '.strtoupper($checkout->currency).' '.$amount.' '.$intervalUnit,
            'description' => Str::limit($checkout->plan->description ?: __('subscription.checkout.product_fallback'), 120),
            'status' => 'ACTIVE',
            'billing_cycles' => [[
                'frequency' => [
                    'interval_unit' => $intervalUnit,
                    'interval_count' => 1,
                ],
                'tenure_type' => 'REGULAR',
                'sequence' => 1,
                'total_cycles' => 0,
                'pricing_scheme' => [
                    'fixed_price' => [
                        'value' => $amount,
                        'currency_code' => strtoupper($checkout->currency),
                    ],
                ],
            ]],
            'payment_preferences' => [
                'auto_bill_outstanding' => true,
                'setup_fee_failure_action' => 'CONTINUE',
                'payment_failure_threshold' => 3,
            ],
        ]);

        if ($response->failed()) {
            Log::warning('PayPal subscription plan failed', [
                'checkout_id' => $checkout->id,
                'status' => $response->status(),
            ]);
            $this->checkoutError(__('subscription.validation.paypal_plan_failed'));
        }

        $payload = $response->json();
        $checkout->plan->forceFill([
            'paypal_product_id' => $productId,
            'paypal_plan_id' => $payload['id'] ?? null,
            'paypal_plan_signature' => $signature,
            'paypal_payload' => array_merge($checkout->plan->paypal_payload ?? [], ['plan' => $payload]),
        ])->save();

        return $checkout->plan->paypal_plan_id;
    }

    private function createPayPalProduct(SubscriptionPlan $plan): string
    {
        $response = Http::withToken($this->paypalAccessToken())->post($this->paypalBaseUrl().'/v1/catalogs/products', [
            'name' => 'Airmius '.$plan->name,
            'description' => Str::limit($plan->description ?: __('subscription.checkout.product_fallback'), 120),
            'type' => 'SERVICE',
        ]);

        if ($response->failed()) {
            Log::warning('PayPal subscription product failed', [
                'plan_id' => $plan->id,
                'status' => $response->status(),
            ]);
            $this->checkoutError(__('subscription.validation.paypal_product_failed'));
        }

        $payload = $response->json();
        $plan->forceFill([
            'paypal_product_id' => $payload['id'] ?? null,
            'paypal_payload' => array_merge($plan->paypal_payload ?? [], ['product' => $payload]),
        ])->save();

        return $plan->paypal_product_id;
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
            $this->checkoutActivator->activate($checkout);

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

    private function syncPayPalSubscription(PaymentCheckout $checkout, ?string $subscriptionId = null): bool
    {
        $providerSubscriptionId = $subscriptionId ?: $checkout->provider_subscription_id ?: $checkout->provider_checkout_id;

        if (! $providerSubscriptionId) {
            return false;
        }

        $response = Http::withToken($this->paypalAccessToken())
            ->get($this->paypalBaseUrl().'/v1/billing/subscriptions/'.$providerSubscriptionId);

        if (! $response->ok()) {
            Log::warning('PayPal subscription sync failed', [
                'checkout_id' => $checkout->id,
                'paypal_subscription_id' => $providerSubscriptionId,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            return false;
        }

        $checkout->update([
            'provider_subscription_id' => $providerSubscriptionId,
            'provider_checkout_id' => $providerSubscriptionId,
            'payload' => array_merge($checkout->payload ?? [], ['paypal_subscription_sync' => $response->json()]),
        ]);

        if (in_array($response->json('status'), ['ACTIVE', 'APPROVAL_PENDING'], true)) {
            if ($response->json('status') === 'ACTIVE') {
                $this->checkoutActivator->activate($checkout);
            }

            return true;
        }

        return false;
    }

    private function syncLocalSubscriptionFromProvider(
        string $provider,
        ?string $providerSubscriptionId,
        ?string $status,
        bool $cancelAtPeriodEnd = false,
        ?Carbon $periodEndsAt = null,
        array $payload = []
    ): void {
        if (! $providerSubscriptionId || ! $status) {
            return;
        }

        $updates = [
            'status' => $cancelAtPeriodEnd ? 'cancels_at_period_end' : $status,
            'cancel_at_period_end' => $cancelAtPeriodEnd,
        ];

        if ($periodEndsAt) {
            $updates['current_period_ends_at'] = $periodEndsAt;
            $updates['next_invoice_at'] = $periodEndsAt;
            $updates['cancels_at'] = $cancelAtPeriodEnd ? $periodEndsAt : null;
        }

        if ($status === 'active') {
            $updates['grace_period_ends_at'] = null;
            $updates['access_restricted_at'] = null;
            $updates['payment_issue_email_sent_at'] = null;

            if (! $cancelAtPeriodEnd) {
                $updates['cancels_at'] = null;
                $updates['cancelled_at'] = null;
            }
        }

        if ($status === 'cancelled') {
            $updates['cancelled_at'] = now();
            $updates['cancels_at'] = $periodEndsAt ?: now();
        }

        $clubSubscription = ClubSubscription::query()
            ->where('payment_provider', $provider)
            ->where('provider_subscription_id', $providerSubscriptionId)
            ->first();

        if ($clubSubscription) {
            $updates = $this->preserveContractualAccessAfterProviderCancellation($clubSubscription, $updates, $status);
            $clubSubscription->forceFill($updates)->save();
            if ($status === 'active') {
                $this->markRecurringInvoicePaid('club', $clubSubscription->id, $provider, $payload);
            }

            return;
        }

        $userSubscription = UserSubscription::query()
            ->where('payment_provider', $provider)
            ->where('provider_subscription_id', $providerSubscriptionId)
            ->first();

        if ($userSubscription) {
            $updates = $this->preserveContractualAccessAfterProviderCancellation($userSubscription, $updates, $status);
            $userSubscription->forceFill($updates)->save();
            if ($status === 'active') {
                app(UserSubscriptionActivationService::class)->retireOtherUserSubscriptions($userSubscription->fresh('plan'));
                $this->markRecurringInvoicePaid('user', $userSubscription->id, $provider, $payload);
            }
        }
    }

    private function preserveContractualAccessAfterProviderCancellation(
        ClubSubscription|UserSubscription $subscription,
        array $updates,
        string $providerStatus,
    ): array {
        if ($providerStatus !== 'cancelled'
            || $subscription->status !== 'cancels_at_period_end'
            || ! $subscription->cancels_at
            || ! $subscription->cancels_at->isFuture()) {
            return $updates;
        }

        return array_merge($updates, [
            'status' => 'cancels_at_period_end',
            'cancel_at_period_end' => true,
            'cancels_at' => $subscription->cancels_at,
            'cancelled_at' => $subscription->cancelled_at ?: now(),
            'next_invoice_at' => null,
        ]);
    }

    private function markRecurringInvoicePaid(string $subscriptionType, int $subscriptionId, string $provider, array $payload): void
    {
        $providerInvoiceId = data_get($payload, 'data.object.id')
            ?: data_get($payload, 'resource.id')
            ?: null;

        $invoice = SubscriptionInvoice::query()
            ->where('subscription_type', $subscriptionType)
            ->where('subscription_id', $subscriptionId)
            ->whereIn('status', ['open', 'awaiting_transfer', 'overdue'])
            ->latest('billing_period_start')
            ->first();

        if (! $invoice) {
            return;
        }

        $invoice->forceFill([
            'status' => 'paid',
            'paid_at' => now(),
            'payment_method' => $provider,
            'payment_reference' => $providerInvoiceId ?: $invoice->payment_reference,
            'meta' => array_merge($invoice->meta ?? [], [
                'provider_invoice_id' => $providerInvoiceId,
                'paid_from_provider_webhook' => true,
            ]),
        ])->save();

        AppNotification::sendLocalized(
            $invoice->user_id,
            'subscription.invoice.paid',
            'subscription.notifications.paid_title',
            'subscription.notifications.paid_body',
            ['invoice' => $invoice->number],
            ['subscription_invoice_id' => $invoice->id],
            ['dedupe_key' => 'subscription-invoice:'.$invoice->id.':paid'],
        );

        $invoice->loadMissing('user');

        if (! $invoice->payment_confirmation_email_sent_at && filled($invoice->user?->email)) {
            $invoice->user->notify(new SubscriptionInvoicePaid($invoice));
            $invoice->forceFill(['payment_confirmation_email_sent_at' => now()])->save();
        }
    }

    private function paypalAccessToken(): string
    {
        if (blank(config('services.paypal.client_id')) || blank(config('services.paypal.client_secret'))) {
            $this->checkoutError(__('subscription.validation.provider_not_configured', ['provider' => 'PayPal']));
        }

        $response = Http::asForm()
            ->withBasicAuth(config('services.paypal.client_id'), config('services.paypal.client_secret'))
            ->post($this->paypalBaseUrl().'/v1/oauth2/token', [
                'grant_type' => 'client_credentials',
            ]);

        if ($response->failed()) {
            $this->checkoutError(__('subscription.validation.paypal_token_failed'));
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

    private function sendAwaitingTransferEmail(PaymentCheckout $checkout): void
    {
        $invoice = $checkout->invoice;

        if (! $invoice || $invoice->invoice_email_sent_at || ! $checkout->user?->email) {
            return;
        }

        $locale = SupportedLocale::normalize($checkout->user->language) ?? SupportedLocale::DEFAULT;
        $checkout->user->notify((new SubscriptionInvoiceAwaitingTransfer($invoice))->locale($locale));

        $invoice->forceFill(['invoice_email_sent_at' => now()])->save();
    }

    private function createSubscriptionInvoice(PaymentCheckout $checkout): SubscriptionInvoice
    {
        $checkout->loadMissing(['plan', 'user:id,language']);
        $locale = SupportedLocale::normalize($checkout->user?->language) ?? SupportedLocale::DEFAULT;
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
                'subscription_type' => $checkout->club_id ? 'club' : 'user',
                'number' => $this->nextInvoiceNumber(),
                'title' => Lang::get('subscription.invoice.title', ['plan' => $checkout->plan->name], $locale),
                'description' => Lang::get(
                    $checkout->billing_interval === 'yearly'
                        ? 'subscription.invoice.description_yearly'
                        : 'subscription.invoice.description_monthly',
                    ['plan' => $checkout->plan->name],
                    $locale,
                ),
                'amount_cents' => $checkout->amount_cents,
                'currency' => $checkout->currency,
                'status' => $checkout->provider === 'bank_transfer' ? 'awaiting_transfer' : 'open',
                'payment_method' => $checkout->provider,
                'payment_reference' => $checkout->payment_reference,
                'billing_period_start' => $periodStart,
                'billing_period_end' => $periodEnd,
                'issued_at' => now(),
                'due_at' => $checkout->due_at ?: now()->addDays((int) Setting::valueFor('billing_payment_terms_days', 14)),
                'meta' => [
                    'checkout_id' => $checkout->id,
                    'billing_interval' => $checkout->billing_interval,
                    'original_amount_cents' => $checkout->original_amount_cents,
                    'discount_cents' => $checkout->discount_cents,
                    'coupon_code' => $checkout->coupon?->code,
                    'pricing_country' => $checkout->payload['pricing_country'] ?? null,
                    'pricing_country_source' => $checkout->payload['pricing_country_source'] ?? null,
                    'localized_price' => $checkout->payload['localized_price'] ?? false,
                    'bank_transfer' => $checkout->provider === 'bank_transfer' ? $checkout->payload : null,
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
            $this->checkoutError(__('subscription.validation.coupon_invalid'));
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
        return app(PaymentWebhookVerifier::class)->isValidStripeSignature($payload, $signature);
    }

    private function isValidPayPalWebhook(Request $request): bool
    {
        return app(PaymentWebhookVerifier::class)->isValidPayPalWebhook($request, config('services.paypal.webhook_id'));
    }
}
