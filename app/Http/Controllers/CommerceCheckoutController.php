<?php

namespace App\Http\Controllers;

use App\Models\AdCampaign;
use App\Models\AdCreative;
use App\Models\AdEvent;
use App\Models\AdCampaignStat;
use App\Models\Club;
use App\Models\CommerceCart;
use App\Models\CommerceCartItem;
use App\Models\CommerceOrder;
use App\Models\CommerceOrderItem;
use App\Models\CommerceReturnRequest;
use App\Models\CommerceShippingAddress;
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
use App\Services\MediaOptimizer;
use App\Services\MarketplacePricingService;
use App\Services\ModerationService;
use App\Support\UploadStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CommerceCheckoutController extends Controller
{
    public function __construct(
        private ModerationService $moderation,
        private MarketplacePricingService $pricing,
        private CommerceAuditService $audit,
        private CommerceDocumentService $documents,
        private MediaOptimizer $mediaOptimizer,
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
                ->where('manages_stock', true)
                ->whereNotNull('stock_quantity')
                ->where('stock_quantity', '>', 0)
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
                ->with(['creatives', 'stats' => fn ($query) => $query->latest('date')->limit(30)])
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
            'profileAddress' => $this->profileAddressFor($request->user()),
            'shippingAddresses' => $this->shippingAddressesFor($request->user()),
        ]);
    }

    public function cart(Request $request)
    {
        return inertia('Auth/Dashboard/Commerce/Cart', [
            'authUser' => $request->user() ? [
                'id' => $request->user()->id,
                'name' => $request->user()->name,
                'email' => $request->user()->email,
            ] : null,
            'cart' => $this->cartResource($request),
            'pricingCountries' => $this->pricingCountries(),
            'checkoutAddress' => $this->shippingAddressForAuthenticatedUser($request, []),
            'profileAddress' => $this->profileAddressFor($request->user()),
            'shippingAddresses' => $this->shippingAddressesFor($request->user()),
            'marketplaceVisuals' => $this->marketplaceVisuals(),
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
        abort_unless($product->status === 'published' && $this->hasSellableStock($product), 404);
        $data = $request->validate([
            'provider' => ['required', Rule::in(['stripe', 'paypal', 'bank_transfer'])],
            'accepted_terms' => ['accepted'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'shipping_country' => ['nullable', 'string', 'size:2'],
            'shipping_state' => ['nullable', 'string', 'max:80'],
            'shipping_postal_code' => ['nullable', 'string', 'max:30'],
            'shipping_city' => ['nullable', 'string', 'max:120'],
            'shipping_street' => ['nullable', 'string', 'max:180'],
            'shipping_house_number' => ['nullable', 'string', 'max:40'],
            'customer_type' => ['nullable', Rule::in(['consumer', 'business'])],
            'customer_company' => ['nullable', 'string', 'max:255'],
            'customer_vat_id' => ['nullable', 'string', 'max:40'],
            'save_shipping_address' => ['boolean'],
            'shipping_address_label' => ['nullable', 'string', 'max:80'],
        ]);
        $quantity = (int) ($data['quantity'] ?? 1);
        if ((int) $product->stock_quantity < $quantity) {
            throw ValidationException::withMessages([
                'quantity' => 'So viele Artikel sind aktuell nicht auf Lager.',
            ]);
        }

        $shippingAddress = $this->shippingAddressForAuthenticatedUser($request, $data);
        $this->saveShippingAddressIfRequested($request, $data, $shippingAddress);
        $quote = $this->pricing->quoteForRequest($product, $request, $shippingAddress['country'], $shippingAddress);
        $quote = $this->quoteWithQuantity($quote, $quantity);
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
        $this->createOrderItem($order, $product, $quote, $quantity);

        return $this->startCheckout($order);
    }

    public function addCartItem(Request $request, MarketplaceProduct $product)
    {
        abort_unless($product->status === 'published', 404);
        abort_unless($this->hasSellableStock($product), 404);

        $data = $request->validate([
            'quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        $cart = $this->cartFor($request);
        $quantity = (int) ($data['quantity'] ?? 1);
        $item = $cart->items()->firstOrNew(['marketplace_product_id' => $product->id]);
        $item->quantity = (int) $item->quantity + $quantity;

        if ($item->quantity > (int) $product->stock_quantity) {
            throw ValidationException::withMessages([
                'quantity' => 'So viele Artikel sind aktuell nicht auf Lager.',
            ]);
        }

        $item->save();

        return back()->with('success', 'Artikel wurde in den Einkaufswagen gelegt.');
    }

    public function updateCartItem(Request $request, CommerceCartItem $item)
    {
        abort_unless((int) $item->cart->user_id === (int) $request->user()->id, 403);

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $product = $item->product;
        abort_unless($product && $this->hasSellableStock($product), 404);
        if ((int) $data['quantity'] > (int) $product->stock_quantity) {
            throw ValidationException::withMessages([
                'quantity' => 'So viele Artikel sind aktuell nicht auf Lager.',
            ]);
        }

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
        $this->saveShippingAddressIfRequested($request, $data, $shippingAddress);
        $customer = $this->customerFromData($data);
        $summary = $this->cartQuote($cart, $request, $shippingAddress, $customer);

        DB::transaction(function () use ($cart) {
            foreach ($cart->items as $item) {
                $product = MarketplaceProduct::query()->lockForUpdate()->findOrFail($item->marketplace_product_id);
                abort_unless($product->status === 'published' && $this->hasSellableStock($product), 422, $product->title.' ist nicht mehr verfuegbar.');
                abort_if((int) $product->stock_quantity < (int) $item->quantity, 422, $product->title.' ist nicht ausreichend auf Lager.');
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
            'attributes_text' => ['nullable', 'string', 'max:2000'],
            'attribute_options' => ['nullable', 'array', 'max:20'],
            'attribute_options.*.name' => ['nullable', 'string', 'max:80'],
            'attribute_options.*.values' => ['nullable', 'array', 'max:30'],
            'attribute_options.*.values.*' => ['nullable', 'string', 'max:80'],
            'variants' => ['nullable', 'array', 'max:80'],
            'variants.*.sku' => ['nullable', 'string', 'max:80'],
            'variants.*.price_cents' => ['nullable', 'integer', 'min:0'],
            'variants.*.stock_quantity' => ['nullable', 'integer', 'min:0'],
            'variants.*.image_url' => ['nullable', 'url', 'max:2048'],
            'variants.*.attributes' => ['nullable', 'array', 'max:20'],
            'variants.*.attributes.*.name' => ['nullable', 'string', 'max:80'],
            'variants.*.attributes.*.value' => ['nullable', 'string', 'max:80'],
            'image_url' => ['nullable', 'url', 'max:2048'],
            'image_urls_text' => ['nullable', 'string', 'max:4000'],
            'image_upload' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'image_uploads' => ['nullable', 'array', 'max:8'],
            'image_uploads.*' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'category' => ['required', Rule::in(['product', 'course', 'camp', 'service'])],
            'product_type' => ['nullable', Rule::in(['single', 'variable', 'digital'])],
            'sku' => ['nullable', 'string', 'max:80'],
            'is_shippable' => ['boolean'],
            'manages_stock' => ['boolean'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'tax_class' => ['nullable', 'string', 'max:30'],
            'return_policy_type' => ['nullable', Rule::in(['standard', 'digital', 'service', 'hygiene', 'custom'])],
            'return_window_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'digital_delivery_note' => ['nullable', 'string', 'max:2000'],
            'price_cents' => ['required', 'integer', 'min:0'],
        ]);

        if (! empty($data['club_id'])) {
            Club::query()->visibleTo($request->user())->findOrFail($data['club_id']);
        }
        $attributesText = (string) ($data['attributes_text'] ?? '');
        $imageUrlsText = (string) ($data['image_urls_text'] ?? '');
        $attributeOptions = $this->normalizeAttributeOptions($data['attribute_options'] ?? []);
        $variants = $this->normalizeVariants($data['variants'] ?? [], $attributeOptions, (int) $data['price_cents']);
        unset($data['attributes_text']);
        unset($data['attribute_options']);
        unset($data['variants']);
        unset($data['image_urls_text']);
        unset($data['image_upload']);
        unset($data['image_uploads']);

        $galleryImages = collect(preg_split('/\r\n|\r|\n/', $imageUrlsText))
            ->map(fn (string $url) => trim($url))
            ->filter()
            ->take(12)
            ->values()
            ->all();

        if ($request->hasFile('image_upload')) {
            $stored = app(MediaOptimizer::class)->store($request->file('image_upload'), 'marketplace/products');
            $data['image_url'] = UploadStorage::url($stored['path']);
        }

        foreach ($request->file('image_uploads', []) as $file) {
            if (! $file) {
                continue;
            }

            $stored = app(MediaOptimizer::class)->store($file, 'marketplace/products');
            $galleryImages[] = UploadStorage::url($stored['path']);
        }

        $galleryImages = collect([$data['image_url'] ?? null, ...$galleryImages])
            ->filter()
            ->unique()
            ->take(12)
            ->values()
            ->all();
        $data['gallery_images'] = $galleryImages;
        $data['image_url'] = $data['image_url'] ?: ($galleryImages[0] ?? null);

        $product = MarketplaceProduct::create([
            ...$data,
            'product_attributes' => $this->productAttributesFromText($attributesText),
            'attribute_options' => $attributeOptions,
            'variants' => ($data['product_type'] ?? 'single') === 'variable' ? $variants : [],
            'product_type' => $data['product_type'] ?? 'single',
            'user_id' => $request->user()->id,
            'currency' => 'EUR',
            'is_shippable' => ($data['product_type'] ?? 'single') === 'digital' ? false : (bool) ($data['is_shippable'] ?? $data['category'] === 'product'),
            'manages_stock' => (bool) ($data['manages_stock'] ?? false),
            'tax_class' => $data['tax_class'] ?? 'standard',
            'return_policy_type' => ($data['product_type'] ?? 'single') === 'digital' ? 'digital' : ($data['return_policy_type'] ?? 'standard'),
            'return_window_days' => ($data['product_type'] ?? 'single') === 'digital' ? 0 : (int) ($data['return_window_days'] ?? 14),
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
                'gallery_images' => $product->gallery_images ?: array_values(array_filter([$product->image_url])),
                'user' => $product->user,
                'club' => $product->club,
            'price' => $quote,
            ],
            'pricingCountries' => $this->pricingCountries(),
            'checkoutAddress' => $shippingAddress,
            'profileAddress' => $this->profileAddressFor($request->user()),
            'shippingAddresses' => $this->shippingAddressesFor($request->user()),
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
        abort_unless($order->status === 'completed', 422, 'Ruecksendungen sind nur für abgeschlossene Bestellungen moeglich.');

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
            'commerce_order_item_id' => ['nullable', 'integer', Rule::exists('commerce_order_items', 'id')],
            'quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        $item = $order->items()->when($data['commerce_order_item_id'] ?? null, fn ($query, $id) => $query->whereKey($id))->first();
        abort_if($item && ! $item->is_shippable, 422, 'Dieses Angebot ist nicht ruecksendepflichtig.');
        abort_if($item && ! $this->itemStillReturnable($item), 422, 'Die Ruecksendefrist für diesen Artikel ist abgelaufen oder ausgeschlossen.');

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
        $minimumBudget = (int) Setting::valueFor('ads_min_budget_cents', 1000);

        $data = $request->validate([
            'club_id' => ['nullable', Rule::exists('clubs', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'headline' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'primary_text' => ['nullable', 'string', 'max:500'],
            'target_url' => ['nullable', 'url', 'max:255'],
            'cta_label' => ['nullable', 'string', 'max:80'],
            'objective' => ['required', Rule::in(['traffic', 'awareness', 'leads', 'sales'])],
            'placement' => ['required', Rule::in(['marketplace_card', 'feed', 'sidebar', 'sponsor_section'])],
            'creative_format' => ['required', Rule::in(['feed_square', 'feed_portrait', 'story_vertical', 'banner_wide'])],
            'creative_image_url' => ['nullable', 'url', 'max:255'],
            'creative_image_upload' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'creatives' => ['nullable', 'array', 'max:6'],
            'creatives.*.name' => ['nullable', 'string', 'max:80'],
            'creatives.*.headline' => ['nullable', 'string', 'max:120'],
            'creatives.*.description' => ['nullable', 'string', 'max:2000'],
            'creatives.*.primary_text' => ['nullable', 'string', 'max:500'],
            'creatives.*.target_url' => ['nullable', 'url', 'max:255'],
            'creatives.*.cta_label' => ['nullable', 'string', 'max:80'],
            'creatives.*.creative_image_url' => ['nullable', 'url', 'max:255'],
            'creatives.*.weight' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'creatives.*.is_active' => ['boolean'],
            'audience_locations' => ['nullable', 'string', 'max:255'],
            'audience_interests' => ['nullable', 'string', 'max:500'],
            'audience_age_min' => ['nullable', 'integer', 'min:13', 'max:100'],
            'audience_age_max' => ['nullable', 'integer', 'min:13', 'max:100', 'gte:audience_age_min'],
            'budget_cents' => ['required', 'integer', 'min:'.$minimumBudget],
            'daily_budget_cents' => ['nullable', 'integer', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        $data['audience'] = [
            'locations' => $this->splitCampaignList($data['audience_locations'] ?? ''),
            'interests' => $this->splitCampaignList($data['audience_interests'] ?? ''),
            'age_min' => $data['audience_age_min'] ?? null,
            'age_max' => $data['audience_age_max'] ?? null,
        ];
        $creativeRows = $data['creatives'] ?? [];
        unset($data['audience_locations'], $data['audience_interests'], $data['audience_age_min'], $data['audience_age_max'], $data['creative_image_upload'], $data['creatives']);

        if ($request->hasFile('creative_image_upload')) {
            $data['creative_image_path'] = $this->mediaOptimizer->store($request->file('creative_image_upload'), 'ads/creatives')['path'];
            $data['creative_image_url'] = null;
        }

        $campaign = AdCampaign::create([
            ...$data,
            'user_id' => $request->user()->id,
            'daily_budget_cents' => (int) ($data['daily_budget_cents'] ?? 0),
            'billing_event' => 'impression',
            'status' => 'pending_review',
        ]);

        $this->syncAdCreatives($campaign, $creativeRows);

        return back()->with('success', 'Kampagne wurde zur Freigabe eingereicht.');
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
        abort_unless($order->status === 'completed', 422, 'Ruecksendungen sind nur für abgeschlossene Bestellungen moeglich.');

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

    public function activeAd(Request $request)
    {
        $placement = $request->string('placement')->toString();
        $objective = $request->string('objective')->toString();

        $campaigns = AdCampaign::query()
            ->where('status', 'active')
            ->whereColumn('spent_cents', '<', 'budget_cents')
            ->where(function ($query) {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->when($placement, fn ($query) => $query->where('placement', $placement))
            ->when($objective, fn ($query) => $query->where('objective', $objective))
            ->with('creatives')
            ->with(['stats' => fn ($query) => $query->where('date', today()->toDateString())])
            ->limit(50)
            ->get()
            ->filter(fn (AdCampaign $campaign) => $this->campaignHasBudgetFor($campaign, 'impression'));

        $campaign = $this->chooseCampaignForDelivery($campaigns);

        if (! $campaign) {
            return response()->json(null);
        }

        $creative = $this->chooseCreativeForDelivery($campaign);
        $this->recordAdEvent($request, $campaign, 'impression', 0, [], $creative);

        return response()->json([
            'id' => $campaign->id,
            'creative_id' => $creative?->id,
            'name' => $campaign->name,
            'creative_name' => $creative?->name,
            'headline' => $creative?->headline ?: ($campaign->headline ?: $campaign->name),
            'description' => $creative?->description ?: $campaign->description,
            'primary_text' => $creative?->primary_text ?: $campaign->primary_text,
            'cta_label' => $creative?->cta_label ?: ($campaign->cta_label ?: 'Mehr erfahren'),
            'objective' => $campaign->objective,
            'placement' => $campaign->placement,
            'creative_format' => $creative?->creative_format ?: $campaign->creative_format,
            'image_url' => $this->creativeImageUrl($creative) ?: (UploadStorage::url($campaign->creative_image_path) ?: $campaign->creative_image_url),
            'click_url' => route('ads.click', array_filter(['campaign' => $campaign->id, 'c' => $creative?->id])),
        ]);
    }

    public function clickAd(Request $request, AdCampaign $campaign)
    {
        if ($campaign->status === 'active' && $this->campaignHasBudgetFor($campaign, 'click')) {
            $this->recordAdEvent($request, $campaign, 'click', 0, [], $this->creativeFromRequest($request, $campaign));
        }

        $creative = $this->creativeFromRequest($request, $campaign);

        return redirect()->away($creative?->target_url ?: ($campaign->target_url ?: route('guest.pricing')));
    }

    public function conversionAd(Request $request, AdCampaign $campaign)
    {
        abort_unless($campaign->status === 'active', 404);

        $data = $request->validate([
            'event_type' => ['required', Rule::in(['lead', 'sale'])],
            'value_cents' => ['nullable', 'integer', 'min:0'],
            'metadata' => ['nullable', 'array'],
        ]);

        $creative = $this->creativeFromRequest($request, $campaign, $data['metadata']['creative_id'] ?? null);
        $this->recordAdEvent($request, $campaign, $data['event_type'], (int) ($data['value_cents'] ?? 0), $data['metadata'] ?? [], $creative);

        return response()->json(['ok' => true]);
    }

    private function trackAdStat(AdCampaign $campaign, ?string $metric, int $costCents = 0): void
    {
        $stat = AdCampaignStat::query()->firstOrCreate([
            'ad_campaign_id' => $campaign->id,
            'date' => today()->toDateString(),
        ]);

        if (in_array($metric, ['impressions', 'clicks'], true)) {
            $stat->increment($metric);
        }

        if ($costCents > 0) {
            $stat->increment('spent_cents', $costCents);
        }
    }

    private function recordAdEvent(Request $request, AdCampaign $campaign, string $eventType, int $valueCents = 0, array $metadata = [], ?AdCreative $creative = null): ?AdEvent
    {
        if ($this->isDuplicateAdEvent($request, $campaign, $eventType, $creative)) {
            return null;
        }

        $costCents = $this->costForAdEvent($campaign, $eventType, $valueCents);

        if ($costCents > 0 && ! $this->campaignHasBudgetFor($campaign, $eventType, $costCents)) {
            return null;
        }

        $event = AdEvent::create([
            'ad_campaign_id' => $campaign->id,
            'ad_creative_id' => $creative?->id,
            'user_id' => $request->user()?->id,
            'event_type' => $eventType,
            'objective' => $campaign->objective,
            'placement' => $campaign->placement,
            'cost_cents' => $costCents,
            'value_cents' => $valueCents,
            'currency' => 'EUR',
            'session_hash' => $this->adHash($request->session()->getId()),
            'ip_hash' => $this->adHash($request->ip()),
            'user_agent_hash' => $this->adHash((string) $request->userAgent()),
            'metadata' => $metadata,
            'occurred_at' => now(),
        ]);

        match ($eventType) {
            'impression' => $campaign->increment('impressions'),
            'click' => $campaign->increment('clicks'),
            default => null,
        };

        if ($creative && in_array($eventType, ['impression', 'click'], true)) {
            $creative->increment($eventType === 'impression' ? 'impressions' : 'clicks');
        }

        $this->trackAdStat($campaign, $eventType === 'impression' ? 'impressions' : ($eventType === 'click' ? 'clicks' : null), $costCents);

        if ($costCents > 0) {
            $campaign->increment('spent_cents', $costCents);
            $creative?->increment('spent_cents', $costCents);
        }

        return $event;
    }

    private function costForAdEvent(AdCampaign $campaign, string $eventType, int $valueCents = 0): int
    {
        return match ($eventType) {
            'impression' => $this->cpmImpressionCost($campaign),
            'click' => $campaign->objective === 'traffic' ? $this->adPrice('ads_cpc_cents', 30) : 0,
            'lead' => $campaign->objective === 'leads' ? $this->adPrice('ads_cpl_cents', 200) : 0,
            'sale' => $campaign->objective === 'sales' ? (int) round($valueCents * ($this->adPrice('ads_cpa_percent', 10) / 100)) : 0,
            default => 0,
        };
    }

    private function cpmImpressionCost(AdCampaign $campaign): int
    {
        if ($campaign->objective !== 'awareness') {
            return 0;
        }

        $cpmCents = max(1, $this->adPrice('ads_cpm_cents', 500));
        $interval = max(1, (int) floor(1000 / $cpmCents));
        $todayImpressions = (int) $this->todayAdStat($campaign)?->impressions;

        return (($todayImpressions + 1) % $interval) === 0 ? 1 : 0;
    }

    private function campaignHasBudgetFor(AdCampaign $campaign, string $eventType, ?int $costCents = null): bool
    {
        $cost = $costCents ?? $this->costForAdEvent($campaign, $eventType);
        $remaining = max(0, (int) $campaign->budget_cents - (int) $campaign->spent_cents);

        if ($cost > $remaining) {
            return false;
        }

        if ((int) $campaign->daily_budget_cents <= 0) {
            return true;
        }

        $todaySpent = (int) ($this->todayAdStat($campaign)?->spent_cents ?? 0);

        return ($todaySpent + $cost) <= (int) $campaign->daily_budget_cents;
    }

    private function todayAdStat(AdCampaign $campaign): ?AdCampaignStat
    {
        if ($campaign->relationLoaded('stats')) {
            return $campaign->stats->firstWhere('date', today()->toDateString()) ?: $campaign->stats->first();
        }

        return $campaign->stats()->where('date', today()->toDateString())->first();
    }

    private function chooseCampaignForDelivery($campaigns): ?AdCampaign
    {
        $weighted = $campaigns
            ->map(fn (AdCampaign $campaign) => ['campaign' => $campaign, 'weight' => $this->campaignDeliveryWeight($campaign)])
            ->filter(fn (array $item) => $item['weight'] > 0)
            ->values();

        $total = (int) $weighted->sum('weight');

        if ($total <= 0) {
            return null;
        }

        $pick = random_int(1, $total);

        foreach ($weighted as $item) {
            $pick -= $item['weight'];

            if ($pick <= 0) {
                return $item['campaign'];
            }
        }

        return $weighted->first()['campaign'] ?? null;
    }

    private function campaignDeliveryWeight(AdCampaign $campaign): int
    {
        $impressions = max(1, (int) $campaign->impressions);
        $clicks = (int) $campaign->clicks;
        $ctr = $clicks / $impressions;
        $budgetRemainingRatio = max(0.1, ((int) $campaign->budget_cents - (int) $campaign->spent_cents) / max(1, (int) $campaign->budget_cents));
        $freshnessBoost = $impressions < 100 ? 1.5 : 1;

        $objectiveWeight = match ($campaign->objective) {
            'traffic' => 1 + min(3, $ctr * 100),
            'awareness' => 1 + min(2, 100 / $impressions),
            'leads', 'sales' => 1 + min(2, $ctr * 60),
            default => 1,
        };

        return max(1, (int) round(100 * $objectiveWeight * $budgetRemainingRatio * $freshnessBoost));
    }

    private function chooseCreativeForDelivery(AdCampaign $campaign): ?AdCreative
    {
        $creatives = $campaign->relationLoaded('creatives')
            ? $campaign->creatives
            : $campaign->creatives()->get();

        $weighted = $creatives
            ->filter(fn (AdCreative $creative) => $creative->is_active)
            ->map(function (AdCreative $creative) {
                $impressions = max(1, (int) $creative->impressions);
                $clicks = (int) $creative->clicks;
                $ctrBoost = 1 + min(3, ($clicks / $impressions) * 100);
                $freshnessBoost = $impressions < 50 ? 1.4 : 1;

                return [
                    'creative' => $creative,
                    'weight' => max(1, (int) round((int) $creative->weight * $ctrBoost * $freshnessBoost)),
                ];
            })
            ->values();

        $total = (int) $weighted->sum('weight');

        if ($total <= 0) {
            return null;
        }

        $pick = random_int(1, $total);

        foreach ($weighted as $item) {
            $pick -= $item['weight'];

            if ($pick <= 0) {
                return $item['creative'];
            }
        }

        return $weighted->first()['creative'] ?? null;
    }

    private function creativeFromRequest(Request $request, AdCampaign $campaign, mixed $fallbackId = null): ?AdCreative
    {
        $creativeId = $request->query('c') ?: $request->input('creative_id') ?: $fallbackId;

        if (! $creativeId) {
            return null;
        }

        return AdCreative::query()
            ->where('ad_campaign_id', $campaign->id)
            ->whereKey($creativeId)
            ->first();
    }

    private function creativeImageUrl(?AdCreative $creative): ?string
    {
        if (! $creative) {
            return null;
        }

        return UploadStorage::url($creative->creative_image_path) ?: $creative->creative_image_url;
    }

    private function syncAdCreatives(AdCampaign $campaign, array $creativeRows): void
    {
        $rows = collect($creativeRows)
            ->map(fn (array $row, int $index) => [
                'name' => filled($row['name'] ?? null) ? trim((string) $row['name']) : 'Variante '.chr(65 + $index),
                'headline' => filled($row['headline'] ?? null) ? trim((string) $row['headline']) : null,
                'description' => filled($row['description'] ?? null) ? trim((string) $row['description']) : null,
                'primary_text' => filled($row['primary_text'] ?? null) ? trim((string) $row['primary_text']) : null,
                'target_url' => filled($row['target_url'] ?? null) ? trim((string) $row['target_url']) : null,
                'cta_label' => filled($row['cta_label'] ?? null) ? trim((string) $row['cta_label']) : null,
                'creative_format' => $campaign->creative_format,
                'creative_image_url' => filled($row['creative_image_url'] ?? null) ? trim((string) $row['creative_image_url']) : null,
                'weight' => max(1, (int) ($row['weight'] ?? 100)),
                'is_active' => (bool) ($row['is_active'] ?? true),
            ])
            ->filter(fn (array $row) => filled($row['headline']) || filled($row['primary_text']) || filled($row['creative_image_url']))
            ->values();

        if ($rows->isEmpty()) {
            $rows = collect([[
                'name' => 'Variante A',
                'headline' => $campaign->headline,
                'description' => $campaign->description,
                'primary_text' => $campaign->primary_text,
                'target_url' => $campaign->target_url,
                'cta_label' => $campaign->cta_label,
                'creative_format' => $campaign->creative_format,
                'creative_image_path' => $campaign->creative_image_path,
                'creative_image_url' => $campaign->creative_image_url,
                'weight' => 100,
                'is_active' => true,
            ]]);
        }

        $rows->each(fn (array $row) => $campaign->creatives()->create($row));
    }

    private function isDuplicateAdEvent(Request $request, AdCampaign $campaign, string $eventType, ?AdCreative $creative = null): bool
    {
        $windowMinutes = match ($eventType) {
            'impression' => 10,
            'click' => 30,
            default => 60,
        };

        return AdEvent::query()
            ->where('ad_campaign_id', $campaign->id)
            ->when($creative, fn ($query) => $query->where('ad_creative_id', $creative->id))
            ->where('event_type', $eventType)
            ->where('session_hash', $this->adHash($request->session()->getId()))
            ->where('occurred_at', '>=', now()->subMinutes($windowMinutes))
            ->exists();
    }

    private function adPrice(string $key, int $default): int
    {
        return max(0, (int) Setting::valueFor($key, $default));
    }

    private function adHash(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        return hash_hmac('sha256', $value, (string) config('app.key'));
    }

    private function splitCampaignList(?string $value): array
    {
        return collect(preg_split('/[,;\n]+/', (string) $value))
            ->map(fn ($item) => trim($item))
            ->filter()
            ->values()
            ->all();
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

                    if (! $product) {
                        continue;
                    }

                    abort_unless($this->hasSellableStock($product), 422, $product->title.' ist nicht mehr verfuegbar.');
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
            ->withBody('{}', 'application/json')
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

    private function profileAddressFor(?\App\Models\User $user): ?array
    {
        if (! $user) {
            return null;
        }

        return $this->addressResource([
            'id' => 'profile',
            'label' => 'Meine Adresse',
            'country' => $user->country ?: 'DE',
            'state' => $user->state,
            'postal_code' => $user->postal_code,
            'city' => $user->city,
            'street' => $user->street,
            'house_number' => $user->house_number,
            'is_default' => false,
        ]);
    }

    private function shippingAddressesFor(?\App\Models\User $user): array
    {
        if (! $user) {
            return [];
        }

        return CommerceShippingAddress::query()
            ->where('user_id', $user->id)
            ->orderByDesc('is_default')
            ->latest('id')
            ->get()
            ->map(fn (CommerceShippingAddress $address) => $this->addressResource($address->toArray()))
            ->all();
    }

    private function addressResource(array $address): array
    {
        $lineOne = trim(implode(' ', array_filter([
            $address['street'] ?? '',
            $address['house_number'] ?? '',
        ])));
        $lineTwo = trim(implode(' ', array_filter([
            $address['postal_code'] ?? '',
            $address['city'] ?? '',
        ])));

        return [
            'id' => $address['id'] ?? null,
            'label' => $address['label'] ?: ($lineOne ?: 'Lieferadresse'),
            'country' => strtoupper((string) ($address['country'] ?? 'DE')),
            'state' => trim((string) ($address['state'] ?? '')),
            'postal_code' => trim((string) ($address['postal_code'] ?? '')),
            'city' => trim((string) ($address['city'] ?? '')),
            'street' => trim((string) ($address['street'] ?? '')),
            'house_number' => trim((string) ($address['house_number'] ?? '')),
            'is_default' => (bool) ($address['is_default'] ?? false),
            'summary' => trim(implode(', ', array_filter([$lineOne, $lineTwo, strtoupper((string) ($address['country'] ?? 'DE'))]))),
        ];
    }

    private function saveShippingAddressIfRequested(Request $request, array $data, array $address): void
    {
        $user = $request->user();

        if (! $user || ! (bool) ($data['save_shipping_address'] ?? false)) {
            return;
        }

        $hasAddressDetails = filled($address['street'] ?? null)
            || filled($address['postal_code'] ?? null)
            || filled($address['city'] ?? null);

        if (! $hasAddressDetails) {
            return;
        }

        CommerceShippingAddress::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'country' => $address['country'],
                'postal_code' => $address['postal_code'],
                'city' => $address['city'],
                'street' => $address['street'],
                'house_number' => $address['house_number'],
            ],
            [
                'label' => trim((string) ($data['shipping_address_label'] ?? '')) ?: 'Lieferadresse',
                'state' => $address['state'],
            ],
        );
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

    private function productAttributesFromText(string $text): array
    {
        return collect(preg_split('/\r\n|\r|\n/', $text))
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->map(function (string $line) {
                [$name, $value] = array_pad(preg_split('/[:=]/', $line, 2), 2, '');

                return [
                    'name' => trim($name),
                    'value' => trim($value),
                ];
            })
            ->filter(fn (array $attribute) => filled($attribute['name']) && filled($attribute['value']))
            ->take(20)
            ->values()
            ->all();
    }

    private function normalizeAttributeOptions(array $options): array
    {
        return collect($options)
            ->map(function (array $option) {
                $values = collect($option['values'] ?? [])
                    ->map(fn ($value) => trim((string) $value))
                    ->filter()
                    ->unique()
                    ->take(30)
                    ->values()
                    ->all();

                return [
                    'name' => trim((string) ($option['name'] ?? '')),
                    'values' => $values,
                ];
            })
            ->filter(fn (array $option) => filled($option['name']) && $option['values'] !== [])
            ->take(20)
            ->values()
            ->all();
    }

    private function normalizeVariants(array $variants, array $attributeOptions, int $fallbackPriceCents): array
    {
        $allowed = collect($attributeOptions)
            ->mapWithKeys(fn (array $option) => [$option['name'] => $option['values']])
            ->all();

        return collect($variants)
            ->map(function (array $variant) use ($allowed, $fallbackPriceCents) {
                $attributes = collect($variant['attributes'] ?? [])
                    ->map(fn (array $attribute) => [
                        'name' => trim((string) ($attribute['name'] ?? '')),
                        'value' => trim((string) ($attribute['value'] ?? '')),
                    ])
                    ->filter(fn (array $attribute) => filled($attribute['name'])
                        && filled($attribute['value'])
                        && in_array($attribute['value'], $allowed[$attribute['name']] ?? [], true))
                    ->values()
                    ->all();

                return [
                    'sku' => filled($variant['sku'] ?? null) ? trim((string) $variant['sku']) : null,
                    'price_cents' => (int) ($variant['price_cents'] ?? $fallbackPriceCents),
                    'stock_quantity' => ($variant['stock_quantity'] ?? null) === null || ($variant['stock_quantity'] ?? '') === ''
                        ? null
                        : max(0, (int) $variant['stock_quantity']),
                    'image_url' => filled($variant['image_url'] ?? null) ? trim((string) $variant['image_url']) : null,
                    'attributes' => $attributes,
                ];
            })
            ->filter(fn (array $variant) => $variant['attributes'] !== [])
            ->take(80)
            ->values()
            ->all();
    }

    private function quoteWithQuantity(array $quote, int $quantity): array
    {
        $quantity = max(1, $quantity);

        $quote['item_gross_cents'] = (int) ($quote['item_gross_cents'] ?? $quote['gross_cents'] ?? 0) * $quantity;
        $quote['item_net_cents'] = (int) ($quote['item_net_cents'] ?? $quote['net_cents'] ?? 0) * $quantity;
        $quote['item_tax_cents'] = (int) ($quote['item_tax_cents'] ?? $quote['tax_cents'] ?? 0) * $quantity;
        $quote['gross_cents'] = $quote['item_gross_cents'] + (int) ($quote['shipping_gross_cents'] ?? 0);
        $quote['net_cents'] = $quote['item_net_cents'] + (int) ($quote['shipping_net_cents'] ?? 0);
        $quote['tax_cents'] = $quote['item_tax_cents'] + (int) ($quote['shipping_tax_cents'] ?? 0);

        return $quote;
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
        $summaryForClient = $summary;
        unset($summaryForClient['items']);

        return [
            'id' => $cart->id,
            'items_count' => $cart->items->count(),
            'items' => collect($summary['items'] ?? [])->map(function (array $summaryItem) {
                /** @var CommerceCartItem $item */
                $item = $summaryItem['cart_item'];
                /** @var MarketplaceProduct $product */
                $product = $summaryItem['product'];
                $quote = $summaryItem['quote'];

                return [
                    'id' => $item->id,
                    'quantity' => (int) $summaryItem['quantity'],
                    'line_total_cents' => (int) ($quote['item_gross_cents'] ?? ((int) $product->price_cents * (int) $summaryItem['quantity'])),
                    'product' => [
                        'id' => $product->id,
                        'title' => $product->title,
                        'description' => $product->description,
                        'category' => $product->category,
                        'sku' => $product->sku,
                        'image_url' => $product->image_url,
                        'price_cents' => $product->price_cents,
                        'currency' => $product->currency,
                        'stock_quantity' => $product->stock_quantity,
                        'show_url' => route('auth.commerce.products.show', $product),
                    ],
                ];
            })->values(),
            'summary' => $summaryForClient,
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
            if (! $product || $product->status !== 'published' || ! $this->hasSellableStock($product)) {
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
            $items[] = ['cart_item' => $cartItem, 'product' => $product, 'quantity' => $quantity, 'quote' => $quote];
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

    private function hasSellableStock(MarketplaceProduct $product): bool
    {
        return (bool) $product->manages_stock && $product->stock_quantity !== null && (int) $product->stock_quantity > 0;
    }

    private function marketplaceVisuals(): array
    {
        $defaults = [
            'side_banner' => '/images/marketplace/airmius-marketplace-side-banner.png',
            'hero_banner' => '',
            'sale_banner' => '',
        ];

        $visuals = collect($defaults)
            ->mapWithKeys(fn (string $default, string $key) => [
                $key => UploadStorage::url(Setting::valueFor('marketplace_visual_'.$key, $default)),
            ])
            ->all();

        $visuals['dimensions'] = [
            'side_banner' => [
                'width' => (int) Setting::valueFor('marketplace_visual_side_banner_width', 306),
                'height' => (int) Setting::valueFor('marketplace_visual_side_banner_height', 786),
            ],
        ];

        return $visuals;
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
