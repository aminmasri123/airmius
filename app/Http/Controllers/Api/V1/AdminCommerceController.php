<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\AdminCommerceController as WebAdminCommerceController;
use App\Http\Controllers\CommerceCheckoutController;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CommerceOrderResource;
use App\Http\Resources\Api\V1\MarketplacePayoutResource;
use App\Http\Resources\Api\V1\MarketplaceProductResource;
use App\Http\Resources\Api\V1\PayoutProfileResource;
use App\Models\AdCampaign;
use App\Models\CommerceAuditLog;
use App\Models\CommerceOrder;
use App\Models\CommerceReturnRequest;
use App\Models\CommerceShippingRate;
use App\Models\CommerceTaxRate;
use App\Models\MarketplacePayout;
use App\Models\MarketplaceProduct;
use App\Models\MarketplaceSellerApplication;
use App\Models\PayoutProfile;
use App\Models\PublicContactRequest;
use App\Models\Setting;
use App\Models\SubscriptionAddon;
use App\Models\SubscriptionCoupon;
use App\Models\SubscriptionInvoice;
use App\Models\User;
use App\Models\WebsiteRequest;
use App\Services\AdminCommerceDashboardPayloadService;
use App\Services\CommerceDocumentService;
use App\Services\CommerceRefundService;
use App\Services\MarketplacePayoutService;
use App\Services\RevenueTrustService;
use App\Services\WebsiteRequestService;
use App\Support\AppNotification;
use App\Support\CarrierTracking;
use App\Support\MarketplaceSellerReadiness;
use App\Support\Roles;
use App\Support\UploadStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;

class AdminCommerceController extends Controller
{
    private const VALIDATION_FAILED = 'validation failed';

    public function __construct(
        private CommerceDocumentService $documents,
        private CommerceRefundService $refunds,
        private MarketplacePayoutService $payouts,
        private AdminCommerceDashboardPayloadService $dashboardPayload,
        private WebsiteRequestService $websiteRequests,
        private RevenueTrustService $revenueTrust,
    ) {}

    public function dashboard(Request $request)
    {
        $this->authorizeCommerceAdmin($request);

        $pages = [
            'products' => $this->listPage($request, MarketplaceProduct::query()->with(['user', 'club']), 'products', ['title', 'user.email']),
            'orders' => $this->listPage($request, CommerceOrder::query()->with(['user', 'club', 'items', 'refunds']), 'orders', ['invoice_number', 'guest_email', 'user.email', 'tracking_number']),
            'payouts' => $this->listPage($request, MarketplacePayout::query()->with('user'), 'payouts', ['reference', 'user.email']),
            'payout_profiles' => $this->listPage($request, PayoutProfile::query()->with('user'), 'payout_profiles', ['account_holder', 'user.email']),
            'return_requests' => $this->listPage($request, CommerceReturnRequest::query()->with(['order.user:id,name,email', 'item']), 'return_requests', ['reason']),
        ];

        return response()->json([
            'data' => [
                'summary' => [
                    'subscription_revenue_cents' => SubscriptionInvoice::query()->where('status', 'paid')->sum('amount_cents'),
                    'subscription_open_cents' => SubscriptionInvoice::query()->whereIn('status', ['open', 'awaiting_transfer', 'overdue'])->sum('amount_cents'),
                    'coupons' => SubscriptionCoupon::query()->count(),
                    'addons' => SubscriptionAddon::query()->count(),
                    'products' => MarketplaceProduct::query()->count(),
                    'products_in_review' => MarketplaceProduct::query()->where('status', 'review')->count(),
                    'orders' => CommerceOrder::query()->count(),
                    'orders_awaiting_transfer' => CommerceOrder::query()->where('status', 'awaiting_transfer')->count(),
                    'commission_cents' => CommerceOrder::query()->where('status', 'completed')->sum('commission_cents'),
                    'payouts_prepared' => MarketplacePayout::query()->where('status', 'prepared')->count(),
                    'seller_applications' => MarketplaceSellerApplication::query()->count(),
                    'website_requests' => WebsiteRequest::query()->count(),
                    'campaigns' => AdCampaign::query()->count(),
                    'public_contact_requests' => PublicContactRequest::query()->count(),
                    'public_contact_requests_new' => PublicContactRequest::query()->where('status', 'new')->count(),
                ],
                'products' => MarketplaceProductResource::collection($pages['products'])->response()->getData(true),
                'orders' => CommerceOrderResource::collection($pages['orders'])->response()->getData(true),
                'payouts' => MarketplacePayoutResource::collection($pages['payouts']->getCollection())->resolve($request),
                'payout_profiles' => PayoutProfileResource::collection($pages['payout_profiles']->getCollection())->resolve($request),
                'payout_candidates' => $this->payouts->candidates(),
                'return_requests' => $pages['return_requests']->items(),
                'shipping_carriers' => CarrierTracking::carriers(),
                'pagination' => array_map(fn ($page) => $this->pageMeta($page), $pages),
            ],
        ]);
    }

    public function catalog(Request $request)
    {
        $this->authorizeCommerceAdmin($request);

        $pages = [
            'coupons' => $this->listPage($request, SubscriptionCoupon::query(), 'coupons', ['name', 'code'], 'is_active'),
            'addons' => $this->listPage($request, SubscriptionAddon::query()->withCount('purchases'), 'addons', ['name'], 'is_active'),
            'tax_rates' => $this->listPage($request, CommerceTaxRate::query()->orderBy('priority')->orderBy('country_code'), 'tax_rates', ['name', 'country_code'], 'is_active'),
            'shipping_rates' => $this->listPage($request, CommerceShippingRate::query()->orderBy('priority')->orderBy('country_code'), 'shipping_rates', ['name', 'country_code'], 'is_active'),
            'seller_applications' => $this->listPage($request, MarketplaceSellerApplication::query()->with([
                'user:id,name,email', 'user.marketplaceProviderProfile.locations', 'user.payoutProfile',
            ]), 'seller_applications', ['business_name', 'user.email']),
            'website_requests' => $this->listPage($request, WebsiteRequest::query()->with(['user:id,name,email', 'club:id,name']), 'website_requests', ['club_name', 'domain', 'guest_email', 'user.email', 'club.name']),
            'campaigns' => $this->listPage($request, AdCampaign::query()->with('creatives'), 'campaigns', ['name', 'headline']),
            'public_contact_requests' => $this->listPage($request, PublicContactRequest::query()->with(['user:id,name,email', 'statusChanger:id,name,email']), 'public_contact_requests', ['name', 'email', 'subject']),
            'audit_logs' => $this->listPage($request, CommerceAuditLog::query()->with('user:id,name,email'), 'audit_logs', ['action', 'note', 'user.email'], null),
        ];
        $pages['seller_applications']->setCollection(
            $pages['seller_applications']->getCollection()
                ->map(fn (MarketplaceSellerApplication $application) => MarketplaceSellerReadiness::attach($application))
        );

        return response()->json([
            'data' => [
                ...array_map(fn ($page) => $page->items(), $pages),
                'pagination' => array_map(fn ($page) => $this->pageMeta($page), $pages),
                'commerce_settings' => $this->dashboardPayload->commerceSettings(),
                'marketplace_visuals' => $this->marketplaceVisuals(),
                'marketplace_commissions' => $this->marketplaceCommissions(),
            ],
        ]);
    }

    public function updatePublicContactRequest(Request $request, PublicContactRequest $publicContactRequest)
    {
        $this->authorizeCommerceAdmin($request);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['new', 'in_progress', 'approved', 'completed'])],
            'internal_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $publicContactRequest->update([
            'status' => $validated['status'],
            'internal_notes' => $validated['internal_notes'] ?? null,
            'status_changed_at' => now(),
            'status_changed_by' => $request->user()->id,
        ]);

        return response()->json([
            'data' => $publicContactRequest->fresh([
                'user:id,name,email',
                'statusChanger:id,name,email',
            ]),
        ]);
    }

    public function storeProduct(Request $request)
    {
        $this->authorizeCommerceAdmin($request);
        $latestId = (int) MarketplaceProduct::query()->max('id');

        app(WebAdminCommerceController::class)->storeProduct($request);

        $product = MarketplaceProduct::query()
            ->where('id', '>', $latestId)
            ->latest('id')
            ->firstOrFail();

        return (new MarketplaceProductResource($product->load(['user', 'club'])))
            ->response()
            ->setStatusCode(201);
    }

    public function updateProduct(Request $request, MarketplaceProduct $product)
    {
        $this->authorizeCommerceAdmin($request);

        app(WebAdminCommerceController::class)->updateProduct($request, $product);

        return new MarketplaceProductResource($product->fresh(['user', 'club']));
    }

    public function destroyProduct(Request $request, MarketplaceProduct $product)
    {
        $this->authorizeCommerceAdmin($request);
        $productId = $product->id;

        app(WebAdminCommerceController::class)->destroyProduct($request, $product);

        $remaining = MarketplaceProduct::query()->find($productId);

        return response()->json([
            'data' => [
                'id' => $productId,
                'deleted' => $remaining === null,
                'archived' => $remaining?->status === 'archived',
            ],
        ]);
    }

    public function adjustProductStock(Request $request, MarketplaceProduct $product)
    {
        $this->authorizeCommerceAdmin($request);

        app(WebAdminCommerceController::class)->adjustProductStock($request, $product);

        return new MarketplaceProductResource($product->fresh(['user', 'club']));
    }

    public function storeCoupon(Request $request)
    {
        $this->authorizeCommerceAdmin($request);

        $coupon = SubscriptionCoupon::query()->create($this->couponData($request));

        return response()->json(['data' => $coupon], 201);
    }

    public function updateCoupon(Request $request, SubscriptionCoupon $coupon)
    {
        $this->authorizeCommerceAdmin($request);

        $coupon->update($this->couponData($request));

        return response()->json(['data' => $coupon->fresh()]);
    }

    public function storeAddon(Request $request)
    {
        $this->authorizeCommerceAdmin($request);

        $addon = SubscriptionAddon::query()->create($this->addonData($request));

        return response()->json(['data' => $addon], 201);
    }

    public function updateAddon(Request $request, SubscriptionAddon $addon)
    {
        $this->authorizeCommerceAdmin($request);

        $addon->update($this->addonData($request));

        return response()->json(['data' => $addon->fresh()]);
    }

    public function storeTaxRate(Request $request)
    {
        $this->authorizeCommerceAdmin($request);

        $taxRate = CommerceTaxRate::query()->create($this->taxRateData($request));

        return response()->json(['data' => $taxRate], 201);
    }

    public function updateTaxRate(Request $request, CommerceTaxRate $taxRate)
    {
        $this->authorizeCommerceAdmin($request);

        $taxRate->update($this->taxRateData($request));

        return response()->json(['data' => $taxRate->fresh()]);
    }

    public function storeShippingRate(Request $request)
    {
        $this->authorizeCommerceAdmin($request);

        $shippingRate = CommerceShippingRate::query()->create($this->shippingRateData($request));

        return response()->json(['data' => $shippingRate], 201);
    }

    public function updateShippingRate(Request $request, CommerceShippingRate $shippingRate)
    {
        $this->authorizeCommerceAdmin($request);

        $shippingRate->update($this->shippingRateData($request));

        return response()->json(['data' => $shippingRate->fresh()]);
    }

    public function updateProductStatus(Request $request, MarketplaceProduct $product)
    {
        $this->authorizeCommerceAdmin($request);

        $data = $request->validate([
            'status' => ['required', Rule::in(['draft', 'review', 'published', 'rejected', 'archived'])],
            'moderation_status' => ['nullable', Rule::in(['pending', 'approved', 'rejected', 'flagged', 'reported'])],
            'rejection_reason' => ['nullable', 'string', 'max:2000'],
        ]);

        if (($data['status'] === 'rejected' || ($data['moderation_status'] ?? null) === 'rejected') && blank($data['rejection_reason'] ?? null)) {
            return response()->json([
                'message' => self::VALIDATION_FAILED,
                'message_text' => __('commerce.validation.validation_failed'),
                'errors' => [
                    'rejection_reason' => [__('commerce.validation.rejection_reason_required')],
                ],
            ], 422);
        }

        $product->update([
            'status' => $data['status'],
            'moderation_status' => $data['moderation_status'] ?? ($data['status'] === 'published' ? 'approved' : $product->moderation_status),
            'rejection_reason' => $data['rejection_reason'] ?? ($data['status'] === 'rejected' ? $product->rejection_reason : null),
        ]);

        return new MarketplaceProductResource($product->fresh(['user', 'club']));
    }

    public function updateOrderShipping(Request $request, CommerceOrder $order)
    {
        $this->authorizeCommerceAdmin($request);

        $data = $request->validate([
            'shipping_status' => ['required', Rule::in(['open', 'prepared', 'shipped', 'delivered'])],
            'shipping_carrier' => ['nullable', 'string', 'max:80'],
            'shipping_label_url' => ['nullable', 'url', 'max:2048'],
            'tracking_number' => ['nullable', 'string', 'max:120'],
            'tracking_url' => ['nullable', 'url', 'max:2048'],
        ]);

        $carrier = CarrierTracking::normalizeCarrier($data['shipping_carrier'] ?? null);
        $trackingNumber = CarrierTracking::trackingNumber($data['tracking_number'] ?? null);

        $order->update([
            'shipping_status' => $data['shipping_status'],
            'shipping_carrier' => $carrier,
            'shipping_label_url' => $data['shipping_label_url'] ?? null,
            'tracking_number' => $trackingNumber,
            'tracking_url' => CarrierTracking::trackingUrl($carrier, $trackingNumber, $data['tracking_url'] ?? null),
            'shipped_at' => $data['shipping_status'] === 'shipped' && ! $order->shipped_at ? now() : $order->shipped_at,
            'delivered_at' => $data['shipping_status'] === 'delivered' && ! $order->delivered_at ? now() : $order->delivered_at,
        ]);

        return new CommerceOrderResource($order->fresh(['club', 'items', 'refunds']));
    }

    public function markOrderPaid(Request $request, CommerceOrder $order)
    {
        $this->authorizeCommerceAdmin($request);

        app(CommerceCheckoutController::class)->activate($order);

        return new CommerceOrderResource($order->fresh(['club', 'items']));
    }

    public function markPayoutPaid(Request $request, MarketplacePayout $payout)
    {
        $this->authorizeCommerceAdmin($request);

        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $payout = $this->payouts->markPaid($payout, $data['notes'] ?? null);

        return new MarketplacePayoutResource($payout);
    }

    public function updateSellerApplication(Request $request, MarketplaceSellerApplication $sellerApplication)
    {
        $this->authorizeCommerceAdmin($request);

        $data = $request->validate([
            'status' => ['required', Rule::in(['pending', 'approved', 'rejected'])],
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $sellerApplication = $this->revenueTrust->reviewSellerApplication(
            $sellerApplication,
            $request->user(),
            $data['status'],
            $data['review_note'] ?? null,
        );

        AppNotification::send($sellerApplication->user_id, 'marketplace.seller_application.'.$data['status'], [
            'title' => $data['status'] === 'approved' ? 'Shop-Zugang freigegeben' : 'Shop-Antrag aktualisiert',
            'message' => $data['status'] === 'approved'
                ? 'Du kannst jetzt Produkte im Marketplace verkaufen.'
                : ($data['review_note'] ?? 'Dein Shop-Antrag wurde geprüft.'),
            'url' => route('auth.commerce.index', ['tab' => 'create']),
        ]);

        return response()->json(['data' => $sellerApplication->fresh(['user', 'reviewer'])]);
    }

    public function updateWebsiteRequest(Request $request, WebsiteRequest $websiteRequest)
    {
        $this->authorizeCommerceAdmin($request);

        $data = $request->validate([
            'status' => ['required', Rule::in(WebsiteRequestService::STATUSES)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        return response()->json([
            'message' => __('agency.flash.request_updated'),
            'data' => $this->websiteRequests->updateWorkflow($websiteRequest, $request->user(), $data),
        ]);
    }

    public function updateCampaignStatus(Request $request, AdCampaign $campaign)
    {
        $this->authorizeCommerceAdmin($request);

        $data = $request->validate([
            'status' => ['required', Rule::in(['draft', 'pending_payment', 'pending_review', 'active', 'paused', 'completed', 'rejected'])],
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $campaign->forceFill([
            'status' => $data['status'],
            'review_note' => $data['review_note'] ?? $campaign->review_note,
            'reviewed_at' => in_array($data['status'], ['active', 'rejected'], true) ? now() : $campaign->reviewed_at,
        ])->save();

        return response()->json(['data' => $campaign->fresh()]);
    }

    public function storeCampaign(Request $request)
    {
        $this->authorizeCommerceAdmin($request);
        $latestId = (int) AdCampaign::query()->max('id');

        app(WebAdminCommerceController::class)->storeCampaign($request);

        $campaign = AdCampaign::query()
            ->where('id', '>', $latestId)
            ->with('creatives')
            ->latest('id')
            ->firstOrFail();

        return response()->json(['data' => $campaign], 201);
    }

    public function updateCampaign(Request $request, AdCampaign $campaign)
    {
        $this->authorizeCommerceAdmin($request);

        app(WebAdminCommerceController::class)->updateCampaign($request, $campaign);

        return response()->json(['data' => $campaign->fresh('creatives')]);
    }

    public function updateOrderIssue(Request $request, CommerceOrder $order)
    {
        $this->authorizeCommerceAdmin($request);

        app(WebAdminCommerceController::class)->updateOrderIssue($request, $order);

        return new CommerceOrderResource($order->fresh(['club', 'items']));
    }

    public function replyOrderIssue(Request $request, CommerceOrder $order)
    {
        $this->authorizeCommerceAdmin($request);

        app(WebAdminCommerceController::class)->replyOrderIssue($request, $order);

        return new CommerceOrderResource($order->fresh(['club', 'items']));
    }

    public function updateReturnRequest(Request $request, CommerceReturnRequest $returnRequest)
    {
        $this->authorizeCommerceAdmin($request);

        app(WebAdminCommerceController::class)->updateReturnRequest($request, $returnRequest);

        return response()->json([
            'data' => $returnRequest->fresh(['order.user:id,name,email', 'item']),
        ]);
    }

    public function createPayout(Request $request, User $user)
    {
        $this->authorizeCommerceAdmin($request);
        $data = $request->validate([
            'method' => ['required', Rule::in(['bank_transfer', 'paypal', 'manual'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $payout = $this->payouts->create(
            $user,
            $data['method'],
            $data['notes'] ?? null,
            'prepared',
        );

        return (new MarketplacePayoutResource($payout))
            ->response()
            ->setStatusCode(201);
    }

    public function updateMarketplaceVisuals(Request $request)
    {
        $this->authorizeCommerceAdmin($request);

        app(WebAdminCommerceController::class)->updateMarketplaceVisuals($request);

        return response()->json(['data' => $this->marketplaceVisuals()]);
    }

    public function updateMarketplaceCommissions(Request $request)
    {
        $this->authorizeCommerceAdmin($request);

        app(WebAdminCommerceController::class)->updateMarketplaceCommissions($request);

        return response()->json(['data' => $this->marketplaceCommissions()]);
    }

    public function updateCommerceSettings(Request $request)
    {
        $this->authorizeCommerceAdmin($request);

        app(WebAdminCommerceController::class)->updateCommerceSettings($request);

        return response()->json(['data' => $this->dashboardPayload->commerceSettings()]);
    }

    public function refundOrder(Request $request, CommerceOrder $order)
    {
        $this->authorizeCommerceAdmin($request);

        $data = $request->validate([
            'amount_cents' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
        ]);

        $this->refunds->refund(
            $order,
            (int) $data['amount_cents'],
            $data['reason'] ?? null,
            $request->user(),
            $data['idempotency_key'] ?? null,
        );

        return new CommerceOrderResource($order->fresh(['club', 'items', 'refunds']));
    }

    public function orderDocuments(Request $request, CommerceOrder $order)
    {
        $this->authorizeCommerceAdmin($request);

        return response()->json([
            'data' => [
                'order_id' => $order->id,
                'invoice' => [
                    'available' => filled($order->invoice_number),
                    'number' => $order->invoice_number,
                    'url' => $order->invoice_number
                        ? URL::temporarySignedRoute('commerce.documents.signed', now()->addMinutes(10), [
                            'order' => $order,
                            'type' => 'invoice',
                        ])
                        : null,
                ],
                'credit_note' => [
                    'available' => filled($order->credit_note_number),
                    'number' => $order->credit_note_number,
                    'url' => $order->credit_note_number
                        ? URL::temporarySignedRoute('commerce.documents.signed', now()->addMinutes(10), [
                            'order' => $order,
                            'type' => 'credit-note',
                        ])
                        : null,
                ],
            ],
        ]);
    }

    public function downloadInvoice(Request $request, CommerceOrder $order)
    {
        $this->authorizeCommerceAdmin($request);
        abort_unless($order->invoice_number, 404);

        return response($this->documents->pdf($order, 'invoice'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$order->invoice_number.'.pdf"',
        ]);
    }

    public function downloadCreditNote(Request $request, CommerceOrder $order)
    {
        $this->authorizeCommerceAdmin($request);
        abort_unless($order->credit_note_number, 404);

        return response($this->documents->pdf($order, 'credit_note'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$order->credit_note_number.'.pdf"',
        ]);
    }

    public function export(Request $request)
    {
        $this->authorizeCommerceAdmin($request);

        $columns = [
            'order_id', 'invoice_number', 'credit_note_number', 'date', 'customer_email', 'country', 'net_cents', 'tax_cents', 'gross_cents', 'currency', 'status', 'shipping_status', 'tracking_number',
        ];

        $request->validate(['format' => ['nullable', Rule::in(['json', 'csv'])]]);
        $query = $this->filteredList(
            $request, CommerceOrder::query()->with('user:id,name,email'),
            'orders', ['invoice_number', 'guest_email', 'user.email', 'tracking_number']
        )->latest('id');
        $row = fn (CommerceOrder $order) => [
            'order_id' => $order->id,
            'invoice_number' => $order->invoice_number,
            'credit_note_number' => $order->credit_note_number,
            'date' => $order->created_at?->toDateString(),
            'customer_email' => $order->user?->email ?: $order->guest_email,
            'country' => $order->tax_country,
            'net_cents' => $order->net_cents,
            'tax_cents' => $order->tax_cents,
            'gross_cents' => $order->amount_cents,
            'currency' => $order->currency,
            'status' => $order->status,
            'shipping_status' => $order->shipping_status,
            'tracking_number' => $order->tracking_number,
        ];

        if ($request->input('format') === 'csv') {
            $lastId = (clone $query)->max('id') ?? 0;

            return response()->streamDownload(function () use ($query, $columns, $row, $lastId) {
                $stream = fopen('php://output', 'w');
                fwrite($stream, "\xEF\xBB\xBF");
                fputcsv($stream, $columns, ';', '"', '', "\r\n");
                foreach ($query->where('id', '<=', $lastId)->lazyByIdDesc(500) as $order) {
                    $cells = array_map(function ($value) {
                        // Quoting alone does not prevent spreadsheet formulas.
                        if (is_string($value) && preg_match('/^[\x00-\x20]*[=+@-]|^[\t\r\n]/u', $value)) {
                            return "'".$value;
                        }

                        return $value;
                    }, array_values($row($order)));
                    fputcsv($stream, $cells, ';', '"', '', "\r\n");
                }
                fclose($stream);
            }, 'airmius-commerce-export.csv', [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        // Retain the JSON contract for older clients, with explicit paging.
        $page = $query->paginate($request->has('per_page') ? $this->perPage($request) : 1000, ['*'], 'orders_page');
        $rows = $page->getCollection()->map($row)->values();

        return response()->json([
            'data' => [
                'columns' => $columns,
                'rows' => $rows,
                'meta' => $this->pageMeta($page),
            ],
        ]);
    }

    public function updatePayoutProfile(Request $request, PayoutProfile $profile)
    {
        $this->authorizeCommerceAdmin($request);

        $data = $request->validate([
            'status' => ['required', Rule::in(['draft', 'review', 'approved', 'blocked'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $profile = $this->revenueTrust->reviewPayoutProfile(
            $profile,
            $request->user(),
            $data['status'],
            $data['notes'] ?? null,
        );

        return new PayoutProfileResource($profile);
    }

    private function authorizeCommerceAdmin(Request $request): void
    {
        $user = $request->user();

        abort_unless(
            $user->can('subscriptions.manage')
                || $user->can('marketplace.manage')
                || $user->can('system.manage')
                || $user->hasAnyRole(Roles::FULL_ACCESS),
            403
        );
    }

    private function perPage(Request $request): int
    {
        return min(max((int) $request->integer('per_page', 20), 1), 50);
    }

    private function filteredList(Request $request, $query, string $key, array $searchColumns, ?string $statusColumn = 'status')
    {
        $input = $request->validate([
            $key.'_search' => ['nullable', 'string', 'max:200'],
            $key.'_status' => ['nullable', 'string', 'max:80'],
            $key.'_page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $search = trim($input[$key.'_search'] ?? '');
        if ($search !== '') {
            $query->where(function ($builder) use ($searchColumns, $search) {
                foreach ($searchColumns as $column) {
                    if (str_contains($column, '.')) {
                        [$relation, $field] = explode('.', $column, 2);
                        $builder->orWhereHas($relation, fn ($related) => $related->where($field, 'like', '%'.$search.'%'));
                    } else {
                        $builder->orWhere($column, 'like', '%'.$search.'%');
                    }
                }
            });
        }
        $status = $input[$key.'_status'] ?? '';
        if ($statusColumn !== null && $status !== '') {
            if ($statusColumn === 'is_active') {
                $request->validate([$key.'_status' => [Rule::in(['0', '1'])]]);
            }
            $query->where($statusColumn, $status);
        }

        return $query;
    }

    private function listPage(Request $request, $query, string $key, array $searchColumns, ?string $statusColumn = 'status')
    {
        return $this->filteredList($request, $query, $key, $searchColumns, $statusColumn)
            ->latest('id')->paginate($this->perPage($request), ['*'], $key.'_page');
    }

    private function pageMeta($page): array
    {
        return [
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'per_page' => $page->perPage(),
            'total' => $page->total(),
        ];
    }

    private function couponData(Request $request): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:80'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['percent', 'fixed'])],
            'value_cents' => ['nullable', 'integer', 'min:0'],
            'percent_off' => ['nullable', 'integer', 'min:1', 'max:100'],
            'max_redemptions' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['boolean'],
        ]);

        $data['code'] = strtoupper(trim($data['code']));
        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        return $data;
    }

    private function addonData(Request $request): array
    {
        return $request->validate([
            'slug' => ['required', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'monthly_price_cents' => ['required', 'integer', 'min:0'],
            'yearly_price_cents' => ['required', 'integer', 'min:0'],
            'target_actor' => ['required', 'string', 'max:30'],
            'features' => ['nullable', 'array'],
            'is_active' => ['boolean'],
        ]);
    }

    private function taxRateData(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'country_code' => ['required', 'string', 'size:2'],
            'region' => ['nullable', 'string', 'max:80'],
            'tax_class' => ['nullable', 'string', 'max:30'],
            'tax_label' => ['required', 'string', 'max:40'],
            'rate_percent' => ['required', 'numeric', 'min:0', 'max:99.99'],
            'currency' => ['required', 'string', 'size:3'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
            'priority' => ['nullable', 'integer', 'min:1', 'max:9999'],
        ]);

        return [
            ...$data,
            'country_code' => strtoupper($data['country_code']),
            'tax_class' => ($data['tax_class'] ?? null) ?: 'standard',
            'currency' => strtoupper($data['currency']),
            'is_default' => (bool) ($data['is_default'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? false),
            'priority' => (int) ($data['priority'] ?? 100),
        ];
    }

    private function shippingRateData(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'origin_country_code' => ['nullable', 'string', 'size:2'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'postal_code_prefix' => ['nullable', 'string', 'max:20'],
            'amount_cents' => ['required', 'integer', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'free_from_cents' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'priority' => ['nullable', 'integer', 'min:1', 'max:9999'],
        ]);

        return [
            ...$data,
            'origin_country_code' => filled($data['origin_country_code'] ?? null) ? strtoupper($data['origin_country_code']) : null,
            'country_code' => filled($data['country_code'] ?? null) ? strtoupper($data['country_code']) : null,
            'currency' => strtoupper($data['currency']),
            'is_active' => (bool) ($data['is_active'] ?? false),
            'priority' => (int) ($data['priority'] ?? 100),
        ];
    }

    private function marketplaceVisuals(): array
    {
        $definitions = [
            'side_banner' => [
                'label' => __('media_guidelines.visuals.marketplace_side.label'),
                'description' => __('media_guidelines.visuals.marketplace_side.description_compact'),
                'recommended_size' => '192 x 1080 px',
                'default_width' => 192,
                'default_height' => 1080,
                'default' => '/images/marketplace/airmius-marketplace-side-banner.png',
            ],
            'hero_banner' => [
                'label' => __('media_guidelines.visuals.marketplace_hero.label'),
                'description' => __('media_guidelines.visuals.marketplace_hero.description_compact'),
                'recommended_size' => '1600 x 900 px',
                'default_width' => 1600,
                'default_height' => 900,
                'default' => '',
            ],
            'sale_banner' => [
                'label' => __('media_guidelines.visuals.marketplace_sale.label'),
                'description' => __('media_guidelines.visuals.marketplace_sale.description_compact'),
                'recommended_size' => '800 x 1000 px',
                'default_width' => 800,
                'default_height' => 1000,
                'default' => '',
            ],
        ];

        return collect($definitions)->map(function (array $definition, string $key) {
            $settingKey = 'marketplace_visual_'.$key;
            $source = (string) Setting::valueFor($settingKey, $definition['default']);

            return [
                'key' => $key,
                'label' => $definition['label'],
                'description' => $definition['description'],
                'recommended_size' => $definition['recommended_size'],
                'width' => (int) Setting::valueFor($settingKey.'_width', $definition['default_width']),
                'height' => (int) Setting::valueFor($settingKey.'_height', $definition['default_height']),
                'source' => $source,
                'url' => UploadStorage::url($source),
            ];
        })->values()->all();
    }

    private function marketplaceCommissions(): array
    {
        $defaults = [
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
        $configured = $this->settingMap('marketplace_category_commissions');
        $labels = collect($defaults)
            ->merge($this->settingMap('marketplace_category_labels'))
            ->merge(MarketplaceProduct::query()
                ->whereNotNull('category')
                ->distinct()
                ->pluck('category')
                ->mapWithKeys(fn (string $category) => [
                    $category => $defaults[$category] ?? str($category)->replace(['_', '-'], ' ')->title()->toString(),
                ]));
        $fallback = (int) Setting::valueFor('marketplace_default_commission_percent', 10);

        return $labels->map(fn (string $label, string $category) => [
            'category' => $category,
            'label' => $label,
            'commission_percent' => (int) ($configured[$category] ?? $fallback),
        ])->values()->all();
    }

    private function settingMap(string $key): array
    {
        $raw = Setting::valueFor($key, '{}');
        $decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
