<?php

namespace App\Http\Controllers;

use App\Models\CommerceCart;
use App\Models\CommerceOrder;
use App\Models\CommerceShippingAddress;
use App\Models\MarketplaceProduct;
use App\Models\OutfitSubscriptionPlan;
use App\Models\Setting;
use App\Services\MarketplacePricingService;
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
        ]);
        $country = $filters['country'] ?? null;

        $products = $this->marketplaceProductQuery($filters)
            ->latest('id')
            ->paginate(40)
            ->withQueryString()
            ->through(fn (MarketplaceProduct $product) => $this->productCard($product, $request, $country));

        $featuredProducts = $this->marketplaceProductQuery()
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn (MarketplaceProduct $product) => $this->productCard($product, $request, $country));

        $flashDeals = $this->marketplaceProductQuery()
            ->latest('id')
            ->limit(12)
            ->get()
            ->map(fn (MarketplaceProduct $product) => $this->productCard($product, $request, $country));

        $essentialDeals = $this->marketplaceProductQuery(['category' => 'product'])
            ->latest('id')
            ->skip(12)
            ->limit(12)
            ->get()
            ->map(fn (MarketplaceProduct $product) => $this->productCard($product, $request, $country));

        $learningDeals = $this->marketplaceProductQuery()
            ->whereIn('category', ['course', 'camp'])
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn (MarketplaceProduct $product) => $this->productCard($product, $request, $country));

        $serviceDeals = $this->marketplaceProductQuery(['category' => 'service'])
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn (MarketplaceProduct $product) => $this->productCard($product, $request, $country));

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
            'pricingCountries' => $this->pricingCountries(),
            'marketplaceVisuals' => $this->marketplaceVisuals(),
        ]);
    }

    private function marketplaceProductQuery(array $filters = [])
    {
        return MarketplaceProduct::query()
            ->with(['user:id,name', 'club:id,name'])
            ->where('status', 'published')
            ->where('manages_stock', true)
            ->whereNotNull('stock_quantity')
            ->where('stock_quantity', '>', 0)
            ->when(($filters['category'] ?? null) && $filters['category'] !== 'outfit_subscription', fn ($query, $category) => $query->where('category', $category))
            ->when(($filters['category'] ?? null) === 'outfit_subscription', fn ($query) => $query->whereRaw('1 = 0'))
            ->when($filters['segment'] ?? null, fn ($query, $segment) => $this->applySegmentFilter($query, $segment))
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
        abort_unless($product->status === 'published' && $this->hasSellableStock($product), 404);

        $product->load(['user:id,name', 'club:id,name']);
        $initialAddress = $this->shippingAddressFromData([
            'shipping_country' => $request->input('shipping_country', $request->user()?->country ?: 'DE'),
            'shipping_state' => $request->user()?->state,
            'shipping_postal_code' => $request->user()?->postal_code,
            'shipping_city' => $request->user()?->city,
            'shipping_street' => $request->user()?->street,
            'shipping_house_number' => $request->user()?->house_number,
        ]);
        $quote = $this->pricing->quoteForRequest($product, $request, $initialAddress['country'], $initialAddress);

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
                'image_url' => $product->image_url,
                'gallery_images' => $product->gallery_images ?: array_values(array_filter([$product->image_url])),
                'category' => $product->category,
                'sku' => $product->sku,
                'is_shippable' => (bool) $product->is_shippable,
                'manages_stock' => (bool) $product->manages_stock,
                'stock_quantity' => (int) $product->stock_quantity,
                'return_policy_type' => $product->return_policy_type,
                'return_window_days' => $product->return_window_days,
                'price_cents' => $product->price_cents,
                'currency' => $product->currency,
                'price' => $quote,
                'provider_name' => $product->club?->name ?: $product->user?->name,
            ],
            'pricingCountries' => $this->pricingCountries(),
            'checkoutAddress' => $initialAddress,
            'profileAddress' => $this->profileAddressFor($request->user()),
            'shippingAddresses' => $this->shippingAddressesFor($request->user()),
            'marketplaceVisuals' => $this->marketplaceVisuals(),
        ]);
    }

    public function checkout(Request $request, MarketplaceProduct $product)
    {
        abort_unless($product->status === 'published', 404);
        abort_unless($this->hasSellableStock($product), 404);

        $data = $request->validate([
            'guest_name' => ['required_without:login_checkout', 'nullable', 'string', 'max:255'],
            'guest_email' => ['required_without:login_checkout', 'nullable', 'email', 'max:255'],
            'provider' => ['required', Rule::in(['stripe', 'paypal', 'bank_transfer'])],
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
        ]);
        $quantity = (int) ($data['quantity'] ?? 1);
        if ((int) $product->stock_quantity < $quantity) {
            throw ValidationException::withMessages([
                'quantity' => 'So viele Artikel sind aktuell nicht auf Lager.',
            ]);
        }

        $shippingAddress = $this->shippingAddressFromData($data);
        $this->saveShippingAddressIfRequested($request, $data, $shippingAddress);
        $customer = $this->customerFromData($data);
        $quote = $this->pricing->quoteForRequest($product, $request, $shippingAddress['country'], $shippingAddress);
        $quote = $this->quoteWithQuantity($quote, $quantity);
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

        return app(CommerceCheckoutController::class)->startPublicCheckout($order);
    }

    private function productCard(MarketplaceProduct $product, Request $request, ?string $country = null): array
    {
        $rating = 42 + ($product->id % 8);
        $quote = $this->pricing->quote($product, $country);
        $oldGrossCents = $product->price_cents > 0 ? (int) round($quote['gross_cents'] * 1.18) : null;

        return [
            'id' => $product->id,
            'title' => $product->title,
            'description' => $product->description,
            'features' => $product->features ?: [],
            'product_attributes' => $product->product_attributes ?: [],
            'image_url' => $product->image_url,
            'gallery_images' => $product->gallery_images ?: array_values(array_filter([$product->image_url])),
            'category' => $product->category,
            'sku' => $product->sku,
            'manages_stock' => (bool) $product->manages_stock,
            'stock_quantity' => (int) $product->stock_quantity,
            'price_cents' => $quote['gross_cents'],
            'old_price_cents' => $oldGrossCents,
            'currency' => $quote['currency'],
            'price' => $quote,
            'provider_name' => $product->club?->name ?: $product->user?->name,
            'show_url' => route('guest.marketplace.products.show', $product),
            'segment' => $this->productSegment($product),
            'rating' => number_format($rating / 10, 1, ',', '.'),
            'sold_count' => 12 + ($product->id * 7 % 240),
            'badge' => match ($product->category) {
                'course' => 'Top Kurs',
                'camp' => 'Camp',
                'service' => 'Service',
                default => $product->id % 2 === 0 ? 'Deal' : 'Neu',
            },
            'visual_icon' => match ($product->category) {
                'course' => 'las la-chalkboard-teacher',
                'camp' => 'las la-campground',
                'service' => 'las la-hands-helping',
                default => 'las la-dumbbell',
            },
        ];
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
            'rating' => '4,8',
            'sold_count' => 24 + ($plan->id * 5 % 180),
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
            ['value' => 'nutrition', 'label' => 'Ernaehrung', 'icon' => 'las la-apple-alt'],
            ['value' => 'plans', 'label' => 'Plaene & Kurse', 'icon' => 'las la-chalkboard-teacher'],
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

    private function hasSellableStock(MarketplaceProduct $product): bool
    {
        return (bool) $product->manages_stock && (int) $product->stock_quantity > 0;
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
            'nutrition' => ['ernaehrung', 'wettkampfplanung'],
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
            ['label' => 'Fussball', 'icon' => 'las la-futbol', 'query' => 'fussball'],
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
            ['name' => 'Airmius Teamsport', 'discount' => 'bis -40%', 'icon' => 'las la-tshirt'],
            ['name' => 'RunLab', 'discount' => 'bis -30%', 'icon' => 'las la-running'],
            ['name' => 'Club Gear', 'discount' => 'bis -35%', 'icon' => 'las la-shield-alt'],
            ['name' => 'Recovery Pro', 'discount' => 'bis -25%', 'icon' => 'las la-heartbeat'],
            ['name' => 'Coach Campus', 'discount' => 'Top Kurse', 'icon' => 'las la-chalkboard-teacher'],
            ['name' => 'FitMarket', 'discount' => 'Neuheiten', 'icon' => 'las la-dumbbell'],
            ['name' => 'MatchDay', 'discount' => 'Camps', 'icon' => 'las la-futbol'],
            ['name' => 'SwimTech', 'discount' => 'Analyse', 'icon' => 'las la-swimmer'],
        ];
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
            'vat_id' => strtoupper(preg_replace('/\s+/', '', (string) ($data['customer_vat_id'] ?? ''))),
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
                'width' => (int) Setting::valueFor('marketplace_visual_side_banner_width', 306),
                'height' => (int) Setting::valueFor('marketplace_visual_side_banner_height', 786),
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
