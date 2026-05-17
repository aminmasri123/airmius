<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\CommerceCheckoutController;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CommerceOrderResource;
use App\Http\Resources\Api\V1\MarketplacePayoutResource;
use App\Http\Resources\Api\V1\MarketplaceProductResource;
use App\Http\Resources\Api\V1\PayoutProfileResource;
use App\Models\AdCampaign;
use App\Models\CommerceOrder;
use App\Models\CommerceShippingRate;
use App\Models\CommerceTaxRate;
use App\Models\MarketplacePayout;
use App\Models\MarketplaceProduct;
use App\Models\MarketplaceSellerApplication;
use App\Models\PayoutProfile;
use App\Models\Setting;
use App\Models\SubscriptionAddon;
use App\Models\SubscriptionCoupon;
use App\Models\SubscriptionInvoice;
use App\Models\WebsiteRequest;
use App\Services\CommerceDocumentService;
use App\Support\AppNotification;
use App\Support\CarrierTracking;
use App\Support\Roles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminCommerceController extends Controller
{
    public function __construct(private CommerceDocumentService $documents) {}

    public function dashboard(Request $request)
    {
        $this->authorizeCommerceAdmin($request);

        $products = MarketplaceProduct::query()
            ->with(['user', 'club'])
            ->latest('id')
            ->paginate($this->perPage($request), ['*'], 'products_page');

        $orders = CommerceOrder::query()
            ->with(['user', 'club', 'items'])
            ->latest('id')
            ->paginate($this->perPage($request), ['*'], 'orders_page');

        $payouts = MarketplacePayout::query()
            ->with('user')
            ->latest('id')
            ->limit(25)
            ->get();

        $payoutProfiles = PayoutProfile::query()
            ->with('user')
            ->latest('id')
            ->limit(25)
            ->get();

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
                ],
                'products' => MarketplaceProductResource::collection($products)->response()->getData(true),
                'orders' => CommerceOrderResource::collection($orders)->response()->getData(true),
                'payouts' => MarketplacePayoutResource::collection($payouts)->resolve($request),
                'payout_profiles' => PayoutProfileResource::collection($payoutProfiles)->resolve($request),
                'shipping_carriers' => CarrierTracking::carriers(),
            ],
        ]);
    }

    public function catalog(Request $request)
    {
        $this->authorizeCommerceAdmin($request);

        return response()->json([
            'data' => [
                'coupons' => SubscriptionCoupon::query()->latest('id')->limit(100)->get(),
                'addons' => SubscriptionAddon::query()->latest('id')->limit(100)->get(),
                'tax_rates' => CommerceTaxRate::query()->orderBy('priority')->orderBy('country_code')->limit(100)->get(),
                'shipping_rates' => CommerceShippingRate::query()->orderBy('priority')->orderBy('country_code')->limit(100)->get(),
                'seller_applications' => MarketplaceSellerApplication::query()->with('user:id,name,email')->latest('id')->limit(100)->get(),
                'website_requests' => WebsiteRequest::query()->with(['user:id,name,email', 'club:id,name'])->latest('id')->limit(100)->get(),
                'campaigns' => AdCampaign::query()->latest('id')->limit(100)->get(),
            ],
        ]);
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
                'message' => 'validation failed',
                'errors' => [
                    'rejection_reason' => ['Bitte gib einen Ablehnungsgrund an.'],
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

        return new CommerceOrderResource($order->fresh(['club', 'items']));
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

        DB::transaction(function () use ($payout, $data) {
            $payout->update([
                'status' => 'paid',
                'paid_at' => now(),
                'notes' => $data['notes'] ?? $payout->notes,
            ]);

            CommerceOrder::query()
                ->where('payout_id', $payout->id)
                ->update(['payout_status' => 'paid']);
        });

        return new MarketplacePayoutResource($payout->fresh('user'));
    }

    public function updateSellerApplication(Request $request, MarketplaceSellerApplication $sellerApplication)
    {
        $this->authorizeCommerceAdmin($request);

        $data = $request->validate([
            'status' => ['required', Rule::in(['pending', 'approved', 'rejected'])],
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $sellerApplication->update([
            'status' => $data['status'],
            'review_note' => $data['review_note'] ?? null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        AppNotification::send($sellerApplication->user_id, 'marketplace.seller_application.'.$data['status'], [
            'title' => $data['status'] === 'approved' ? 'Shop-Zugang freigegeben' : 'Shop-Antrag aktualisiert',
            'message' => $data['status'] === 'approved'
                ? 'Du kannst jetzt Produkte im Marketplace verkaufen.'
                : ($data['review_note'] ?? 'Dein Shop-Antrag wurde geprueft.'),
            'url' => route('auth.commerce.index', ['tab' => 'create']),
        ]);

        return response()->json(['data' => $sellerApplication->fresh(['user', 'reviewer'])]);
    }

    public function updateWebsiteRequest(Request $request, WebsiteRequest $websiteRequest)
    {
        $this->authorizeCommerceAdmin($request);

        $websiteRequest->update($request->validate([
            'status' => ['required', Rule::in(['new', 'contacted', 'quoted', 'in_progress', 'done', 'cancelled'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]));

        return response()->json(['data' => $websiteRequest->fresh(['user', 'club'])]);
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

    public function refundOrder(Request $request, CommerceOrder $order)
    {
        $this->authorizeCommerceAdmin($request);

        $data = $request->validate([
            'amount_cents' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $remainingCents = max(0, (int) $order->amount_cents - (int) $order->refunded_cents);

        abort_if((int) $data['amount_cents'] > $remainingCents, 422, 'Die Erstattung darf den offenen Restbetrag nicht uebersteigen.');

        $order->update([
            'status' => (int) $data['amount_cents'] >= $remainingCents ? 'refunded' : $order->status,
            'issue_status' => 'refunded',
            'issue_note' => $data['reason'] ?? $order->issue_note,
            'refunded_cents' => (int) $order->refunded_cents + (int) $data['amount_cents'],
            'refund_provider_id' => $order->refund_provider_id ?: 'manual-mobile-'.$order->id.'-'.now()->timestamp,
            'credit_note_number' => $order->credit_note_number ?: $this->nextDocumentNumber('commerce_credit_note_number_next', 'AIR-GS'),
        ]);

        return new CommerceOrderResource($order->fresh(['club', 'items']));
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
                    'url' => $order->invoice_number ? route('api.v1.admin.commerce.orders.invoice', $order) : null,
                ],
                'credit_note' => [
                    'available' => filled($order->credit_note_number),
                    'number' => $order->credit_note_number,
                    'url' => $order->credit_note_number ? route('api.v1.admin.commerce.orders.credit-note', $order) : null,
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

        $rows = CommerceOrder::query()
            ->with('user:id,name,email')
            ->latest('id')
            ->limit(1000)
            ->get()
            ->map(fn (CommerceOrder $order) => [
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
            ])
            ->values();

        return response()->json([
            'data' => [
                'columns' => $columns,
                'rows' => $rows,
            ],
        ]);
    }

    public function updatePayoutProfile(Request $request, PayoutProfile $profile)
    {
        $this->authorizeCommerceAdmin($request);

        $profile->update($request->validate([
            'status' => ['required', Rule::in(['draft', 'review', 'approved', 'blocked'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]));

        return new PayoutProfileResource($profile->fresh('user'));
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

    private function nextDocumentNumber(string $settingKey, string $prefix): string
    {
        $next = (int) Setting::valueFor($settingKey, 1);
        Setting::setValue($settingKey, (string) ($next + 1));

        return $prefix.'-'.now()->format('Y').'-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }
}
