<?php

namespace App\Http\Controllers;

use App\Models\OutfitStyleProfile;
use App\Models\OutfitDelivery;
use App\Models\OutfitSubscription;
use App\Models\OutfitSubscriptionPlan;
use App\Models\Setting;
use App\Models\Sport;
use App\Models\User;
use App\Services\OutfitInvoiceService;
use App\Support\AppNotification;
use App\Support\Roles;
use App\Support\UploadStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class OutfitSubscriptionController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Auth/Dashboard/OutfitSubscriptions/Index', [
            'plans' => OutfitSubscriptionPlan::query()
                ->with('sponsor:id,name,logo,logo_light,logo_dark,website')
                ->where('is_active', true)
                ->where('is_public', true)
                ->orderBy('sort_order')
                ->orderBy('monthly_price_cents')
                ->get()
                ->map(fn (OutfitSubscriptionPlan $plan) => $this->planPayload($plan)),
            'styleProfile' => $request->user()
                ->outfitStyleProfile()
                ->first(),
            'subscriptions' => $request->user()
                ->outfitSubscriptions()
                ->with(['plan.sponsor:id,name,logo,logo_light,logo_dark,website', 'sponsor:id,name,logo,logo_light,logo_dark,website', 'deliveries' => fn ($query) => $query->latest()->limit(6)])
                ->latest()
                ->get()
                ->map(fn (OutfitSubscription $subscription) => $this->subscriptionPayload($subscription)),
            'heroImageUrl' => UploadStorage::url(Setting::valueFor('outfit_subscription_hero_image', '/images/marketplace/airmius_outfit_abo.webp')),
            'sports' => Sport::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'category']),
            'contractRules' => $this->contractRules(null),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $data = $request->validate([
            'sport_focus' => ['nullable', 'string', 'max:120'],
            'sizes' => ['nullable', 'array'],
            'sizes.*' => ['nullable', 'string', 'max:20'],
            'fit_preference' => ['nullable', 'string', 'max:60'],
            'colors' => ['nullable', 'array'],
            'colors.*' => ['nullable', 'string', 'max:40'],
            'excluded_colors' => ['nullable', 'array'],
            'excluded_colors.*' => ['nullable', 'string', 'max:40'],
            'brand_style' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        OutfitStyleProfile::query()->updateOrCreate(
            ['user_id' => $request->user()->id],
            $data,
        );

        return back()->with('success', 'Style-Profil gespeichert.');
    }

    public function store(Request $request, OutfitSubscriptionPlan $plan)
    {
        abort_unless($plan->is_active && $plan->is_public, 404);

        $data = $request->validate([
            'accepted_terms' => ['accepted'],
            'accepted_contract' => ['accepted'],
            'payment_provider' => ['required', Rule::in(['bank_transfer', 'paypal'])],
            'shipping_name' => ['nullable', 'string', 'max:255'],
            'shipping_country' => ['nullable', 'string', 'size:2'],
            'shipping_street' => ['nullable', 'string', 'max:255'],
            'shipping_house_number' => ['nullable', 'string', 'max:40'],
            'shipping_postal_code' => ['nullable', 'string', 'max:30'],
            'shipping_city' => ['nullable', 'string', 'max:255'],
            'shipping_state' => ['nullable', 'string', 'max:255'],
            'shipping_note' => ['nullable', 'string', 'max:1000'],
        ], [
            'accepted_terms.accepted' => 'Bitte bestaetige AGB und Widerrufshinweise, bevor du das Outfit-Abo anfragst.',
            'accepted_contract.accepted' => 'Bitte bestaetige den Outfit-Abo-Vertrag, bevor du das Outfit-Abo anfragst.',
        ]);

        $existingSubscription = $request->user()
            ->outfitSubscriptions()
            ->where('outfit_subscription_plan_id', $plan->id)
            ->whereNotIn('status', ['cancelled'])
            ->first();

        if ($existingSubscription) {
            return back()->with('error', 'Du hast diesen Outfit-Abo-Plan bereits angefragt oder aktiviert.');
        }

        $paymentProvider = $data['payment_provider'] ?? 'bank_transfer';
        $bankTransfer = $this->bankTransferSettings();
        $contractSnapshot = $this->contractSnapshot($plan, $request->user());
        $shippingAddress = $this->shippingAddressFor($request, $data);
        $missingAddressFields = collect([
            'shipping_name' => 'Name',
            'shipping_country' => 'Land',
            'shipping_street' => 'Strasse',
            'shipping_postal_code' => 'Postleitzahl',
            'shipping_city' => 'Stadt',
        ])->filter(fn ($label, $field) => blank($shippingAddress[$field] ?? null));

        if ($missingAddressFields->isNotEmpty()) {
            return back()
                ->withErrors(['shipping_address' => 'Bitte vervollstaendige deine Lieferadresse: '.$missingAddressFields->implode(', ').'.'])
                ->withInput();
        }

        if ($paymentProvider === 'bank_transfer' && blank($bankTransfer['iban'])) {
            return back()->with('error', 'Bankverbindung für Ueberweisung ist noch nicht konfiguriert.');
        }

        $subscription = OutfitSubscription::query()->create([
            'user_id' => $request->user()->id,
            'outfit_subscription_plan_id' => $plan->id,
            'sponsor_id' => $plan->sponsor_id,
            'status' => 'pending_payment',
            'payment_provider' => $paymentProvider,
            'payment_status' => 'pending',
            'monthly_price_cents' => $plan->effectiveMonthlyPriceCents(),
            'sponsor_discount_cents' => $plan->sponsor_discount_cents,
            'currency' => $plan->currency,
            ...$shippingAddress,
            'next_delivery_at' => null,
            'current_period_ends_at' => null,
            'accepted_terms_at' => now(),
            'accepted_contract_at' => now(),
            'contract_version' => $contractSnapshot['version'],
            'contract_snapshot' => $contractSnapshot,
            'accepted_ip' => $request->ip(),
            'accepted_user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
        ]);

        if ($paymentProvider === 'bank_transfer') {
            $subscription->forceFill([
                'payment_reference' => $this->paymentReference($subscription),
                'payment_due_at' => now()->addDays((int) $bankTransfer['payment_terms_days'])->endOfDay(),
                'payment_payload' => ['bank_transfer' => $bankTransfer],
            ])->save();
        }

        if ($paymentProvider === 'paypal') {
            $checkoutUrl = $this->createPayPalSubscription($subscription);
            $subscription->forceFill(['checkout_url' => $checkoutUrl])->save();

            $this->notifyOutfitSubscriptionRequested($request, $plan, $subscription);

            return Inertia::location($checkoutUrl);
        }

        AppNotification::send($request->user(), 'outfit.subscription.pending_payment', [
            'title' => 'Outfit-Abo wartet auf Zahlung',
            'message' => "Dein Outfit-Abo {$plan->name} wurde vorgemerkt. Es wird erst nach bestaetigter Zahlung aktiviert.",
            'plan' => $plan->name,
            'amount_cents' => $subscription->monthly_price_cents,
            'currency' => $subscription->currency,
            'payment_provider' => $subscription->payment_provider,
            'payment_reference' => $subscription->payment_reference,
            'url' => route('auth.outfit-subscriptions.index'),
        ]);

        $this->notifyOutfitSubscriptionRequested($request, $plan, $subscription);

        return back()->with('success', 'Outfit-Abo wurde angefragt. Es wird erst nach Zahlung aktiviert.');
    }

    public function success(Request $request, OutfitSubscription $subscription)
    {
        $this->authorizeSubscription($request, $subscription);

        if ($subscription->payment_provider === 'paypal' && $subscription->payment_status === 'pending') {
            $this->syncPayPalSubscription($subscription, $request->query('subscription_id'));
        }

        return redirect()
            ->route('auth.outfit-subscriptions.index')
            ->with('success', 'PayPal-Zahlung wurde verarbeitet. Dein Outfit-Abo wird aktiviert, sobald PayPal die Zahlung bestaetigt.');
    }

    public function cancelCheckout(Request $request, OutfitSubscription $subscription)
    {
        $this->authorizeSubscription($request, $subscription);

        if ($subscription->payment_status === 'pending') {
            $subscription->forceFill([
                'status' => 'cancelled',
                'payment_status' => 'cancelled',
                'cancelled_at' => now(),
            ])->save();
        }

        return redirect()
            ->route('auth.outfit-subscriptions.index')
            ->with('success', 'PayPal-Zahlung wurde abgebrochen.');
    }

    public function paypalWebhook(Request $request)
    {
        if (! $this->isValidPayPalWebhook($request)) {
            return response('Invalid signature', 400);
        }

        $event = $request->all();
        $eventType = $event['event_type'] ?? null;
        $resource = $event['resource'] ?? [];
        $subscriptionId = $resource['billing_agreement_id']
            ?? $resource['subscription_id']
            ?? $resource['id']
            ?? null;

        if (in_array($eventType, ['BILLING.SUBSCRIPTION.ACTIVATED', 'PAYMENT.SALE.COMPLETED'], true)) {
            $subscription = OutfitSubscription::query()
                ->where('payment_provider', 'paypal')
                ->where('provider_subscription_id', $subscriptionId)
                ->first();

            if ($subscription) {
                $subscription->forceFill([
                    'payment_payload' => array_merge($subscription->payment_payload ?? [], ['paypal_webhook' => $event]),
                ])->save();

                $this->activatePaidSubscription($subscription);
            }
        }

        if (in_array($eventType, ['BILLING.SUBSCRIPTION.CANCELLED', 'BILLING.SUBSCRIPTION.SUSPENDED', 'BILLING.SUBSCRIPTION.EXPIRED'], true)) {
            $subscription = OutfitSubscription::query()
                ->where('payment_provider', 'paypal')
                ->where('provider_subscription_id', $subscriptionId)
                ->first();

            if ($subscription) {
                $isSuspended = $eventType === 'BILLING.SUBSCRIPTION.SUSPENDED';
                $paymentStatus = $subscription->payment_status;

                if (! $isSuspended && in_array($subscription->payment_status, ['pending', 'failed'], true)) {
                    $paymentStatus = 'cancelled';
                }

                $subscription->forceFill([
                    'status' => $isSuspended ? 'paused' : 'cancelled',
                    'payment_status' => $paymentStatus,
                    'cancelled_at' => $isSuspended ? $subscription->cancelled_at : now(),
                    'next_delivery_at' => $isSuspended ? $subscription->next_delivery_at : null,
                    'payment_payload' => array_merge($subscription->payment_payload ?? [], ['paypal_webhook' => $event]),
                ])->save();
            }
        }

        return response('ok');
    }

    public function pause(Request $request, OutfitSubscription $subscription)
    {
        $this->authorizeSubscription($request, $subscription);

        $pauseAllowedAt = $this->pauseAllowedAt($subscription);

        if ($pauseAllowedAt && $pauseAllowedAt->isFuture()) {
            return back()->with('error', 'Dieses Outfit-Abo kann erst ab dem '.$pauseAllowedAt->format('d.m.Y').' pausiert werden.');
        }

        if ($subscription->payment_provider === 'paypal' && $subscription->provider_subscription_id) {
            $this->suspendPayPalSubscription($subscription);
        }

        $subscription->update(['status' => 'paused']);

        return back()->with('success', 'Abo wurde pausiert.');
    }

    public function resume(Request $request, OutfitSubscription $subscription)
    {
        $this->authorizeSubscription($request, $subscription);

        if ($subscription->payment_provider === 'paypal' && $subscription->provider_subscription_id) {
            $this->activatePayPalSubscription($subscription);
        }

        $subscription->update([
            'status' => 'active',
            'next_delivery_at' => $subscription->next_delivery_at ?: now()->addMonth()->startOfDay(),
        ]);

        return back()->with('success', 'Abo wurde fortgesetzt.');
    }

    public function cancel(Request $request, OutfitSubscription $subscription)
    {
        $this->authorizeSubscription($request, $subscription);

        $wasPending = $subscription->status === 'pending_payment';
        $minimumTermEndsAt = $this->minimumTermEndsAt($subscription);

        if (! $wasPending && $minimumTermEndsAt && $minimumTermEndsAt->isFuture()) {
            return back()->with('error', 'Dieses Outfit-Abo kann erst nach der Mindestlaufzeit ab dem '.$minimumTermEndsAt->format('d.m.Y').' gekuendigt werden.');
        }

        if ($wasPending) {
            $subscription->update([
                'status' => 'cancelled',
                'payment_status' => 'cancelled',
                'cancelled_at' => now(),
                'next_delivery_at' => null,
                'current_period_ends_at' => null,
            ]);

            return back()->with('success', 'Outfit-Abo-Anfrage wurde abgebrochen.');
        }

        $effectiveAt = $this->cancellationEffectiveAt($subscription);

        if ($subscription->payment_provider === 'paypal' && $subscription->provider_subscription_id) {
            $this->cancelPayPalSubscription($subscription);
        }

        $subscription->update([
            'status' => $effectiveAt->isFuture() ? 'cancels_at_period_end' : 'cancelled',
            'cancelled_at' => now(),
            'current_period_ends_at' => $effectiveAt,
            'next_delivery_at' => $subscription->next_delivery_at && $subscription->next_delivery_at->lte($effectiveAt)
                ? $subscription->next_delivery_at
                : null,
        ]);

        return back()->with('success', 'Outfit-Abo wurde zum '.$effectiveAt->format('d.m.Y').' gekuendigt.');
    }

    public function requestDeliveryIssue(Request $request, OutfitDelivery $delivery)
    {
        $delivery->loadMissing(['subscription.plan', 'subscription.user']);
        $subscription = $delivery->subscription;

        abort_unless($subscription && (int) $subscription->user_id === (int) $request->user()->id, 403);

        if (! in_array($delivery->status, ['shipped', 'delivered'], true)) {
            return back()->with('error', 'Ein Problem kann erst gemeldet werden, wenn die Lieferung versendet oder zugestellt wurde.');
        }

        if (in_array($delivery->issue_status, ['open', 'reviewing', 'approved', 'return_waiting', 'replacement_preparing'], true)) {
            return back()->with('error', 'Für diese Lieferung ist bereits ein offener Vorgang vorhanden.');
        }

        $data = $request->validate([
            'issue_type' => ['required', Rule::in(['exchange', 'return', 'damaged', 'missing_item', 'wrong_item', 'other'])],
            'issue_description' => ['required', 'string', 'max:2000'],
            'issue_requested_resolution' => ['nullable', 'string', 'max:1000'],
            'issue_exchange_size' => ['nullable', 'string', 'max:60'],
        ]);

        $delivery->forceFill([
            'issue_type' => $data['issue_type'],
            'issue_status' => 'open',
            'issue_description' => trim($data['issue_description']),
            'issue_requested_resolution' => filled($data['issue_requested_resolution'] ?? null) ? trim($data['issue_requested_resolution']) : null,
            'issue_exchange_size' => filled($data['issue_exchange_size'] ?? null) ? trim($data['issue_exchange_size']) : null,
            'issue_admin_note' => null,
            'return_tracking_number' => null,
            'return_tracking_url' => null,
            'issue_requested_at' => now(),
            'issue_resolved_at' => null,
        ])->save();

        $this->notifyOutfitDeliveryIssueRequested($request, $delivery->fresh(['subscription.plan']));

        return back()->with('success', 'Deine Meldung wurde gesendet. Unser Team prueft die Lieferung.');
    }

    private function authorizeSubscription(Request $request, OutfitSubscription $subscription): void
    {
        abort_unless((int) $subscription->user_id === (int) $request->user()->id, 403);
    }

    private function pauseAllowedAt(OutfitSubscription $subscription)
    {
        $months = (int) $this->contractRules($subscription->plan)['pause_allowed_after_months'];

        if ($months <= 0) {
            return null;
        }

        return ($subscription->accepted_contract_at ?: $subscription->created_at)?->copy()->addMonthsNoOverflow($months);
    }

    private function minimumTermEndsAt(OutfitSubscription $subscription)
    {
        $months = (int) $this->contractRules($subscription->plan)['minimum_term_months'];

        if ($months <= 0) {
            return null;
        }

        return ($subscription->accepted_contract_at ?: $subscription->created_at)?->copy()->addMonthsNoOverflow($months);
    }

    private function cancellationEffectiveAt(OutfitSubscription $subscription)
    {
        $noticeEnd = now()->addDays((int) $this->contractRules($subscription->plan)['cancellation_notice_days']);
        $periodEnd = $subscription->current_period_ends_at && $subscription->current_period_ends_at->isFuture()
            ? $subscription->current_period_ends_at
            : now();

        return $periodEnd->greaterThan($noticeEnd) ? $periodEnd->copy() : $noticeEnd;
    }

    private function planPayload(OutfitSubscriptionPlan $plan): array
    {
        return [
            'id' => $plan->id,
            'name' => $plan->name,
            'slug' => $plan->slug,
            'description' => $plan->description,
            'contract_title' => $plan->contract_title,
            'contract_terms' => $plan->contract_terms ?: [],
            'contract_rules' => $this->contractRules($plan),
            'monthly_price_cents' => $plan->monthly_price_cents,
            'sponsor_discount_cents' => $plan->sponsor_discount_cents,
            'effective_monthly_price_cents' => $plan->effectiveMonthlyPriceCents(),
            'currency' => $plan->currency,
            'target_gender' => $plan->target_gender,
            'sizes' => $plan->sizes ?: [],
            'sports' => $plan->sports ?: [],
            'items_per_box' => $plan->items_per_box,
            'branding_type' => $plan->branding_type,
            'sponsor' => $plan->sponsor ? [
                'id' => $plan->sponsor->id,
                'name' => $plan->sponsor->name,
                'logo' => $plan->sponsor->logo,
                'logo_url' => UploadStorage::url($plan->sponsor->logo),
                'logo_light_url' => UploadStorage::url($plan->sponsor->logo_light ?: $plan->sponsor->logo),
                'logo_dark_url' => UploadStorage::url($plan->sponsor->logo_dark ?: $plan->sponsor->logo_light ?: $plan->sponsor->logo),
                'website' => $plan->sponsor->website,
            ] : null,
        ];
    }

    private function subscriptionPayload(OutfitSubscription $subscription): array
    {
        $bankTransfer = $subscription->payment_provider === 'bank_transfer'
            ? ($subscription->payment_payload['bank_transfer'] ?? $this->bankTransferSettings())
            : null;

        return [
            'id' => $subscription->id,
            'status' => $subscription->status,
            'payment_provider' => $subscription->payment_provider,
            'payment_status' => $subscription->payment_status,
            'payment_reference' => $subscription->payment_reference ?: $this->paymentReference($subscription),
            'payment_due_at' => $subscription->payment_due_at ?: $subscription->created_at?->copy()->addDays((int) Setting::valueFor('billing_payment_terms_days', 14))->endOfDay(),
            'dunning_level' => (int) $subscription->dunning_level,
            'last_dunning_sent_at' => $subscription->last_dunning_sent_at,
            'payment_paused_at' => $subscription->payment_paused_at,
            'payment_paused_reason' => $subscription->payment_paused_reason,
            'bank_transfer' => $bankTransfer,
            'monthly_price_cents' => $subscription->monthly_price_cents,
            'sponsor_discount_cents' => $subscription->sponsor_discount_cents,
            'currency' => $subscription->currency,
            'next_delivery_at' => $subscription->next_delivery_at,
            'current_period_ends_at' => $subscription->current_period_ends_at,
            'cancelled_at' => $subscription->cancelled_at,
            'shipping_address' => $this->shippingAddressPayload($subscription),
            'plan' => $subscription->plan ? $this->planPayload($subscription->plan) : null,
            'sponsor' => $subscription->sponsor,
            'deliveries' => $subscription->deliveries,
            'accepted_contract_at' => $subscription->accepted_contract_at,
            'contract_version' => $subscription->contract_version,
        ];
    }

    private function shippingAddressFor(Request $request, array $data): array
    {
        $user = $request->user();

        $address = [
            'shipping_name' => $data['shipping_name'] ?? $user->name,
            'shipping_country' => $data['shipping_country'] ?? $user->country ?? 'DE',
            'shipping_street' => $data['shipping_street'] ?? $user->street,
            'shipping_house_number' => $data['shipping_house_number'] ?? $user->house_number,
            'shipping_postal_code' => $data['shipping_postal_code'] ?? $user->postal_code,
            'shipping_city' => $data['shipping_city'] ?? $user->city,
            'shipping_state' => $data['shipping_state'] ?? $user->state,
            'shipping_note' => $data['shipping_note'] ?? null,
        ];

        return collect($address)
            ->map(fn ($value, $key) => $key === 'shipping_country'
                ? strtoupper(Str::limit(trim((string) $value), 2, ''))
                : (filled($value) ? trim((string) $value) : null))
            ->all();
    }

    private function shippingAddressPayload(OutfitSubscription $subscription): array
    {
        return [
            'name' => $subscription->shipping_name,
            'country' => $subscription->shipping_country,
            'street' => $subscription->shipping_street,
            'house_number' => $subscription->shipping_house_number,
            'postal_code' => $subscription->shipping_postal_code,
            'city' => $subscription->shipping_city,
            'state' => $subscription->shipping_state,
            'note' => $subscription->shipping_note,
        ];
    }

    private function contractRules(?OutfitSubscriptionPlan $plan): array
    {
        return [
            'version' => 'outfit-abo-plan-v1-2026-05-09',
            'minimum_term_months' => (int) ($plan?->minimum_term_months ?? Setting::valueFor('outfit_minimum_term_months', 3)),
            'pause_allowed_after_months' => (int) ($plan?->pause_allowed_after_months ?? Setting::valueFor('outfit_pause_allowed_after_months', 3)),
            'cancellation_notice_days' => (int) ($plan?->cancellation_notice_days ?? Setting::valueFor('outfit_cancellation_notice_days', 14)),
            'changes_locked_after_shipping_preparation' => true,
        ];
    }

    private function contractSnapshot(OutfitSubscriptionPlan $plan, User $user): array
    {
        $rules = $this->contractRules($plan);
        $terms = $plan->contract_terms ?: [
            'Das Outfit-Abo ist ein monatliches Abonnement mit wiederkehrender Zahlung.',
            'Die erste Lieferung wird erst nach bestaetigter Zahlung vorbereitet.',
            'Pause und Kuendigung gelten nur für zukuenftige Lieferungen.',
            'Bereits vorbereitete, versendete oder zugestellte Boxen bleiben kostenpflichtig.',
            'Bei PayPal wird der Monatsbetrag automatisch wiederkehrend eingezogen, bis die Pause oder Kuendigung wirksam ist.',
        ];

        return array_merge($rules, [
            'accepted_at' => now()->toIso8601String(),
            'contract_title' => $plan->contract_title ?: 'Outfit-Abo-Vertrag '.$plan->name,
            'customer' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'monthly_price_cents' => $plan->effectiveMonthlyPriceCents(),
            'currency' => $plan->currency,
            'items_per_box' => $plan->items_per_box,
            'sponsor_discount_cents' => $plan->sponsor_discount_cents,
            'terms' => $terms,
            'personalized_summary' => sprintf(
                '%s (%s) akzeptiert den Vertrag "%s" für den Plan "%s" zum Monatsbetrag von %.2f %s.',
                $user->name,
                $user->email,
                $plan->contract_title ?: 'Outfit-Abo-Vertrag '.$plan->name,
                $plan->name,
                $plan->effectiveMonthlyPriceCents() / 100,
                strtoupper($plan->currency)
            ),
        ]);
    }

    private function paymentReference(OutfitSubscription $subscription): string
    {
        return 'AIR-OUT-'.$subscription->created_at->format('Y').'-'.str_pad((string) $subscription->id, 6, '0', STR_PAD_LEFT);
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

    private function createPayPalSubscription(OutfitSubscription $subscription): string
    {
        $subscription->loadMissing('plan', 'user');

        $amountCents = max(0, (int) $subscription->monthly_price_cents);
        abort_if($amountCents <= 0, 422, 'Kostenlose Outfit-Abos koennen nicht per PayPal verarbeitet werden.');

        $planId = $this->ensurePayPalPlan($subscription->plan, $amountCents, $subscription->currency);

        $response = Http::withToken($this->paypalAccessToken())->post($this->paypalBaseUrl().'/v1/billing/subscriptions', [
            'plan_id' => $planId,
            'custom_id' => 'airmius-outfit-'.$subscription->id,
            'quantity' => '1',
            'subscriber' => [
                'email_address' => $subscription->user?->email,
                'name' => [
                    'given_name' => Str::of((string) $subscription->user?->name)->explode(' ')->first() ?: 'Airmius',
                    'surname' => Str::of((string) $subscription->user?->name)->explode(' ')->slice(1)->implode(' ') ?: 'Kunde',
                ],
            ],
            'application_context' => [
                'brand_name' => 'Airmius',
                'user_action' => 'SUBSCRIBE_NOW',
                'return_url' => route('outfit-subscription-checkout.success', $subscription),
                'cancel_url' => route('outfit-subscription-checkout.cancel', $subscription),
            ],
        ]);

        if ($response->failed()) {
            Log::warning('Outfit PayPal checkout failed', ['body' => $response->json()]);
            abort(422, 'PayPal Checkout konnte nicht gestartet werden.');
        }

        $payload = $response->json();
        $subscription->forceFill([
            'payment_reference' => $this->paymentReference($subscription),
            'provider_subscription_id' => $payload['id'] ?? null,
            'provider_checkout_id' => $payload['id'] ?? null,
            'payment_payload' => array_merge($subscription->payment_payload ?? [], ['paypal_subscription' => $payload]),
        ])->save();

        $approveLink = collect($payload['links'] ?? [])->firstWhere('rel', 'approve');
        abort_if(blank($approveLink['href'] ?? null), 422, 'PayPal Genehmigungslink fehlt.');

        return $approveLink['href'];
    }

    private function ensurePayPalPlan(OutfitSubscriptionPlan $plan, int $amountCents, string $currency): string
    {
        $signature = hash('sha256', implode('|', [
            config('services.paypal.mode'),
            $plan->id,
            $amountCents,
            strtoupper($currency),
            'MONTH',
            1,
        ]));

        if ($plan->paypal_plan_id && $plan->paypal_plan_signature === $signature) {
            return $plan->paypal_plan_id;
        }

        $productId = $plan->paypal_product_id ?: $this->createPayPalProduct($plan);
        $amount = number_format($amountCents / 100, 2, '.', '');

        $response = Http::withToken($this->paypalAccessToken())->post($this->paypalBaseUrl().'/v1/billing/plans', [
            'product_id' => $productId,
            'name' => 'Airmius Outfit-Abo '.$plan->name.' '.strtoupper($currency).' '.$amount,
            'description' => Str::limit($plan->description ?: 'Monatliches Airmius Outfit-Abo', 120),
            'status' => 'ACTIVE',
            'billing_cycles' => [[
                'frequency' => [
                    'interval_unit' => 'MONTH',
                    'interval_count' => 1,
                ],
                'tenure_type' => 'REGULAR',
                'sequence' => 1,
                'total_cycles' => 0,
                'pricing_scheme' => [
                    'fixed_price' => [
                        'value' => $amount,
                        'currency_code' => strtoupper($currency),
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
            Log::warning('Outfit PayPal plan failed', ['body' => $response->json()]);
            abort(422, 'PayPal Monatsplan konnte nicht erstellt werden.');
        }

        $payload = $response->json();
        $plan->forceFill([
            'paypal_product_id' => $productId,
            'paypal_plan_id' => $payload['id'] ?? null,
            'paypal_plan_signature' => $signature,
            'paypal_payload' => array_merge($plan->paypal_payload ?? [], ['plan' => $payload]),
        ])->save();

        return $plan->paypal_plan_id;
    }

    private function createPayPalProduct(OutfitSubscriptionPlan $plan): string
    {
        $response = Http::withToken($this->paypalAccessToken())->post($this->paypalBaseUrl().'/v1/catalogs/products', [
            'name' => 'Airmius Outfit-Abo '.$plan->name,
            'description' => Str::limit($plan->description ?: 'Monatliches Airmius Outfit-Abo', 120),
            'type' => 'SERVICE',
        ]);

        if ($response->failed()) {
            Log::warning('Outfit PayPal product failed', ['body' => $response->json()]);
            abort(422, 'PayPal Produkt konnte nicht erstellt werden.');
        }

        $payload = $response->json();
        $plan->forceFill([
            'paypal_product_id' => $payload['id'] ?? null,
            'paypal_payload' => array_merge($plan->paypal_payload ?? [], ['product' => $payload]),
        ])->save();

        return $plan->paypal_product_id;
    }

    private function syncPayPalSubscription(OutfitSubscription $subscription, ?string $subscriptionId = null): void
    {
        $providerSubscriptionId = $subscriptionId ?: $subscription->provider_subscription_id;

        if (! $providerSubscriptionId) {
            return;
        }

        $response = Http::withToken($this->paypalAccessToken())
            ->get($this->paypalBaseUrl().'/v1/billing/subscriptions/'.$providerSubscriptionId);

        if ($response->ok()) {
            $subscription->forceFill([
                'provider_subscription_id' => $providerSubscriptionId,
                'provider_checkout_id' => $providerSubscriptionId,
                'payment_payload' => array_merge($subscription->payment_payload ?? [], ['paypal_subscription_sync' => $response->json()]),
            ])->save();
        }

        if ($response->ok() && $response->json('status') === 'ACTIVE') {
            $this->activatePaidSubscription($subscription);
        }
    }

    private function cancelPayPalSubscription(OutfitSubscription $subscription): void
    {
        try {
            Http::withToken($this->paypalAccessToken())
                ->withBody(json_encode(['reason' => 'Airmius Outfit-Abo wurde vom Kunden beendet.']), 'application/json')
                ->post($this->paypalBaseUrl().'/v1/billing/subscriptions/'.$subscription->provider_subscription_id.'/cancel');
        } catch (\Throwable $exception) {
            Log::warning('Outfit PayPal subscription cancel failed', [
                'subscription_id' => $subscription->id,
                'paypal_subscription_id' => $subscription->provider_subscription_id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function suspendPayPalSubscription(OutfitSubscription $subscription): void
    {
        try {
            Http::withToken($this->paypalAccessToken())
                ->withBody(json_encode(['reason' => 'Airmius Outfit-Abo wurde vom Kunden pausiert.']), 'application/json')
                ->post($this->paypalBaseUrl().'/v1/billing/subscriptions/'.$subscription->provider_subscription_id.'/suspend');
        } catch (\Throwable $exception) {
            Log::warning('Outfit PayPal subscription suspend failed', [
                'subscription_id' => $subscription->id,
                'paypal_subscription_id' => $subscription->provider_subscription_id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function activatePayPalSubscription(OutfitSubscription $subscription): void
    {
        try {
            Http::withToken($this->paypalAccessToken())
                ->withBody(json_encode(['reason' => 'Airmius Outfit-Abo wurde vom Kunden fortgesetzt.']), 'application/json')
                ->post($this->paypalBaseUrl().'/v1/billing/subscriptions/'.$subscription->provider_subscription_id.'/activate');
        } catch (\Throwable $exception) {
            Log::warning('Outfit PayPal subscription activate failed', [
                'subscription_id' => $subscription->id,
                'paypal_subscription_id' => $subscription->provider_subscription_id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function activatePaidSubscription(OutfitSubscription $subscription): void
    {
        if ($subscription->payment_status === 'paid') {
            return;
        }

        $subscription->forceFill([
            'status' => 'active',
            'payment_status' => 'paid',
            'dunning_level' => 0,
            'last_dunning_sent_at' => null,
            'payment_paused_at' => null,
            'payment_paused_reason' => null,
            'payment_due_at' => null,
            'next_delivery_at' => $subscription->next_delivery_at ?? now()->addMonth()->startOfDay(),
            'current_period_ends_at' => $subscription->current_period_ends_at ?? now()->addMonth(),
            'cancelled_at' => null,
        ])->save();

        if (! $subscription->deliveries()->exists()) {
            $subscription->deliveries()->create([
                'status' => 'planned',
                'delivery_month' => now()->addMonth()->startOfMonth(),
                'items' => [],
                'notes' => 'PayPal-Zahlung bestaetigt. Erste personalisierte Box wird vorbereitet.',
            ]);
        }

        app(OutfitInvoiceService::class)->createPaidInvoice($subscription->fresh(['plan', 'sponsor']));

        AppNotification::send($subscription->user_id, 'outfit.subscription.paid', [
            'title' => 'Outfit-Abo aktiviert',
            'message' => 'Deine Zahlung für '.$subscription->plan?->name.' wurde bestaetigt. Dein Outfit-Abo ist jetzt aktiv.',
            'url' => route('auth.outfit-subscriptions.index'),
            'subscription_id' => $subscription->id,
        ]);
    }

    private function notifyOutfitSubscriptionRequested(Request $request, OutfitSubscriptionPlan $plan, OutfitSubscription $subscription): void
    {
        $this->outfitAdminRecipients()
            ->each(function (User $admin) use ($request, $plan, $subscription) {
                AppNotification::send($admin, 'outfit.subscription.requested', [
                    'title' => 'Neues Outfit-Abo angefragt',
                    'message' => "{$request->user()->name} hat {$plan->name} angefragt. Zahlung ist noch offen.",
                    'user_id' => $request->user()->id,
                    'user_name' => $request->user()->name,
                    'plan' => $plan->name,
                    'subscription_id' => $subscription->id,
                    'amount_cents' => $subscription->monthly_price_cents,
                    'currency' => $subscription->currency,
                    'payment_provider' => $subscription->payment_provider,
                    'payment_reference' => $subscription->payment_reference,
                    'url' => route('admin.outfit-subscriptions.index'),
                ]);
            });
    }

    private function notifyOutfitDeliveryIssueRequested(Request $request, OutfitDelivery $delivery): void
    {
        $admins = $this->outfitAdminRecipients();

        $admins->each(function (User $admin) use ($request, $delivery) {
            AppNotification::send($admin, 'outfit.delivery.issue_requested', [
                'title' => 'Outfit-Lieferung braucht Support',
                'message' => $request->user()->name.' hat ein Problem zu einer Outfit-Lieferung gemeldet.',
                'user_id' => $request->user()->id,
                'user_name' => $request->user()->name,
                'delivery_id' => $delivery->id,
                'subscription_id' => $delivery->outfit_subscription_id,
                'issue_type' => $delivery->issue_type,
                'issue_status' => $delivery->issue_status,
                'url' => route('admin.outfit-subscriptions.index'),
            ]);
        });
    }

    private function outfitAdminRecipients()
    {
        $roleNames = array_unique(array_merge(Roles::FULL_ACCESS, Roles::MARKETPLACE_OPERATIONS));
        $permissionNames = [
            'outfit-subscriptions.manage',
            'marketplace.manage',
            'commerce.orders.manage',
            'subscriptions.manage',
            'billing.manage',
            'finance.edit',
        ];

        $existingRoleNames = Role::query()
            ->whereIn('name', $roleNames)
            ->pluck('name')
            ->all();
        $existingPermissionNames = Permission::query()
            ->whereIn('name', $permissionNames)
            ->pluck('name')
            ->all();

        $admins = collect();

        if ($existingRoleNames) {
            $admins = $admins->merge(User::role($existingRoleNames)->get(['id', 'name', 'email']));
        }

        if ($existingPermissionNames) {
            $admins = $admins->merge(User::permission($existingPermissionNames)->get(['id', 'name', 'email']));
        }

        return $admins->unique('id')->values();
    }

    private function paypalAccessToken(): string
    {
        abort_if(blank(config('services.paypal.client_id')) || blank(config('services.paypal.client_secret')), 422, 'PayPal ist noch nicht konfiguriert.');

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

    private function isValidPayPalWebhook(Request $request): bool
    {
        $webhookId = config('services.paypal.outfit_webhook_id') ?: config('services.paypal.webhook_id');

        if (blank($webhookId)) {
            return config('services.paypal.mode') !== 'live';
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
}
