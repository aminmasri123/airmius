<?php

namespace App\Http\Controllers;

use App\Http\Resources\Api\V1\CommerceOrderResource;
use App\Models\AdCampaign;
use App\Models\AdCampaignStat;
use App\Models\AdCreative;
use App\Models\AdEvent;
use App\Models\AdGroup;
use App\Models\Club;
use App\Models\CommerceCart;
use App\Models\CommerceCartItem;
use App\Models\CommerceOrder;
use App\Models\CommerceOrderItem;
use App\Models\CommerceReturnRequest;
use App\Models\CommerceShippingAddress;
use App\Models\CommerceStockMovement;
use App\Models\CommerceWarehouse;
use App\Models\LearningCourse;
use App\Models\MarketplacePayout;
use App\Models\MarketplaceProduct;
use App\Models\MarketplaceProductInventory;
use App\Models\MarketplaceProviderLocation;
use App\Models\MarketplaceProviderProfile;
use App\Models\MarketplaceSellerApplication;
use App\Models\OutfitSubscription;
use App\Models\OutfitSubscriptionPlan;
use App\Models\PaymentCheckout;
use App\Models\PayoutProfile;
use App\Models\Setting;
use App\Models\Sport;
use App\Models\SubscriptionAddon;
use App\Models\SubscriptionAddonPurchase;
use App\Models\SubscriptionInvoice;
use App\Models\User;
use App\Models\WebsiteRequest;
use App\Notifications\CommerceOrderAwaitingTransfer;
use App\Notifications\CommerceOrderCompleted;
use App\Services\ClubShopOrderNumberService;
use App\Services\ClubShopProductNumberService;
use App\Services\CommerceAuditService;
use App\Services\CommerceCartService;
use App\Services\CommerceCheckoutPayloadService;
use App\Services\CommerceDocumentService;
use App\Services\CommerceLearningOrderService;
use App\Services\CommercePaymentGatewayService;
use App\Services\MarketplacePayoutService;
use App\Services\MarketplacePricingService;
use App\Services\MarketplaceProductImportService;
use App\Services\MediaOptimizer;
use App\Services\ModerationService;
use App\Services\ProviderWebhookEventService;
use App\Services\RevenueTrustService;
use App\Services\WebsiteRequestService;
use App\Support\AppNotification;
use App\Support\ClubPermissions;
use App\Support\CommerceOrderNotifier;
use App\Support\CommerceOrderSupport;
use App\Support\MarketplaceProductInput;
use App\Support\MarketplaceProductQualityGate;
use App\Support\MarketplaceSellerReadiness;
use App\Support\Roles;
use App\Support\UploadStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class CommerceCheckoutController extends Controller
{
    public function __construct(
        private ModerationService $moderation,
        private CommerceCartService $cartService,
        private MarketplacePricingService $pricing,
        private CommerceAuditService $audit,
        private CommerceDocumentService $documents,
        private MediaOptimizer $mediaOptimizer,
        private CommerceOrderSupport $orderSupport,
        private CommerceCheckoutPayloadService $checkoutPayload,
        private CommerceLearningOrderService $learningOrders,
        private MarketplaceProductImportService $productImport,
        private MarketplacePayoutService $payouts,
        private CommercePaymentGatewayService $payments,
        private WebsiteRequestService $websiteRequests,
    ) {}

    public function index(Request $request)
    {
        $sellerApplication = MarketplaceSellerApplication::query()
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->first();

        return inertia('Auth/Dashboard/Commerce/Index', [
            'clubs' => $this->commerceClubsFor($request->user())
                ->sortBy('name')
                ->values(),
            'sports' => Sport::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'slug', 'name', 'category']),
            'learningCourses' => LearningCourse::query()
                ->where('user_id', $request->user()->id)
                ->where('status', 'published')
                ->where('is_public', true)
                ->orderBy('title')
                ->get(['id', 'title', 'status', 'is_public', 'is_free', 'price_cents']),
            'addons' => SubscriptionAddon::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'accountPlans' => $this->checkoutPayload->accountPlansFor($request),
            'products' => MarketplaceProduct::query()
                ->where('status', 'published')
                ->where(fn ($query) => $query
                    ->whereIn('offer_type', ['online_course', 'training_plan', 'service'])
                    ->orWhere('product_type', 'digital')
                    ->orWhere(fn ($query) => $query
                        ->where('manages_stock', true)
                        ->whereNotNull('stock_quantity')
                        ->where('stock_quantity', '>', 0))
                    ->orWhere('manages_stock', false))
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
                ->with(['orderable', 'club:id,name', 'returnRequests', 'items.orderable'])
                ->where('user_id', $request->user()->id)
                ->latest('id')
                ->limit(30)
                ->get()
                ->map(function (CommerceOrder $order) {
                    $order->setAttribute('learning_course', $this->learningCourseLinkForOrder($order));
                    $order->setAttribute('support_summary', $this->orderSupport->summary($order));

                    return $order;
                }),
            'purchaseHistory' => $this->purchaseHistoryFor($request->user()),
            'myProducts' => MarketplaceProduct::query()
                ->where('user_id', $request->user()->id)
                ->with(['inventories.warehouse:id,name,country_code,city,postal_code'])
                ->withCount('stockMovements')
                ->latest('id')
                ->limit(20)
                ->get(),
            'sellerApplication' => MarketplaceSellerApplication::query()
                ->where('user_id', $request->user()->id)
                ->latest('id')
                ->get()
                ->map(fn (MarketplaceSellerApplication $application) => MarketplaceSellerReadiness::attach($application))
                ->first(),
            'sellerReadiness' => $sellerApplication ? MarketplaceSellerReadiness::forApplication($sellerApplication) : null,
            'sellerCanSell' => $this->userCanSellInMarketplace($request->user()),
            'marketplaceCategoryCommissions' => $this->marketplaceCategoryCommissionsForSeller(),
            'returnRequests' => CommerceReturnRequest::query()
                ->with(['order.orderable', 'item'])
                ->where('user_id', $request->user()->id)
                ->latest('id')
                ->limit(20)
                ->get(),
            'myCampaigns' => AdCampaign::query()
                ->where('user_id', $request->user()->id)
                ->with(['creatives', 'groups.creatives', 'stats' => fn ($query) => $query->latest('date')->limit(30)])
                ->withExists([
                    'commerceOrders as payment_completed' => fn ($query) => $query
                        ->where('type', 'ads_campaign')
                        ->where('status', 'completed'),
                    'commerceOrders as payment_pending' => fn ($query) => $query
                        ->where('type', 'ads_campaign')
                        ->whereIn('status', ['pending', 'awaiting_transfer']),
                ])
                ->latest('id')
                ->limit(20)
                ->get()
                ->map(fn (AdCampaign $campaign) => $this->campaignResourceWithPreviews($campaign)),
            'websiteRequests' => WebsiteRequest::query()
                ->with('club:id,name')
                ->where('user_id', $request->user()->id)
                ->latest('id')
                ->limit(10)
                ->get(),
            'payoutProfile' => PayoutProfile::query()
                ->where('user_id', $request->user()->id)
                ->first(),
            'providerProfile' => $this->marketplaceProviderProfileResource(
                MarketplaceProviderProfile::query()->where('user_id', $request->user()->id)->first(),
                $request->user(),
            ),
            'providerLocations' => $this->providerLocationsForUser($request->user()),
            'payoutSummary' => $this->payouts->summary($request->user()),
            'myPayouts' => MarketplacePayout::query()
                ->where('user_id', $request->user()->id)
                ->withCount('orders')
                ->latest('id')
                ->limit(20)
                ->get(),
            'cart' => $this->checkoutPayload->cartResource($request),
            'pricingCountries' => $this->checkoutPayload->pricingCountries(),
            'checkoutAddress' => $this->checkoutPayload->shippingAddressForAuthenticatedUser($request, []),
            'profileAddress' => $this->checkoutPayload->profileAddressFor($request->user()),
            'shippingAddresses' => $this->checkoutPayload->shippingAddressesFor($request->user()),
        ]);
    }

    public function sellerDashboard(Request $request)
    {
        $user = $request->user();
        $sellerApplication = MarketplaceSellerApplication::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        $products = MarketplaceProduct::query()
            ->where('user_id', $user->id)
            ->with(['inventories.warehouse:id,name,country_code,city,postal_code'])
            ->withCount('stockMovements')
            ->latest('id')
            ->limit(100)
            ->get();

        $orders = CommerceOrder::query()
            ->with([
                'user:id,name,email',
                'items.orderable',
                'returnRequests',
                'orderable',
            ])
            ->whereIn('type', ['marketplace_product', 'marketplace_cart'])
            ->where(function ($query) use ($user) {
                $query
                    ->whereHasMorph(
                        'orderable',
                        [MarketplaceProduct::class],
                        fn ($product) => $product->where('user_id', $user->id),
                    )
                    ->orWhereHas('items', fn ($items) => $items
                        ->where('orderable_type', MarketplaceProduct::class)
                        ->whereHasMorph(
                            'orderable',
                            [MarketplaceProduct::class],
                            fn ($product) => $product->where('user_id', $user->id),
                        ));
            })
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(fn (CommerceOrder $order) => $this->sellerOrderResource($order, $user));

        return response()->json([
            'data' => [
                'seller_application' => $sellerApplication
                    ? MarketplaceSellerReadiness::attach($sellerApplication)
                    : null,
                'seller_readiness' => $sellerApplication
                    ? MarketplaceSellerReadiness::forApplication($sellerApplication)
                    : null,
                'seller_can_sell' => $this->userCanSellInMarketplace($user),
                'category_commissions' => $this->marketplaceCategoryCommissionsForSeller(),
                'products' => $products,
                'orders' => $orders,
                'payout_profile' => PayoutProfile::query()
                    ->where('user_id', $user->id)
                    ->first(),
                'payout_summary' => $this->payouts->summary($user),
                'payouts' => MarketplacePayout::query()
                    ->where('user_id', $user->id)
                    ->withCount('orders')
                    ->latest('id')
                    ->limit(100)
                    ->get(),
                'provider_profile' => $this->marketplaceProviderProfileResource(
                    MarketplaceProviderProfile::query()->where('user_id', $user->id)->first(),
                    $user,
                ),
                'provider_locations' => $this->providerLocationsForUser($user),
                'campaigns' => AdCampaign::query()
                    ->where('user_id', $user->id)
                    ->with([
                        'creatives',
                        'groups.creatives',
                        'stats' => fn ($query) => $query->latest('date')->limit(30),
                    ])
                    ->withExists([
                        'commerceOrders as payment_completed' => fn ($query) => $query
                            ->where('type', 'ads_campaign')
                            ->where('status', 'completed'),
                        'commerceOrders as payment_pending' => fn ($query) => $query
                            ->where('type', 'ads_campaign')
                            ->whereIn('status', ['pending', 'awaiting_transfer']),
                    ])
                    ->latest('id')
                    ->limit(100)
                    ->get()
                    ->map(fn (AdCampaign $campaign) => $this->campaignResourceWithPreviews($campaign)),
                'website_requests' => WebsiteRequest::query()
                    ->with('club:id,name')
                    ->where('user_id', $user->id)
                    ->latest('id')
                    ->limit(50)
                    ->get(),
                'clubs' => $this->commerceClubsFor($user)
                    ->sortBy('name')
                    ->values(),
                'ads_min_budget_cents' => (int) Setting::valueFor('ads_min_budget_cents', 1000),
            ],
        ]);
    }

    public function cart(Request $request)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'data' => [
                    'cart' => $this->checkoutPayload->cartResource($request),
                    'pricing_countries' => $this->checkoutPayload->pricingCountries(),
                    'checkout_address' => $this->checkoutPayload->shippingAddressForAuthenticatedUser($request, []),
                    'profile_address' => $this->checkoutPayload->profileAddressFor($request->user()),
                    'shipping_addresses' => $this->checkoutPayload->shippingAddressesFor($request->user()),
                    'payment_providers' => ['stripe', 'paypal', 'bank_transfer'],
                ],
            ]);
        }

        return inertia('Auth/Dashboard/Commerce/Cart', [
            'authUser' => $request->user() ? [
                'id' => $request->user()->id,
                'name' => $request->user()->name,
                'email' => $request->user()->email,
            ] : null,
            'cart' => $this->checkoutPayload->cartResource($request),
            'pricingCountries' => $this->checkoutPayload->pricingCountries(),
            'checkoutAddress' => $this->checkoutPayload->shippingAddressForAuthenticatedUser($request, []),
            'profileAddress' => $this->checkoutPayload->profileAddressFor($request->user()),
            'shippingAddresses' => $this->checkoutPayload->shippingAddressesFor($request->user()),
            'marketplaceVisuals' => $this->marketplaceVisuals(),
        ]);
    }

    private function commerceClubsFor(User $user)
    {
        $permissions = [
            'can_purchase_addons' => ClubPermissions::COMMERCE_ADDONS_PURCHASE,
            'can_manage_shop_products' => ClubPermissions::COMMERCE_PRODUCTS_EDIT,
            'can_manage_advertising' => ClubPermissions::ADVERTISING_EDIT,
            'can_request_website' => ClubPermissions::WEBSITE_REQUEST_CREATE,
        ];

        return Club::query()
            ->when(! $user->hasAnyRole(Roles::FULL_ACCESS), function ($query) use ($user) {
                $query->where(function ($clubQuery) use ($user) {
                    $clubQuery
                        ->where('owner_id', $user->id)
                        ->orWhereHas('users', fn ($memberQuery) => $memberQuery->where('users.id', $user->id));
                });
            })
            ->get(['id', 'name', 'owner_id'])
            ->map(function (Club $club) use ($user, $permissions) {
                foreach ($permissions as $attribute => $permission) {
                    $club->setAttribute($attribute, ClubPermissions::allows($club, $user, $permission));
                }

                return $club;
            })
            ->filter(fn (Club $club) => collect(array_keys($permissions))->contains(
                fn (string $attribute) => (bool) $club->getAttribute($attribute),
            ))
            ->values();
    }

    private function authorizedCommerceClub(Request $request, mixed $clubId, string $permission): ?Club
    {
        if (blank($clubId)) {
            return null;
        }

        $club = Club::query()->find($clubId);

        if (! $club || ! ClubPermissions::allows($club, $request->user(), $permission)) {
            throw ValidationException::withMessages([
                'club_id' => __('commerce.validation.club_unauthorized'),
            ]);
        }

        return $club;
    }

    private function userCanSellInMarketplace(User $user): bool
    {
        return MarketplaceSellerApplication::query()
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->exists();
    }

    private function marketplaceCategoryCommissionsForSeller(): array
    {
        $labels = [
            'equipment' => 'Sportgeräte & Equipment',
            'apparel' => 'Bekleidung & Schuhe',
            'nutrition' => 'Ernährung & Supplements',
            'accessories' => 'Zubehör',
            'digital_products' => 'Digitale Produkte',
            'product' => 'Sonstige Produkte',
            'course' => 'Kurse / E-Learning',
            'camp' => 'Camps',
            'service' => 'Services / Airmius intern',
            'outfit_subscription' => 'Outfit-Abo',
        ];

        $raw = Setting::valueFor('marketplace_category_commissions', '{}');
        $configured = is_array($raw) ? $raw : json_decode((string) $raw, true);
        $configured = is_array($configured) ? $configured : [];
        $labelRaw = Setting::valueFor('marketplace_category_labels', '{}');
        $customLabels = is_array($labelRaw) ? $labelRaw : json_decode((string) $labelRaw, true);
        $customLabels = is_array($customLabels) ? $customLabels : [];
        $labels = array_merge($labels, $customLabels);
        $default = max(0, min(100, (int) Setting::valueFor('marketplace_default_commission_percent', 10)));

        return collect($labels)
            ->map(fn (string $label, string $category) => [
                'category' => $category,
                'label' => $label,
                'commission_percent' => max(0, min(100, (int) ($configured[$category] ?? $default))),
            ])
            ->values()
            ->all();
    }

    private function campaignResourceWithPreviews(AdCampaign $campaign): AdCampaign
    {
        $campaign->setAttribute(
            'preview_image_url',
            UploadStorage::url($campaign->creative_image_path) ?: $campaign->creative_image_url
        );

        $campaign->creatives->each(function (AdCreative $creative): void {
            $creative->setAttribute(
                'preview_image_url',
                UploadStorage::url($creative->creative_image_path) ?: $creative->creative_image_url
            );
        });

        $campaign->groups->each(function (AdGroup $group): void {
            $group->creatives->each(function (AdCreative $creative): void {
                $creative->setAttribute(
                    'preview_image_url',
                    UploadStorage::url($creative->creative_image_path) ?: $creative->creative_image_url
                );
            });
        });

        return $campaign;
    }

    public function storePayoutProfile(Request $request)
    {
        $data = $request->validate([
            'account_holder' => ['nullable', 'string', 'max:255'],
            'iban' => ['nullable', 'string', 'max:34'],
            'bic' => ['nullable', 'string', 'max:20'],
            'paypal_email' => ['nullable', 'email', 'max:255'],
            'tax_number' => ['nullable', 'string', 'max:80'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'tax_status' => ['nullable', Rule::in(['taxable', 'small_business', 'private_occasional', 'tax_exempt'])],
            'beneficial_owner_confirmed' => ['sometimes', 'accepted'],
            'payout_terms_accepted' => ['sometimes', 'accepted'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        abort_if(blank($data['iban'] ?? null) && blank($data['paypal_email'] ?? null), 422, __('commerce.validation.payout_method_required'));

        $acceptsCurrentTerms = ($data['payout_terms_accepted'] ?? false) !== false;
        unset($data['payout_terms_accepted']);
        $data['country_code'] = filled($data['country_code'] ?? null)
            ? strtoupper((string) $data['country_code'])
            : null;
        $data['beneficial_owner_confirmed'] = (bool) ($data['beneficial_owner_confirmed'] ?? false);
        $data['terms_version'] = $acceptsCurrentTerms ? RevenueTrustService::CONTRACT_VERSION : null;
        $data['terms_accepted_at'] = $acceptsCurrentTerms ? now() : null;

        $profile = PayoutProfile::query()->updateOrCreate(
            ['user_id' => $request->user()->id],
            [...$data, 'status' => 'review', 'verified_by' => null, 'verified_at' => null],
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('commerce.flash.payout_profile_saved'),
                'data' => $profile->fresh(),
            ]);
        }

        return back()->with('success', __('commerce.flash.payout_profile_saved'));
    }

    public function storeProviderProfile(Request $request)
    {
        $data = $request->validate($this->providerProfileRules());
        $profile = $this->providerProfileForUser($request->user());

        $profile->forceFill([
            ...$data,
            'legal_country' => strtoupper((string) ($data['legal_country'] ?? 'DE')),
            'status' => 'active',
        ])->save();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('commerce.flash.provider_profile_saved'),
                'data' => $this->marketplaceProviderProfileResource($profile->fresh(), $request->user()),
            ]);
        }

        return back()->with('success', __('commerce.flash.provider_profile_saved_web'));
    }

    public function storeProviderLocation(Request $request)
    {
        $profile = $this->providerProfileForUser($request->user());
        $data = $request->validate($this->providerLocationRules());

        $location = $profile->locations()->create([
            ...$data,
            'country' => strtoupper((string) ($data['country'] ?? 'DE')),
            'sort_order' => ((int) MarketplaceProviderLocation::query()
                ->where('marketplace_provider_profile_id', $profile->id)
                ->max('sort_order')) + 1,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('commerce.flash.location_saved'),
                'data' => $this->marketplaceProviderLocationResource($location),
            ], 201);
        }

        return back()->with('success', __('commerce.flash.location_saved_web'));
    }

    public function updateProviderLocation(Request $request, MarketplaceProviderLocation $location)
    {
        $this->authorizeProviderLocation($request, $location);
        $data = $request->validate($this->providerLocationRules());

        $location->forceFill([
            ...$data,
            'country' => strtoupper((string) ($data['country'] ?? 'DE')),
        ])->save();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('commerce.flash.location_updated'),
                'data' => $this->marketplaceProviderLocationResource($location->fresh()),
            ]);
        }

        return back()->with('success', __('commerce.flash.location_updated'));
    }

    public function destroyProviderLocation(Request $request, MarketplaceProviderLocation $location)
    {
        $this->authorizeProviderLocation($request, $location);
        $location->delete();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('commerce.flash.location_removed'),
            ]);
        }

        return back()->with('success', __('commerce.flash.location_removed'));
    }

    public function requestPayout(Request $request)
    {
        $data = $request->validate([
            'method' => ['required', Rule::in(['bank_transfer', 'paypal'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $profile = PayoutProfile::query()
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $profile || $profile->status !== 'approved') {
            throw ValidationException::withMessages([
                'payout' => __('commerce.validation.payout_profile_required'),
            ]);
        }

        $payout = $this->payouts->create(
            $request->user(),
            $data['method'],
            $data['notes'] ?? null,
            'requested',
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('commerce.flash.payout_requested'),
                'data' => $payout,
            ], 201);
        }

        return back()->with('success', __('commerce.flash.payout_requested_web'));
    }

    public function storeSellerApplication(Request $request)
    {
        $existing = MarketplaceSellerApplication::query()
            ->where('user_id', $request->user()->id)
            ->first();

        if ($existing?->status === 'approved') {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => __('commerce.flash.seller_approved'),
                    'data' => MarketplaceSellerReadiness::attach($existing),
                ]);
            }

            return back()->with('success', __('commerce.flash.seller_approved'));
        }

        $data = $request->validate([
            'applicant_type' => ['required', Rule::in(['private', 'business', 'club'])],
            'business_name' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'rule_product_truth' => ['accepted'],
            'rule_rights' => ['accepted'],
            'rule_shipping_returns' => ['accepted'],
            'rule_commission' => ['accepted'],
            'rule_data_privacy' => ['accepted'],
        ]);

        $application = MarketplaceSellerApplication::query()->updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'applicant_type' => $data['applicant_type'],
                'business_name' => $data['business_name'] ?? null,
                'notes' => $data['notes'] ?? null,
                'accepted_rules' => [
                    'product_truth' => true,
                    'rights' => true,
                    'shipping_returns' => true,
                    'commission' => true,
                    'data_privacy' => true,
                    'accepted_at' => now()->toISOString(),
                ],
                'verification_version' => RevenueTrustService::CONTRACT_VERSION,
                'verification_snapshot' => null,
                'status' => 'pending',
                'review_note' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
            ],
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('commerce.flash.seller_submitted'),
                'data' => MarketplaceSellerReadiness::attach($application->fresh()),
            ], 201);
        }

        return back()->with('success', __('commerce.flash.seller_submitted_web'));
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

        $club = $this->authorizedCommerceClub(
            $request,
            $data['club_id'] ?? null,
            ClubPermissions::COMMERCE_ADDONS_PURCHASE,
        );
        $targetActor = $addon->target_actor ?: 'verein';

        if ($targetActor === 'verein' && ! $club) {
            throw ValidationException::withMessages([
                'club_id' => __('commerce.validation.addon_club_required'),
            ]);
        }

        if ($targetActor !== 'verein' && $club) {
            throw ValidationException::withMessages([
                'club_id' => __('commerce.validation.addon_personal_only'),
            ]);
        }

        $amount = $data['billing_interval'] === 'yearly'
            ? (int) $addon->yearly_price_cents
            : (int) $addon->monthly_price_cents;

        $order = DB::transaction(function () use ($request, $club, $addon, $data, $amount) {
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

            return $order;
        });

        return $this->startCheckout($order);
    }

    public function storeProduct(Request $request, MarketplaceProduct $product)
    {
        abort_unless($product->status === 'published' && $product->moderation_status === 'approved', 404);
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
            'coupon_code' => ['nullable', 'string', 'max:40'],
        ]);
        $quantity = (int) ($data['quantity'] ?? 1);
        $shippingAddress = $this->checkoutPayload->shippingAddressForAuthenticatedUser($request, $data);
        if (! $this->cartService->hasSellableStock($product, $shippingAddress['country'], $quantity)) {
            throw ValidationException::withMessages([
                'quantity' => __('commerce.validation.stock_unavailable'),
            ]);
        }
        $fulfillmentInventory = $this->cartService->fulfillmentInventoryFor($product, $shippingAddress['country'], $quantity);

        $this->saveShippingAddressIfRequested($request, $data, $shippingAddress);
        $quote = $this->pricing->quoteForRequest($product, $request, $shippingAddress['country'], [
            ...$shippingAddress,
            'origin_country' => $fulfillmentInventory?->warehouse?->country_code,
        ]);
        $quote = $this->cartService->quoteWithQuantity($quote, $quantity);
        $quote = $this->learningOrders->applyCoupon($product, $quote, $data['coupon_code'] ?? null);
        $quote['fulfillment_inventory'] = $fulfillmentInventory ? [
            'id' => $fulfillmentInventory->id,
            'commerce_warehouse_id' => $fulfillmentInventory->commerce_warehouse_id,
            'country_code' => $fulfillmentInventory->country_code,
        ] : null;
        $customer = $this->checkoutPayload->customerFromData($data);

        $order = DB::transaction(function () use ($request, $product, $data, $quote, $customer, $shippingAddress, $quantity) {
            $order = CommerceOrder::create([
                'user_id' => $request->user()->id,
                'club_id' => $product->club_id,
                'orderable_type' => $product::class,
                'orderable_id' => $product->id,
                'type' => 'marketplace_product',
                'provider' => $data['provider'],
                ...$this->checkoutPayload->orderAmountsFromQuote($quote),
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

            return $order;
        });
        app(CommerceOrderNotifier::class)->notifySalesRecipients($order);
        $this->rememberMarketplaceInterest($request, $product, 'checkout_started');
        $this->trackAttributedAdConversion($request, 'checkout_started', (int) ($quote['gross_cents'] ?? $order->amount_cents), [
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_category' => $product->category,
            'quantity' => $quantity,
        ]);

        return $this->startCheckout($order);
    }

    public function addCartItem(Request $request, MarketplaceProduct $product)
    {
        abort_unless(
            $product->status === 'published' && $product->moderation_status === 'approved',
            404,
        );
        $country = strtoupper((string) ($request->user()?->country ?: 'DE'));
        abort_unless($this->cartService->hasSellableStock($product, $country), 404);

        $data = $request->validate([
            'quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        $cart = $this->cartService->cartFor($request->user());
        $quantity = (int) ($data['quantity'] ?? 1);
        $item = $cart->items()->firstOrNew(['marketplace_product_id' => $product->id]);
        $item->quantity = (int) $item->quantity + $quantity;

        if (! $this->cartService->hasSellableStock($product, $country, (int) $item->quantity)) {
            throw ValidationException::withMessages([
                'quantity' => __('commerce.validation.stock_unavailable'),
            ]);
        }

        $item->save();
        $this->rememberMarketplaceInterest($request, $product, 'cart_add');
        $this->trackAttributedAdConversion($request, 'cart_add', (int) $product->price_cents, [
            'product_id' => $product->id,
            'product_category' => $product->category,
            'quantity' => $quantity,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'data' => $this->checkoutPayload->cartResource($request),
                'message' => __('commerce.flash.cart_added'),
            ], 201);
        }

        return back()->with('success', __('commerce.flash.cart_added'));
    }

    public function updateCartItem(Request $request, CommerceCartItem $item)
    {
        abort_unless((int) $item->cart->user_id === (int) $request->user()->id, 403);

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $product = $item->product;
        $country = strtoupper((string) ($request->user()?->country ?: 'DE'));
        abort_unless($product && $this->cartService->hasSellableStock($product, $country), 404);
        if (! $this->cartService->hasSellableStock($product, $country, (int) $data['quantity'])) {
            throw ValidationException::withMessages([
                'quantity' => __('commerce.validation.stock_unavailable'),
            ]);
        }

        $item->update(['quantity' => (int) $data['quantity']]);

        if ($request->expectsJson()) {
            return response()->json([
                'data' => $this->checkoutPayload->cartResource($request),
                'message' => __('commerce.flash.cart_updated'),
            ]);
        }

        return back()->with('success', __('commerce.flash.cart_updated'));
    }

    public function removeCartItem(Request $request, CommerceCartItem $item)
    {
        abort_unless((int) $item->cart->user_id === (int) $request->user()->id, 403);
        $item->delete();

        if ($request->expectsJson()) {
            return response()->json([
                'data' => $this->checkoutPayload->cartResource($request),
                'message' => __('commerce.flash.cart_removed'),
            ]);
        }

        return back()->with('success', __('commerce.flash.cart_removed'));
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
            'save_shipping_address' => ['nullable', 'boolean'],
            'shipping_address_label' => ['nullable', 'string', 'max:120'],
        ]);

        $cart = $this->cartService->cartFor($request->user());
        $shippingAddress = $this->checkoutPayload->shippingAddressForAuthenticatedUser($request, $data);
        $this->saveShippingAddressIfRequested($request, $data, $shippingAddress);
        $customer = $this->checkoutPayload->customerFromData($data);

        [$order, $summary] = DB::transaction(function () use ($request, $cart, $data, $shippingAddress, $customer) {
            $lockedCart = CommerceCart::query()->lockForUpdate()->findOrFail($cart->id);
            $items = $lockedCart->items()->lockForUpdate()->get();
            abort_if($items->isEmpty(), 422, __('commerce.validation.cart_empty'));

            $products = MarketplaceProduct::query()
                ->whereKey($items->pluck('marketplace_product_id')->unique())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($items as $item) {
                $product = $products->get($item->marketplace_product_id);
                abort_unless(
                    $product
                        && $product->status === 'published'
                        && $product->moderation_status === 'approved'
                        && $this->cartService->hasSellableStock($product, $shippingAddress['country'], (int) $item->quantity),
                    422,
                    __('commerce.validation.product_unavailable', ['product' => $product?->title ?: (string) $item->marketplace_product_id]),
                );

                $item->setRelation('product', $product);
            }
            $lockedCart->setRelation('items', $items);
            $summary = $this->cartService->quote($lockedCart, $shippingAddress, $customer);

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
                'customer_vat_is_valid' => $customer['vat_id'] ? $customer['vat_id_is_valid'] : null,
                'customer_vat_validated_at' => $customer['vat_id'] ? now() : null,
                'status' => 'pending',
                'payload' => ['pricing' => $summary, 'shipping_address' => $shippingAddress, 'cart_id' => $lockedCart->id],
            ]);

            foreach ($summary['items'] as $item) {
                $this->createOrderItem($order, $item['product'], $item['quote'], $item['quantity']);
            }

            $lockedCart->items()->delete();

            return [$order, $summary];
        });

        foreach ($summary['items'] as $item) {
            $this->rememberMarketplaceInterest($request, $item['product'], 'checkout_started');
        }
        app(CommerceOrderNotifier::class)->notifySalesRecipients($order);
        $this->trackAttributedAdConversion($request, 'checkout_started', (int) $summary['amount_cents'], [
            'order_id' => $order->id,
            'cart_id' => $cart->id,
            'items' => collect($summary['items'])->map(fn ($item) => [
                'product_id' => $item['product']->id,
                'category' => $item['product']->category,
                'quantity' => $item['quantity'],
            ])->values()->all(),
        ]);

        return $this->startCheckout($order);
    }

    public function storeOwnProduct(Request $request)
    {
        if (! $this->userCanSellInMarketplace($request->user())) {
            throw ValidationException::withMessages([
                'seller_application' => 'Bitte stelle zuerst einen Shop-Antrag und warte auf die Freigabe.',
            ]);
        }

        $data = $request->validate([
            'club_id' => ['nullable', Rule::exists('clubs', 'id')],
            'learning_course_id' => [
                'nullable',
                Rule::exists('learning_courses', 'id')->where('user_id', $request->user()->id),
            ],
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
            'teamwear_supplier' => ['nullable', 'string', 'max:160'],
            'teamwear_funded_share_cents' => ['nullable', 'integer', 'min:0'],
            'teamwear_personalization_rules' => ['nullable', 'array'],
            'teamwear_personalization_rules.allowed_types' => ['nullable', 'array', 'max:3'],
            'teamwear_personalization_rules.allowed_types.*' => ['nullable', Rule::in(['name', 'initials', 'number'])],
            'teamwear_personalization_rules.requires_team_member' => ['boolean'],
            'teamwear_personalization_rules.privacy_acknowledgement_required' => ['boolean'],
            'teamwear_personalization_rules.number_min' => ['nullable', 'integer', 'min:0', 'max:999'],
            'teamwear_personalization_rules.number_max' => ['nullable', 'integer', 'min:0', 'max:999'],
            'teamwear_personalization_rules.blocked_terms' => ['nullable', 'array', 'max:50'],
            'teamwear_personalization_rules.blocked_terms.*' => ['nullable', 'string', 'max:80'],
            'image_url' => ['nullable', 'url', 'max:2048'],
            'image_urls_text' => ['nullable', 'string', 'max:4000'],
            'image_upload' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'image_uploads' => ['nullable', 'array', 'max:8'],
            'image_uploads.*' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'category' => ['required', 'string', 'max:80'],
            'offer_type' => ['nullable', Rule::in(['physical_product', 'online_course', 'training_plan', 'camp', 'service'])],
            'product_type' => ['nullable', Rule::in(['single', 'variable', 'digital'])],
            'sku' => ['nullable', 'string', 'max:80'],
            'is_shippable' => ['boolean'],
            'manages_stock' => ['boolean'],
            'stock_quantity' => [
                Rule::requiredIf(fn () => $request->input('offer_type', 'physical_product') === 'physical_product'
                    && $request->input('product_type', 'single') !== 'digital'),
                'nullable',
                'integer',
                'min:1',
            ],
            'inventories' => ['nullable', 'array', 'max:20'],
            'inventories.*.country_code' => ['required_with:inventories', 'string', 'size:2'],
            'inventories.*.stock_quantity' => ['nullable', 'integer', 'min:0'],
            'inventories.*.low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'inventories.*.lead_time_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'inventories.*.city' => ['nullable', 'string', 'max:120'],
            'inventories.*.postal_code' => ['nullable', 'string', 'max:30'],
            'tax_class' => ['nullable', 'string', 'max:30'],
            'return_policy_type' => ['nullable', Rule::in(['standard', 'digital', 'service', 'hygiene', 'custom'])],
            'return_window_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'digital_delivery_note' => ['nullable', 'string', 'max:2000'],
            'course_outline_text' => ['nullable', 'string', 'max:4000'],
            'learning_goals_text' => ['nullable', 'string', 'max:4000'],
            'coaching_enabled' => ['boolean'],
            'coach_feedback_instructions' => ['nullable', 'string', 'max:2000'],
            'price_cents' => ['required', 'integer', 'min:0'],
        ]);

        $this->authorizedCommerceClub(
            $request,
            $data['club_id'] ?? null,
            ClubPermissions::COMMERCE_PRODUCTS_EDIT,
        );
        $attributesText = (string) ($data['attributes_text'] ?? '');
        $imageUrlsText = (string) ($data['image_urls_text'] ?? '');
        $courseOutlineText = (string) ($data['course_outline_text'] ?? '');
        $learningGoalsText = (string) ($data['learning_goals_text'] ?? '');
        $inventories = $data['inventories'] ?? [];
        $offerType = $data['offer_type'] ?? match ($data['category']) {
            'course' => 'online_course',
            'camp' => 'camp',
            'service' => 'service',
            default => 'physical_product',
        };

        if (in_array($offerType, ['online_course', 'training_plan'], true)) {
            $data['category'] = $data['category'] === 'course' ? 'course' : ($data['category'] ?: 'course');
            $data['product_type'] = 'digital';
            $data['is_shippable'] = false;
            $data['manages_stock'] = false;
            $data['stock_quantity'] = null;
        } elseif ($offerType === 'camp') {
            $data['category'] = 'camp';
            $data['learning_course_id'] = null;
        } elseif ($offerType === 'service') {
            throw ValidationException::withMessages([
                'offer_type' => 'Dienstleistungen werden aktuell nur intern von Airmius angelegt.',
            ]);
        } else {
            $data['learning_course_id'] = null;
            if (in_array($data['category'], ['course', 'camp', 'service', 'outfit_subscription'], true)) {
                $data['category'] = 'equipment';
            }
            if (($data['product_type'] ?? 'single') !== 'digital') {
                $data['manages_stock'] = true;
                $data['stock_quantity'] = max(1, (int) ($data['stock_quantity'] ?? 0));
            }
        }

        if (($data['learning_course_id'] ?? null) && $offerType !== 'online_course') {
            throw ValidationException::withMessages([
                'learning_course_id' => 'Ein Learning-Kurs kann nur mit einem Kurs / E-Learning Angebot verknüpft werden.',
            ]);
        }

        if ($data['learning_course_id'] ?? null) {
            $linkedCourse = LearningCourse::query()
                ->where('user_id', $request->user()->id)
                ->find($data['learning_course_id']);

            if (! $linkedCourse || $linkedCourse->status !== 'published' || ! $linkedCourse->is_public) {
                throw ValidationException::withMessages([
                    'learning_course_id' => 'Bitte verknüpfe nur veröffentlichte und öffentliche Sportschule-Kurse.',
                ]);
            }
        }

        $attributeOptions = MarketplaceProductInput::normalizeAttributeOptions($data['attribute_options'] ?? []);
        $variants = MarketplaceProductInput::normalizeVariants($data['variants'] ?? [], $attributeOptions, (int) $data['price_cents']);
        $teamwearRules = MarketplaceProductInput::normalizeTeamwearPersonalizationRules($data['teamwear_personalization_rules'] ?? []);
        $teamwearConflicts = MarketplaceProductInput::teamwearPersonalizationConflicts($teamwearRules);
        if ($teamwearConflicts !== []) {
            throw ValidationException::withMessages([
                'teamwear_personalization_rules' => implode(' ', array_values($teamwearConflicts)),
            ]);
        }
        unset($teamwearRules['has_duplicate_types']);
        if (($data['teamwear_funded_share_cents'] ?? 0) > (int) $data['price_cents']) {
            throw ValidationException::withMessages([
                'teamwear_funded_share_cents' => 'Der vereinsfinanzierte Anteil darf den Verkaufspreis nicht überschreiten.',
            ]);
        }
        unset($data['attributes_text']);
        unset($data['course_outline_text']);
        unset($data['learning_goals_text']);
        unset($data['attribute_options']);
        unset($data['variants']);
        unset($data['teamwear_personalization_rules']);
        unset($data['inventories']);
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
        $data['image_url'] = ($data['image_url'] ?? null) ?: ($galleryImages[0] ?? null);

        $product = DB::transaction(function () use ($request, $data, $attributesText, $attributeOptions, $variants, $teamwearRules, $offerType, $courseOutlineText, $learningGoalsText, $inventories) {
            $product = app(ClubShopProductNumberService::class)->create([
                ...$data,
                'product_attributes' => MarketplaceProductInput::attributesFromText($attributesText),
                'attribute_options' => $attributeOptions,
                'variants' => ($data['product_type'] ?? 'single') === 'variable' ? $variants : [],
                'teamwear_supplier' => filled($data['teamwear_supplier'] ?? null) ? trim((string) $data['teamwear_supplier']) : null,
                'teamwear_funded_share_cents' => (int) ($data['teamwear_funded_share_cents'] ?? 0),
                'teamwear_personalization_rules' => $teamwearRules,
                'product_type' => $data['product_type'] ?? 'single',
                'offer_type' => $offerType,
                'course_outline' => MarketplaceProductInput::linesFromText($courseOutlineText, 20),
                'learning_goals' => MarketplaceProductInput::linesFromText($learningGoalsText, 12),
                'coaching_enabled' => $offerType === 'training_plan' ? (bool) ($data['coaching_enabled'] ?? true) : (bool) ($data['coaching_enabled'] ?? false),
                'coach_feedback_instructions' => $data['coach_feedback_instructions'] ?? null,
                'user_id' => $request->user()->id,
                'currency' => 'EUR',
                'is_shippable' => ($data['product_type'] ?? 'single') === 'digital' ? false : (bool) ($data['is_shippable'] ?? $data['category'] === 'product'),
                'manages_stock' => (bool) ($data['manages_stock'] ?? false),
                'tax_class' => $data['tax_class'] ?? 'standard',
                'return_policy_type' => ($data['product_type'] ?? 'single') === 'digital' ? 'digital' : ($data['return_policy_type'] ?? 'standard'),
                'return_window_days' => ($data['product_type'] ?? 'single') === 'digital' ? 0 : (int) ($data['return_window_days'] ?? 14),
                'status' => 'published',
                'moderation_status' => 'approved',
                'commission_percent' => $this->pricing->commissionPercentFor((new MarketplaceProduct)->forceFill(['category' => $data['category']])),
                'payout_status' => 'pending_sales',
            ], $request->user());

            $this->syncSellerInventories($product, $inventories);

            return MarketplaceProductQualityGate::applyPublicationGate($product);
        });

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

        if ($request->expectsJson()) {
            $product = $product->fresh(['inventories.warehouse:id,name,country_code,city,postal_code']);

            return response()->json([
                'message' => $product->status === 'published'
                    ? 'Produkt wurde veröffentlicht.'
                    : 'Produkt wurde geprüft und abgelehnt.',
                'data' => $product,
            ], 201);
        }

        return back()->with('success', $product->fresh()->status === 'published'
            ? 'Produkt wurde automatisch geprüft und im Marketplace veröffentlicht.'
            : 'Produkt wurde automatisch geprüft und abgelehnt.');
    }

    private function syncSellerInventories(MarketplaceProduct $product, array $inventories): void
    {
        if (! (bool) $product->manages_stock || $product->isDigitalDelivery()) {
            $product->inventories()->update(['is_active' => false]);
            $product->forceFill(['available_countries' => null])->save();

            return;
        }

        $activeIds = [];
        $totalStock = 0;
        $countries = [];

        foreach ($inventories as $inventoryData) {
            $country = strtoupper(trim((string) ($inventoryData['country_code'] ?? '')));

            if (! preg_match('/^[A-Z]{2}$/', $country)) {
                continue;
            }

            $stock = max(0, (int) ($inventoryData['stock_quantity'] ?? 0));
            $warehouse = CommerceWarehouse::query()->firstOrCreate(
                [
                    'user_id' => $product->user_id,
                    'club_id' => $product->club_id,
                    'country_code' => $country,
                    'name' => 'Marketplace Lager '.$country,
                ],
                [
                    'city' => trim((string) ($inventoryData['city'] ?? '')) ?: null,
                    'postal_code' => trim((string) ($inventoryData['postal_code'] ?? '')) ?: null,
                    'is_active' => true,
                ],
            );

            $warehouse->forceFill([
                'city' => trim((string) ($inventoryData['city'] ?? '')) ?: $warehouse->city,
                'postal_code' => trim((string) ($inventoryData['postal_code'] ?? '')) ?: $warehouse->postal_code,
                'is_active' => true,
            ])->save();

            $inventory = MarketplaceProductInventory::query()->updateOrCreate(
                [
                    'marketplace_product_id' => $product->id,
                    'commerce_warehouse_id' => $warehouse->id,
                    'country_code' => $country,
                ],
                [
                    'stock_quantity' => $stock,
                    'reserved_quantity' => 0,
                    'low_stock_threshold' => max(0, (int) ($inventoryData['low_stock_threshold'] ?? 0)),
                    'lead_time_days' => ($inventoryData['lead_time_days'] ?? null) === null || $inventoryData['lead_time_days'] === ''
                        ? null
                        : max(0, (int) $inventoryData['lead_time_days']),
                    'is_active' => true,
                ],
            );

            $activeIds[] = $inventory->id;
            $totalStock += $stock;
            if ($stock > 0) {
                $countries[] = $country;
            }
        }

        $product->inventories()
            ->when($activeIds !== [], fn ($query) => $query->whereNotIn('id', $activeIds))
            ->update(['is_active' => false]);

        if ($activeIds !== []) {
            $product->forceFill([
                'stock_quantity' => $totalStock,
                'available_countries' => array_values(array_unique($countries)),
            ])->save();
        }
    }

    public function downloadProductImportTemplate(Request $request)
    {
        abort_unless($this->userCanSellInMarketplace($request->user()), 403);

        $path = $this->productImport->buildTemplate($this->marketplaceCategoryCommissionsForSeller());

        return response()
            ->download($path, 'airmius-marketplace-produkte-import.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend(true);
    }

    public function importOwnProducts(Request $request)
    {
        if (! $this->userCanSellInMarketplace($request->user())) {
            throw ValidationException::withMessages([
                'import_file' => 'Bitte stelle zuerst einen Shop-Antrag und warte auf die Freigabe.',
            ]);
        }

        $data = $request->validate([
            'import_file' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:8192'],
        ]);

        $file = $data['import_file'];
        $rows = $this->productImport->readRows($file->getRealPath(), $file->getClientOriginalExtension());

        if ($rows === []) {
            throw ValidationException::withMessages([
                'import_file' => 'Die Datei enthält keine gültigen Produktzeilen. Bitte nutze die Airmius-Vorlage.',
            ]);
        }

        $errors = [];
        $created = 0;

        DB::transaction(function () use ($rows, $request, &$errors, &$created) {
            foreach ($rows as $index => $row) {
                $line = $index + 2;
                $prepared = $this->productImport->productDataFromRow(
                    $row,
                    $request->user(),
                    fn (mixed $clubId) => $this->authorizedCommerceClub(
                        $request,
                        $clubId,
                        ClubPermissions::COMMERCE_PRODUCTS_EDIT,
                    ),
                    $line,
                    $errors,
                );

                if (! $prepared) {
                    continue;
                }

                $product = app(ClubShopProductNumberService::class)->create($prepared, $request->user());
                $this->moderation->flagIfNeeded(
                    $product,
                    trim($product->title.' '.$product->description),
                    $request->user()->id,
                    'marketplace_excel_import',
                );

                if ($product->fresh()->moderation_status === 'removed') {
                    $product->forceFill([
                        'status' => 'rejected',
                        'rejection_reason' => 'Automatische Ablehnung wegen schwerem Moderationsrisiko. Der Fall wurde für Admins markiert.',
                    ])->save();
                }

                $created++;
            }

            if ($errors !== []) {
                throw ValidationException::withMessages([
                    'import_file' => implode(' ', array_slice($errors, 0, 8)),
                ]);
            }
        });

        return back()->with('success', $created.' Produkte wurden importiert und automatisch geprüft.');
    }

    public function updateOwnProduct(Request $request, MarketplaceProduct $product)
    {
        $this->authorizeOwnProduct($request, $product);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'image_url' => ['nullable', 'url', 'max:2048'],
            'image_upload' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'sku' => ['nullable', 'string', 'max:80'],
            'price_cents' => ['required', 'integer', 'min:0'],
            'manages_stock' => ['boolean'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'inventories' => ['nullable', 'array', 'max:20'],
            'inventories.*.country_code' => ['required_with:inventories', 'string', 'size:2'],
            'inventories.*.stock_quantity' => ['nullable', 'integer', 'min:0'],
            'inventories.*.low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'inventories.*.lead_time_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'inventories.*.city' => ['nullable', 'string', 'max:120'],
            'inventories.*.postal_code' => ['nullable', 'string', 'max:30'],
            'teamwear_supplier' => ['nullable', 'string', 'max:160'],
            'teamwear_funded_share_cents' => ['nullable', 'integer', 'min:0'],
            'teamwear_personalization_rules' => ['nullable', 'array'],
            'teamwear_personalization_rules.allowed_types' => ['nullable', 'array', 'max:3'],
            'teamwear_personalization_rules.allowed_types.*' => ['nullable', Rule::in(['name', 'initials', 'number'])],
            'teamwear_personalization_rules.requires_team_member' => ['boolean'],
            'teamwear_personalization_rules.privacy_acknowledgement_required' => ['boolean'],
            'teamwear_personalization_rules.number_min' => ['nullable', 'integer', 'min:0', 'max:999'],
            'teamwear_personalization_rules.number_max' => ['nullable', 'integer', 'min:0', 'max:999'],
            'teamwear_personalization_rules.blocked_terms' => ['nullable', 'array', 'max:50'],
            'teamwear_personalization_rules.blocked_terms.*' => ['nullable', 'string', 'max:80'],
        ]);

        if ($request->hasFile('image_upload')) {
            $stored = app(MediaOptimizer::class)->store($request->file('image_upload'), 'marketplace/products');
            $data['image_url'] = UploadStorage::url($stored['path']);
        }

        $data['manages_stock'] = (bool) ($data['manages_stock'] ?? false);
        $data['stock_quantity'] = $data['manages_stock'] ? (int) ($data['stock_quantity'] ?? 0) : null;
        $data['sku'] = filled($data['sku'] ?? null) ? trim((string) $data['sku']) : null;
        $data['teamwear_supplier'] = filled($data['teamwear_supplier'] ?? null) ? trim((string) $data['teamwear_supplier']) : null;
        $data['teamwear_funded_share_cents'] = (int) ($data['teamwear_funded_share_cents'] ?? 0);
        if ($data['teamwear_funded_share_cents'] > (int) $data['price_cents']) {
            throw ValidationException::withMessages([
                'teamwear_funded_share_cents' => 'Der vereinsfinanzierte Anteil darf den Verkaufspreis nicht überschreiten.',
            ]);
        }
        $teamwearRules = MarketplaceProductInput::normalizeTeamwearPersonalizationRules($data['teamwear_personalization_rules'] ?? []);
        $teamwearConflicts = MarketplaceProductInput::teamwearPersonalizationConflicts($teamwearRules);
        if ($teamwearConflicts !== []) {
            throw ValidationException::withMessages([
                'teamwear_personalization_rules' => implode(' ', array_values($teamwearConflicts)),
            ]);
        }
        unset($teamwearRules['has_duplicate_types']);
        $data['teamwear_personalization_rules'] = $teamwearRules;
        $data['gallery_images'] = collect([$data['image_url'] ?? $product->image_url, ...($product->gallery_images ?? [])])
            ->filter()
            ->unique()
            ->take(12)
            ->values()
            ->all();
        $data['status'] = $product->status === 'archived' ? 'archived' : 'review';
        $data['rejection_reason'] = null;
        $inventories = $data['inventories'] ?? [];
        unset($data['inventories']);

        DB::transaction(function () use ($product, $data, $inventories) {
            $product->update($data);
            $this->syncSellerInventories($product->fresh(), $inventories);
        });

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('commerce.flash.product_updated'),
                'data' => $product->fresh(['inventories.warehouse:id,name,country_code,city,postal_code']),
            ]);
        }

        return back()->with('success', __('commerce.flash.product_updated'));
    }

    public function updateOwnProductStatus(Request $request, MarketplaceProduct $product)
    {
        $this->authorizeOwnProduct($request, $product);

        $data = $request->validate([
            'status' => ['required', Rule::in(['draft', 'review', 'archived'])],
        ]);

        $product->update([
            'status' => $data['status'],
            'rejection_reason' => $data['status'] === 'review' ? null : $product->rejection_reason,
        ]);

        $message = match ($data['status']) {
            'archived' => 'Produkt wurde archiviert.',
            'review' => 'Produkt wurde zur Prüfung eingereicht.',
            default => 'Produkt wurde als Entwurf gespeichert.',
        };

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'data' => $product->fresh(),
            ]);
        }

        return back()->with('success', $message);
    }

    public function destroyOwnProduct(Request $request, MarketplaceProduct $product)
    {
        $this->authorizeOwnProduct($request, $product);

        $hasOrders = CommerceOrder::query()
            ->where('orderable_type', MarketplaceProduct::class)
            ->where('orderable_id', $product->id)
            ->exists()
            || CommerceOrderItem::query()
                ->where('orderable_type', MarketplaceProduct::class)
                ->where('orderable_id', $product->id)
                ->exists();

        if ($hasOrders) {
            $product->update(['status' => 'archived']);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => __('commerce.flash.product_archived_orders'),
                    'data' => $product->fresh(),
                ]);
            }

            return back()->with('success', __('commerce.flash.product_archived_orders'));
        }

        $product->delete();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('commerce.flash.product_deleted'),
            ]);
        }

        return back()->with('success', __('commerce.flash.product_deleted'));
    }

    public function showProduct(Request $request, MarketplaceProduct $product)
    {
        abort_unless(
            ($product->status === 'published' && $product->moderation_status === 'approved')
                || $product->user_id === $request->user()->id
                || $request->user()->can('subscriptions.manage'),
            404,
        );

        $product->load(['user:id,name', 'club:id,name']);
        $shippingAddress = $this->checkoutPayload->shippingAddressForAuthenticatedUser($request, []);
        $quote = $this->pricing->quoteForRequest($product, $request, $shippingAddress['country'], $shippingAddress);
        $this->rememberMarketplaceInterest($request, $product, 'view');

        return inertia('Auth/Dashboard/Commerce/ProductShow', [
            'product' => [
                ...$product->toArray(),
                'gallery_images' => $product->gallery_images ?: array_values(array_filter([$product->image_url])),
                'user' => $product->user,
                'club' => $product->club,
                'price' => $quote,
            ],
            'pricingCountries' => $this->checkoutPayload->pricingCountries(),
            'checkoutAddress' => $shippingAddress,
            'profileAddress' => $this->checkoutPayload->profileAddressFor($request->user()),
            'shippingAddresses' => $this->checkoutPayload->shippingAddressesFor($request->user()),
        ]);
    }

    public function reportOrderIssue(Request $request, CommerceOrder $order)
    {
        abort_unless($order->user_id === $request->user()->id, 403);
        abort_unless($order->status === 'completed', 422, __('commerce.validation.issue_paid_order_required'));

        $support = $this->orderSupport->summary($order->loadMissing(['items.orderable', 'returnRequests']));
        abort_unless($support['can_report_issue'], 422, __('commerce.validation.issue_already_open'));

        $data = $request->validate([
            'issue_note' => ['required', 'string', 'max:2000'],
        ]);

        $order->update([
            'issue_status' => 'reported',
            'issue_note' => $data['issue_note'],
            'issue_reported_at' => now(),
        ]);

        app(CommerceOrderNotifier::class)->notifyIssueReported($order->fresh(['items.orderable', 'orderable', 'user']));

        if ($request->expectsJson()) {
            return $this->orderJsonResponse(
                $order->fresh(['club', 'items.orderable', 'returnRequests']),
            );
        }

        return back()->with('success', __('commerce.flash.issue_reported'));
    }

    public function cancelOrder(Request $request, CommerceOrder $order)
    {
        abort_unless($order->user_id === $request->user()->id, 403);
        abort_unless(in_array($order->type, ['marketplace_product', 'marketplace_cart'], true), 422, __('commerce.validation.marketplace_order_required'));
        abort_unless(in_array($order->status, ['pending', 'awaiting_transfer', 'completed'], true), 422, __('commerce.validation.order_not_cancellable'));
        abort_if(in_array($order->shipping_status, ['shipped', 'delivered'], true), 422, __('commerce.validation.order_already_shipped'));

        $order->loadMissing(['items.orderable', 'orderable', 'user']);
        $hasShippableItems = $order->items->contains(fn (CommerceOrderItem $item) => (bool) $item->is_shippable);
        $hasLearningProduct = $this->learningOrders->productsForOrder($order)->isNotEmpty();
        abort_unless($hasShippableItems || $hasLearningProduct, 422, __('commerce.validation.order_cancellation_unsupported'));

        DB::transaction(function () use ($order, $request) {
            if ($order->status === 'completed' && $order->items->contains(fn (CommerceOrderItem $item) => (bool) $item->is_shippable)) {
                $this->restoreMarketplaceStock($order, 'Storno vor Versand Bestellung #'.$order->id);
            }

            $creditNoteNumber = $order->credit_note_number ?: app(ClubShopOrderNumberService::class)->assign(
                $order,
                'shop_credit_note',
                fn () => $this->nextDocumentNumber('commerce_credit_note_number_next', 'AIR-GS'),
                $request->user(),
            );
            $order->forceFill([
                'status' => 'cancelled',
                'issue_status' => 'cancelled',
                'issue_note' => $order->issue_note ?: __('commerce.validation.buyer_cancelled_note'),
                'issue_reported_at' => $order->issue_reported_at ?: now(),
                'credit_note_number' => $creditNoteNumber,
                'payout_status' => 'cancelled',
            ])->save();

            $this->learningOrders->revokeAccessForOrder($order, 'cancelled');
        });

        AppNotification::sendLocalized(
            $order->user ?? $order->user_id,
            'commerce.order.cancelled',
            'commerce.notifications.cancelled_title',
            'commerce.notifications.cancelled_body',
            ['id' => $order->id],
            [
                'url' => route('auth.commerce.index', ['tab' => 'invoices', 'order' => $order->id]),
                'order_id' => $order->id,
            ],
        );

        app(CommerceOrderNotifier::class)->notifyBuyerCancelled($order->fresh(['items.orderable', 'orderable', 'user']));

        if ($request->expectsJson()) {
            return $this->orderJsonResponse(
                $order->fresh(['club', 'items.orderable', 'returnRequests']),
            );
        }

        return back()->with('success', __('commerce.flash.order_cancelled'));
    }

    public function requestReturn(Request $request, CommerceOrder $order)
    {
        abort_unless($order->user_id === $request->user()->id, 403);
        abort_unless($order->status === 'completed', 422, __('commerce.validation.paid_order_required'));

        $support = $this->orderSupport->summary($order->loadMissing(['items.orderable', 'returnRequests']));
        abort_unless($support['can_request_return'], 422, $this->orderSupport->returnBlockedReasonMessage($support['return_blocked_reason'] ?? null));

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
            'commerce_order_item_id' => ['nullable', 'integer', Rule::exists('commerce_order_items', 'id')],
            'quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        $item = $order->items()->when($data['commerce_order_item_id'] ?? null, fn ($query, $id) => $query->whereKey($id))->first();
        abort_if($item && ! $item->is_shippable, 422, __('commerce.validation.return_not_required'));
        abort_if($item && ! $this->orderSupport->itemStillReturnable($item), 422, __('commerce.validation.return_expired'));

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

        if ($request->expectsJson()) {
            return $this->orderJsonResponse(
                $order->fresh(['club', 'items.orderable', 'returnRequests']),
                201,
            );
        }

        return back()->with('success', __('commerce.flash.return_requested'));
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

    public function downloadSignedDocument(CommerceOrder $order, string $type)
    {
        abort_unless(in_array($type, ['invoice', 'credit-note'], true), 404);

        $documentType = $type === 'credit-note' ? 'credit_note' : 'invoice';
        $documentNumber = $documentType === 'credit_note'
            ? $order->credit_note_number
            : $order->invoice_number;

        abort_unless($documentNumber, 404);

        return response($this->documents->pdf($order, $documentType), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$documentNumber.'.pdf"',
            'Cache-Control' => 'private, no-store, max-age=0',
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
            'placement' => ['nullable', Rule::in(['marketplace_card', 'feed', 'sidebar', 'sponsor_section'])],
            'creative_format' => ['nullable', Rule::in(['feed_square', 'feed_portrait', 'story_vertical', 'banner_wide'])],
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
            'provider' => ['nullable', Rule::in(['stripe', 'paypal', 'bank_transfer'])],
            'accepted_terms' => ['accepted_if:start_payment,1'],
            'client_reference' => ['nullable', 'string', 'max:120'],
            'start_payment' => ['boolean'],
        ]);

        $this->authorizedCommerceClub(
            $request,
            $data['club_id'] ?? null,
            ClubPermissions::ADVERTISING_EDIT,
        );
        $startPayment = (bool) ($data['start_payment'] ?? false);

        if ($startPayment && ! ($data['accepted_terms'] ?? false)) {
            throw ValidationException::withMessages([
                'accepted_terms' => __('commerce.validation.terms_required'),
            ]);
        }

        $clientReference = $data['client_reference'] ?? null;
        if ($clientReference) {
            $existingOrder = CommerceOrder::query()
                ->where('user_id', $request->user()->id)
                ->where('type', 'ads_campaign')
                ->where('payload->ads_client_reference', $clientReference)
                ->whereIn('status', ['pending', 'awaiting_transfer', 'completed'])
                ->latest('id')
                ->first();

            if ($existingOrder) {
                if ($existingOrder->status === 'completed') {
                    return redirect()->route('auth.commerce.index')->with('success', __('commerce.flash.ads_payment_processed'));
                }

                return $this->startCheckout($existingOrder);
            }
        }

        $data['audience'] = [
            'locations' => $this->splitCampaignList($data['audience_locations'] ?? ''),
            'interests' => $this->splitCampaignList($data['audience_interests'] ?? ''),
            'age_min' => $data['audience_age_min'] ?? null,
            'age_max' => $data['audience_age_max'] ?? null,
        ];
        $creativeRows = $data['creatives'] ?? [];
        $provider = $data['provider'] ?? 'bank_transfer';
        unset($data['audience_locations'], $data['audience_interests'], $data['audience_age_min'], $data['audience_age_max'], $data['creative_image_upload'], $data['creatives'], $data['provider'], $data['accepted_terms'], $data['client_reference'], $data['start_payment']);

        if ($request->hasFile('creative_image_upload')) {
            $data['creative_image_path'] = $this->mediaOptimizer->store($request->file('creative_image_upload'), 'ads/creatives')['path'];
            $data['creative_image_url'] = null;
        }

        $campaign = AdCampaign::create([
            ...$data,
            'user_id' => $request->user()->id,
            'placement' => $data['placement'] ?? 'feed',
            'creative_format' => $data['creative_format'] ?? 'feed_square',
            'daily_budget_cents' => (int) ($data['daily_budget_cents'] ?? 0),
            'billing_event' => 'impression',
            'status' => $startPayment ? 'pending_payment' : 'draft',
        ]);

        $this->syncAdCreatives($campaign, $creativeRows);

        if (! $startPayment) {
            if ($request->expectsJson()) {
                return response()->json([
                    'data' => $this->campaignResourceWithPreviews(
                        $campaign->fresh(['creatives', 'groups.creatives', 'stats']),
                    ),
                ], 201);
            }

            return redirect()
                ->route('auth.commerce.index', ['tab' => 'ads'])
                ->with('success', __('commerce.flash.ads_draft_created'));
        }

        $order = CommerceOrder::create([
            'user_id' => $request->user()->id,
            'club_id' => $campaign->club_id,
            'orderable_type' => $campaign::class,
            'orderable_id' => $campaign->id,
            'type' => 'ads_campaign',
            'provider' => $provider,
            'item_gross_cents' => (int) $campaign->budget_cents,
            'net_cents' => (int) $campaign->budget_cents,
            'tax_cents' => 0,
            'amount_cents' => (int) $campaign->budget_cents,
            'currency' => 'EUR',
            'status' => 'pending',
            'payload' => [
                'campaign_id' => $campaign->id,
                'campaign_name' => $campaign->name,
                'budget_cents' => (int) $campaign->budget_cents,
                'ads_client_reference' => $clientReference,
            ],
        ]);

        $order->items()->create([
            'orderable_type' => $campaign::class,
            'orderable_id' => $campaign->id,
            'title' => __('commerce.order_items.ads_campaign', ['name' => $campaign->name]),
            'quantity' => 1,
            'unit_gross_cents' => (int) $campaign->budget_cents,
            'net_cents' => (int) $campaign->budget_cents,
            'total_cents' => (int) $campaign->budget_cents,
            'currency' => 'EUR',
            'is_shippable' => false,
        ]);

        return $this->startCheckout($order);
    }

    public function updateOwnCampaignStatus(Request $request, AdCampaign $campaign)
    {
        $this->authorizeOwnCampaign($request, $campaign);

        $data = $request->validate([
            'status' => ['required', Rule::in(['draft', 'pending_review', 'active', 'paused'])],
        ]);

        if ($data['status'] === 'active') {
            if ($campaign->status !== 'paused' || ! $campaign->reviewed_at || ! $this->campaignPaymentCompleted($campaign)) {
                throw ValidationException::withMessages([
                    'campaign_status' => __('commerce.validation.campaign_paid_and_approved_required'),
                ]);
            }
        }

        if ($data['status'] === 'pending_review' && ! $this->campaignPaymentCompleted($campaign)) {
            throw ValidationException::withMessages([
                'campaign_status' => __('commerce.validation.campaign_payment_required'),
            ]);
        }

        $campaign->forceFill([
            'status' => $data['status'],
        ])->save();

        if ($request->expectsJson()) {
            return response()->json([
                'data' => $this->campaignResourceWithPreviews(
                    $campaign->fresh(['creatives', 'groups.creatives', 'stats']),
                ),
            ]);
        }

        return back()->with('success', __('commerce.flash.campaign_status_updated'));
    }

    public function storeOwnCampaignGroup(Request $request, AdCampaign $campaign)
    {
        $this->authorizeOwnCampaign($request, $campaign);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'placement' => ['required', Rule::in(['marketplace_card', 'feed', 'sidebar', 'sponsor_section'])],
            'sports' => ['nullable', 'array', 'max:20'],
            'sports.*' => ['string', 'max:120'],
            'interests' => ['nullable', 'string', 'max:500'],
            'gender' => ['nullable', Rule::in(['all', 'female', 'male', 'diverse'])],
            'age_min' => ['nullable', 'integer', 'min:13', 'max:100'],
            'age_max' => ['nullable', 'integer', 'min:13', 'max:100', 'gte:age_min'],
            'locations' => ['nullable', 'string', 'max:500'],
            'zones' => ['nullable', 'string', 'max:500'],
            'daily_budget_cents' => ['nullable', 'integer', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        AdGroup::create([
            'ad_campaign_id' => $campaign->id,
            'name' => $data['name'],
            'placement' => $data['placement'],
            'audience' => [
                'sports' => array_values(array_filter($data['sports'] ?? [])),
                'interests' => $this->splitCampaignList($data['interests'] ?? ''),
                'gender' => $data['gender'] ?? 'all',
                'age_min' => $data['age_min'] ?? null,
                'age_max' => $data['age_max'] ?? null,
                'locations' => $this->splitCampaignList($data['locations'] ?? ''),
                'zones' => $this->splitCampaignList($data['zones'] ?? ''),
            ],
            'daily_budget_cents' => (int) ($data['daily_budget_cents'] ?? 0),
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'status' => 'draft',
        ]);

        return redirect()
            ->route('auth.commerce.index', ['tab' => 'ads'])
            ->with('success', __('commerce.flash.ad_group_created'));
    }

    public function storeOwnCampaignGroupCreatives(Request $request, AdCampaign $campaign, AdGroup $group)
    {
        $this->authorizeOwnCampaign($request, $campaign);
        abort_unless((int) $group->ad_campaign_id === (int) $campaign->id, 404);

        $data = $request->validate([
            'ad_name' => ['required', 'string', 'max:120'],
            'creatives' => ['required', 'array', 'min:1', 'max:6'],
            'creatives.*.name' => ['nullable', 'string', 'max:80'],
            'creatives.*.headline' => ['nullable', 'string', 'max:120'],
            'creatives.*.description' => ['nullable', 'string', 'max:2000'],
            'creatives.*.primary_text' => ['nullable', 'string', 'max:500'],
            'creatives.*.target_url' => ['nullable', 'url', 'max:255'],
            'creatives.*.cta_label' => ['nullable', 'string', 'max:80'],
            'creatives.*.creative_image_url' => ['nullable', 'url', 'max:255'],
            'creatives.*.weight' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'creatives.*.is_active' => ['boolean'],
        ]);

        $created = $this->syncAdCreatives(
            $campaign,
            $data['creatives'],
            $group,
            trim((string) $data['ad_name']),
            false,
        );

        if ($created === 0) {
            throw ValidationException::withMessages([
                'creatives' => __('commerce.validation.creative_required'),
            ]);
        }

        return redirect()
            ->route('auth.commerce.index', ['tab' => 'ads'])
            ->with('success', __('commerce.flash.ad_creatives_created'));
    }

    public function updateOwnCampaign(Request $request, AdCampaign $campaign)
    {
        $this->authorizeOwnCampaign($request, $campaign);

        abort_if($campaign->status === 'completed', 422, __('commerce.validation.campaign_completed'));

        $minimumBudget = (int) Setting::valueFor('ads_min_budget_cents', 1000);
        $paymentCompleted = $this->campaignPaymentCompleted($campaign);

        $data = $request->validate([
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
            'budget_cents' => [$paymentCompleted ? 'nullable' : 'required', 'integer', 'min:'.$minimumBudget],
            'daily_budget_cents' => ['nullable', 'integer', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        $creativeRows = $data['creatives'] ?? [];
        $data['audience'] = [
            'locations' => $this->splitCampaignList($data['audience_locations'] ?? ''),
            'interests' => $this->splitCampaignList($data['audience_interests'] ?? ''),
            'age_min' => $data['audience_age_min'] ?? null,
            'age_max' => $data['audience_age_max'] ?? null,
        ];
        unset($data['audience_locations'], $data['audience_interests'], $data['audience_age_min'], $data['audience_age_max'], $data['creative_image_upload'], $data['creatives']);

        if ($request->hasFile('creative_image_upload')) {
            $data['creative_image_path'] = $this->mediaOptimizer->store($request->file('creative_image_upload'), 'ads/creatives')['path'];
            $data['creative_image_url'] = null;
        }

        $data['daily_budget_cents'] = (int) ($data['daily_budget_cents'] ?? 0);

        if ($paymentCompleted) {
            unset($data['budget_cents']);
            $data['status'] = 'pending_review';
            $data['reviewed_at'] = null;
            $data['review_note'] = 'Nach Bearbeitung erneut zur Prüfung eingereicht.';
        } else {
            $data['status'] = 'pending_payment';
            $data['reviewed_at'] = null;
        }

        $campaign->update($data);
        $this->syncAdCreatives($campaign, $creativeRows);

        if (! $paymentCompleted) {
            $order = CommerceOrder::query()
                ->where('type', 'ads_campaign')
                ->where('orderable_type', AdCampaign::class)
                ->where('orderable_id', $campaign->id)
                ->whereIn('status', ['pending', 'awaiting_transfer'])
                ->latest('id')
                ->first();

            if ($order) {
                $amount = (int) $campaign->fresh()->budget_cents;
                $order->forceFill([
                    'item_gross_cents' => $amount,
                    'net_cents' => $amount,
                    'tax_cents' => 0,
                    'amount_cents' => $amount,
                    'payload' => [
                        ...($order->payload ?: []),
                        'campaign_name' => $campaign->name,
                        'budget_cents' => $amount,
                    ],
                ])->save();

                $order->items()->update([
                    'title' => __('commerce.order_items.ads_campaign', ['name' => $campaign->name]),
                    'unit_gross_cents' => $amount,
                    'net_cents' => $amount,
                    'total_cents' => $amount,
                ]);
            }
        }

        if ($request->expectsJson()) {
            return response()->json([
                'data' => $this->campaignResourceWithPreviews(
                    $campaign->fresh(['creatives', 'groups.creatives', 'stats']),
                ),
            ]);
        }

        return back()->with('success', $paymentCompleted
            ? __('commerce.flash.campaign_updated_review')
            : __('commerce.flash.campaign_updated_payment'));
    }

    public function destroyOwnCampaign(Request $request, AdCampaign $campaign)
    {
        $this->authorizeOwnCampaign($request, $campaign);

        $request->validate([
            'confirmation' => ['required', 'in:delete'],
        ]);

        $paths = collect([$campaign->creative_image_path])
            ->merge($campaign->creatives()->pluck('creative_image_path'))
            ->filter()
            ->unique()
            ->values();

        foreach ($paths as $path) {
            Storage::disk(UploadStorage::disk())->delete($path);
        }

        $campaign->delete();

        if ($request->expectsJson()) {
            return response()->json(['data' => ['deleted' => true]]);
        }

        return back()->with('success', __('commerce.flash.campaign_deleted'));
    }

    public function storeWebsiteRequest(Request $request)
    {
        $data = $request->validate(WebsiteRequestService::authenticatedRules());

        $this->authorizedCommerceClub(
            $request,
            $data['club_id'] ?? null,
            ClubPermissions::WEBSITE_REQUEST_CREATE,
        );

        $websiteRequest = $this->websiteRequests->createForUser($request->user(), $data);

        if ($request->expectsJson()) {
            return response()->json([
                'data' => $websiteRequest->fresh('club:id,name'),
            ], 201);
        }

        return back()->with('success', __('commerce.flash.website_request_sent'));
    }

    public function storePublicWebsiteRequest(Request $request)
    {
        $data = $request->validate(WebsiteRequestService::publicRules());
        $this->websiteRequests->createPublic($data, $request->user());

        return back()->with('success', __('agency.flash.request_sent'));
    }

    public function success(Request $request, CommerceOrder $order)
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        if ($order->provider === 'stripe' && $order->status === 'pending') {
            $this->payments->syncStripeOrder($order, $request->query('session_id'), fn (CommerceOrder $paidOrder) => $this->activate($paidOrder));
        }

        if ($order->provider === 'paypal' && $order->status === 'pending') {
            $this->payments->capturePayPalOrder($order, fn (CommerceOrder $paidOrder) => $this->activate($paidOrder));
        }

        return redirect()->route('auth.commerce.index')->with('success', __('commerce.flash.order_processed'));
    }

    public function cancel(Request $request, CommerceOrder $order)
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        $this->cancelPendingProviderOrder($order, $request->user()->id);

        return redirect()->route('auth.commerce.index')->with('success', __('commerce.flash.order_payment_cancelled'));
    }

    public function bankTransfer(Request $request, CommerceOrder $order)
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        return inertia('Auth/Dashboard/Commerce/BankTransfer', [
            'order' => $this->orderResource($order->load(['orderable', 'items', 'returnRequests'])),
            'bank' => $this->payments->bankTransferSettings(),
        ]);
    }

    public function guestSuccess(Request $request, CommerceOrder $order, string $token)
    {
        $this->authorizeGuestOrder($order, $token);

        if ($order->provider === 'stripe' && $order->status === 'pending') {
            $this->payments->syncStripeOrder($order, $request->query('session_id'), fn (CommerceOrder $paidOrder) => $this->activate($paidOrder));
        }

        if ($order->provider === 'paypal' && $order->status === 'pending') {
            $this->payments->capturePayPalOrder($order, fn (CommerceOrder $paidOrder) => $this->activate($paidOrder));
        }

        return $this->privateGuestOrderPage($request, 'Guest/MarketplaceOrderStatus', [
            'status' => 'success',
            'order' => $this->orderResource($order->refresh()->load(['orderable', 'items', 'returnRequests'])),
        ]);
    }

    public function guestCancel(Request $request, CommerceOrder $order, string $token)
    {
        $this->authorizeGuestOrder($order, $token);

        $this->cancelPendingProviderOrder($order);

        return $this->privateGuestOrderPage($request, 'Guest/MarketplaceOrderStatus', [
            'status' => 'cancelled',
            'order' => $this->orderResource($order->load(['orderable', 'items', 'returnRequests'])),
        ]);
    }

    public function guestBankTransfer(Request $request, CommerceOrder $order, string $token)
    {
        $this->authorizeGuestOrder($order, $token);

        return $this->privateGuestOrderPage($request, 'Guest/MarketplaceBankTransfer', [
            'order' => $this->orderResource($order->load(['orderable', 'items', 'returnRequests'])),
            'bank' => $this->payments->bankTransferSettings(),
        ]);
    }

    private function privateGuestOrderPage(Request $request, string $component, array $props): Response
    {
        $response = Inertia::render($component, $props)->toResponse($request);
        $response->headers->set('Cache-Control', 'private, no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }

    public function guestReturn(Request $request, CommerceOrder $order, string $token)
    {
        $this->authorizeGuestOrder($order, $token);
        abort_unless($order->status === 'completed', 422, 'Rücksendungen sind nur für bezahlte Bestellungen möglich.');

        $support = $this->orderSupport->summary($order->loadMissing(['items.orderable', 'returnRequests']));
        abort_unless($support['can_request_return'], 422, $this->orderSupport->returnBlockedReasonMessage($support['return_blocked_reason'] ?? null));

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);
        $item = $order->items->first(fn (CommerceOrderItem $item) => $item->is_shippable && $this->orderSupport->itemStillReturnable($item));

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

        return back()->with('success', __('commerce.flash.return_requested'));
    }

    public function activeAd(Request $request)
    {
        $placement = $request->string('placement')->toString();
        $objective = $request->string('objective')->toString();

        $campaigns = AdCampaign::query()
            ->where('status', 'active')
            ->where(function ($query) {
                $query->where('is_internal', true)
                    ->orWhereColumn('spent_cents', '<', 'budget_cents');
            })
            ->where(function ($query) {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->when($placement, fn ($query) => $query->where('placement', $placement))
            ->when($objective, fn ($query) => $query->where('objective', $objective))
            ->orderByDesc('force_priority')
            ->orderByDesc('is_internal')
            ->latest('id')
            ->with('creatives')
            ->with(['stats' => fn ($query) => $query->where('date', today()->toDateString())])
            ->limit(50)
            ->get();

        $this->loadCampaignDeliverySignals($campaigns, $request);

        $campaigns = $campaigns
            ->filter(fn (AdCampaign $campaign) => $this->campaignHasBudgetFor($campaign, 'impression'))
            ->filter(fn (AdCampaign $campaign) => $this->campaignPassesFrequencyCap($request, $campaign))
            ->filter(fn (AdCampaign $campaign) => $this->campaignPassesBudgetPacing($campaign))
            ->filter(fn (AdCampaign $campaign) => $this->campaignMatchesAudience($request, $campaign));

        $campaign = $this->chooseCampaignForDelivery($campaigns, $request);

        if (! $campaign) {
            return response()->json(null);
        }

        $this->loadCreativeDeliverySignals($campaign);
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
            'cta_label' => $creative?->cta_label ?: ($campaign->cta_label ?: __('commerce.ads.default_cta')),
            'objective' => $campaign->objective,
            'placement' => $campaign->placement,
            'creative_format' => $creative?->creative_format ?: $campaign->creative_format,
            'image_url' => $this->creativeImageUrl($creative) ?: (UploadStorage::url($campaign->creative_image_path) ?: $campaign->creative_image_url),
            'click_url' => route('ads.click', array_filter(['campaign' => $campaign->id, 'c' => $creative?->id])),
        ]);
    }

    public function clickAd(Request $request, AdCampaign $campaign)
    {
        $creative = $this->creativeFromRequest($request, $campaign);

        if ($campaign->status === 'active' && $this->campaignHasBudgetFor($campaign, 'click')) {
            $this->recordAdEvent($request, $campaign, 'click', 0, [], $creative);
            $this->rememberAdClick($request, $campaign, $creative);
        }

        return redirect()->away($this->safeAdTargetUrl($creative?->target_url ?: $campaign->target_url));
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

    public function trackAttributedAdConversion(Request $request, string $conversionType, int $valueCents = 0, array $metadata = []): ?AdEvent
    {
        if (! $this->allowsAdMeasurement($request)) {
            return null;
        }

        $click = $request->session()->get('last_ad_click');

        if (! is_array($click) || empty($click['campaign_id']) || empty($click['clicked_at'])) {
            return null;
        }

        if (! $this->adTimestampWithinDays($click['clicked_at'], 14)) {
            $request->session()->forget('last_ad_click');

            return null;
        }

        $campaign = AdCampaign::query()->find($click['campaign_id']);

        if (! $campaign || $campaign->status !== 'active') {
            return null;
        }

        $creative = $this->creativeFromRequest($request, $campaign, $click['creative_id'] ?? null);
        $eventType = $conversionType === 'sale' ? 'sale' : 'lead';

        return $this->recordAdEvent($request, $campaign, $eventType, $valueCents, [
            ...$metadata,
            'conversion_type' => $conversionType,
            'attribution' => 'last_click',
            'clicked_at' => $click['clicked_at'],
            'target_url' => $click['target_url'] ?? null,
        ], $creative);
    }

    public function rememberMarketplaceInterest(Request $request, MarketplaceProduct $product, string $eventType = 'view'): void
    {
        if (! $this->allowsAdPersonalization($request)) {
            return;
        }

        $interests = collect($request->session()->get('ad_marketplace_interests', []))
            ->filter(fn ($row) => is_array($row) && ! empty($row['at']))
            ->filter(fn ($row) => $this->adTimestampWithinDays($row['at'], 14))
            ->reject(fn ($row) => (int) ($row['product_id'] ?? 0) === (int) $product->id)
            ->prepend([
                'product_id' => $product->id,
                'category' => $product->category,
                'title' => $product->title,
                'event_type' => $eventType,
                'at' => now()->toIso8601String(),
            ])
            ->take(20)
            ->values()
            ->all();

        $request->session()->put('ad_marketplace_interests', $interests);
    }

    private function trackAdStat(AdCampaign $campaign, ?string $metric, int $costCents = 0): void
    {
        $stat = AdCampaignStat::query()->firstOrCreate([
            'ad_campaign_id' => $campaign->id,
            // Eloquent serializes date casts as midnight timestamps on
            // SQLite. Query with that exact representation as well, or a
            // second event can miss the row and violate the daily unique key.
            'date' => today()->startOfDay(),
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
        if ($this->isDuplicateAdEvent($request, $campaign, $eventType, $creative, $metadata)) {
            return null;
        }

        if ($this->isSuspiciousAdTraffic($request, $campaign, $eventType)) {
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

    private function rememberAdClick(Request $request, AdCampaign $campaign, ?AdCreative $creative = null): void
    {
        if (! $this->allowsAdMeasurement($request) && ! $this->allowsAdPersonalization($request)) {
            return;
        }

        $request->session()->put('last_ad_click', [
            'campaign_id' => $campaign->id,
            'creative_id' => $creative?->id,
            'placement' => $campaign->placement,
            'objective' => $campaign->objective,
            'target_url' => $creative?->target_url ?: $campaign->target_url,
            'clicked_at' => now()->toIso8601String(),
        ]);
    }

    private function isSuspiciousAdTraffic(Request $request, AdCampaign $campaign, string $eventType): bool
    {
        if (! in_array($eventType, ['impression', 'click'], true)) {
            return false;
        }

        $sessionHash = $this->adHash($request->session()->getId());
        $ipHash = $this->adHash($request->ip());
        $userAgentHash = $this->adHash((string) $request->userAgent());
        $limit = $eventType === 'click' ? 5 : 20;

        $recentEvents = AdEvent::query()
            ->where('ad_campaign_id', $campaign->id)
            ->where('event_type', $eventType)
            ->where('occurred_at', '>=', now()->subMinute())
            ->where(function ($query) use ($request, $sessionHash, $ipHash, $userAgentHash) {
                if ($request->user()) {
                    $query->where('user_id', $request->user()->id);

                    return;
                }

                $query->where('session_hash', $sessionHash)
                    ->orWhere(function ($fallback) use ($ipHash, $userAgentHash) {
                        $fallback->where('ip_hash', $ipHash)
                            ->where('user_agent_hash', $userAgentHash);
                    });
            })
            ->count();

        return $recentEvents >= $limit;
    }

    private function costForAdEvent(AdCampaign $campaign, string $eventType, int $valueCents = 0): int
    {
        if ($campaign->is_internal) {
            return 0;
        }

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
        if ($campaign->is_internal) {
            return true;
        }

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

    private function campaignPassesFrequencyCap(Request $request, AdCampaign $campaign): bool
    {
        $cap = $this->frequencyCapForPlacement((string) $campaign->placement);

        if ($cap <= 0) {
            return true;
        }

        if (array_key_exists('viewer_impressions_today', $campaign->getAttributes())) {
            return (int) $campaign->getAttribute('viewer_impressions_today') < $cap;
        }

        $sessionHash = $this->adHash($request->session()->getId());
        $ipHash = $this->adHash($request->ip());
        $userAgentHash = $this->adHash((string) $request->userAgent());

        $seenToday = AdEvent::query()
            ->where('ad_campaign_id', $campaign->id)
            ->where('event_type', 'impression')
            ->where('occurred_at', '>=', today())
            ->where(function ($query) use ($request, $sessionHash, $ipHash, $userAgentHash) {
                if ($request->user()) {
                    $query->where('user_id', $request->user()->id);

                    return;
                }

                $query->where('session_hash', $sessionHash)
                    ->orWhere(function ($fallback) use ($ipHash, $userAgentHash) {
                        $fallback->where('ip_hash', $ipHash)
                            ->where('user_agent_hash', $userAgentHash);
                    });
            })
            ->count();

        return $seenToday < $cap;
    }

    private function frequencyCapForPlacement(?string $placement): int
    {
        $fallback = (int) $this->adSetting('ads_frequency_cap_per_day', 3);

        return match ($placement) {
            'feed' => (int) $this->adSetting('ads_frequency_cap_feed', $fallback ?: 3),
            'sidebar' => (int) $this->adSetting('ads_frequency_cap_sidebar', $fallback ?: 6),
            'marketplace_card' => (int) $this->adSetting('ads_frequency_cap_marketplace_card', $fallback ?: 3),
            'sponsor_section' => (int) $this->adSetting('ads_frequency_cap_sponsor_section', $fallback ?: 4),
            default => $fallback,
        };
    }

    private function campaignPassesBudgetPacing(AdCampaign $campaign): bool
    {
        if ($campaign->is_internal || (int) $campaign->daily_budget_cents <= 0) {
            return true;
        }

        $todaySpent = (int) ($this->todayAdStat($campaign)?->spent_cents ?? 0);
        $minutesElapsed = max(1, now()->diffInMinutes(today()));
        $dayProgress = min(1, $minutesElapsed / 1440);
        $dailyBudget = (int) $campaign->daily_budget_cents;
        $buffer = max(100, (int) round($dailyBudget * 0.12));
        $allowedSpend = (int) round($dailyBudget * $dayProgress) + $buffer;

        return $todaySpent <= $allowedSpend;
    }

    private function campaignMatchesAudience(Request $request, AdCampaign $campaign): bool
    {
        $score = $this->campaignAudienceScore($request, $campaign);

        return $score > 0;
    }

    private function campaignAudienceScore(Request $request, AdCampaign $campaign): float
    {
        $audience = is_array($campaign->audience) ? $campaign->audience : [];

        if ($campaign->is_internal || $audience === []) {
            return 1.0;
        }

        $user = $request->user();
        $locations = collect($audience['locations'] ?? [])->map(fn ($value) => Str::lower(trim((string) $value)))->filter();
        $interests = collect($audience['interests'] ?? [])->map(fn ($value) => Str::lower(trim((string) $value)))->filter();
        $excludedLocations = collect($audience['excluded_locations'] ?? [])->map(fn ($value) => Str::lower(trim((string) $value)))->filter();
        $excludedInterests = collect($audience['excluded_interests'] ?? [])->map(fn ($value) => Str::lower(trim((string) $value)))->filter();
        $devices = collect($audience['devices'] ?? [])->map(fn ($value) => Str::lower(trim((string) $value)))->filter();
        $languages = collect($audience['languages'] ?? [])->map(fn ($value) => Str::lower(trim((string) $value)))->filter();
        $hours = collect($audience['hours'] ?? [])->map(fn ($value) => trim((string) $value))->filter();
        $ageMin = filled($audience['age_min'] ?? null) ? (int) $audience['age_min'] : null;
        $ageMax = filled($audience['age_max'] ?? null) ? (int) $audience['age_max'] : null;

        $hasTargeting = $locations->isNotEmpty()
            || $interests->isNotEmpty()
            || $excludedLocations->isNotEmpty()
            || $excludedInterests->isNotEmpty()
            || $devices->isNotEmpty()
            || $languages->isNotEmpty()
            || $hours->isNotEmpty()
            || $ageMin
            || $ageMax;

        if (! $hasTargeting) {
            return 1.0;
        }

        $requestDevice = $this->requestDevice($request);

        if ($devices->isNotEmpty() && ! $devices->contains($requestDevice)) {
            return 0.0;
        }

        if ($languages->isNotEmpty()) {
            $requestLanguages = $this->requestLanguages($request);
            $matchesLanguage = $languages->contains(function (string $language) use ($requestLanguages) {
                return $requestLanguages->contains(fn (string $requestLanguage) => $requestLanguage === $language || Str::startsWith($requestLanguage, $language.'-') || Str::startsWith($language, $requestLanguage.'-'));
            });

            if (! $matchesLanguage) {
                return 0.0;
            }
        }

        if ($hours->isNotEmpty() && ! $this->matchesAnyAdHourWindow($hours)) {
            return 0.0;
        }

        if (! $user) {
            return ($ageMin || $ageMax || $interests->isNotEmpty()) ? 0.35 : 0.7;
        }

        $score = 1.0;

        if ($devices->isNotEmpty()) {
            $score *= 1.1;
        }

        if ($languages->isNotEmpty()) {
            $score *= 1.08;
        }

        if ($ageMin || $ageMax) {
            $age = $user->birth_date?->age;

            if (! $age) {
                $score *= 0.7;
            } elseif (($ageMin && $age < $ageMin) || ($ageMax && $age > $ageMax)) {
                return 0.0;
            } else {
                $score *= 1.15;
            }
        }

        $userLocations = collect([$user->country, $user->city, $user->state])
            ->map(fn ($value) => Str::lower(trim((string) $value)))
            ->filter();

        if ($excludedLocations->isNotEmpty() && $excludedLocations->contains(function (string $location) use ($userLocations) {
            return $userLocations->contains(fn (string $userLocation) => $userLocation !== '' && (str_contains($userLocation, $location) || str_contains($location, $userLocation)));
        })) {
            return 0.0;
        }

        if ($locations->isNotEmpty()) {
            $matchesLocation = $locations->contains(function (string $location) use ($userLocations) {
                return $userLocations->contains(fn (string $userLocation) => $userLocation !== '' && (str_contains($userLocation, $location) || str_contains($location, $userLocation)));
            });

            if (! $matchesLocation) {
                return 0.0;
            }

            $score *= 1.25;
        }

        $userInterests = collect($user->event_default_sport_ids ?? [])
            ->map(fn ($value) => (string) $value)
            ->merge($user->sportProfiles()->with('sport:id,name,slug,category')->get()->flatMap(function ($profile) {
                return [
                    $profile->sport_id,
                    $profile->sport?->name,
                    $profile->sport?->slug,
                    $profile->sport?->category,
                ];
            }))
            ->map(fn ($value) => Str::lower(trim((string) $value)))
            ->filter()
            ->unique();

        if ($excludedInterests->isNotEmpty() && $excludedInterests->contains(function (string $interest) use ($userInterests) {
            return $userInterests->contains(fn (string $userInterest) => $userInterest !== '' && (str_contains($userInterest, $interest) || str_contains($interest, $userInterest)));
        })) {
            return 0.0;
        }

        if ($interests->isNotEmpty()) {
            $matchesInterest = $interests->contains(function (string $interest) use ($userInterests) {
                return $userInterests->contains(fn (string $userInterest) => $userInterest !== '' && (str_contains($userInterest, $interest) || str_contains($interest, $userInterest)));
            });

            if (! $matchesInterest) {
                $score *= 0.45;
            } else {
                $score *= 1.35;
            }
        }

        return max(0.0, min(2.5, $score));
    }

    private function requestDevice(Request $request): string
    {
        $agent = Str::lower((string) $request->userAgent());

        if (str_contains($agent, 'ipad') || str_contains($agent, 'tablet')) {
            return 'tablet';
        }

        if (str_contains($agent, 'mobile') || str_contains($agent, 'iphone') || str_contains($agent, 'android')) {
            return 'mobile';
        }

        return 'desktop';
    }

    private function requestLanguages(Request $request)
    {
        return collect(explode(',', (string) $request->header('Accept-Language')))
            ->map(fn (string $language) => Str::lower(trim(explode(';', $language)[0] ?? '')))
            ->filter()
            ->flatMap(fn (string $language) => [$language, Str::before($language, '-')])
            ->filter()
            ->unique()
            ->values();
    }

    private function matchesAnyAdHourWindow($windows): bool
    {
        $currentMinutes = ((int) now()->format('H')) * 60 + (int) now()->format('i');

        return collect($windows)->contains(function (string $window) use ($currentMinutes) {
            if (! preg_match('/^(\d{1,2})(?::(\d{2}))?\s*-\s*(\d{1,2})(?::(\d{2}))?$/', trim($window), $matches)) {
                return false;
            }

            $start = min(23, (int) $matches[1]) * 60 + min(59, (int) ($matches[2] ?? 0));
            $end = min(23, (int) $matches[3]) * 60 + min(59, (int) ($matches[4] ?? 59));

            if ($start <= $end) {
                return $currentMinutes >= $start && $currentMinutes <= $end;
            }

            return $currentMinutes >= $start || $currentMinutes <= $end;
        });
    }

    private function campaignPaymentCompleted(AdCampaign $campaign): bool
    {
        return CommerceOrder::query()
            ->where('type', 'ads_campaign')
            ->where('orderable_type', AdCampaign::class)
            ->where('orderable_id', $campaign->id)
            ->where('status', 'completed')
            ->exists();
    }

    private function authorizeOwnCampaign(Request $request, AdCampaign $campaign): void
    {
        abort_unless($campaign->user_id === $request->user()->id, 403);
    }

    private function todayAdStat(AdCampaign $campaign): ?AdCampaignStat
    {
        if ($campaign->relationLoaded('stats')) {
            return $campaign->stats->firstWhere('date', today()->toDateString()) ?: $campaign->stats->first();
        }

        return $campaign->stats()->where('date', today()->toDateString())->first();
    }

    private function chooseCampaignForDelivery($campaigns, Request $request): ?AdCampaign
    {
        $priorityCampaigns = $campaigns
            ->filter(fn (AdCampaign $campaign) => $campaign->is_internal && $campaign->force_priority)
            ->values();

        if ($priorityCampaigns->isNotEmpty()) {
            return $this->chooseWeightedCampaign($priorityCampaigns, $request);
        }

        $weighted = $campaigns
            ->map(fn (AdCampaign $campaign) => ['campaign' => $campaign, 'weight' => $this->campaignDeliveryWeight($campaign, $request)])
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

    private function chooseWeightedCampaign($campaigns, Request $request): ?AdCampaign
    {
        $weighted = $campaigns
            ->map(fn (AdCampaign $campaign) => ['campaign' => $campaign, 'weight' => $this->campaignDeliveryWeight($campaign, $request)])
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

    private function campaignDeliveryWeight(AdCampaign $campaign, Request $request): int
    {
        if ($campaign->is_internal) {
            return $campaign->force_priority ? 10000 : 500;
        }

        $impressions = max(1, (int) $campaign->impressions);
        $clicks = (int) $campaign->clicks;
        $ctr = $clicks / $impressions;
        $budgetRemainingRatio = max(0.1, ((int) $campaign->budget_cents - (int) $campaign->spent_cents) / max(1, (int) $campaign->budget_cents));
        $freshnessBoost = $impressions < 100 ? 1.5 : 1;
        $pacingBoost = $this->campaignPacingWeight($campaign);
        $fairnessBoost = $this->campaignFairnessWeight($campaign);
        $conversionBoost = $this->campaignConversionWeight($campaign);
        $retargetingBoost = $this->campaignRetargetingWeight($request, $campaign);

        $objectiveWeight = match ($campaign->objective) {
            'traffic' => 1 + min(3, $ctr * 100),
            'awareness' => 1 + min(2, 100 / $impressions),
            'leads', 'sales' => 1 + min(2, $ctr * 45),
            default => 1,
        };

        return max(1, (int) round(100 * $objectiveWeight * $budgetRemainingRatio * $freshnessBoost * $pacingBoost * $fairnessBoost * $conversionBoost * $retargetingBoost));
    }

    private function campaignPacingWeight(AdCampaign $campaign): float
    {
        if ((int) $campaign->daily_budget_cents <= 0) {
            return 1.0;
        }

        $todaySpent = (int) ($this->todayAdStat($campaign)?->spent_cents ?? 0);
        $minutesElapsed = max(1, now()->diffInMinutes(today()));
        $expectedSpend = max(1, (int) round((int) $campaign->daily_budget_cents * min(1, $minutesElapsed / 1440)));

        if ($todaySpent < $expectedSpend * 0.55) {
            return 1.45;
        }

        if ($todaySpent > $expectedSpend * 1.25) {
            return 0.45;
        }

        return 1.0;
    }

    private function campaignConversionWeight(AdCampaign $campaign): float
    {
        if ($campaign->is_internal || ! in_array($campaign->objective, ['leads', 'sales'], true)) {
            return 1.0;
        }

        $metrics = $this->campaignRecentEventMetrics($campaign);
        $clicks = max(1, (int) ($metrics['click'] ?? 0));
        $leads = (int) ($metrics['lead'] ?? 0);
        $sales = (int) ($metrics['sale'] ?? 0);
        $conversions = $leads + ($sales * 2);

        if ($clicks < 10 && $conversions === 0) {
            return 1.0;
        }

        if ($conversions === 0) {
            return 0.72;
        }

        return min(2.6, 1 + (($conversions / $clicks) * 7));
    }

    private function campaignRetargetingWeight(Request $request, AdCampaign $campaign): float
    {
        if (! $this->allowsAdPersonalization($request)) {
            return 1.0;
        }

        $boost = 1.0;
        $lastClick = $request->session()->get('last_ad_click');

        if (is_array($lastClick) && (int) ($lastClick['campaign_id'] ?? 0) === (int) $campaign->id && ! empty($lastClick['clicked_at'])) {
            $boost *= $this->adTimestampWithinDays($lastClick['clicked_at'], 7) ? 1.75 : 1.2;
        }

        $audience = is_array($campaign->audience) ? $campaign->audience : [];
        $campaignInterests = collect($audience['interests'] ?? [])
            ->map(fn ($value) => Str::lower(trim((string) $value)))
            ->filter();

        if ($campaignInterests->isNotEmpty()) {
            $marketplaceInterests = collect($request->session()->get('ad_marketplace_interests', []))
                ->filter(fn ($row) => is_array($row) && ! empty($row['at']) && $this->adTimestampWithinDays($row['at'], 14))
                ->flatMap(fn ($row) => [$row['category'] ?? null, $row['title'] ?? null])
                ->map(fn ($value) => Str::lower(trim((string) $value)))
                ->filter();

            $matches = $campaignInterests->contains(function (string $interest) use ($marketplaceInterests) {
                return $marketplaceInterests->contains(fn (string $value) => $value !== '' && (str_contains($value, $interest) || str_contains($interest, $value)));
            });

            if ($matches) {
                $boost *= 1.45;
            }
        }

        return min(2.4, $boost);
    }

    private function allowsAdPersonalization(Request $request): bool
    {
        return (bool) $request->user()?->ads_personalization_consent;
    }

    private function allowsAdMeasurement(Request $request): bool
    {
        return (bool) $request->user()?->ads_measurement_consent;
    }

    private function adTimestampWithinDays(?string $timestamp, int $days): bool
    {
        if (! $timestamp) {
            return false;
        }

        try {
            return now()->diffInDays(Carbon::parse($timestamp)) <= $days;
        } catch (\Throwable) {
            return false;
        }
    }

    private function campaignRecentEventMetrics(AdCampaign $campaign): array
    {
        if (array_key_exists('delivery_recent_metrics', $campaign->getAttributes())) {
            return (array) $campaign->getAttribute('delivery_recent_metrics');
        }

        static $cache = [];

        if (array_key_exists($campaign->id, $cache)) {
            return $cache[$campaign->id];
        }

        return $cache[$campaign->id] = AdEvent::query()
            ->select('event_type', DB::raw('COUNT(*) as total'))
            ->where('ad_campaign_id', $campaign->id)
            ->where('occurred_at', '>=', now()->subDays(14))
            ->whereIn('event_type', ['click', 'lead', 'sale'])
            ->groupBy('event_type')
            ->pluck('total', 'event_type')
            ->map(fn ($value) => (int) $value)
            ->all();
    }

    private function campaignFairnessWeight(AdCampaign $campaign): float
    {
        if ($campaign->is_internal) {
            return 1.0;
        }

        $impressions = (int) $campaign->impressions;
        $budgetCents = max(1, (int) $campaign->budget_cents);
        $smallBudget = $budgetCents <= (int) $this->adSetting('ads_small_campaign_budget_cents', 5000);

        if ($impressions < 50) {
            return $smallBudget ? 1.9 : 1.35;
        }

        if ($smallBudget && $impressions < 250) {
            return 1.35;
        }

        return 1.0;
    }

    private function creativeConversionWeight(AdCreative $creative): float
    {
        $metrics = $this->creativeRecentEventMetrics($creative);
        $clicks = max(1, (int) ($metrics['click'] ?? 0));
        $conversions = (int) ($metrics['lead'] ?? 0) + (((int) ($metrics['sale'] ?? 0)) * 2);

        if ($conversions === 0) {
            return $clicks >= 20 ? 0.82 : 1.0;
        }

        return min(2.2, 1 + (($conversions / $clicks) * 6));
    }

    private function creativeRecentEventMetrics(AdCreative $creative): array
    {
        if (array_key_exists('delivery_recent_metrics', $creative->getAttributes())) {
            return (array) $creative->getAttribute('delivery_recent_metrics');
        }

        static $cache = [];

        if (array_key_exists($creative->id, $cache)) {
            return $cache[$creative->id];
        }

        return $cache[$creative->id] = AdEvent::query()
            ->select('event_type', DB::raw('COUNT(*) as total'))
            ->where('ad_creative_id', $creative->id)
            ->where('occurred_at', '>=', now()->subDays(14))
            ->whereIn('event_type', ['click', 'lead', 'sale'])
            ->groupBy('event_type')
            ->pluck('total', 'event_type')
            ->map(fn ($value) => (int) $value)
            ->all();
    }

    private function loadCampaignDeliverySignals($campaigns, Request $request): void
    {
        $campaignIds = $campaigns->pluck('id')->map(fn ($id) => (int) $id)->filter()->values();

        if ($campaignIds->isEmpty()) {
            return;
        }

        $recentMetrics = AdEvent::query()
            ->select(['ad_campaign_id', 'event_type', DB::raw('COUNT(*) as total')])
            ->whereIn('ad_campaign_id', $campaignIds)
            ->where('occurred_at', '>=', now()->subDays(14))
            ->whereIn('event_type', ['click', 'lead', 'sale'])
            ->groupBy('ad_campaign_id', 'event_type')
            ->get()
            ->groupBy('ad_campaign_id')
            ->map(fn ($rows) => $rows->pluck('total', 'event_type')->map(fn ($value) => (int) $value)->all());

        $sessionHash = $this->adHash($request->session()->getId());
        $ipHash = $this->adHash($request->ip());
        $userAgentHash = $this->adHash((string) $request->userAgent());
        $viewerImpressions = AdEvent::query()
            ->select(['ad_campaign_id', DB::raw('COUNT(*) as total')])
            ->whereIn('ad_campaign_id', $campaignIds)
            ->where('event_type', 'impression')
            ->where('occurred_at', '>=', today())
            ->where(function ($query) use ($request, $sessionHash, $ipHash, $userAgentHash): void {
                if ($request->user()) {
                    $query->where('user_id', $request->user()->id);

                    return;
                }

                $query->where('session_hash', $sessionHash)
                    ->orWhere(function ($fallback) use ($ipHash, $userAgentHash): void {
                        $fallback->where('ip_hash', $ipHash)
                            ->where('user_agent_hash', $userAgentHash);
                    });
            })
            ->groupBy('ad_campaign_id')
            ->pluck('total', 'ad_campaign_id');

        $campaigns->each(function (AdCampaign $campaign) use ($recentMetrics, $viewerImpressions): void {
            $campaign->setAttribute('delivery_recent_metrics', $recentMetrics->get($campaign->id, []));
            $campaign->setAttribute('viewer_impressions_today', (int) $viewerImpressions->get($campaign->id, 0));
        });
    }

    private function loadCreativeDeliverySignals(AdCampaign $campaign): void
    {
        $creatives = $campaign->relationLoaded('creatives')
            ? $campaign->creatives
            : $campaign->creatives()->get();
        $creativeIds = $creatives->pluck('id')->map(fn ($id) => (int) $id)->filter()->values();

        if ($creativeIds->isEmpty()) {
            return;
        }

        $metrics = AdEvent::query()
            ->select(['ad_creative_id', 'event_type', DB::raw('COUNT(*) as total')])
            ->whereIn('ad_creative_id', $creativeIds)
            ->where('occurred_at', '>=', now()->subDays(14))
            ->whereIn('event_type', ['click', 'lead', 'sale'])
            ->groupBy('ad_creative_id', 'event_type')
            ->get()
            ->groupBy('ad_creative_id')
            ->map(fn ($rows) => $rows->pluck('total', 'event_type')->map(fn ($value) => (int) $value)->all());

        $creatives->each(fn (AdCreative $creative) => $creative->setAttribute(
            'delivery_recent_metrics',
            $metrics->get($creative->id, []),
        ));
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
                $ctr = $clicks / $impressions;
                $ctrBoost = $impressions >= 30 ? 1 + min(3, $ctr * 100) : 1.1;
                $freshnessBoost = $impressions < 50 ? 1.4 : 1;
                $learningPenalty = ($impressions >= 100 && $ctr < 0.002) ? 0.45 : 1;
                $conversionBoost = $this->creativeConversionWeight($creative);

                return [
                    'creative' => $creative,
                    'weight' => max(1, (int) round((int) $creative->weight * $ctrBoost * $freshnessBoost * $learningPenalty * $conversionBoost)),
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

    private function syncAdCreatives(AdCampaign $campaign, array $creativeRows, ?AdGroup $group = null, ?string $adName = null, bool $createFallback = true): int
    {
        $rows = collect($creativeRows)
            ->map(fn (array $row, int $index) => [
                'ad_group_id' => $group?->id,
                'ad_name' => $adName,
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

        if ($rows->isEmpty() && $createFallback) {
            $rows = collect([[
                'ad_group_id' => $group?->id,
                'ad_name' => $adName,
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

        return $rows->count();
    }

    private function isDuplicateAdEvent(Request $request, AdCampaign $campaign, string $eventType, ?AdCreative $creative = null, array $metadata = []): bool
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
            ->when($metadata['conversion_type'] ?? null, fn ($query, $conversionType) => $query->where('metadata->conversion_type', $conversionType))
            ->when($metadata['order_id'] ?? null, fn ($query, $orderId) => $query->where('metadata->order_id', $orderId))
            ->where('session_hash', $this->adHash($request->session()->getId()))
            ->where('occurred_at', '>=', now()->subMinutes($windowMinutes))
            ->exists();
    }

    private function adPrice(string $key, int $default): int
    {
        return max(0, (int) $this->adSetting($key, $default));
    }

    private function adSetting(string $key, mixed $default = null): mixed
    {
        static $cache = [];

        return $cache[$key] ??= Setting::valueFor($key, $default);
    }

    private function safeAdTargetUrl(?string $targetUrl): string
    {
        if (! is_string($targetUrl) || filter_var($targetUrl, FILTER_VALIDATE_URL) === false) {
            return route('guest.pricing');
        }

        $scheme = strtolower((string) parse_url($targetUrl, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true)
            ? $targetUrl
            : route('guest.pricing');
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

        if (! $this->payments->isValidStripeSignature($payload, $signature)) {
            return response('Invalid signature', 400);
        }

        $event = json_decode($payload, true);
        $object = $event['data']['object'] ?? [];

        app(ProviderWebhookEventService::class)->handle('stripe', 'commerce', $event['id'] ?? null, $event['type'] ?? null, function () use ($event, $object) {
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

            return null;
        });

        return response('ok');
    }

    public function paypalWebhook(Request $request)
    {
        if (! $this->payments->isValidPayPalWebhook($request)) {
            return response('Invalid signature', 400);
        }

        $event = $request->all();
        $resource = $event['resource'] ?? [];
        $orderId = $resource['supplementary_data']['related_ids']['order_id'] ?? $resource['id'] ?? null;

        app(ProviderWebhookEventService::class)->handle('paypal', 'commerce', $event['id'] ?? null, $event['event_type'] ?? null, function () use ($event, $orderId) {
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

            return null;
        });

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

                    abort_unless($this->cartService->hasSellableStock($product), 422, $product->title.' ist nicht mehr verfügbar.');

                    if (! (bool) $product->manages_stock) {
                        continue;
                    }

                    $stockAfter = null;
                    if ($item->commerce_warehouse_id) {
                        $inventory = MarketplaceProductInventory::query()
                            ->where('marketplace_product_id', $product->id)
                            ->where('commerce_warehouse_id', $item->commerce_warehouse_id)
                            ->lockForUpdate()
                            ->first();

                        abort_unless($inventory && $inventory->availableQuantity() >= (int) $item->quantity, 422, $product->title.' ist nicht ausreichend im Zielland auf Lager.');
                        $inventory->decrement('stock_quantity', (int) $item->quantity);
                        $inventory->refresh();
                        $stockAfter = $inventory->availableQuantity();
                    } else {
                        abort_if((int) $product->stock_quantity < (int) $item->quantity, 422, $product->title.' ist nicht ausreichend auf Lager.');
                        $product->decrement('stock_quantity', (int) $item->quantity);
                        $product->refresh();
                        $stockAfter = $product->stock_quantity;
                    }

                    CommerceStockMovement::create([
                        'marketplace_product_id' => $product->id,
                        'commerce_warehouse_id' => $item->commerce_warehouse_id,
                        'commerce_order_id' => $order->id,
                        'type' => 'sale',
                        'quantity_delta' => -1 * (int) $item->quantity,
                        'stock_after' => $stockAfter,
                        'note' => 'Bestellung #'.$order->id.' bezahlt',
                    ]);
                }
            });

            $this->learningOrders->grantAccessForOrder($order);
        }

        if ($order->type === 'ads_campaign' && $order->orderable instanceof AdCampaign) {
            $order->orderable->forceFill([
                'status' => 'pending_review',
                'review_note' => null,
            ])->save();
        }

        $invoiceNumber = $order->invoice_number ?: app(ClubShopOrderNumberService::class)->assign(
            $order,
            'shop_invoice',
            fn () => $this->nextDocumentNumber('commerce_invoice_number_next', 'AIR-RE'),
        );
        $order->update([
            'status' => 'completed',
            'completed_at' => now(),
            'invoice_number' => $invoiceNumber,
            'payout_status' => in_array($order->type, ['marketplace_product', 'marketplace_cart'], true) ? 'pending' : 'not_applicable',
        ]);

        if (in_array($order->type, ['marketplace_product', 'marketplace_cart'], true)) {
            $this->trackAttributedOrderSale($order->refresh());
        }

        $this->notifyOrderCompleted($order->refresh());
        $this->sendConfirmationEmail($order);
    }

    private function trackAttributedOrderSale(CommerceOrder $order): void
    {
        $lastAttributedLead = AdEvent::query()
            ->when($order->user_id, fn ($query) => $query->where('user_id', $order->user_id))
            ->where('event_type', 'lead')
            ->where('occurred_at', '>=', now()->subDays(14))
            ->where('metadata->conversion_type', 'checkout_started')
            ->where('metadata->order_id', $order->id)
            ->latest('occurred_at')
            ->first();

        if (! $lastAttributedLead) {
            return;
        }

        $campaign = AdCampaign::query()->find($lastAttributedLead->ad_campaign_id);

        if (! $campaign || $campaign->status !== 'active') {
            return;
        }

        $creative = $lastAttributedLead->ad_creative_id
            ? AdCreative::query()->where('ad_campaign_id', $campaign->id)->find($lastAttributedLead->ad_creative_id)
            : null;
        $costCents = $this->costForAdEvent($campaign, 'sale', (int) $order->amount_cents);

        if ($costCents > 0 && ! $this->campaignHasBudgetFor($campaign, 'sale', $costCents)) {
            return;
        }

        if (AdEvent::query()
            ->where('ad_campaign_id', $campaign->id)
            ->where('event_type', 'sale')
            ->where('metadata->order_id', $order->id)
            ->exists()) {
            return;
        }

        AdEvent::create([
            'ad_campaign_id' => $campaign->id,
            'ad_creative_id' => $creative?->id,
            'user_id' => $order->user_id,
            'event_type' => 'sale',
            'objective' => $campaign->objective,
            'placement' => $campaign->placement,
            'cost_cents' => $costCents,
            'value_cents' => (int) $order->amount_cents,
            'currency' => $order->currency ?: 'EUR',
            'session_hash' => $lastAttributedLead->session_hash,
            'ip_hash' => $lastAttributedLead->ip_hash,
            'user_agent_hash' => $lastAttributedLead->user_agent_hash,
            'metadata' => [
                'conversion_type' => 'sale',
                'attribution' => 'last_click',
                'order_id' => $order->id,
                'lead_event_id' => $lastAttributedLead->id,
            ],
            'occurred_at' => now(),
        ]);

        if ($costCents > 0) {
            $campaign->increment('spent_cents', $costCents);
            $creative?->increment('spent_cents', $costCents);
            $this->trackAdStat($campaign, null, $costCents);
        }
    }

    private function sellerOrderResource(CommerceOrder $order, User $seller): array
    {
        $ownsDirectProduct = $order->orderable instanceof MarketplaceProduct
            && (int) $order->orderable->user_id === (int) $seller->id;
        $items = $order->items
            ->filter(fn (CommerceOrderItem $item) => $item->orderable instanceof MarketplaceProduct
                && (int) $item->orderable->user_id === (int) $seller->id)
            ->values();
        $itemIds = $items->pluck('id');
        $shippingAddress = collect((array) data_get($order->payload, 'shipping_address'))
            ->only(['name', 'company', 'street', 'house_number', 'postal_code', 'city', 'state', 'country'])
            ->all();
        $hasShippableItem = $ownsDirectProduct
            ? (bool) $order->orderable->is_shippable
            : $items->contains(fn (CommerceOrderItem $item) => (bool) $item->is_shippable);

        return [
            'id' => $order->id,
            'reference' => $order->invoice_number ?: 'AIR-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT),
            'type' => $order->type,
            'status' => $order->status,
            'shipping_status' => $order->shipping_status,
            'shipping_carrier' => $order->shipping_carrier,
            'tracking_number' => $order->tracking_number,
            'tracking_url' => $order->tracking_url,
            'currency' => $order->currency ?: 'EUR',
            'seller_gross_cents' => $ownsDirectProduct
                ? (int) $order->amount_cents
                : (int) $items->sum('total_cents'),
            'seller_commission_cents' => $ownsDirectProduct
                ? (int) $order->commission_cents
                : (int) round($items->sum(function (CommerceOrderItem $item) {
                    $product = $item->orderable;

                    return (int) $item->total_cents * ((int) ($product?->commission_percent ?? 0) / 100);
                })),
            'payout_status' => $order->payout_status,
            'buyer' => [
                'name' => $order->user?->name ?: $order->guest_name,
                'email' => $order->user?->email ?: $order->guest_email,
            ],
            'shipping_address' => $hasShippableItem ? $shippingAddress : null,
            'items' => $items->map(fn (CommerceOrderItem $item) => [
                'id' => $item->id,
                'product_id' => $item->orderable_id,
                'title' => $item->title,
                'sku' => $item->sku,
                'quantity' => $item->quantity,
                'total_cents' => $item->total_cents,
                'currency' => $item->currency,
                'is_shippable' => (bool) $item->is_shippable,
            ])->all(),
            'product' => $ownsDirectProduct ? [
                'id' => $order->orderable->id,
                'title' => $order->orderable->title,
                'sku' => $order->orderable->sku,
                'is_shippable' => (bool) $order->orderable->is_shippable,
            ] : null,
            'issue' => [
                'status' => $order->issue_status ?: 'none',
                'note' => $order->issue_note,
                'response' => $order->issue_response,
            ],
            'return_requests' => $order->returnRequests
                ->filter(fn (CommerceReturnRequest $returnRequest) => $ownsDirectProduct
                    || $returnRequest->commerce_order_item_id === null
                    || $itemIds->contains($returnRequest->commerce_order_item_id))
                ->map(fn (CommerceReturnRequest $returnRequest) => [
                    'id' => $returnRequest->id,
                    'commerce_order_item_id' => $returnRequest->commerce_order_item_id,
                    'reason' => $returnRequest->reason,
                    'quantity' => $returnRequest->quantity,
                    'status' => $returnRequest->status,
                    'created_at' => $returnRequest->created_at?->toJSON(),
                ])
                ->values()
                ->all(),
            'created_at' => $order->created_at?->toJSON(),
            'completed_at' => $order->completed_at?->toJSON(),
        ];
    }

    private function purchaseHistoryFor(User $user): array
    {
        $commerceOrders = CommerceOrder::query()
            ->with(['orderable', 'items.orderable'])
            ->where('user_id', $user->id)
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn (CommerceOrder $order) => $this->commerceOrderPurchaseItem($order));

        $subscriptionInvoices = SubscriptionInvoice::query()
            ->with('plan:id,name')
            ->where('user_id', $user->id)
            ->latest('issued_at')
            ->latest('id')
            ->limit(30)
            ->get()
            ->map(fn (SubscriptionInvoice $invoice) => [
                'key' => 'subscription-invoice-'.$invoice->id,
                'category' => 'Konto-Abo',
                'title' => $invoice->title ?: ($invoice->plan?->name ?: 'Konto-Abo Rechnung'),
                'description' => $invoice->number ? 'Rechnung '.$invoice->number : 'Abo-Rechnung',
                'status' => $invoice->status,
                'status_label' => $this->purchaseStatusLabel($invoice->status),
                'amount_cents' => $invoice->amount_cents,
                'currency' => $invoice->currency ?: 'EUR',
                'ordered_at' => optional($invoice->issued_at ?: $invoice->created_at)->toIso8601String(),
                'invoice_url' => route('auth.subscription-invoices.download', $invoice),
                'detail_url' => route('auth.settings'),
            ]);

        $openCheckouts = PaymentCheckout::query()
            ->with(['plan:id,name', 'invoice'])
            ->where('user_id', $user->id)
            ->whereDoesntHave('invoice')
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(fn (PaymentCheckout $checkout) => [
                'key' => 'payment-checkout-'.$checkout->id,
                'category' => 'Konto-Abo',
                'title' => $checkout->plan?->name ?: 'Konto-Abo Zahlung',
                'description' => $checkout->payment_reference ? 'Referenz '.$checkout->payment_reference : 'Offene Zahlung',
                'status' => $checkout->status,
                'status_label' => $this->purchaseStatusLabel($checkout->status),
                'amount_cents' => $checkout->amount_cents,
                'currency' => $checkout->currency ?: 'EUR',
                'ordered_at' => optional($checkout->created_at)->toIso8601String(),
                'invoice_url' => null,
                'detail_url' => route('guest.pricing'),
            ]);

        $outfitSubscriptions = OutfitSubscription::query()
            ->with('plan:id,name')
            ->where('user_id', $user->id)
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(fn (OutfitSubscription $subscription) => [
                'key' => 'outfit-subscription-'.$subscription->id,
                'category' => 'Outfit-Abo',
                'title' => $subscription->plan?->name ?: 'Outfit-Abo',
                'description' => $subscription->payment_reference ? 'Referenz '.$subscription->payment_reference : 'Monatliches Outfit-Abo',
                'status' => $subscription->payment_status ?: $subscription->status,
                'status_label' => $this->purchaseStatusLabel($subscription->payment_status ?: $subscription->status),
                'amount_cents' => $subscription->monthly_price_cents,
                'currency' => $subscription->currency ?: 'EUR',
                'ordered_at' => optional($subscription->created_at)->toIso8601String(),
                'invoice_url' => null,
                'detail_url' => route('auth.outfit-subscriptions.index'),
            ]);

        return $commerceOrders
            ->concat($subscriptionInvoices)
            ->concat($openCheckouts)
            ->concat($outfitSubscriptions)
            ->sortByDesc(fn (array $item) => $item['ordered_at'] ?? '')
            ->values()
            ->take(100)
            ->all();
    }

    private function commerceOrderPurchaseItem(CommerceOrder $order): array
    {
        $category = match ($order->type) {
            'ads_campaign' => 'Ads',
            'addon' => 'Konto-Abo',
            'marketplace_cart', 'marketplace_product' => $this->marketplaceOrderCategory($order),
            default => 'Bestellung',
        };

        return [
            'key' => 'commerce-order-'.$order->id,
            'category' => $category,
            'title' => $order->orderable?->name
                ?: $order->orderable?->title
                ?: $order->items->pluck('title')->filter()->take(2)->join(', ')
                ?: $order->type,
            'description' => $order->invoice_number ? 'Rechnung '.$order->invoice_number : 'Bestellung #'.$order->id,
            'status' => $order->status,
            'status_label' => $this->purchaseStatusLabel($order->status),
            'amount_cents' => $order->amount_cents,
            'currency' => $order->currency ?: 'EUR',
            'ordered_at' => optional($order->created_at)->toIso8601String(),
            'invoice_url' => $order->invoice_number ? route('auth.commerce.orders.invoice', $order) : null,
            'detail_url' => route('auth.commerce.index', ['tab' => 'invoices', 'order' => $order->id]),
            'order_id' => $order->id,
            'learning_course' => $this->learningCourseLinkForOrder($order),
        ];
    }

    private function marketplaceOrderCategory(CommerceOrder $order): string
    {
        $products = collect([$order->orderable])
            ->concat($order->items->pluck('orderable'))
            ->filter(fn ($item) => $item instanceof MarketplaceProduct);

        if ($products->contains(fn (MarketplaceProduct $product) => $this->isLearningProduct($product))) {
            return 'Kurse / E-Learning';
        }

        return 'Marketplace';
    }

    private function isLearningProduct(MarketplaceProduct $product): bool
    {
        return $product->category === 'course'
            || in_array($product->offer_type, ['online_course', 'training_plan'], true);
    }

    private function authorizeOwnProduct(Request $request, MarketplaceProduct $product): void
    {
        abort_unless((int) $product->user_id === (int) $request->user()->id, 403);
    }

    private function purchaseStatusLabel(?string $status): string
    {
        return [
            'pending' => 'Offen',
            'awaiting_transfer' => 'Wartet auf Überweisung',
            'pending_payment' => 'Zahlung offen',
            'paid' => 'Bezahlt',
            'completed' => 'Bezahlt',
            'active' => 'Aktiv',
            'paused' => 'Pausiert',
            'cancelled' => 'Storniert',
            'canceled' => 'Storniert',
            'refunded' => 'Erstattet',
            'failed' => 'Fehlgeschlagen',
            'open' => 'Offen',
            'draft' => 'Entwurf',
        ][$status ?: ''] ?? ($status ?: 'Unbekannt');
    }

    public function startPublicCheckout(CommerceOrder $order)
    {
        return $this->startCheckout($order);
    }

    private function startCheckout(CommerceOrder $order)
    {
        abort_if($order->amount_cents <= 0, 422, __('commerce.validation.free_checkout'));

        if ($order->provider === 'bank_transfer') {
            $this->payments->prepareBankTransfer($order);
            $redirectUrl = $this->payments->orderRoute($order, 'bank-transfer');
            $this->notifyBankTransferBuyer($order->fresh(['items.orderable', 'orderable', 'user']), $redirectUrl);

            if (request()->expectsJson()) {
                return $this->orderJsonResponse(
                    $order->fresh(['club', 'items.orderable', 'returnRequests']),
                    201,
                    $redirectUrl,
                );
            }

            return redirect()->to($redirectUrl);
        }

        $url = $this->payments->createProviderCheckout($order);

        if (request()->expectsJson()) {
            return $this->orderJsonResponse(
                $order->fresh(['club', 'items.orderable', 'returnRequests']),
                201,
                $url,
            );
        }

        if (request()->header('X-Inertia')) {
            return Inertia::location($url);
        }

        return redirect()->away($url);
    }

    private function orderJsonResponse(CommerceOrder $order, int $status = 200, ?string $redirectUrl = null)
    {
        $resource = new CommerceOrderResource($order);

        if ($redirectUrl) {
            $resource->additional(['redirect_url' => $redirectUrl]);
        }

        return $resource->response()->setStatusCode($status);
    }

    private function sendConfirmationEmail(CommerceOrder $order): void
    {
        if ($order->confirmation_email_sent_at || ! $this->payments->buyerEmail($order)) {
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

    private function notifyBankTransferBuyer(CommerceOrder $order, string $actionUrl): void
    {
        if (data_get($order->payload, 'bank_transfer_buyer_notified_at')) {
            return;
        }

        $amount = ($order->currency ?: 'EUR').' '.number_format($order->amount_cents / 100, 2, '.', ',');
        $dueDate = $order->due_at?->format('d.m.Y') ?: '-';

        if ($order->user_id) {
            AppNotification::sendLocalized(
                $order->user ?? $order->user_id,
                'commerce.order.awaiting_transfer',
                'commerce.notifications.awaiting_transfer_title',
                'commerce.notifications.awaiting_transfer_body',
                [
                    'id' => $order->id,
                    'amount' => $amount,
                    'reference' => $order->payment_reference ?: '-',
                    'due_date' => $dueDate,
                ],
                [
                    'url' => route('auth.commerce.index', ['tab' => 'invoices', 'order' => $order->id]),
                    'mobile_url' => 'airmius://marketplace/orders/'.$order->id,
                    'deep_link' => 'airmius://marketplace/orders/'.$order->id,
                    'order_id' => $order->id,
                    'status' => 'awaiting_transfer',
                    'payment_reference' => $order->payment_reference,
                    'due_at' => $order->due_at?->toIso8601String(),
                ],
                [
                    'bypass_preferences' => true,
                    'priority' => 'high',
                    'dedupe_key' => 'commerce-order-awaiting-transfer:'.$order->id,
                ],
            );
        }

        if ($this->payments->buyerEmail($order)) {
            try {
                $notification = new CommerceOrderAwaitingTransfer($order, $actionUrl);

                if ($order->user?->email) {
                    $order->user->notify($notification);
                } else {
                    Notification::route('mail', $order->guest_email)->notify($notification);
                }
            } catch (\Throwable $exception) {
                Log::warning('Bank transfer order email could not be sent.', [
                    'order_id' => $order->id,
                    'message' => $exception->getMessage(),
                ]);

                return;
            }
        }

        $order->forceFill([
            'payload' => [
                ...($order->payload ?: []),
                'bank_transfer_buyer_notified_at' => now()->toIso8601String(),
            ],
        ])->save();
    }

    private function notifyOrderCompleted(CommerceOrder $order): void
    {
        if (! $order->user_id) {
            return;
        }

        $order->loadMissing(['orderable', 'user:id,language']);
        $title = $order->orderable?->name ?? $order->orderable?->title;

        AppNotification::sendLocalized(
            $order->user ?? $order->user_id,
            'commerce.order.paid',
            'commerce.notifications.paid_title',
            'commerce.notifications.paid_body',
            [
                'order' => $title ?: AppNotification::translatedReplacement(
                    'commerce.notifications.fallback_order',
                    'deine Bestellung',
                ),
            ],
            [
                'url' => route('auth.commerce.index'),
                'order_id' => $order->id,
            ],
        );
    }

    private function authorizeGuestOrder(CommerceOrder $order, string $token): void
    {
        abort_unless($order->access_token && hash_equals($order->access_token, $token), 403);
    }

    private function cancelPendingProviderOrder(CommerceOrder $order, ?int $userId = null): void
    {
        DB::transaction(function () use ($order, $userId) {
            $lockedOrder = CommerceOrder::query()
                ->with('orderable')
                ->lockForUpdate()
                ->findOrFail($order->id);

            if ($lockedOrder->status === 'cancelled') {
                return;
            }

            abort_unless($lockedOrder->status === 'pending', 422, __('commerce.validation.provider_cancel_invalid_status'));

            $lockedOrder->update([
                'status' => 'cancelled',
                'checkout_url' => null,
                'payload' => [
                    ...($lockedOrder->payload ?: []),
                    'checkout_cancelled_at' => now()->toISOString(),
                    'checkout_cancelled_by' => $userId,
                ],
            ]);

            if ($lockedOrder->type === 'ads_campaign' && $lockedOrder->orderable instanceof AdCampaign) {
                $lockedOrder->orderable->forceFill([
                    'status' => 'draft',
                    'review_note' => __('commerce.validation.payment_checkout_cancelled_note'),
                ])->save();
            }
        });

        $order->refresh();
    }

    private function orderResource(CommerceOrder $order): array
    {
        $pricing = $order->payload['pricing'] ?? null;
        $support = $this->orderSupport->summary($order);

        return [
            'id' => $order->id,
            'type' => $order->type,
            'status' => $order->status,
            'title' => $order->orderable?->name ?? $order->orderable?->title ?? 'Airmius Bestellung',
            'amount_cents' => $order->amount_cents,
            'currency' => $order->currency,
            'amount' => number_format($order->amount_cents / 100, 2, ',', '.').' '.$order->currency,
            'pricing' => $pricing,
            'items' => $order->items->map(fn (CommerceOrderItem $item) => [
                'id' => $item->id,
                'title' => $item->title,
                'sku' => $item->sku,
                'quantity' => $item->quantity,
                'is_shippable' => $item->is_shippable,
                'total_cents' => $item->total_cents,
                'returnable' => $item->is_shippable && $this->orderSupport->itemStillReturnable($item),
                'return_deadline' => $this->orderSupport->itemReturnDeadline($item)?->toDateString(),
            ])->values(),
            'return_requests' => $order->returnRequests->map(fn (CommerceReturnRequest $return) => [
                'id' => $return->id,
                'status' => $return->status,
                'reason' => $return->reason,
            ])->values(),
            'return_url' => $support['can_request_return'] && $order->access_token
                ? route('commerce-checkout.guest.returns.store', [$order, $order->access_token])
                : null,
            'support_summary' => $support,
            'payment_reference' => $order->payment_reference,
            'due_at' => $order->due_at?->toDateString(),
            'invoice_number' => $order->invoice_number,
            'credit_note_number' => $order->credit_note_number,
            'issue_status' => $order->issue_status,
            'issue_note' => $order->issue_note,
            'issue_response' => $order->issue_response,
            'issue_responded_at' => $order->issue_responded_at,
            'shipping_status' => $order->shipping_status,
            'shipping_carrier' => $order->shipping_carrier,
            'tracking_number' => $order->tracking_number,
            'tracking_url' => $order->tracking_url,
            'learning_course' => $this->learningCourseLinkForOrder($order),
        ];
    }

    private function learningCourseLinkForOrder(CommerceOrder $order): ?array
    {
        if ($order->status !== 'completed') {
            return null;
        }

        $products = collect([$order->orderable])
            ->concat($order->items->pluck('orderable'))
            ->filter(fn ($item) => $item instanceof MarketplaceProduct && $item->learning_course_id);
        $courseId = $products->pluck('learning_course_id')->filter()->first();

        if (! $courseId) {
            return null;
        }

        $course = LearningCourse::query()
            ->whereKey($courseId)
            ->where('status', 'published')
            ->where('is_public', true)
            ->first(['id', 'title', 'slug']);

        if (! $course) {
            return null;
        }

        return [
            'id' => $course->id,
            'title' => $course->title,
            'url' => route('guest.learning.courses.show', $course),
        ];
    }

    private function restoreMarketplaceStock(CommerceOrder $order, string $note): void
    {
        $order->loadMissing('items');

        foreach ($order->items as $item) {
            if ($item->orderable_type !== MarketplaceProduct::class || ! $item->orderable_id) {
                continue;
            }

            $product = MarketplaceProduct::query()->lockForUpdate()->find($item->orderable_id);

            if (! $product || ! (bool) $product->manages_stock) {
                continue;
            }

            $stockAfter = null;

            if ($item->commerce_warehouse_id) {
                $inventory = MarketplaceProductInventory::query()
                    ->where('marketplace_product_id', $product->id)
                    ->where('commerce_warehouse_id', $item->commerce_warehouse_id)
                    ->lockForUpdate()
                    ->first();

                if (! $inventory) {
                    continue;
                }

                $inventory->increment('stock_quantity', (int) $item->quantity);
                $inventory->refresh();
                $stockAfter = $inventory->availableQuantity();
            } else {
                $product->increment('stock_quantity', (int) $item->quantity);
                $product->refresh();
                $stockAfter = $product->stock_quantity;
            }

            CommerceStockMovement::create([
                'marketplace_product_id' => $product->id,
                'commerce_warehouse_id' => $item->commerce_warehouse_id,
                'commerce_order_id' => $order->id,
                'type' => 'cancellation',
                'quantity_delta' => (int) $item->quantity,
                'stock_after' => $stockAfter,
                'note' => $note,
            ]);
        }
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

    private function providerProfileForUser(User $user): MarketplaceProviderProfile
    {
        return MarketplaceProviderProfile::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'display_name' => $user->name,
                'support_email' => $user->email,
                'legal_country' => strtoupper((string) ($user->country ?: 'DE')),
                'legal_state' => $user->state,
                'legal_postal_code' => $user->postal_code,
                'legal_city' => $user->city,
                'legal_street' => $user->street,
                'legal_house_number' => $user->house_number,
                'provider_type' => 'private',
                'status' => 'draft',
            ],
        );
    }

    private function marketplaceProviderProfileResource(?MarketplaceProviderProfile $profile, ?User $user = null): array
    {
        return [
            'id' => $profile?->id,
            'display_name' => $profile?->display_name ?: $user?->name,
            'legal_name' => $profile?->legal_name,
            'provider_type' => $profile?->provider_type ?: 'private',
            'support_email' => $profile?->support_email ?: $user?->email,
            'phone' => $profile?->phone,
            'website' => $profile?->website,
            'logo_url' => $profile?->logo_url,
            'public_description' => $profile?->public_description,
            'legal_country' => $profile?->legal_country ?: ($user?->country ?: 'DE'),
            'legal_state' => $profile?->legal_state ?: $user?->state,
            'legal_postal_code' => $profile?->legal_postal_code ?: $user?->postal_code,
            'legal_city' => $profile?->legal_city ?: $user?->city,
            'legal_street' => $profile?->legal_street ?: $user?->street,
            'legal_house_number' => $profile?->legal_house_number ?: $user?->house_number,
            'show_public_address' => (bool) $profile?->show_public_address,
            'show_support_email' => $profile ? (bool) $profile->show_support_email : true,
            'show_phone' => (bool) $profile?->show_phone,
            'status' => $profile?->status ?: 'draft',
        ];
    }

    private function providerLocationsForUser(User $user): array
    {
        $profile = MarketplaceProviderProfile::query()
            ->where('user_id', $user->id)
            ->first();

        if (! $profile) {
            return [];
        }

        return $profile->locations()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (MarketplaceProviderLocation $location) => $this->marketplaceProviderLocationResource($location))
            ->all();
    }

    private function marketplaceProviderLocationResource(MarketplaceProviderLocation $location): array
    {
        return [
            'id' => $location->id,
            'name' => $location->name,
            'type' => $location->type,
            'country' => $location->country ?: 'DE',
            'state' => $location->state,
            'postal_code' => $location->postal_code,
            'city' => $location->city,
            'street' => $location->street,
            'house_number' => $location->house_number,
            'opening_hours' => $location->opening_hours,
            'note' => $location->note,
            'phone' => $location->phone,
            'email' => $location->email,
            'image_url' => $location->image_url,
            'latitude' => $location->latitude,
            'longitude' => $location->longitude,
            'pickup_enabled' => (bool) $location->pickup_enabled,
            'returns_enabled' => (bool) $location->returns_enabled,
            'is_public' => (bool) $location->is_public,
            'address' => $location->addressSummary(),
        ];
    }

    private function providerProfileRules(): array
    {
        return [
            'display_name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'provider_type' => ['required', Rule::in(['private', 'business', 'club'])],
            'support_email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:80'],
            'website' => ['nullable', 'url', 'max:500'],
            'logo_url' => ['nullable', 'url', 'max:1000'],
            'public_description' => ['nullable', 'string', 'max:2000'],
            'legal_country' => ['required', 'string', 'size:2'],
            'legal_state' => ['nullable', 'string', 'max:120'],
            'legal_postal_code' => ['nullable', 'string', 'max:30'],
            'legal_city' => ['nullable', 'string', 'max:120'],
            'legal_street' => ['nullable', 'string', 'max:180'],
            'legal_house_number' => ['nullable', 'string', 'max:40'],
            'show_public_address' => ['boolean'],
            'show_support_email' => ['boolean'],
            'show_phone' => ['boolean'],
        ];
    }

    private function providerLocationRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['boutique', 'branch', 'pickup', 'warehouse', 'partner'])],
            'country' => ['required', 'string', 'size:2'],
            'state' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'city' => ['nullable', 'string', 'max:120'],
            'street' => ['nullable', 'string', 'max:180'],
            'house_number' => ['nullable', 'string', 'max:40'],
            'opening_hours' => ['nullable', 'string', 'max:1000'],
            'note' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:255'],
            'image_url' => ['nullable', 'url', 'max:1000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'pickup_enabled' => ['boolean'],
            'returns_enabled' => ['boolean'],
            'is_public' => ['boolean'],
        ];
    }

    private function authorizeProviderLocation(Request $request, MarketplaceProviderLocation $location): void
    {
        $location->loadMissing('providerProfile:id,user_id');
        abort_unless((int) $location->providerProfile?->user_id === (int) $request->user()->id, 403);
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
        ];

        return $visuals;
    }

    private function nextDocumentNumber(string $settingKey, string $prefix): string
    {
        return DB::transaction(function () use ($settingKey, $prefix) {
            $next = (int) Setting::valueFor($settingKey, 1);
            Setting::setValue($settingKey, (string) ($next + 1));

            return $prefix.'-'.now()->format('Y').'-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
        });
    }
}
