<?php

namespace App\Http\Controllers;

use App\Models\AdCampaign;
use App\Models\AdCampaignStat;
use App\Models\Club;
use App\Models\CommerceCart;
use App\Models\CommerceCartItem;
use App\Models\CommerceOrder;
use App\Models\CommerceOrderItem;
use App\Models\CommerceReturnRequest;
use App\Models\CommerceStockMovement;
use App\Models\MarketplaceProduct;
use App\Models\OutfitSubscriptionPlan;
use App\Models\PayoutProfile;
use App\Models\Setting;
use App\Models\SubscriptionAddon;
use App\Models\SubscriptionAddonPurchase;
use App\Models\WebsiteRequest;
use App\Notifications\CommerceOrderCompleted;
use App\Services\CommerceAuditService;
use App\Services\CommerceDocumentService;
use App\Services\MarketplacePricingService;
use App\Services\ModerationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CommerceCheckoutController extends Controller
{
    public function __construct(
        private ModerationService $moderation,
        private MarketplacePricingService $pricing,
        private CommerceAuditService $audit,
        private CommerceDocumentService $documents,
    ) {}

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
                ->with(['orderable', 'club:id,name', 'returnRequests', 'items'])
                ->where('user_id', $request->user()->id)
                ->latest('id')
                ->limit(30)
                ->get(),
            'myProducts' => MarketplaceProduct::query()
                ->where('user_id', $request->user()->id)
                ->withCount('stockMovements')
                ->latest('id')
                ->limit(20)
                ->get(),
            'returnRequests' => CommerceReturnRequest::query()
                ->with(['order.orderable', 'item'])
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
            'cart' => $this->cartResource($request),
            'pricingCountries' => $this->pricingCountries(),
            'checkoutAddress' => $this->shippingAddressForAuthenticatedUser($request, []),
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
        $order->items()->create([
            'orderable_type' => $addon::class,
            'orderable_id' => $addon->id,
            'title' => $addon->name,
            'quantity' => 1,
            'unit_gross_cents' => $amount,
            'net_cents' => $amount,
            'total_cents' => $amount,
            'currency' => 'EUR',
            'is_shippable' => false,
        ]);

        return $this->startCheckout($order);
    }

    public function storeProduct(Request $request, MarketplaceProduct $product)
    {
        abort_unless($product->status === 'published', 404);
        abort_if($product->manages_stock && (int) $product->stock_quantity < 1, 422, 'Dieses Angebot ist aktuell ausverkauft.');

        $data = $request->validate([
            'provider' => ['required', Rule::in(['stripe', 'paypal', 'bank_transfer'])],
            'accepted_terms' => ['accepted'],
            'shipping_country' => ['nullable', 'string', 'size:2'],
            'shipping_state' => ['nullable', 'string', 'max:80'],
            'shipping_postal_code' => ['nullable', 'string', 'max:30'],
            'shipping_city' => ['nullable', 'string', 'max:120'],
            'shipping_street' => ['nullable', 'string', 'max:180'],
            'shipping_house_number' => ['nullable', 'string', 'max:40'],
            'customer_type' => ['nullable', Rule::in(['consumer', 'business'])],
            'customer_company' => ['nullable', 'string', 'max:255'],
            'customer_vat_id' => ['nullable', 'string', 'max:40'],
        ]);
        $shippingAddress = $this->shippingAddressForAuthenticatedUser($request, $data);
        $quote = $this->pricing->quoteForRequest($product, $request, $shippingAddress['country'], $shippingAddress);
        $customer = $this->customerFromData($data);

        $order = CommerceOrder::create([
            'user_id' => $request->user()->id,
            'club_id' => $product->club_id,
            'orderable_type' => $product::class,
            'orderable_id' => $product->id,
            'type' => 'marketplace_product',
            'provider' => $data['provider'],
            ...$this->orderAmountsFromQuote($quote),
            'commission_cents' => (int) floor($quote['item_gross_cents'] * ($product->commission_percent / 100)),
            'currency' => $quote['currency'],
            'tax_country' => $quote['country'],
            'tax_rate_percent' => $quote['tax_rate'],
            'customer_type' => $customer['type'],
            'customer_company' => $customer['company'] ?: null,
            'customer_vat_id' => $customer['vat_id'] ?: null,
            'status' => 'pending',
            'payload' => ['pricing' => $quote, 'shipping_address' => $shippingAddress],
        ]);
        $this->createOrderItem($order, $product, $quote, 1);

        return $this->startCheckout($order);
    }

    public function addCartItem(Request $request, MarketplaceProduct $product)
    {
        abort_unless($product->status === 'published', 404);

        $data = $request->validate([
            'quantity' => ['nullable', 'integer', 'min:1', 'max:99'],
        ]);

        $cart = $this->cartFor($request);
        $quantity = (int) ($data['quantity'] ?? 1);
        $item = $cart->items()->firstOrNew(['marketplace_product_id' => $product->id]);
        $item->quantity = min(99, (int) $item->quantity + $quantity);

        if ($product->manages_stock) {
            abort_if($item->quantity > (int) $product->stock_quantity, 422, 'So viele Artikel sind aktuell nicht auf Lager.');
        }

        $item->save();

        return back()->with('success', 'Artikel wurde in den Einkaufswagen gelegt.');
    }

    public function updateCartItem(Request $request, CommerceCartItem $item)
    {
        abort_unless((int) $item->cart->user_id === (int) $request->user()->id, 403);

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $product = $item->product;
        abort_if($product?->manages_stock && (int) $data['quantity'] > (int) $product->stock_quantity, 422, 'So viele Artikel sind aktuell nicht auf Lager.');

        $item->update(['quantity' => (int) $data['quantity']]);

        return back()->with('success', 'Einkaufswagen wurde aktualisiert.');
    }

    public function removeCartItem(Request $request, CommerceCartItem $item)
    {
        abort_unless((int) $item->cart->user_id === (int) $request->user()->id, 403);
        $item->delete();

        return back()->with('success', 'Artikel wurde aus dem Einkaufswagen entfernt.');
    }

    public function checkoutCart(Request $request)
    {
        $data = $request->validate([
            'provider' => ['required', Rule::in(['stripe', 'paypal', 'bank_transfer'])],
            'accepted_terms' => ['accepted'],
            'shipping_country' => ['nullable', 'string', 'size:2'],
            'shipping_state' => ['nullable', 'string', 'max:80'],
            'shipping_postal_code' => ['nullable', 'string', 'max:30'],
            'shipping_city' => ['nullable', 'string', 'max:120'],
            'shipping_street' => ['nullable', 'string', 'max:180'],
            'shipping_house_number' => ['nullable', 'string', 'max:40'],
            'customer_type' => ['nullable', Rule::in(['consumer', 'business'])],
            'customer_company' => ['nullable', 'string', 'max:255'],
            'customer_vat_id' => ['nullable', 'string', 'max:40'],
        ]);

        $cart = $this->cartFor($request)->load('items.product');
        abort_if($cart->items->isEmpty(), 422, 'Dein Einkaufswagen ist leer.');

        $shippingAddress = $this->shippingAddressForAuthenticatedUser($request, $data);
        $customer = $this->customerFromData($data);
        $summary = $this->cartQuote($cart, $request, $shippingAddress, $customer);

        DB::transaction(function () use ($cart) {
            foreach ($cart->items as $item) {
                $product = MarketplaceProduct::query()->lockForUpdate()->findOrFail($item->marketplace_product_id);
                abort_unless($product->status === 'published', 422, $product->title.' ist nicht mehr verfuegbar.');
                abort_if($product->manages_stock && (int) $product->stock_quantity < (int) $item->quantity, 422, $product->title.' ist nicht ausreichend auf Lager.');
            }
        });

        $order = CommerceOrder::create([
            'user_id' => $request->user()->id,
            'type' => 'marketplace_cart',
            'provider' => $data['provider'],
            'item_gross_cents' => $summary['item_gross_cents'],
            'shipping_cents' => $summary['shipping_cents'],
            'net_cents' => $summary['net_cents'],
            'tax_cents' => $summary['tax_cents'],
            'amount_cents' => $summary['amount_cents'],
            'commission_cents' => $summary['commission_cents'],
            'currency' => $summary['currency'],
            'tax_country' => $summary['tax_country'],
            'tax_rate_percent' => $summary['tax_rate_percent'],
            'customer_type' => $customer['type'],
            'customer_company' => $customer['company'] ?: null,
            'customer_vat_id' => $customer['vat_id'] ?: null,
            'customer_vat_is_valid' => $customer['vat_id'] ? $this->looksLikeEuVatId($customer['vat_id']) : null,
            'customer_vat_validated_at' => $customer['vat_id'] ? now() : null,
            'status' => 'pending',
            'payload' => ['pricing' => $summary, 'shipping_address' => $shippingAddress, 'cart_id' => $cart->id],
        ]);

        foreach ($summary['items'] as $item) {
            $this->createOrderItem($order, $item['product'], $item['quote'], $item['quantity']);
        }

        $cart->items()->delete();

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
            'sku' => ['nullable', 'string', 'max:80'],
            'is_shippable' => ['boolean'],
            'manages_stock' => ['boolean'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'tax_class' => ['nullable', 'string', 'max:30'],
            'return_policy_type' => ['nullable', Rule::in(['standard', 'digital', 'service', 'hygiene', 'custom'])],
            'return_window_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'price_cents' => ['required', 'integer', 'min:0'],
        ]);

        if (! empty($data['club_id'])) {
            Club::query()->visibleTo($request->user())->findOrFail($data['club_id']);
        }

        $product = MarketplaceProduct::create([
            ...$data,
            'user_id' => $request->user()->id,
            'currency' => 'EUR',
            'is_shippable' => (bool) ($data['is_shippable'] ?? $data['category'] === 'product'),
            'manages_stock' => (bool) ($data['manages_stock'] ?? false),
            'tax_class' => $data['tax_class'] ?? 'standard',
            'return_policy_type' => $data['return_policy_type'] ?? 'standard',
            'return_window_days' => (int) ($data['return_window_days'] ?? 14),
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
        $shippingAddress = $this->shippingAddressForAuthenticatedUser($request, []);
        $quote = $this->pricing->quoteForRequest($product, $request, $shippingAddress['country'], $shippingAddress);

        return inertia('Auth/Dashboard/Commerce/ProductShow', [
            'product' => [
                ...$product->toArray(),
                'user' => $product->user,
                'club' => $product->club,
                'price' => $quote,
            ],
            'pricingCountries' => $this->pricingCountries(),
            'checkoutAddress' => $shippingAddress,
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

    public function requestReturn(Request $request, CommerceOrder $order)
    {
        abort_unless($order->user_id === $request->user()->id, 403);
        abort_unless($order->status === 'completed', 422, 'Ruecksendungen sind nur fuer abgeschlossene Bestellungen moeglich.');

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
            'commerce_order_item_id' => ['nullable', 'integer', Rule::exists('commerce_order_items', 'id')],
            'quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        $item = $order->items()->when($data['commerce_order_item_id'] ?? null, fn ($query, $id) => $query->whereKey($id))->first();
        abort_if($item && ! $item->is_shippable, 422, 'Dieses Angebot ist nicht ruecksendepflichtig.');
        abort_if($item && ! $this->itemStillReturnable($item), 422, 'Die Ruecksendefrist fuer diesen Artikel ist abgelaufen oder ausgeschlossen.');

        CommerceReturnRequest::create([
            'commerce_order_id' => $order->id,
            'commerce_order_item_id' => $item?->id,
            'user_id' => $request->user()->id,
            'status' => 'requested',
            'reason' => $data['reason'],
            'quantity' => (int) ($data['quantity'] ?? 1),
            'requested_amount_cents' => $item?->total_cents ?: $order->amount_cents,
            'currency' => $order->currency,
            'requested_at' => now(),
        ]);

        return back()->with('success', 'Ruecksendung wurde angefragt.');
    }

    public function downloadInvoice(Request $request, CommerceOrder $order)
    {
        abort_unless((int) $order->user_id === (int) $request->user()->id, 403);
        abort_unless($order->invoice_number, 404);

        return response($this->documents->pdf($order, 'invoice'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$order->invoice_number.'.pdf"',
        ]);
    }

    public function downloadCreditNote(Request $request, CommerceOrder $order)
    {
        abort_unless((int) $order->user_id === (int) $request->user()->id, 403);
        abort_unless($order->credit_note_number, 404);

        return response($this->documents->pdf($order, 'credit_note'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$order->credit_note_number.'.pdf"',
        ]);
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
            'order' => $this->orderResource($order->load(['orderable', 'items', 'returnRequests'])),
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
            'order' => $this->orderResource($order->refresh()->load(['orderable', 'items', 'returnRequests'])),
        ]);
    }

    public function guestCancel(Request $request, CommerceOrder $order, string $token)
    {
        $this->authorizeGuestOrder($order, $token);

        $order->update(['status' => 'cancelled']);

        return inertia('Guest/MarketplaceOrderStatus', [
            'status' => 'cancelled',
            'order' => $this->orderResource($order->load(['orderable', 'items', 'returnRequests'])),
        ]);
    }

    public function guestBankTransfer(Request $request, CommerceOrder $order, string $token)
    {
        $this->authorizeGuestOrder($order, $token);

        return inertia('Guest/MarketplaceBankTransfer', [
            'order' => $this->orderResource($order->load(['orderable', 'items', 'returnRequests'])),
            'bank' => $this->bankTransferSettings(),
        ]);
    }

    public function guestReturn(Request $request, CommerceOrder $order, string $token)
    {
        $this->authorizeGuestOrder($order, $token);
        abort_unless($order->status === 'completed', 422, 'Ruecksendungen sind nur fuer abgeschlossene Bestellungen moeglich.');

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);
        $item = $order->items()->first();

        CommerceReturnRequest::create([
            'commerce_order_id' => $order->id,
            'commerce_order_item_id' => $item?->id,
            'guest_email' => $order->guest_email,
            'status' => 'requested',
            'reason' => $data['reason'],
            'quantity' => 1,
            'requested_amount_cents' => $item?->total_cents ?: $order->amount_cents,
            'currency' => $order->currency,
            'requested_at' => now(),
        ]);

        return back()->with('success', 'Ruecksendung wurde angefragt.');
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

        if (in_array($order->type, ['marketplace_product', 'marketplace_cart'], true)) {
            DB::transaction(function () use ($order) {
                $order->loadMissing('items');
                if ($order->items->isEmpty() && $order->orderable instanceof MarketplaceProduct) {
                    $this->createOrderItem($order, $order->orderable, $order->payload['pricing'] ?? [], 1);
                    $order->load('items');
                }

                foreach ($order->items as $item) {
                    if ($item->orderable_type !== MarketplaceProduct::class || ! $item->orderable_id) {
                        continue;
                    }

                    $product = MarketplaceProduct::query()->lockForUpdate()->find($item->orderable_id);

                    if (! $product || ! $product->manages_stock) {
                        continue;
                    }

                    abort_if((int) $product->stock_quantity < (int) $item->quantity, 422, $product->title.' ist nicht ausreichend auf Lager.');

                    $product->decrement('stock_quantity', (int) $item->quantity);
                    $product->refresh();

                    CommerceStockMovement::create([
                        'marketplace_product_id' => $product->id,
                        'commerce_order_id' => $order->id,
                        'type' => 'sale',
                        'quantity_delta' => -1 * (int) $item->quantity,
                        'stock_after' => $product->stock_quantity,
                        'note' => 'Bestellung #'.$order->id.' bezahlt',
                    ]);
                }
            });
        }

        $order->update([
            'status' => 'completed',
            'completed_at' => now(),
            'invoice_number' => $order->invoice_number ?: $this->nextDocumentNumber('commerce_invoice_number_next', 'AIR-RE'),
            'payout_status' => in_array($order->type, ['marketplace_product', 'marketplace_cart'], true) ? 'pending' : 'not_applicable',
        ]);
        $this->sendConfirmationEmail($order->refresh());
    }

    private function payoutSummaryFor(int $userId): array
    {
        $orders = CommerceOrder::query()
            ->whereIn('type', ['marketplace_product', 'marketplace_cart'])
            ->where('status', 'completed')
            ->where('payout_status', 'pending')
            ->where(function ($query) use ($userId) {
                $query->whereHasMorph('orderable', [MarketplaceProduct::class], fn ($product) => $product->where('user_id', $userId))
                    ->orWhereHas('items', fn ($items) => $items
                        ->where('orderable_type', MarketplaceProduct::class)
                        ->whereHasMorph('orderable', [MarketplaceProduct::class], fn ($product) => $product->where('user_id', $userId)));
            })
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
            'items' => $order->items->map(fn (CommerceOrderItem $item) => [
                'id' => $item->id,
                'title' => $item->title,
                'sku' => $item->sku,
                'quantity' => $item->quantity,
                'is_shippable' => $item->is_shippable,
                'total_cents' => $item->total_cents,
            ])->values(),
            'return_requests' => $order->returnRequests->map(fn (CommerceReturnRequest $return) => [
                'id' => $return->id,
                'status' => $return->status,
                'reason' => $return->reason,
            ])->values(),
            'return_url' => $order->access_token ? route('commerce-checkout.guest.returns.store', [$order, $order->access_token]) : null,
            'payment_reference' => $order->payment_reference,
            'due_at' => $order->due_at?->toDateString(),
            'invoice_number' => $order->invoice_number,
            'credit_note_number' => $order->credit_note_number,
            'shipping_status' => $order->shipping_status,
            'shipping_carrier' => $order->shipping_carrier,
            'tracking_number' => $order->tracking_number,
            'tracking_url' => $order->tracking_url,
        ];
    }

    private function createOrderItem(CommerceOrder $order, MarketplaceProduct $product, array $quote, int $quantity = 1): void
    {
        $unitGrossCents = (int) round(((int) ($quote['item_gross_cents'] ?? $product->price_cents)) / max(1, $quantity));

        $order->items()->create([
            'orderable_type' => $product::class,
            'orderable_id' => $product->id,
            'title' => $product->title,
            'sku' => $product->sku,
            'quantity' => $quantity,
            'unit_gross_cents' => $unitGrossCents,
            'shipping_cents' => (int) ($quote['shipping_gross_cents'] ?? 0),
            'net_cents' => (int) ($quote['net_cents'] ?? 0),
            'tax_cents' => (int) ($quote['tax_cents'] ?? 0),
            'total_cents' => (int) ($quote['gross_cents'] ?? $order->amount_cents),
            'currency' => $quote['currency'] ?? $order->currency,
            'tax_rate_percent' => $quote['tax_rate'] ?? null,
            'tax_class' => $product->tax_class ?: 'standard',
            'is_shippable' => (bool) $product->is_shippable,
        ]);
    }

    private function shippingAddressForAuthenticatedUser(Request $request, array $data): array
    {
        $user = $request->user();

        return [
            'country' => strtoupper((string) ($data['shipping_country'] ?? $user?->country ?? 'DE')),
            'state' => trim((string) ($data['shipping_state'] ?? $user?->state ?? '')),
            'postal_code' => trim((string) ($data['shipping_postal_code'] ?? $user?->postal_code ?? '')),
            'city' => trim((string) ($data['shipping_city'] ?? $user?->city ?? '')),
            'street' => trim((string) ($data['shipping_street'] ?? $user?->street ?? '')),
            'house_number' => trim((string) ($data['shipping_house_number'] ?? $user?->house_number ?? '')),
        ];
    }

    private function customerFromData(array $data): array
    {
        return [
            'type' => ($data['customer_type'] ?? 'consumer') === 'business' ? 'business' : 'consumer',
            'company' => trim((string) ($data['customer_company'] ?? '')),
            'vat_id' => strtoupper(preg_replace('/\s+/', '', (string) ($data['customer_vat_id'] ?? ''))),
        ];
    }

    private function orderAmountsFromQuote(array $quote): array
    {
        return [
            'item_gross_cents' => (int) ($quote['item_gross_cents'] ?? $quote['gross_cents']),
            'shipping_cents' => (int) ($quote['shipping_gross_cents'] ?? 0),
            'net_cents' => (int) ($quote['net_cents'] ?? 0),
            'tax_cents' => (int) ($quote['tax_cents'] ?? 0),
            'amount_cents' => (int) ($quote['gross_cents'] ?? 0),
        ];
    }

    private function cartFor(Request $request): CommerceCart
    {
        return CommerceCart::query()->firstOrCreate(
            ['user_id' => $request->user()->id],
            ['currency' => 'EUR'],
        );
    }

    private function cartResource(Request $request): array
    {
        $cart = $this->cartFor($request)->load('items.product');
        $address = $this->shippingAddressForAuthenticatedUser($request, []);
        $summary = $cart->items->isNotEmpty()
            ? $this->cartQuote($cart, $request, $address, [])
            : [
                'item_gross_cents' => 0,
                'shipping_cents' => 0,
                'net_cents' => 0,
                'tax_cents' => 0,
                'amount_cents' => 0,
                'currency' => 'EUR',
                'items' => [],
            ];

        return [
            'id' => $cart->id,
            'items' => $cart->items->map(fn (CommerceCartItem $item) => [
                'id' => $item->id,
                'quantity' => $item->quantity,
                'product' => $item->product,
                'line_total_cents' => ((int) $item->product?->price_cents) * (int) $item->quantity,
            ])->values(),
            'summary' => $summary,
        ];
    }

    private function cartQuote(CommerceCart $cart, Request $request, array $shippingAddress, array $customer): array
    {
        $items = [];
        $currency = 'EUR';
        $itemGross = 0;
        $shipping = 0;
        $net = 0;
        $tax = 0;
        $commission = 0;
        $country = strtoupper((string) ($shippingAddress['country'] ?? 'DE'));
        $taxRate = 0.0;

        foreach ($cart->items as $cartItem) {
            $product = $cartItem->product;
            if (! $product || $product->status !== 'published') {
                continue;
            }

            $quantity = max(1, (int) $cartItem->quantity);
            $quote = $this->pricing->quote($product, $country, 'cart', $shippingAddress, $customer);
            $quote['item_gross_cents'] *= $quantity;
            $quote['item_net_cents'] *= $quantity;
            $quote['item_tax_cents'] *= $quantity;
            $quote['gross_cents'] = ($quote['item_gross_cents'] ?? 0) + ($quote['shipping_gross_cents'] ?? 0);
            $quote['net_cents'] = ($quote['item_net_cents'] ?? 0) + ($quote['shipping_net_cents'] ?? 0);
            $quote['tax_cents'] = ($quote['item_tax_cents'] ?? 0) + ($quote['shipping_tax_cents'] ?? 0);
            $currency = $quote['currency'] ?? $currency;
            $taxRate = max($taxRate, (float) ($quote['tax_rate'] ?? 0));

            $itemGross += (int) $quote['item_gross_cents'];
            $shipping += (int) ($quote['shipping_gross_cents'] ?? 0);
            $net += (int) ($quote['net_cents'] ?? 0);
            $tax += (int) ($quote['tax_cents'] ?? 0);
            $commission += (int) floor(((int) $quote['item_gross_cents']) * ((int) $product->commission_percent / 100));
            $items[] = ['product' => $product, 'quantity' => $quantity, 'quote' => $quote];
        }

        return [
            'items' => $items,
            'currency' => $currency,
            'tax_country' => $country,
            'tax_rate_percent' => $taxRate,
            'item_gross_cents' => $itemGross,
            'shipping_cents' => $shipping,
            'shipping_gross_cents' => $shipping,
            'net_cents' => $net,
            'tax_cents' => $tax,
            'amount_cents' => $itemGross + $shipping,
            'gross_cents' => $itemGross + $shipping,
            'commission_cents' => $commission,
        ];
    }

    private function itemStillReturnable(CommerceOrderItem $item): bool
    {
        $item->loadMissing(['order', 'orderable']);
        $product = $item->orderable instanceof MarketplaceProduct ? $item->orderable : null;
        $policy = $product?->return_policy_type ?: 'standard';
        $window = (int) ($product?->return_window_days ?? 14);

        if (in_array($policy, ['digital', 'service', 'hygiene'], true) || $window <= 0) {
            return false;
        }

        $completedAt = $item->order?->completed_at ?: $item->order?->created_at;

        return $completedAt ? $completedAt->copy()->addDays($window)->endOfDay()->isFuture() : true;
    }

    private function looksLikeEuVatId(?string $vatId): bool
    {
        return (bool) preg_match('/^[A-Z]{2}[A-Z0-9]{8,12}$/', strtoupper((string) $vatId));
    }

    private function nextDocumentNumber(string $settingKey, string $prefix): string
    {
        return DB::transaction(function () use ($settingKey, $prefix) {
            $next = (int) Setting::valueFor($settingKey, 1);
            Setting::setValue($settingKey, (string) ($next + 1));

            return $prefix.'-'.now()->format('Y').'-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
        });
    }

    private function pricingCountries(): array
    {
        return collect($this->pricing->taxProfiles())
            ->map(fn (array $profile, string $country) => [
                'country' => $country,
                'currency' => $profile['currency'],
                'tax_rate' => $profile['tax_rate'],
                'label' => $country.' - '.$profile['currency'].' - '.$profile['tax_label'].' '.$profile['tax_rate'].'%',
            ])
            ->values()
            ->all();
    }
}
