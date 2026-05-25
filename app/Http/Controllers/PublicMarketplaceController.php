<?php

namespace App\Http\Controllers;

use App\Models\CommerceCart;
use App\Models\CommerceOrder;
use App\Models\CommerceShippingAddress;
use App\Models\Club;
use App\Models\LearningCoupon;
use App\Models\MarketplaceProduct;
use App\Models\MarketplaceProductInventory;
use App\Models\OutfitSubscriptionPlan;
use App\Models\Setting;
use App\Models\User;
use App\Services\MarketplacePricingService;
use App\Support\CommerceOrderNotifier;
use App\Support\EuVatId;
use App\Support\UploadStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PublicMarketplaceController extends Controller
{
    public function __construct(private MarketplacePricingService $pricing) {}

    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'in:product,course,camp,service,outfit_subscription'],
            'segment' => ['nullable', 'in:shoes,apparel,equipment,recovery,analysis,nutrition,plans,camps,team'],
            'country' => ['nullable', 'string', 'size:2'],
            'sort' => ['nullable', 'in:recommended,newest,price_asc,price_desc'],
            'availability' => ['nullable', 'in:available,digital,shippable'],
        ]);
        $country = strtoupper((string) ($filters['country'] ?? $request->user()?->country ?: 'DE'));
        $productCardCache = [];
        $productCard = function (MarketplaceProduct $product) use (&$productCardCache, $request, $country): array {
            return $productCardCache[$product->id] ??= $this->productCard($product, $request, $country);
        };

        $products = $this->applyMarketplaceSort($this->marketplaceProductQuery($filters, $country), $filters['sort'] ?? 'recommended')
            ->paginate(24)
            ->withQueryString()
            ->through(fn (MarketplaceProduct $product) => $productCard($product));

        $featuredProducts = $this->marketplaceProductQuery([], $country)
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn (MarketplaceProduct $product) => $productCard($product));

        $flashDeals = $this->marketplaceProductQuery([], $country)
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn (MarketplaceProduct $product) => $productCard($product));

        $essentialDeals = $this->marketplaceProductQuery(['category' => 'product'], $country)
            ->latest('id')
            ->skip(8)
            ->limit(8)
            ->get()
            ->map(fn (MarketplaceProduct $product) => $productCard($product));

        $learningDeals = $this->marketplaceProductQuery([], $country)
            ->whereIn('category', ['course', 'camp'])
            ->latest('id')
            ->limit(6)
            ->get()
            ->map(fn (MarketplaceProduct $product) => $productCard($product));

        $serviceDeals = $this->marketplaceProductQuery(['category' => 'service'], $country)
            ->latest('id')
            ->limit(6)
            ->get()
            ->map(fn (MarketplaceProduct $product) => $productCard($product));

        $outfitPlans = collect();

        if ((! isset($filters['category']) || in_array($filters['category'], ['', 'outfit_subscription'], true))
            && (! isset($filters['segment']) || in_array($filters['segment'], ['', 'apparel'], true))) {
            $outfitPlans = OutfitSubscriptionPlan::query()
                ->with('sponsor:id,name,logo,website')
                ->where('is_active', true)
                ->where('is_public', true)
                ->when($filters['search'] ?? null, function ($query, $search) {
                    $query->where(function ($query) use ($search) {
                        $query
                            ->where('name', 'like', '%'.$search.'%')
                            ->orWhere('description', 'like', '%'.$search.'%');
                    });
                })
                ->orderBy('sort_order')
                ->orderBy('monthly_price_cents')
                ->limit(8)
                ->get()
                ->map(fn (OutfitSubscriptionPlan $plan) => $this->outfitPlanCard($plan, $country));
        }

        return Inertia::render('Guest/Marketplace', [
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register'),
            'authUser' => $this->authUser($request),
            'cart' => $this->cartBadge($request),
            'filters' => $filters,
            'products' => $products,
            'featuredProducts' => $featuredProducts,
            'flashDeals' => $flashDeals,
            'essentialDeals' => $essentialDeals,
            'learningDeals' => $learningDeals,
            'serviceDeals' => $serviceDeals,
            'outfitPlans' => $outfitPlans,
            'sportCategories' => $this->sportCategories(),
            'officialStores' => $this->officialStores(),
            'categories' => [
                ['value' => '', 'label' => 'Alle'],
                ['value' => 'product', 'label' => 'Produkte'],
                ['value' => 'outfit_subscription', 'label' => 'Outfit-Abos'],
                ['value' => 'course', 'label' => 'Kurse'],
                ['value' => 'camp', 'label' => 'Camps'],
                ['value' => 'service', 'label' => 'Services'],
            ],
            'segments' => $this->segments(),
            'sortOptions' => $this->sortOptions(),
            'availabilityOptions' => $this->availabilityOptions(),
            'trustBenefits' => $this->trustBenefits(),
            'pricingCountries' => $this->pricingCountries(),
            'marketplaceVisuals' => $this->marketplaceVisuals(),
        ]);
    }

    public function learning(Request $request)
    {
        $country = strtoupper((string) $request->input('country', $request->user()?->country ?: 'DE'));
        $learningProducts = $this->marketplaceProductQuery([], $country)
            ->whereIn('offer_type', ['online_course', 'training_plan'])
            ->latest('id')
            ->limit(24)
            ->get()
            ->map(fn (MarketplaceProduct $product) => $this->productCard($product, $request, $country))
            ->values();

        return Inertia::render('Guest/E-Learning', [
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register'),
            'authUser' => $this->authUser($request),
            'cart' => $this->cartBadge($request),
            'learningProducts' => $learningProducts,
        ]);
    }

    public function provider(Request $request, string $type, int $id)
    {
        abort_unless(in_array($type, ['club', 'user'], true), 404);

        $provider = $type === 'club'
            ? Club::query()->select('id', 'name', 'logo', 'cover_image', 'city', 'country', 'verification_status', 'sport_type')->findOrFail($id)
            : User::query()->select('id', 'name', 'city', 'country', 'bio')->findOrFail($id);

        $country = strtoupper((string) $request->input('country', $request->user()?->country ?: 'DE'));
        $query = $this->marketplaceProductQuery([], $country);

        $type === 'club'
            ? $query->where('club_id', $provider->id)
            : $query->where('user_id', $provider->id)->whereNull('club_id');

        $products = $query
            ->latest('id')
            ->paginate(24)
            ->withQueryString()
            ->through(fn (MarketplaceProduct $product) => $this->productCard($product, $request, $country));

        $providerProfile = $this->providerProfileFromModel($provider, $type);

        return Inertia::render('Guest/MarketplaceProviderShow', [
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register'),
            'authUser' => $this->authUser($request),
            'cart' => $this->cartBadge($request),
            'provider' => [
                ...$providerProfile,
                'description' => $type === 'club'
                    ? trim((string) ($provider->sport_type ? 'Sport: '.$provider->sport_type : 'Marketplace-Anbieter auf Airmius.'))
                    : trim((string) ($provider->bio ?: 'Marketplace-Anbieter auf Airmius.')),
                'cover_url' => $type === 'club' && $provider->cover_image ? UploadStorage::url($provider->cover_image) : null,
            ],
            'products' => $products,
            'marketplaceVisuals' => $this->marketplaceVisuals(),
        ]);
    }

    private function marketplaceProductQuery(array $filters = [], ?string $country = null)
    {
        $country = strtoupper((string) ($country ?: ($filters['country'] ?? 'DE')));

        return MarketplaceProduct::query()
            ->with([
                'user:id,name,city,country',
                'club:id,name,logo,city,country,verification_status',
                'inventories' => fn ($query) => $query->where('is_active', true),
            ])
            ->where('status', 'published')
            ->where(fn ($query) => $query
                ->whereIn('offer_type', ['online_course', 'training_plan', 'service'])
                ->orWhere('product_type', 'digital')
                ->orWhere(fn ($query) => $query
                    ->where('manages_stock', true)
                    ->where(fn ($stockQuery) => $stockQuery
                        ->whereHas('inventories', fn ($inventoryQuery) => $inventoryQuery->availableForCountry($country))
                        ->orWhere(fn ($legacyQuery) => $legacyQuery
                            ->whereDoesntHave('inventories')
                            ->whereNotNull('stock_quantity')
                            ->where('stock_quantity', '>', 0))))
                ->orWhere(fn ($query) => $query
                    ->where('manages_stock', false)
                    ->whereIn('category', ['camp', 'service'])))
            ->where(fn ($query) => $query
                ->whereIn('offer_type', ['online_course', 'training_plan', 'service'])
                ->orWhere('product_type', 'digital')
                ->orWhereNull('available_countries')
                ->orWhereJsonLength('available_countries', 0)
                ->orWhereJsonContains('available_countries', $country))
            ->when(($filters['category'] ?? null) && $filters['category'] !== 'outfit_subscription', fn ($query, $category) => $query->where('category', $category))
            ->when(($filters['category'] ?? null) === 'outfit_subscription', fn ($query) => $query->whereRaw('1 = 0'))
            ->when($filters['segment'] ?? null, fn ($query, $segment) => $this->applySegmentFilter($query, $segment))
            ->when($filters['availability'] ?? null, fn ($query, $availability) => $this->applyAvailabilityFilter($query, $availability))
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('title', 'like', '%'.$search.'%')
                        ->orWhere('description', 'like', '%'.$search.'%');
                });
            });
    }

    public function show(Request $request, MarketplaceProduct $product)
    {
        abort_unless($product->status === 'published', 404);

        $product->load([
            'user:id,name,city,country',
            'club:id,name,logo,city,country,verification_status',
            'inventories' => fn ($query) => $query->where('is_active', true),
        ]);
        $initialAddress = $this->shippingAddressFromData([
            'shipping_country' => $request->input('shipping_country', $request->user()?->country ?: 'DE'),
            'shipping_state' => $request->user()?->state,
            'shipping_postal_code' => $request->user()?->postal_code,
            'shipping_city' => $request->user()?->city,
            'shipping_street' => $request->user()?->street,
            'shipping_house_number' => $request->user()?->house_number,
        ]);
        abort_unless($this->hasSellableStock($product, $initialAddress['country']), 404);

        $initialInventory = $this->fulfillmentInventoryFor($product, $initialAddress['country'], 1);
        $quote = $this->pricing->quoteForRequest($product, $request, $initialAddress['country'], [
            ...$initialAddress,
            'origin_country' => $initialInventory?->warehouse?->country_code,
        ]);
        $relatedProducts = $this->relatedProducts($product, $request, $initialAddress['country']);
        app(CommerceCheckoutController::class)->rememberMarketplaceInterest($request, $product, 'view');

        return Inertia::render('Guest/MarketplaceProductShow', [
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register'),
            'authUser' => $this->authUser($request),
            'cart' => $this->cartBadge($request),
            'product' => [
                'id' => $product->id,
                'title' => $product->title,
                'description' => $product->description,
                'features' => $product->features ?: [],
                'product_attributes' => $product->product_attributes ?: [],
                'offer_type' => $product->offer_type ?: 'physical_product',
                'learning_course_id' => $product->learning_course_id,
                'course_outline' => $product->course_outline ?: [],
                'learning_goals' => $product->learning_goals ?: [],
                'coaching_enabled' => (bool) $product->coaching_enabled,
                'coach_feedback_instructions' => $product->coach_feedback_instructions,
                'image_url' => $product->image_url,
                'gallery_images' => $product->gallery_images ?: array_values(array_filter([$product->image_url])),
                'category' => $product->category,
                'sku' => $product->sku,
                'is_shippable' => (bool) $product->is_shippable,
                'manages_stock' => (bool) $product->manages_stock,
                'stock_quantity' => $this->clientStockQuantity($product, $initialAddress['country']),
                'return_policy_type' => $product->return_policy_type,
                'return_window_days' => $product->return_window_days,
                'price_cents' => $product->price_cents,
                'currency' => $product->currency,
                'price' => $quote,
                'provider_name' => $product->club?->name ?: $product->user?->name,
                'provider_type' => $this->providerType($product),
                'provider_profile' => $this->providerProfile($product),
                'delivery_label' => $this->deliveryLabel($product),
                'return_label' => $this->returnLabel($product),
                'trust_badges' => $this->trustBadges($product),
            ],
            'pricingCountries' => $this->pricingCountries(),
            'checkoutAddress' => $initialAddress,
            'profileAddress' => $this->profileAddressFor($request->user()),
            'shippingAddresses' => $this->shippingAddressesFor($request->user()),
            'paymentProviders' => $this->paymentProviders(),
            'marketplaceVisuals' => $this->marketplaceVisuals(),
            'relatedProducts' => $relatedProducts,
        ]);
    }

    public function checkout(Request $request, MarketplaceProduct $product)
    {
        abort_unless($product->status === 'published', 404);

        if ($product->learning_course_id && ! $request->user()) {
            throw ValidationException::withMessages([
                'login_checkout' => 'Bitte melde dich an, damit der Kurs nach der Zahlung deinem Konto zugeordnet werden kann.',
            ]);
        }

        $enabledProviders = array_column($this->paymentProviders(), 'value');

        if ($enabledProviders === []) {
            throw ValidationException::withMessages([
                'provider' => 'Aktuell ist noch keine Zahlungsart für den Marketplace konfiguriert.',
            ]);
        }

        $data = $request->validate([
            'guest_name' => ['required_without:login_checkout', 'nullable', 'string', 'max:255'],
            'guest_email' => ['required_without:login_checkout', 'nullable', 'email', 'max:255'],
            'provider' => ['required', Rule::in($enabledProviders)],
            'accepted_terms' => ['accepted'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'shipping_country' => ['required', 'string', 'size:2'],
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
            'coupon_code' => ['nullable', 'string', 'max:40'],
        ]);
        $quantity = (int) ($data['quantity'] ?? 1);
        $shippingAddress = $this->shippingAddressFromData($data);

        if (! $this->hasSellableStock($product, $shippingAddress['country'], $quantity)) {
            throw ValidationException::withMessages([
                'quantity' => 'So viele Artikel sind aktuell nicht auf Lager.',
            ]);
        }
        $fulfillmentInventory = $this->fulfillmentInventoryFor($product, $shippingAddress['country'], $quantity);

        $this->saveShippingAddressIfRequested($request, $data, $shippingAddress);
        $customer = $this->customerFromData($data);
        $quote = $this->pricing->quoteForRequest($product, $request, $shippingAddress['country'], [
            ...$shippingAddress,
            'origin_country' => $fulfillmentInventory?->warehouse?->country_code,
        ]);
        $quote = $this->quoteWithQuantity($quote, $quantity);
        $quote = $this->applyLearningCoupon($product, $quote, $data['coupon_code'] ?? null);
        $quote['fulfillment_inventory'] = $fulfillmentInventory ? [
            'id' => $fulfillmentInventory->id,
            'commerce_warehouse_id' => $fulfillmentInventory->commerce_warehouse_id,
            'country_code' => $fulfillmentInventory->country_code,
        ] : null;
        $orderAmounts = $this->orderAmountsFromQuote($quote, $customer);

        if ($request->user()) {
            $order = CommerceOrder::create([
                'user_id' => $request->user()->id,
                'club_id' => $product->club_id,
                'orderable_type' => $product::class,
                'orderable_id' => $product->id,
                'type' => 'marketplace_product',
                'provider' => $data['provider'],
                ...$orderAmounts,
                'commission_cents' => $this->pricing->commissionCents($product, (int) $quote['item_gross_cents']),
                'currency' => $quote['currency'],
                'tax_country' => $quote['country'],
                'tax_rate_percent' => $quote['tax_rate'],
                'customer_type' => $customer['type'],
                'customer_company' => $customer['company'] ?: null,
                'customer_vat_id' => $customer['vat_id'] ?: null,
                'customer_vat_is_valid' => $customer['vat_id'] ? $customer['vat_id_is_valid'] : null,
                'customer_vat_validated_at' => $customer['vat_id'] ? now() : null,
                'status' => 'pending',
                'payload' => ['pricing' => $quote, 'shipping_address' => $shippingAddress],
            ]);
            $this->createOrderItem($order, $product, $quote, $quantity);
            app(CommerceOrderNotifier::class)->notifySalesRecipients($order);
            app(CommerceCheckoutController::class)->rememberMarketplaceInterest($request, $product, 'checkout_started');
            app(CommerceCheckoutController::class)->trackAttributedAdConversion($request, 'checkout_started', (int) ($quote['gross_cents'] ?? $order->amount_cents), [
                'order_id' => $order->id,
                'product_id' => $product->id,
                'product_category' => $product->category,
                'quantity' => $quantity,
            ]);

            return app(CommerceCheckoutController::class)->startPublicCheckout($order);
        }

        $order = CommerceOrder::create([
            'guest_name' => $data['guest_name'],
            'guest_email' => strtolower($data['guest_email']),
            'access_token' => Str::random(64),
            'club_id' => $product->club_id,
            'orderable_type' => $product::class,
            'orderable_id' => $product->id,
            'type' => 'marketplace_product',
            'provider' => $data['provider'],
            ...$orderAmounts,
            'commission_cents' => $this->pricing->commissionCents($product, (int) $quote['item_gross_cents']),
            'currency' => $quote['currency'],
            'tax_country' => $quote['country'],
            'tax_rate_percent' => $quote['tax_rate'],
            'customer_type' => $customer['type'],
            'customer_company' => $customer['company'] ?: null,
            'customer_vat_id' => $customer['vat_id'] ?: null,
            'customer_vat_is_valid' => $customer['vat_id'] ? $customer['vat_id_is_valid'] : null,
            'customer_vat_validated_at' => $customer['vat_id'] ? now() : null,
            'status' => 'pending',
            'payload' => ['pricing' => $quote, 'shipping_address' => $shippingAddress],
        ]);
        $this->createOrderItem($order, $product, $quote, $quantity);
        app(CommerceOrderNotifier::class)->notifySalesRecipients($order);
        app(CommerceCheckoutController::class)->rememberMarketplaceInterest($request, $product, 'checkout_started');
        app(CommerceCheckoutController::class)->trackAttributedAdConversion($request, 'checkout_started', (int) ($quote['gross_cents'] ?? $order->amount_cents), [
                'order_id' => $order->id,
                'product_id' => $product->id,
                'product_category' => $product->category,
                'quantity' => $quantity,
            ]);

        return app(CommerceCheckoutController::class)->startPublicCheckout($order);
    }

    private function productCard(MarketplaceProduct $product, Request $request, ?string $country = null): array
    {
        $quote = $this->pricing->quote($product, $country);

        return [
            'id' => $product->id,
            'title' => $product->title,
            'description' => $product->description,
            'features' => $product->features ?: [],
            'product_attributes' => $product->product_attributes ?: [],
            'offer_type' => $product->offer_type ?: 'physical_product',
            'course_outline' => $product->course_outline ?: [],
            'learning_goals' => $product->learning_goals ?: [],
            'coaching_enabled' => (bool) $product->coaching_enabled,
            'coach_feedback_instructions' => $product->coach_feedback_instructions,
            'image_url' => $product->image_url,
            'gallery_images' => $product->gallery_images ?: array_values(array_filter([$product->image_url])),
            'category' => $product->category,
            'sku' => $product->sku,
            'manages_stock' => (bool) $product->manages_stock,
            'stock_quantity' => $this->clientStockQuantity($product, $country),
            'price_cents' => $quote['gross_cents'],
            'old_price_cents' => null,
            'currency' => $quote['currency'],
            'price' => $quote,
            'provider_name' => $product->club?->name ?: $product->user?->name,
            'provider_type' => $this->providerType($product),
            'provider_profile' => $this->providerProfile($product),
            'delivery_label' => $this->deliveryLabel($product),
            'return_label' => $this->returnLabel($product),
            'trust_badges' => $this->trustBadges($product),
            'show_url' => route('guest.marketplace.products.show', $product),
            'segment' => $this->productSegment($product),
            'badge' => match ($product->offer_type === 'physical_product' ? $product->category : ($product->offer_type ?: $product->category)) {
                'training_plan' => 'Coach-Plan',
                'online_course' => 'Online-Kurs',
                'course' => 'Top Kurs',
                'camp' => 'Camp',
                'service' => 'Service',
                default => $product->id % 2 === 0 ? 'Deal' : 'Neu',
            },
            'visual_icon' => match ($product->offer_type === 'physical_product' ? $product->category : ($product->offer_type ?: $product->category)) {
                'training_plan' => 'las la-clipboard-list',
                'online_course' => 'las la-video',
                'course' => 'las la-chalkboard-teacher',
                'camp' => 'las la-campground',
                'service' => 'las la-hands-helping',
                default => 'las la-dumbbell',
            },
        ];
    }

    private function relatedProducts(MarketplaceProduct $product, Request $request, string $country): array
    {
        $sameCategory = $this->marketplaceProductQuery(['category' => $product->category], $country)
            ->whereKeyNot($product->id)
            ->latest('id')
            ->limit(5)
            ->get();

        $products = $sameCategory;

        if ($products->count() < 5) {
            $fallback = $this->marketplaceProductQuery([], $country)
                ->whereKeyNot($product->id)
                ->whereNotIn('id', $products->pluck('id'))
                ->latest('id')
                ->limit(5 - $products->count())
                ->get();

            $products = $products->concat($fallback);
        }

        return $products
            ->take(5)
            ->map(fn (MarketplaceProduct $relatedProduct) => $this->productCard($relatedProduct, $request, $country))
            ->values()
            ->all();
    }

    private function applyMarketplaceSort($query, string $sort)
    {
        return match ($sort) {
            'price_asc' => $query->orderBy('price_cents')->orderByDesc('id'),
            'price_desc' => $query->orderByDesc('price_cents')->orderByDesc('id'),
            'newest' => $query->latest('id'),
            default => $query
                ->orderByRaw("CASE WHEN image_url IS NULL OR image_url = '' THEN 1 ELSE 0 END")
                ->orderByRaw("CASE WHEN description IS NULL OR description = '' THEN 1 ELSE 0 END")
                ->latest('id'),
        };
    }

    private function applyAvailabilityFilter($query, string $availability)
    {
        return match ($availability) {
            'digital' => $query->where(function ($query) {
                $query
                    ->whereIn('offer_type', ['online_course', 'training_plan', 'service'])
                    ->orWhere('product_type', 'digital')
                    ->orWhereIn('category', ['course', 'service']);
            }),
            'shippable' => $query->where('is_shippable', true),
            default => $query->where(function ($query) {
                $query
                    ->whereIn('offer_type', ['online_course', 'training_plan', 'service'])
                    ->orWhere('product_type', 'digital')
                    ->orWhere('manages_stock', false)
                    ->orWhere('stock_quantity', '>', 0);
            }),
        };
    }

    private function authUser(Request $request): ?array
    {
        $user = $request->user();

        if (! $user) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];
    }

    private function cartBadge(Request $request): array
    {
        if (! $request->user()) {
            return [
                'items_count' => 0,
            ];
        }

        $cart = CommerceCart::query()
            ->with('items:id,commerce_cart_id,quantity')
            ->where('user_id', $request->user()->id)
            ->first();

        return [
            'items_count' => (int) ($cart?->items->count() ?? 0),
        ];
    }

    private function outfitPlanCard(OutfitSubscriptionPlan $plan, ?string $country = null): array
    {
        $effectivePrice = max(0, (int) $plan->monthly_price_cents - (int) $plan->sponsor_discount_cents);
        $profile = $this->pricing->taxProfiles()[strtoupper((string) $country)] ?? $this->pricing->taxProfiles()['DE'];
        $taxRate = (float) $profile['tax_rate'];
        $netCents = $taxRate > 0 ? (int) round($effectivePrice / (1 + ($taxRate / 100))) : $effectivePrice;

        return [
            'id' => $plan->id,
            'title' => $plan->name,
            'description' => $plan->description,
            'category' => 'outfit_subscription',
            'price_cents' => $effectivePrice,
            'old_price_cents' => $plan->sponsor_discount_cents ? $plan->monthly_price_cents : null,
            'currency' => $plan->currency,
            'price' => [
                'country' => strtoupper((string) $country) ?: 'DE',
                'currency' => $plan->currency,
                'gross_cents' => $effectivePrice,
                'net_cents' => $netCents,
                'tax_cents' => max(0, $effectivePrice - $netCents),
                'tax_rate' => $taxRate,
                'tax_label' => $profile['tax_label'],
                'is_estimate' => (bool) $profile['is_estimate'],
            ],
            'provider_name' => $plan->sponsor ? 'Subventioniert von '.$plan->sponsor->name : 'Airmius Outfit-Abo',
            'show_url' => route('login'),
            'badge' => $plan->sponsor_discount_cents ? 'Sponsor Deal' : 'Monatsabo',
            'visual_icon' => 'las la-tshirt',
            'items_per_box' => $plan->items_per_box,
            'sports' => $plan->sports ?: [],
            'segment' => 'apparel',
        ];
    }

    private function segments(): array
    {
        return [
            ['value' => '', 'label' => 'Alle Bereiche', 'icon' => 'las la-border-all'],
            ['value' => 'shoes', 'label' => 'Schuhe', 'icon' => 'las la-shoe-prints'],
            ['value' => 'apparel', 'label' => 'Bekleidung', 'icon' => 'las la-tshirt'],
            ['value' => 'equipment', 'label' => 'Equipment', 'icon' => 'las la-dumbbell'],
            ['value' => 'recovery', 'label' => 'Recovery', 'icon' => 'las la-heartbeat'],
            ['value' => 'analysis', 'label' => 'Analyse', 'icon' => 'las la-chart-line'],
            ['value' => 'nutrition', 'label' => 'Ernährung', 'icon' => 'las la-apple-alt'],
            ['value' => 'plans', 'label' => 'Pläne & Kurse', 'icon' => 'las la-chalkboard-teacher'],
            ['value' => 'camps', 'label' => 'Camps', 'icon' => 'las la-campground'],
            ['value' => 'team', 'label' => 'Team & Verein', 'icon' => 'las la-users'],
        ];
    }

    private function applySegmentFilter($query, string $segment)
    {
        $keywords = $this->segmentKeywords($segment);
        $categories = $this->segmentCategories($segment);

        if ($keywords === [] && $categories === []) {
            return $query;
        }

        return $query->where(function ($query) use ($keywords, $categories) {
            if ($categories !== []) {
                $query->whereIn('category', $categories);
            }

            foreach ($keywords as $keyword) {
                $query
                    ->orWhere('title', 'like', '%'.$keyword.'%')
                    ->orWhere('description', 'like', '%'.$keyword.'%');
            }
        });
    }

    private function productSegment(MarketplaceProduct $product): string
    {
        $categorySegment = match ($product->category) {
            'course' => 'plans',
            'camp' => 'camps',
            'service' => 'analysis',
            default => null,
        };

        if ($categorySegment) {
            return $categorySegment;
        }

        $text = Str::lower($product->title.' '.$product->description);

        foreach (array_keys($this->segmentKeywordMap()) as $segment) {
            foreach ($this->segmentKeywords($segment) as $keyword) {
                if (Str::contains($text, Str::lower($keyword))) {
                    return $segment;
                }
            }
        }

        return 'equipment';
    }

    private function hasSellableStock(MarketplaceProduct $product, ?string $country = null, int $quantity = 1): bool
    {
        if (in_array($product->offer_type, ['online_course', 'training_plan', 'service'], true) || $product->product_type === 'digital') {
            return true;
        }

        if (! $product->isAvailableForCountry($country)) {
            return false;
        }

        if (! (bool) $product->manages_stock) {
            return true;
        }

        return $product->sellableStockForCountry($country) >= max(1, $quantity);
    }

    private function fulfillmentInventoryFor(MarketplaceProduct $product, ?string $country, int $quantity): ?MarketplaceProductInventory
    {
        if (! (bool) $product->manages_stock || $product->isDigitalDelivery()) {
            return null;
        }

        return $product->inventories()
            ->with('warehouse:id,name,country_code,city,postal_code')
            ->availableForCountry(strtoupper((string) ($country ?: 'DE')))
            ->orderBy('lead_time_days')
            ->orderBy('id')
            ->get()
            ->first(fn (MarketplaceProductInventory $inventory) => $inventory->availableQuantity() >= max(1, $quantity));
    }

    private function clientStockQuantity(MarketplaceProduct $product, ?string $country = null): ?int
    {
        if (! (bool) $product->manages_stock) {
            return null;
        }

        return $product->sellableStockForCountry($country);
    }

    private function segmentKeywords(string $segment): array
    {
        return $this->segmentKeywordMap()[$segment] ?? [];
    }

    private function segmentCategories(string $segment): array
    {
        return [
            'plans' => ['course'],
            'camps' => ['camp'],
            'analysis' => ['service'],
            'equipment' => ['product'],
        ][$segment] ?? [];
    }

    private function segmentKeywordMap(): array
    {
        return [
            'shoes' => ['schuh', 'schuhe'],
            'apparel' => ['shirt', 'trikot', 'bekleidung', 'outfit', 'kleidung'],
            'recovery' => ['recovery', 'regeneration', 'mobility', 'faszien', 'erholung'],
            'analysis' => ['analyse', 'check-up', 'sensorik', 'technik', 'feedback'],
            'nutrition' => ['ernährung', 'wettkampfplanung'],
            'plans' => ['trainingsplan', 'kurs', 'playbook', 'fortbildung'],
            'camps' => ['camp', 'clinic', 'workshop'],
            'team' => ['team', 'verein', 'club'],
            'equipment' => ['set', 'kit', 'paket', 'bundle', 'ausstattung', 'material'],
        ];
    }

    private function sportCategories(): array
    {
        return [
            ['label' => 'Running', 'icon' => 'las la-running', 'query' => 'lauf'],
            ['label' => 'Fußball', 'icon' => 'las la-futbol', 'query' => 'fussball'],
            ['label' => 'Fitness', 'icon' => 'las la-dumbbell', 'query' => 'fitness'],
            ['label' => 'Teamsport', 'icon' => 'las la-users', 'query' => 'team'],
            ['label' => 'Recovery', 'icon' => 'las la-heartbeat', 'segment' => 'recovery'],
            ['label' => 'Camps', 'icon' => 'las la-campground', 'category' => 'camp', 'segment' => 'camps'],
            ['label' => 'Kurse', 'icon' => 'las la-video', 'category' => 'course', 'segment' => 'plans'],
            ['label' => 'Services', 'icon' => 'las la-hands-helping', 'category' => 'service', 'segment' => 'analysis'],
        ];
    }

    private function officialStores(): array
    {
        return [
            ['name' => 'Airmius Teamsport', 'discount' => 'Teamwear', 'icon' => 'las la-tshirt'],
            ['name' => 'RunLab', 'discount' => 'Running', 'icon' => 'las la-running'],
            ['name' => 'Club Gear', 'discount' => 'Vereine', 'icon' => 'las la-shield-alt'],
            ['name' => 'Recovery Pro', 'discount' => 'Recovery', 'icon' => 'las la-heartbeat'],
            ['name' => 'Coach Campus', 'discount' => 'Kurse', 'icon' => 'las la-chalkboard-teacher'],
            ['name' => 'FitMarket', 'discount' => 'Fitness', 'icon' => 'las la-dumbbell'],
            ['name' => 'MatchDay', 'discount' => 'Camps', 'icon' => 'las la-futbol'],
            ['name' => 'SwimTech', 'discount' => 'Analyse', 'icon' => 'las la-swimmer'],
        ];
    }

    private function sortOptions(): array
    {
        return [
            ['value' => 'recommended', 'label' => 'Empfohlen'],
            ['value' => 'newest', 'label' => 'Neueste'],
            ['value' => 'price_asc', 'label' => 'Preis aufsteigend'],
            ['value' => 'price_desc', 'label' => 'Preis absteigend'],
        ];
    }

    private function availabilityOptions(): array
    {
        return [
            ['value' => '', 'label' => 'Alle Verfügbarkeiten'],
            ['value' => 'available', 'label' => 'Sofort verfügbar'],
            ['value' => 'shippable', 'label' => 'Versandartikel'],
            ['value' => 'digital', 'label' => 'Digital / Termin'],
        ];
    }

    private function paymentProviders(): array
    {
        return array_values(array_filter([
            filled(Setting::valueFor('billing_iban')) ? [
                'value' => 'bank_transfer',
                'label' => 'Überweisung',
                'description' => 'Bestellung sofort anlegen und per Banküberweisung bezahlen.',
                'icon' => 'las la-university',
            ] : null,
            filled(config('services.stripe.secret')) ? [
                'value' => 'stripe',
                'label' => 'Stripe',
                'description' => 'Sofortige Kartenzahlung Über Stripe Checkout.',
                'icon' => 'las la-credit-card',
            ] : null,
            filled(config('services.paypal.client_id')) && filled(config('services.paypal.client_secret')) ? [
                'value' => 'paypal',
                'label' => 'PayPal',
                'description' => 'Sicher zu PayPal weiterleiten und dort bezahlen.',
                'icon' => 'lab la-paypal',
            ] : null,
        ]));
    }

    private function trustBenefits(): array
    {
        return [
            ['label' => 'Gastkauf möglich', 'description' => 'Direkt bestellen, Konto optional.', 'icon' => 'las la-user-check'],
            ['label' => 'Preis transparent', 'description' => 'Brutto, netto, Steuer und Versand werden ausgewiesen.', 'icon' => 'las la-receipt'],
            ['label' => 'Anbieter sichtbar', 'description' => 'Verein, Trainer oder Shop bleiben klar erkennbar.', 'icon' => 'las la-store'],
            ['label' => 'Bestellstatus', 'description' => 'Updates und Belege werden per E-Mail zugestellt.', 'icon' => 'las la-envelope-open-text'],
        ];
    }

    private function providerType(MarketplaceProduct $product): string
    {
        if ($product->club_id) {
            return $product->club?->verification_status === 'verified' ? 'Verifizierter Verein' : 'Verein / Anbieter';
        }

        return 'Airmius Anbieter';
    }

    private function providerProfile(MarketplaceProduct $product): array
    {
        $club = $product->club;
        $user = $product->user;
        $name = $club?->name ?: ($user?->name ?: 'Airmius Anbieter');
        $location = trim(implode(', ', array_filter([
            $club?->city ?: $user?->city,
            strtoupper((string) ($club?->country ?: $user?->country ?: '')),
        ])));

        return [
            'name' => $name,
            'type' => $this->providerType($product),
            'logo_url' => $club?->logo ? UploadStorage::url($club->logo) : null,
            'initials' => Str::upper(Str::substr($name, 0, 2)),
            'location' => $location ?: 'Online',
            'verified' => $club?->verification_status === 'verified',
            'url' => $club
                ? route('guest.marketplace.providers.show', ['type' => 'club', 'id' => $club->id])
                : ($user ? route('guest.marketplace.providers.show', ['type' => 'user', 'id' => $user->id]) : null),
        ];
    }

    private function providerProfileFromModel(Club|User $provider, string $type): array
    {
        $name = $provider->name ?: 'Airmius Anbieter';
        $location = trim(implode(', ', array_filter([
            $provider->city,
            strtoupper((string) ($provider->country ?: '')),
        ])));
        $verified = $type === 'club' && $provider->verification_status === 'verified';

        return [
            'name' => $name,
            'type' => $verified ? 'Verifizierter Verein' : ($type === 'club' ? 'Verein / Anbieter' : 'Airmius Anbieter'),
            'logo_url' => $type === 'club' && $provider->logo ? UploadStorage::url($provider->logo) : null,
            'initials' => Str::upper(Str::substr($name, 0, 2)),
            'location' => $location ?: 'Online',
            'verified' => $verified,
            'url' => route('guest.marketplace.providers.show', ['type' => $type, 'id' => $provider->id]),
        ];
    }

    private function deliveryLabel(MarketplaceProduct $product): string
    {
        if (in_array($product->offer_type, ['online_course', 'training_plan'], true) || $product->product_type === 'digital') {
            return 'Digital nach Kauf';
        }

        if ($product->category === 'camp') {
            return 'Termin / Teilnahme';
        }

        if ($product->category === 'service' || $product->offer_type === 'service') {
            return 'Termin nach Absprache';
        }

        return $product->is_shippable ? 'Versand nach Bestellung' : 'Ohne Versand';
    }

    private function returnLabel(MarketplaceProduct $product): string
    {
        $days = (int) ($product->return_window_days ?? 14);

        if ($days <= 0 || in_array($product->return_policy_type, ['digital', 'service'], true)) {
            return 'Regeln je Angebot';
        }

        return $days.' Tage Rückgabe';
    }

    private function trustBadges(MarketplaceProduct $product): array
    {
        return array_values(array_filter([
            $this->deliveryLabel($product),
            $this->returnLabel($product),
            $product->manages_stock ? 'Lagerbestand geprüft' : 'Anfrage / Termin',
        ]));
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

    private function shippingAddressFromData(array $data): array
    {
        return [
            'country' => strtoupper((string) ($data['shipping_country'] ?? 'DE')),
            'state' => trim((string) ($data['shipping_state'] ?? '')),
            'postal_code' => trim((string) ($data['shipping_postal_code'] ?? '')),
            'city' => trim((string) ($data['shipping_city'] ?? '')),
            'street' => trim((string) ($data['shipping_street'] ?? '')),
            'house_number' => trim((string) ($data['shipping_house_number'] ?? '')),
        ];
    }

    private function customerFromData(array $data): array
    {
        return [
            'type' => ($data['customer_type'] ?? 'consumer') === 'business' ? 'business' : 'consumer',
            'company' => trim((string) ($data['customer_company'] ?? '')),
            'vat_id' => EuVatId::normalize((string) ($data['customer_vat_id'] ?? '')),
            'vat_id_is_valid' => EuVatId::looksValid((string) ($data['customer_vat_id'] ?? '')),
        ];
    }

    private function orderAmountsFromQuote(array $quote, array $customer): array
    {
        return [
            'item_gross_cents' => (int) ($quote['item_gross_cents'] ?? $quote['gross_cents']),
            'shipping_cents' => (int) ($quote['shipping_gross_cents'] ?? 0),
            'net_cents' => (int) ($quote['net_cents'] ?? 0),
            'tax_cents' => (int) ($quote['tax_cents'] ?? 0),
            'amount_cents' => (int) ($quote['gross_cents'] ?? 0),
        ];
    }

    private function createOrderItem(CommerceOrder $order, MarketplaceProduct $product, array $quote, int $quantity = 1): void
    {
        $unitGrossCents = (int) round(((int) ($quote['item_gross_cents'] ?? $product->price_cents)) / max(1, $quantity));

        $order->items()->create([
            'orderable_type' => $product::class,
            'orderable_id' => $product->id,
            'commerce_warehouse_id' => data_get($quote, 'fulfillment_inventory.commerce_warehouse_id'),
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

    private function applyLearningCoupon(MarketplaceProduct $product, array $quote, ?string $code): array
    {
        if (! $product->learning_course_id || blank($code)) {
            return $quote;
        }

        $coupon = LearningCoupon::query()
            ->where('learning_course_id', $product->learning_course_id)
            ->where('code', strtoupper(trim($code)))
            ->first();

        if (! $coupon || ! $coupon->isRedeemable()) {
            throw ValidationException::withMessages(['coupon_code' => 'Dieser Gutschein ist ungültig oder abgelaufen.']);
        }

        $oldItemGross = max(0, (int) ($quote['item_gross_cents'] ?? $quote['gross_cents'] ?? 0));
        $discount = $coupon->discountFor($oldItemGross);
        $newItemGross = max(0, $oldItemGross - $discount);
        $ratio = $oldItemGross > 0 ? $newItemGross / $oldItemGross : 1;

        $quote['item_gross_cents'] = $newItemGross;
        $quote['item_net_cents'] = (int) round((int) ($quote['item_net_cents'] ?? $quote['net_cents'] ?? 0) * $ratio);
        $quote['item_tax_cents'] = max(0, $newItemGross - $quote['item_net_cents']);
        $quote['gross_cents'] = $newItemGross + (int) ($quote['shipping_gross_cents'] ?? 0);
        $quote['net_cents'] = $quote['item_net_cents'] + (int) ($quote['shipping_net_cents'] ?? 0);
        $quote['tax_cents'] = $quote['item_tax_cents'] + (int) ($quote['shipping_tax_cents'] ?? 0);
        $quote['learning_coupon'] = [
            'id' => $coupon->id,
            'code' => $coupon->code,
            'discount_cents' => $discount,
        ];

        return $quote;
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
                'width' => (int) Setting::valueFor('marketplace_visual_side_banner_width', 192),
                'height' => (int) Setting::valueFor('marketplace_visual_side_banner_height', 1080),
            ],
            'hero_banner' => [
                'width' => (int) Setting::valueFor('marketplace_visual_hero_banner_width', 1600),
                'height' => (int) Setting::valueFor('marketplace_visual_hero_banner_height', 900),
            ],
            'sale_banner' => [
                'width' => (int) Setting::valueFor('marketplace_visual_sale_banner_width', 800),
                'height' => (int) Setting::valueFor('marketplace_visual_sale_banner_height', 1000),
            ],
        ];

        return $visuals;
    }
}
