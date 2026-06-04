<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ClubSubscriptionResource;
use App\Http\Resources\Api\V1\PaymentCheckoutResource;
use App\Http\Resources\Api\V1\SubscriptionInvoiceResource;
use App\Http\Resources\Api\V1\SubscriptionPlanResource;
use App\Http\Resources\Api\V1\UserSubscriptionResource;
use App\Models\Club;
use App\Models\ClubSubscription;
use App\Models\PaymentCheckout;
use App\Models\Setting;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPlan;
use App\Models\UserSubscription;
use App\Services\UserSubscriptionActivationService;
use App\Support\AppNotification;
use App\Support\ClubRoles;
use App\Support\Roles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SubscriptionController extends Controller
{
    public function plans(Request $request)
    {
        $plans = SubscriptionPlan::query()
            ->with('countryPrices')
            ->where('is_active', true)
            ->where('is_public', true)
            ->when($request->filled('target_actor'), fn ($query) => $query->where('target_actor', $request->string('target_actor')))
            ->orderBy('target_actor')
            ->orderBy('sort_order')
            ->orderBy('monthly_price_cents')
            ->get();

        return SubscriptionPlanResource::collection($plans);
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $clubIds = Club::query()
            ->where('owner_id', $user->id)
            ->orWhereHas('users', function ($query) use ($user) {
                $query->where('users.id', $user->id);
                ClubRoles::whereAny($query, ClubRoles::ELEVATED);
            })
            ->pluck('id');

        return response()->json([
            'data' => [
                'user_subscriptions' => UserSubscriptionResource::collection(
                    $user->subscriptions()->with('plan.countryPrices')->latest('id')->get()
                )->resolve($request),
                'club_subscriptions' => ClubSubscriptionResource::collection(
                    ClubSubscription::query()
                        ->with(['club', 'plan.countryPrices'])
                        ->whereIn('club_id', $clubIds)
                        ->latest('id')
                        ->get()
                )->resolve($request),
                'checkouts' => PaymentCheckoutResource::collection(
                    PaymentCheckout::query()
                        ->with(['plan.countryPrices', 'club', 'invoice'])
                        ->where('user_id', $user->id)
                        ->latest('id')
                        ->limit(20)
                        ->get()
                )->resolve($request),
                'invoices' => SubscriptionInvoiceResource::collection(
                    SubscriptionInvoice::query()
                        ->with(['club', 'plan'])
                        ->where('user_id', $user->id)
                        ->latest('id')
                        ->limit(20)
                        ->get()
                )->resolve($request),
            ],
        ]);
    }

    public function startCheckout(Request $request, SubscriptionPlan $subscriptionPlan)
    {
        abort_unless($subscriptionPlan->is_active && $subscriptionPlan->is_public, 404);

        $data = $request->validate([
            'provider' => ['required', Rule::in(['bank_transfer', 'stripe', 'paypal'])],
            'billing_interval' => ['required', Rule::in(['monthly', 'yearly'])],
            'club_id' => ['nullable', Rule::exists('clubs', 'id')],
            'accepted_terms' => ['accepted'],
        ], [
            'provider.in' => 'Dieser Zahlungsanbieter wird mobil nicht unterstützt.',
            'accepted_terms.accepted' => 'Bitte bestätige AGB und Widerrufshinweise, bevor du das Abo kostenpflichtig bestellst.',
        ]);

        $subscriptionPlan->loadMissing('countryPrices');
        $club = $this->resolveClub($request, $subscriptionPlan, $data['club_id'] ?? null);
        $country = strtoupper((string) ($club?->country ?: $request->user()->country));
        $price = $subscriptionPlan->priceForCountry($country);

        if (! $price['available']) {
            throw ValidationException::withMessages([
                'checkout' => 'Dieser Abo-Plan ist in deinem Land aktuell nicht verfügbar.',
            ]);
        }

        $amountCents = $data['billing_interval'] === 'yearly'
            ? (int) $price['yearly_price_cents']
            : (int) $price['monthly_price_cents'];

        if ($amountCents <= 0) {
            throw ValidationException::withMessages([
                'checkout' => 'Kostenlose Pläne brauchen keinen Checkout.',
            ]);
        }

        if ($data['provider'] === 'bank_transfer' && blank($this->bankTransferSettings()['iban'])) {
            $this->checkoutError('Bankverbindung für Überweisung ist noch nicht konfiguriert.');
        }

        $this->ensureProviderIsConfigured($data['provider']);

        $checkout = DB::transaction(function () use ($request, $subscriptionPlan, $club, $data, $price, $amountCents) {
            $checkout = PaymentCheckout::query()->create([
                'user_id' => $request->user()->id,
                'club_id' => $club?->id,
                'subscription_plan_id' => $subscriptionPlan->id,
                'provider' => $data['provider'],
                'billing_interval' => $data['billing_interval'],
                'original_amount_cents' => $amountCents,
                'discount_cents' => 0,
                'amount_cents' => $amountCents,
                'currency' => $price['currency'] ?: 'EUR',
                'status' => 'pending',
                'payload' => [
                    'pricing_country' => $price['country_code'] ?? null,
                    'pricing_country_source' => $club ? 'club' : 'user',
                    'localized_price' => (bool) ($price['localized'] ?? false),
                    'base_currency' => $subscriptionPlan->currency,
                    'base_monthly_price_cents' => $subscriptionPlan->monthly_price_cents,
                    'base_yearly_price_cents' => $subscriptionPlan->yearly_price_cents,
                ],
            ]);

            if ($checkout->provider === 'bank_transfer') {
                $this->prepareBankTransferCheckout($checkout);
            }

            $this->createSubscriptionInvoice($checkout->fresh(['plan']));

            return $checkout->fresh(['plan.countryPrices', 'club', 'invoice']);
        });

        if ($checkout->provider === 'stripe') {
            $this->createStripeCheckout($checkout);
        }

        if ($checkout->provider === 'paypal') {
            $this->createPayPalCheckout($checkout);
        }

        if ($checkout->provider === 'bank_transfer') {
            AppNotification::send($checkout->user_id, 'subscription.invoice.awaiting_transfer', [
                'title' => 'Airmius Rechnung wartet auf Überweisung',
                'body' => $checkout->invoice?->number.' - '.$checkout->payment_reference,
                'subscription_invoice_id' => $checkout->invoice?->id,
            ]);
        }

        return (new PaymentCheckoutResource($checkout->fresh(['plan.countryPrices', 'club', 'invoice'])))
            ->response()
            ->setStatusCode(201);
    }

    public function checkout(Request $request, PaymentCheckout $checkout)
    {
        abort_unless($checkout->user_id === $request->user()->id || $this->canAdminSubscriptions($request), 404);

        return new PaymentCheckoutResource($checkout->loadMissing(['plan.countryPrices', 'club', 'invoice']));
    }

    public function cancelCheckout(Request $request, PaymentCheckout $checkout)
    {
        abort_unless($checkout->user_id === $request->user()->id, 404);
        abort_unless(in_array($checkout->status, ['pending', 'awaiting_transfer'], true), 422);

        $checkout->update([
            'status' => 'cancelled',
            'payload' => array_merge($checkout->payload ?? [], [
                'cancelled_by' => $request->user()->id,
                'cancelled_at' => now()->toISOString(),
            ]),
        ]);

        $checkout->invoice?->update([
            'status' => 'cancelled',
            'meta' => array_merge($checkout->invoice->meta ?? [], [
                'cancelled_by' => $request->user()->id,
                'cancelled_at' => now()->toISOString(),
            ]),
        ]);

        return new PaymentCheckoutResource($checkout->fresh(['plan.countryPrices', 'club', 'invoice']));
    }

    public function markCheckoutPaid(Request $request, PaymentCheckout $checkout)
    {
        abort_unless($this->canAdminSubscriptions($request), 403);
        abort_unless($checkout->provider === 'bank_transfer', 404);

        $checkout->update([
            'payload' => array_merge($checkout->payload ?? [], [
                'marked_paid_by' => $request->user()->id,
                'marked_paid_at' => now()->toISOString(),
            ]),
        ]);

        $this->activateCheckout($checkout->fresh(['user', 'club', 'invoice', 'plan']));

        return new PaymentCheckoutResource($checkout->fresh(['plan.countryPrices', 'club', 'invoice']));
    }

    public function cancelUserSubscription(Request $request, UserSubscription $subscription)
    {
        abort_unless($subscription->user_id === $request->user()->id, 404);

        $data = $request->validate([
            'mode' => ['nullable', Rule::in(['period_end', 'now'])],
        ]);

        $this->cancelSubscription($subscription, $data['mode'] ?? 'period_end');

        return new UserSubscriptionResource($subscription->fresh('plan.countryPrices'));
    }

    public function renewUserSubscription(Request $request, UserSubscription $subscription)
    {
        abort_unless($subscription->user_id === $request->user()->id, 404);

        $data = $request->validate([
            'months' => ['required', 'integer', 'min:1', 'max:36'],
        ]);

        $this->renewSubscription($subscription, (int) $data['months']);

        return new UserSubscriptionResource($subscription->fresh('plan.countryPrices'));
    }

    public function cancelClubSubscription(Request $request, Club $club, ClubSubscription $subscription)
    {
        $this->authorizeClubManager($request, $club);
        abort_unless($subscription->club_id === $club->id, 404);

        $data = $request->validate([
            'mode' => ['nullable', Rule::in(['period_end', 'now'])],
        ]);

        $this->cancelSubscription($subscription, $data['mode'] ?? 'period_end');

        return new ClubSubscriptionResource($subscription->fresh(['club', 'plan.countryPrices']));
    }

    public function renewClubSubscription(Request $request, Club $club, ClubSubscription $subscription)
    {
        $this->authorizeClubManager($request, $club);
        abort_unless($subscription->club_id === $club->id, 404);

        $data = $request->validate([
            'months' => ['required', 'integer', 'min:1', 'max:36'],
        ]);

        $this->renewSubscription($subscription, (int) $data['months']);

        return new ClubSubscriptionResource($subscription->fresh(['club', 'plan.countryPrices']));
    }

    private function resolveClub(Request $request, SubscriptionPlan $plan, ?int $clubId): ?Club
    {
        if (($plan->target_actor ?? 'verein') !== 'verein') {
            return null;
        }

        $clubQuery = Club::query()
            ->where(function ($query) use ($request) {
                $query->where('owner_id', $request->user()->id)
                    ->orWhereHas('users', function ($memberQuery) use ($request) {
                        $memberQuery->where('users.id', $request->user()->id);
                        ClubRoles::whereAny($memberQuery, ClubRoles::ELEVATED);
                    });
            });

        $club = $clubId
            ? (clone $clubQuery)->whereKey($clubId)->first()
            : $clubQuery->oldest('id')->first();

        if (! $club) {
            throw ValidationException::withMessages([
                'club_id' => 'Bitte erst einen Verein erstellen oder auswählen.',
            ]);
        }

        return $club;
    }

    private function authorizeClubManager(Request $request, Club $club): void
    {
        $user = $request->user();

        if ($club->owner_id === $user->id || $user->hasAnyRole(Roles::FULL_ACCESS)) {
            return;
        }

        $isManager = $club->users()
            ->where('users.id', $user->id)
            ->where(function ($query) {
                ClubRoles::whereAny($query, ClubRoles::ELEVATED);
            })
            ->exists();

        abort_unless($isManager, 403);
    }

    private function cancelSubscription(ClubSubscription|UserSubscription $subscription, string $mode): void
    {
        if ($mode === 'now') {
            $subscription->update([
                'status' => 'cancelled',
                'cancel_at_period_end' => false,
                'cancels_at' => now(),
                'cancelled_at' => now(),
                'current_period_ends_at' => now(),
            ]);

            return;
        }

        $subscription->loadMissing('plan');
        $endsAt = $subscription->current_period_ends_at ?: now();
        $noticeEnd = now()->addDays((int) ($subscription->plan?->cancellation_notice_days ?? 0));
        $minimumTermEnd = $subscription->created_at
            ? $subscription->created_at->copy()->addMonths((int) ($subscription->plan?->minimum_term_months ?? 0))
            : now();
        $cancelsAt = collect([$endsAt, $noticeEnd, $minimumTermEnd])
            ->reduce(fn ($latest, $date) => $date->greaterThan($latest) ? $date : $latest, now());

        $subscription->update([
            'status' => 'cancels_at_period_end',
            'cancel_at_period_end' => true,
            'cancels_at' => $cancelsAt,
            'cancelled_at' => now(),
        ]);
    }

    private function renewSubscription(ClubSubscription|UserSubscription $subscription, int $months): void
    {
        $baseDate = $subscription->current_period_ends_at && $subscription->current_period_ends_at->isFuture()
            ? $subscription->current_period_ends_at
            : now();

        $subscription->update([
            'status' => 'active',
            'cancel_at_period_end' => false,
            'cancels_at' => null,
            'cancelled_at' => null,
            'current_period_ends_at' => $baseDate->copy()->addMonths($months),
            'next_invoice_at' => $baseDate->copy()->addMonths($months),
            'last_renewed_at' => now(),
            'renewal_notified_at' => null,
            'renewal_email_sent_at' => null,
            'payment_issue_email_sent_at' => null,
        ]);
    }

    private function ensureProviderIsConfigured(string $provider): void
    {
        if ($provider === 'stripe' && blank(config('services.stripe.secret'))) {
            $this->checkoutError('Stripe ist noch nicht konfiguriert.');
        }

        if ($provider === 'paypal' && (blank(config('services.paypal.client_id')) || blank(config('services.paypal.client_secret')))) {
            $this->checkoutError('PayPal ist noch nicht konfiguriert.');
        }
    }

    private function prepareBankTransferCheckout(PaymentCheckout $checkout): void
    {
        $bank = $this->bankTransferSettings();

        if (blank($bank['iban'])) {
            $this->checkoutError('Bankverbindung für Überweisung ist noch nicht konfiguriert.');
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

    private function createStripeCheckout(PaymentCheckout $checkout): void
    {
        $secret = config('services.stripe.secret');

        if (blank($secret)) {
            $this->checkoutError('Stripe ist noch nicht konfiguriert.');
        }

        $checkout->loadMissing(['plan', 'user']);

        $response = Http::asForm()
            ->withToken($secret)
            ->post('https://api.stripe.com/v1/checkout/sessions', [
                'mode' => 'subscription',
                'success_url' => route('subscription-checkout.success', $checkout).'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('subscription-checkout.cancel', $checkout),
                'client_reference_id' => (string) $checkout->id,
                'customer_email' => $checkout->user?->email,
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
            Log::warning('Mobile Stripe checkout failed', [
                'checkout_id' => $checkout->id,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            $this->checkoutError('Stripe Checkout konnte nicht gestartet werden.');
        }

        $payload = $response->json();
        $checkout->update([
            'provider_checkout_id' => $payload['id'] ?? null,
            'checkout_url' => $payload['url'] ?? null,
            'payload' => array_merge($checkout->payload ?? [], [
                'stripe' => $payload,
                'provider_return_url' => route('subscription-checkout.success', $checkout),
                'provider_cancel_url' => route('subscription-checkout.cancel', $checkout),
            ]),
        ]);
    }

    private function createPayPalCheckout(PaymentCheckout $checkout): void
    {
        $checkout->loadMissing(['plan', 'user']);
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
                'cancel_url' => route('subscription-checkout.cancel', $checkout),
            ],
        ]);

        if ($response->failed()) {
            Log::warning('Mobile PayPal checkout failed', [
                'checkout_id' => $checkout->id,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            $this->checkoutError('PayPal Checkout konnte nicht gestartet werden.');
        }

        $payload = $response->json();
        $approveLink = collect($payload['links'] ?? [])->firstWhere('rel', 'approve');

        if (! $approveLink || blank($approveLink['href'] ?? null)) {
            Log::warning('Mobile PayPal approval link missing', [
                'checkout_id' => $checkout->id,
                'payload' => $payload,
            ]);

            $this->checkoutError('PayPal Genehmigungslink fehlt.');
        }

        $checkout->update([
            'provider_checkout_id' => $payload['id'] ?? null,
            'provider_subscription_id' => $payload['id'] ?? null,
            'checkout_url' => $approveLink['href'],
            'payload' => array_merge($checkout->payload ?? [], [
                'paypal' => $payload,
                'provider_return_url' => route('subscription-checkout.success', $checkout),
                'provider_cancel_url' => route('subscription-checkout.cancel', $checkout),
            ]),
        ]);
    }

    private function ensurePayPalPlan(PaymentCheckout $checkout): string
    {
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
            'description' => Str::limit($checkout->plan->description ?: 'Airmius Abo', 120),
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
            Log::warning('Mobile PayPal subscription plan failed', [
                'checkout_id' => $checkout->id,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            $this->checkoutError('PayPal Abo-Plan konnte nicht erstellt werden.');
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
            'description' => Str::limit($plan->description ?: 'Airmius Abo', 120),
            'type' => 'SERVICE',
        ]);

        if ($response->failed()) {
            Log::warning('Mobile PayPal subscription product failed', [
                'plan_id' => $plan->id,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            $this->checkoutError('PayPal Produkt konnte nicht erstellt werden.');
        }

        $payload = $response->json();
        $plan->forceFill([
            'paypal_product_id' => $payload['id'] ?? null,
            'paypal_payload' => array_merge($plan->paypal_payload ?? [], ['product' => $payload]),
        ])->save();

        return $plan->paypal_product_id;
    }

    private function paypalAccessToken(): string
    {
        $this->ensureProviderIsConfigured('paypal');

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

    private function activateCheckout(PaymentCheckout $checkout): void
    {
        if ($checkout->status === 'completed') {
            return;
        }

        DB::transaction(function () use ($checkout) {
            if ($checkout->coupon) {
                $checkout->coupon->increment('redeemed_count');
            }

            $periodEndsAt = $checkout->billing_interval === 'yearly'
                ? now()->addYear()
                : now()->addMonth();

            if ($checkout->club_id) {
                $subscription = $checkout->club->currentSubscription()->updateOrCreate(
                    ['club_id' => $checkout->club_id],
                    [
                        'subscription_plan_id' => $checkout->subscription_plan_id,
                        'status' => 'active',
                        'payment_provider' => $checkout->provider,
                        'billing_interval' => $checkout->billing_interval,
                        'provider_subscription_id' => $checkout->provider_subscription_id,
                        'provider_customer_id' => $checkout->provider_customer_id,
                        'trial_ends_at' => null,
                        'current_period_ends_at' => $periodEndsAt,
                        'next_invoice_at' => $periodEndsAt,
                        'grace_period_ends_at' => null,
                        'access_restricted_at' => null,
                        'cancel_at_period_end' => false,
                        'cancels_at' => null,
                        'cancelled_at' => null,
                    ],
                );
            } else {
                $subscription = $checkout->user->subscriptions()->updateOrCreate(
                    ['subscription_plan_id' => $checkout->subscription_plan_id],
                    [
                        'status' => 'active',
                        'payment_provider' => $checkout->provider,
                        'billing_interval' => $checkout->billing_interval,
                        'provider_subscription_id' => $checkout->provider_subscription_id,
                        'provider_customer_id' => $checkout->provider_customer_id,
                        'trial_ends_at' => null,
                        'current_period_ends_at' => $periodEndsAt,
                        'next_invoice_at' => $periodEndsAt,
                        'grace_period_ends_at' => null,
                        'access_restricted_at' => null,
                        'cancel_at_period_end' => false,
                        'cancels_at' => null,
                        'cancelled_at' => null,
                    ],
                );

                app(UserSubscriptionActivationService::class)->retireOtherUserSubscriptions($subscription->fresh('plan'));
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
                'subscription_type' => $checkout->club_id ? 'club' : 'user',
                'subscription_id' => $subscription->id,
                'meta' => array_merge($checkout->invoice->meta ?? [], [
                    'provider_checkout_id' => $checkout->provider_checkout_id,
                    'provider_subscription_id' => $checkout->provider_subscription_id,
                    'provider_customer_id' => $checkout->provider_customer_id,
                ]),
            ]);
        });

        AppNotification::send($checkout->user_id, 'subscription.invoice.paid', [
            'title' => 'Airmius Rechnung bezahlt',
            'body' => ($checkout->invoice?->number ?: 'Abo-Rechnung').' wurde als bezahlt markiert.',
            'subscription_invoice_id' => $checkout->invoice?->id,
        ]);
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
                'subscription_type' => $checkout->club_id ? 'club' : 'user',
                'number' => $this->nextInvoiceNumber(),
                'title' => 'Airmius '.$checkout->plan->name,
                'description' => 'Airmius Abo '.$checkout->plan->name.' ('.($checkout->billing_interval === 'yearly' ? 'Jahreszahlung' : 'Monatszahlung').')',
                'amount_cents' => $checkout->amount_cents,
                'currency' => $checkout->currency,
                'status' => $checkout->provider === 'bank_transfer' ? 'awaiting_transfer' : 'open',
                'payment_method' => $checkout->provider,
                'payment_reference' => $checkout->payment_reference,
                'billing_period_start' => $periodStart,
                'billing_period_end' => $periodEnd,
                'issued_at' => now(),
                'due_at' => $checkout->due_at,
                'meta' => [
                    'checkout_id' => $checkout->id,
                    'billing_interval' => $checkout->billing_interval,
                    'original_amount_cents' => $checkout->original_amount_cents,
                    'discount_cents' => $checkout->discount_cents,
                    'pricing_country' => $checkout->payload['pricing_country'] ?? null,
                    'pricing_country_source' => $checkout->payload['pricing_country_source'] ?? null,
                    'localized_price' => $checkout->payload['localized_price'] ?? false,
                    'bank_transfer' => $checkout->provider === 'bank_transfer' ? $checkout->payload : null,
                ],
            ],
        );
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

    private function nextInvoiceNumber(): string
    {
        $prefix = 'AR-'.now()->format('Y').'-';
        $next = SubscriptionInvoice::query()
            ->where('number', 'like', $prefix.'%')
            ->count() + 1;

        return $prefix.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    private function checkoutError(string $message): never
    {
        throw ValidationException::withMessages([
            'checkout' => $message,
        ]);
    }

    private function canAdminSubscriptions(Request $request): bool
    {
        $user = $request->user();

        return $user->can('subscriptions.manage')
            || $user->can('system.manage')
            || $user->hasAnyRole(Roles::FULL_ACCESS);
    }
}

