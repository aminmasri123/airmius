<?php

namespace App\Http\Controllers;

use App\Models\AdCampaign;
use App\Models\AdCampaignStat;
use App\Models\Club;
use App\Models\CommerceOrder;
use App\Models\MarketplaceProduct;
use App\Models\OutfitSubscriptionPlan;
use App\Models\PayoutProfile;
use App\Models\Setting;
use App\Models\SubscriptionAddon;
use App\Models\SubscriptionAddonPurchase;
use App\Models\WebsiteRequest;
use App\Notifications\CommerceOrderCompleted;
use App\Services\ModerationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CommerceCheckoutController extends Controller
{
    public function __construct(private ModerationService $moderation) {}

    public function index(Request $request)
    {
        return inertia('Auth/Dashboard/Commerce/Index', [
            'clubs' => Club::query()
                ->visibleTo($request->user())
                ->orderBy('name')
                ->get(['id', 'name']),
            'addons' => SubscriptionAddon::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'products' => MarketplaceProduct::query()
                ->where('status', 'published')
                ->latest('id')
                ->get(),
            'outfitPlans' => OutfitSubscriptionPlan::query()
                ->with('sponsor:id,name,logo,website')
                ->where('is_active', true)
                ->where('is_public', true)
                ->orderBy('sort_order')
                ->orderBy('monthly_price_cents')
                ->get()
                ->map(fn (OutfitSubscriptionPlan $plan) => [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'description' => $plan->description,
                    'monthly_price_cents' => $plan->monthly_price_cents,
                    'sponsor_discount_cents' => $plan->sponsor_discount_cents,
                    'effective_monthly_price_cents' => $plan->effectiveMonthlyPriceCents(),
                    'currency' => $plan->currency,
                    'items_per_box' => $plan->items_per_box,
                    'sports' => $plan->sports ?: [],
                    'branding_type' => $plan->branding_type,
                    'sponsor' => $plan->sponsor,
                ]),
            'orders' => CommerceOrder::query()
                ->with(['orderable', 'club:id,name'])
                ->where('user_id', $request->user()->id)
                ->latest('id')
                ->limit(30)
                ->get(),
            'myProducts' => MarketplaceProduct::query()
                ->where('user_id', $request->user()->id)
                ->latest('id')
                ->limit(20)
                ->get(),
            'myCampaigns' => AdCampaign::query()
                ->where('user_id', $request->user()->id)
                ->with(['stats' => fn ($query) => $query->latest('date')->limit(30)])
                ->latest('id')
                ->limit(20)
                ->get(),
            'websiteRequests' => WebsiteRequest::query()
                ->with('club:id,name')
                ->where('user_id', $request->user()->id)
                ->latest('id')
                ->limit(10)
                ->get(),
            'payoutProfile' => PayoutProfile::query()
                ->where('user_id', $request->user()->id)
                ->first(),
            'payoutSummary' => $this->payoutSummaryFor($request->user()->id),
        ]);
    }

    public function storePayoutProfile(Request $request)
    {
        $data = $request->validate([
            'account_holder' => ['nullable', 'string', 'max:255'],
            'iban' => ['nullable', 'string', 'max:34'],
            'bic' => ['nullable', 'string', 'max:20'],
            'paypal_email' => ['nullable', 'email', 'max:255'],
            'tax_number' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        abort_if(blank($data['iban'] ?? null) && blank($data['paypal_email'] ?? null), 422, 'Bitte IBAN oder PayPal-E-Mail angeben.');

        PayoutProfile::query()->updateOrCreate(
            ['user_id' => $request->user()->id],
            [...$data, 'status' => 'review'],
        );

        return back()->with('success', 'Auszahlungsdaten wurden gespeichert und werden geprueft.');
    }

    public function storeAddon(Request $request, SubscriptionAddon $addon)
    {
        abort_unless($addon->is_active, 404);

        $data = $request->validate([
            'provider' => ['required', Rule::in(['stripe', 'paypal', 'bank_transfer'])],
            'billing_interval' => ['required', Rule::in(['monthly', 'yearly'])],
            'club_id' => ['nullable', Rule::exists('clubs', 'id')],
            'accepted_terms' => ['accepted'],
        ]);

        $club = $data['club_id']
            ? Club::query()->visibleTo($request->user())->findOrFail($data['club_id'])
            : null;
        $amount = $data['billing_interval'] === 'yearly'
            ? (int) $addon->yearly_price_cents
            : (int) $addon->monthly_price_cents;

        $order = CommerceOrder::create([
            'user_id' => $request->user()->id,
            'club_id' => $club?->id,
            'orderable_type' => $addon::class,
            'orderable_id' => $addon->id,
            'type' => 'addon',
            'provider' => $data['provider'],
            'billing_interval' => $data['billing_interval'],
            'amount_cents' => $amount,
            'currency' => 'EUR',
            'status' => 'pending',
        ]);

        return $this->startCheckout($order);
    }

    public function storeProduct(Request $request, MarketplaceProduct $product)
    {
        abort_unless($product->status === 'published', 404);

        $data = $request->validate([
            'provider' => ['required', Rule::in(['stripe', 'paypal', 'bank_transfer'])],
            'accepted_terms' => ['accepted'],
        ]);

        $order = CommerceOrder::create([
            'user_id' => $request->user()->id,
            'club_id' => $product->club_id,
            'orderable_type' => $product::class,
            'orderable_id' => $product->id,
            'type' => 'marketplace_product',
            'provider' => $data['provider'],
            'amount_cents' => $product->price_cents,
            'commission_cents' => (int) floor($product->price_cents * ($product->commission_percent / 100)),
            'currency' => $product->currency,
            'status' => 'pending',
        ]);

        return $this->startCheckout($order);
    }

    public function storeOwnProduct(Request $request)
    {
        $data = $request->validate([
            'club_id' => ['nullable', Rule::exists('clubs', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'image_url' => ['nullable', 'url', 'max:2048'],
            'category' => ['required', Rule::in(['product', 'course', 'camp', 'service'])],
            'price_cents' => ['required', 'integer', 'min:0'],
        ]);

        if (! empty($data['club_id'])) {
            Club::query()->visibleTo($request->user())->findOrFail($data['club_id']);
        }

        $product = MarketplaceProduct::create([
            ...$data,
            'user_id' => $request->user()->id,
            'currency' => 'EUR',
            'status' => 'review',
            'moderation_status' => 'approved',
            'commission_percent' => 10,
            'payout_status' => 'pending_sales',
        ]);

        $this->moderation->flagIfNeeded(
            $product,
            trim($product->title.' '.$product->description),
            $request->user()->id,
            'marketplace_auto_check',
        );

        if ($product->fresh()->moderation_status === 'removed') {
            $product->forceFill([
                'status' => 'rejected',
                'rejection_reason' => 'Automatische Ablehnung wegen schwerem Moderationsrisiko. Der Fall wurde für Admins markiert.',
            ])->save();
        }

        return back()->with('success', 'Produkt wurde zur Prüfung eingereicht.');
    }

    public function showProduct(Request $request, MarketplaceProduct $product)
    {
        abort_unless(
            $product->status === 'published' || $product->user_id === $request->user()->id || $request->user()->can('subscriptions.manage'),
            404,
        );

        $product->load(['user:id,name', 'club:id,name']);

        return inertia('Auth/Dashboard/Commerce/ProductShow', [
            'product' => $product,
        ]);
    }

    public function reportOrderIssue(Request $request, CommerceOrder $order)
    {
        abort_unless($order->user_id === $request->user()->id, 403);
        abort_unless($order->status === 'completed', 422, 'Nur abgeschlossene Bestellungen können gemeldet werden.');

        $data = $request->validate([
            'issue_note' => ['required', 'string', 'max:2000'],
        ]);

        $order->update([
            'issue_status' => 'reported',
            'issue_note' => $data['issue_note'],
            'issue_reported_at' => now(),
        ]);

        return back()->with('success', 'Problem wurde gemeldet. Airmius prueft den Fall.');
    }

    public function storeOwnCampaign(Request $request)
    {
        $data = $request->validate([
            'club_id' => ['nullable', Rule::exists('clubs', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'target_url' => ['nullable', 'url', 'max:255'],
            'budget_cents' => ['required', 'integer', 'min:0'],
        ]);

        AdCampaign::create([
            ...$data,
            'user_id' => $request->user()->id,
            'status' => 'draft',
        ]);

        return back()->with('success', 'Kampagne wurde vorbereitet.');
    }

    public function storeWebsiteRequest(Request $request)
    {
        $data = $request->validate([
            'club_id' => ['nullable', Rule::exists('clubs', 'id')],
            'domain' => ['nullable', 'string', 'max:255'],
            'goals' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if (! empty($data['club_id'])) {
            Club::query()->visibleTo($request->user())->findOrFail($data['club_id']);
        }

        WebsiteRequest::create([
            ...$data,
            'user_id' => $request->user()->id,
            'status' => 'new',
            'package' => 'website_plus',
        ]);

        return back()->with('success', 'Website-Anfrage wurde gesendet.');
    }

    public function success(Request $request, CommerceOrder $order)
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        if ($order->provider === 'stripe' && $order->status === 'pending') {
            $this->syncStripeOrder($order);
        }

        if ($order->provider === 'paypal' && $order->status === 'pending') {
            $this->capturePayPalOrder($order);
        }

        return redirect()->route('auth.commerce.index')->with('success', 'Bestellung wurde verarbeitet.');
    }

    public function cancel(Request $request, CommerceOrder $order)
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        $order->update(['status' => 'cancelled']);

        return redirect()->route('auth.commerce.index')->with('success', 'Bestellung wurde abgebrochen.');
    }

    public function bankTransfer(Request $request, CommerceOrder $order)
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        return inertia('Auth/Dashboard/Commerce/BankTransfer', [
            'order' => $this->orderResource($order->load('orderable')),
            'bank' => $this->bankTransferSettings(),
        ]);
    }

    public function guestSuccess(Request $request, CommerceOrder $order, string $token)
    {
        $this->authorizeGuestOrder($order, $token);

        if ($order->provider === 'stripe' && $order->status === 'pending') {
            $this->syncStripeOrder($order);
        }

        if ($order->provider === 'paypal' && $order->status === 'pending') {
            $this->capturePayPalOrder($order);
        }

        return inertia('Guest/MarketplaceOrderStatus', [
            'status' => 'success',
            'order' => $this->orderResource($order->refresh()->load('orderable')),
        ]);
    }

    public function guestCancel(Request $request, CommerceOrder $order, string $token)
    {
        $this->authorizeGuestOrder($order, $token);

        $order->update(['status' => 'cancelled']);

        return inertia('Guest/MarketplaceOrderStatus', [
            'status' => 'cancelled',
            'order' => $this->orderResource($order->load('orderable')),
        ]);
    }

    public function guestBankTransfer(Request $request, CommerceOrder $order, string $token)
    {
        $this->authorizeGuestOrder($order, $token);

        return inertia('Guest/MarketplaceBankTransfer', [
            'order' => $this->orderResource($order->load('orderable')),
            'bank' => $this->bankTransferSettings(),
        ]);
    }

    public function activeAd()
    {
        $campaign = AdCampaign::query()
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->inRandomOrder()
            ->first();

        if (! $campaign) {
            return response()->json(null);
        }

        $campaign->increment('impressions');
        $this->trackAdStat($campaign, 'impressions');

        return response()->json([
            'id' => $campaign->id,
            'name' => $campaign->name,
            'description' => $campaign->description,
            'click_url' => route('ads.click', $campaign),
        ]);
    }

    public function clickAd(AdCampaign $campaign)
    {
        $campaign->increment('clicks');
        $this->trackAdStat($campaign, 'clicks');

        return redirect()->away($campaign->target_url ?: route('guest.pricing'));
    }

    private function trackAdStat(AdCampaign $campaign, string $metric): void
    {
        if (! in_array($metric, ['impressions', 'clicks'], true)) {
            return;
        }

        $stat = AdCampaignStat::query()->firstOrCreate([
            'ad_campaign_id' => $campaign->id,
            'date' => today()->toDateString(),
        ]);

        $stat->increment($metric);
    }

    public function stripeWebhook(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        if (! $this->isValidStripeSignature($payload, $signature)) {
            return response('Invalid signature', 400);
        }

        $event = json_decode($payload, true);
        $object = $event['data']['object'] ?? [];

        if (($event['type'] ?? null) === 'checkout.session.completed') {
            $order = CommerceOrder::query()
                ->where('provider', 'stripe')
                ->where('provider_checkout_id', $object['id'] ?? null)
                ->first();

            if ($order) {
                $order->update(['payload' => $event]);
                $this->activate($order);
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
        $resource = $event['resource'] ?? [];
        $orderId = $resource['supplementary_data']['related_ids']['order_id'] ?? $resource['id'] ?? null;

        if (in_array($event['event_type'] ?? null, ['CHECKOUT.ORDER.APPROVED', 'PAYMENT.CAPTURE.COMPLETED'], true)) {
            $order = CommerceOrder::query()
                ->where('provider', 'paypal')
                ->where('provider_checkout_id', $orderId)
                ->first();

            if ($order) {
                $order->update(['payload' => $event]);
                $this->activate($order);
            }
        }

        return response('ok');
    }

    public function activate(CommerceOrder $order): void
    {
        if ($order->status === 'completed') {
            return;
        }

        if ($order->type === 'addon' && $order->orderable instanceof SubscriptionAddon) {
            SubscriptionAddonPurchase::query()->updateOrCreate(
                [
                    'subscription_addon_id' => $order->orderable_id,
                    'club_id' => $order->club_id,
                    'user_id' => $order->user_id,
                ],
                [
                    'status' => 'active',
                    'current_period_ends_at' => $order->billing_interval === 'yearly' ? now()->addYear() : now()->addMonth(),
                ],
            );
        }

        $order->update([
            'status' => 'completed',
            'completed_at' => now(),
            'payout_status' => $order->type === 'marketplace_product' ? 'pending' : 'not_applicable',
        ]);
        $this->sendConfirmationEmail($order->refresh());
    }

    private function payoutSummaryFor(int $userId): array
    {
        $orders = CommerceOrder::query()
            ->where('type', 'marketplace_product')
            ->where('status', 'completed')
            ->where('payout_status', 'pending')
            ->whereHasMorph('orderable', [MarketplaceProduct::class], fn ($query) => $query->where('user_id', $userId))
            ->get();

        return [
            'pending_orders' => $orders->count(),
            'gross_cents' => $orders->sum('amount_cents'),
            'commission_cents' => $orders->sum('commission_cents'),
            'amount_cents' => $orders->sum('amount_cents') - $orders->sum('commission_cents'),
        ];
    }

    public function startPublicCheckout(CommerceOrder $order)
    {
        return $this->startCheckout($order);
    }

    private function startCheckout(CommerceOrder $order)
    {
        abort_if($order->amount_cents <= 0, 422, 'Kostenlose Bestellungen können aktuell nicht per Checkout verarbeitet werden.');

        if ($order->provider === 'bank_transfer') {
            $this->prepareBankTransfer($order);

            return redirect()->to($this->orderRoute($order, 'bank-transfer'));
        }

        $url = $order->provider === 'stripe'
            ? $this->createStripeCheckout($order)
            : $this->createPayPalCheckout($order);

        $order->update(['checkout_url' => $url]);

        return redirect()->away($url);
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

    private function syncStripeOrder(CommerceOrder $order): void
    {
        $sessionId = request('session_id') ?: $order->provider_checkout_id;

        if (! $sessionId || blank(config('services.stripe.secret'))) {
            return;
        }

        $response = Http::withToken(config('services.stripe.secret'))
            ->get('https://api.stripe.com/v1/checkout/sessions/'.$sessionId);

        if ($response->ok() && $response->json('payment_status') === 'paid') {
            $order->update(['payload' => $response->json()]);
            $this->activate($order);
        }
    }

    private function capturePayPalOrder(CommerceOrder $order): void
    {
        $response = Http::withToken($this->paypalAccessToken())
            ->withHeaders(['PayPal-Request-Id' => (string) Str::uuid()])
            ->post($this->paypalBaseUrl().'/v2/checkout/orders/'.$order->provider_checkout_id.'/capture');

        if ($response->ok() && in_array($response->json('status'), ['COMPLETED', 'APPROVED'], true)) {
            $order->update(['payload' => $response->json()]);
            $this->activate($order);
        }
    }

    private function prepareBankTransfer(CommerceOrder $order): void
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

    private function sendConfirmationEmail(CommerceOrder $order): void
    {
        if ($order->confirmation_email_sent_at || ! $this->buyerEmail($order)) {
            return;
        }

        if ($order->user?->email) {
            $order->user->notify(new CommerceOrderCompleted($order));
        } else {
            Notification::route('mail', $order->guest_email)
                ->notify(new CommerceOrderCompleted($order));
        }

        $order->forceFill(['confirmation_email_sent_at' => now()])->save();
    }

    private function authorizeGuestOrder(CommerceOrder $order, string $token): void
    {
        abort_unless($order->access_token && hash_equals($order->access_token, $token), 403);
    }

    private function buyerEmail(CommerceOrder $order): ?string
    {
        return $order->user?->email ?: $order->guest_email;
    }

    private function orderRoute(CommerceOrder $order, string $type): string
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

    private function isValidStripeSignature(string $payload, ?string $signature): bool
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

    private function orderResource(CommerceOrder $order): array
    {
        $pricing = $order->payload['pricing'] ?? null;

        return [
            'id' => $order->id,
            'type' => $order->type,
            'status' => $order->status,
            'title' => $order->orderable?->name ?? $order->orderable?->title ?? 'Airmius Bestellung',
            'amount' => number_format($order->amount_cents / 100, 2, ',', '.').' '.$order->currency,
            'pricing' => $pricing,
            'payment_reference' => $order->payment_reference,
            'due_at' => $order->due_at?->toDateString(),
        ];
    }
}
