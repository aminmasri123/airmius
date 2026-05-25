<?php

namespace App\Http\Controllers;

use App\Models\AdCampaign;
use App\Models\AdCreative;
use App\Models\AdEvent;
use App\Models\CommerceOrder;
use App\Models\CommerceReturnRequest;
use App\Models\CommerceAuditLog;
use App\Models\CommerceWarehouse;
use App\Models\CommerceStockMovement;
use App\Models\CommerceShippingRate;
use App\Models\CommerceTaxRate;
use App\Models\LearningEnrollment;
use App\Models\MarketplacePayout;
use App\Models\MarketplaceProduct;
use App\Models\MarketplaceProductInventory;
use App\Models\MarketplaceSellerApplication;
use App\Models\PayoutProfile;
use App\Models\Setting;
use App\Models\SubscriptionAddon;
use App\Models\SubscriptionCoupon;
use App\Models\SubscriptionInvoice;
use App\Models\User;
use App\Models\WebsiteRequest;
use App\Services\MediaOptimizer;
use App\Services\CommerceAuditService;
use App\Services\CommerceDocumentService;
use App\Support\CarrierTracking;
use App\Support\UploadStorage;
use App\Support\AppNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AdminCommerceController extends Controller
{
    public function __construct(
        private MediaOptimizer $mediaOptimizer,
        private CommerceAuditService $audit,
        private CommerceDocumentService $documents,
    ) {}

    public function index()
    {
        return Inertia::render('Auth/Dashboard/Admin/Commerce/Index', [
            'summary' => [
                'revenue_cents' => SubscriptionInvoice::query()->where('status', 'paid')->sum('amount_cents'),
                'open_cents' => SubscriptionInvoice::query()->whereIn('status', ['open', 'awaiting_transfer', 'overdue'])->sum('amount_cents'),
                'coupons' => SubscriptionCoupon::query()->count(),
                'addons' => SubscriptionAddon::query()->count(),
                'products' => MarketplaceProduct::query()->count(),
                'warehouses' => CommerceWarehouse::query()->count(),
                'campaigns' => AdCampaign::query()->count(),
                'orders' => CommerceOrder::query()->count(),
                'commission_cents' => CommerceOrder::query()->where('status', 'completed')->sum('commission_cents'),
                'payout_cents' => CommerceOrder::query()->where('status', 'completed')->sum('amount_cents')
                    - CommerceOrder::query()->where('status', 'completed')->sum('commission_cents'),
                'website_requests' => WebsiteRequest::query()->count(),
            ],
            'coupons' => SubscriptionCoupon::query()->latest('id')->get(),
            'addons' => SubscriptionAddon::query()->withCount('purchases')->latest('id')->get(),
            'products' => MarketplaceProduct::query()
                ->with(['user:id,name,email', 'club:id,name,verification_status', 'inventories.warehouse:id,name,country_code,city,postal_code'])
                ->latest('id')
                ->limit(100)
                ->get()
                ->map(fn (MarketplaceProduct $product) => $this->adminProductResource($product))
                ->values(),
            'warehouses' => CommerceWarehouse::query()
                ->withCount([
                    'inventories',
                    'inventories as active_inventories_count' => fn ($query) => $query->where('is_active', true),
                ])
                ->orderBy('country_code')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->limit(100)
                ->get(),
            'sellerApplications' => MarketplaceSellerApplication::query()
                ->with(['user:id,name,email', 'reviewer:id,name,email'])
                ->latest('id')
                ->limit(100)
                ->get(),
            'campaigns' => AdCampaign::query()
                ->with(['creatives', 'stats' => fn ($query) => $query->latest('date')->limit(30)])
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
                ->get(),
            'adReport' => [
                'impressions' => AdCampaign::query()->sum('impressions'),
                'clicks' => AdCampaign::query()->sum('clicks'),
                'spent_cents' => AdCampaign::query()->sum('spent_cents'),
                'budget_cents' => AdCampaign::query()->sum('budget_cents'),
                'active' => AdCampaign::query()->where('status', 'active')->count(),
            ],
            'adPlacementReport' => $this->adPlacementReport(),
            'adDiagnostics' => $this->adDiagnostics(),
            'orders' => CommerceOrder::query()
                ->with(['user:id,name,email', 'club:id,name', 'orderable', 'items', 'returnRequests', 'issueResponder:id,name,email'])
                ->latest('id')
                ->limit(50)
                ->get(),
            'returnRequests' => CommerceReturnRequest::query()
                ->with(['order.user:id,name,email', 'order.orderable', 'item.orderable'])
                ->latest('id')
                ->limit(50)
                ->get(),
            'payoutProfiles' => PayoutProfile::query()
                ->with('user:id,name,email')
                ->latest('id')
                ->limit(50)
                ->get(),
            'payoutCandidates' => $this->payoutCandidates(),
            'payouts' => MarketplacePayout::query()
                ->with(['user:id,name,email'])
                ->latest('id')
                ->limit(50)
                ->get(),
            'websiteRequests' => WebsiteRequest::query()
                ->with(['user:id,name,email', 'club:id,name'])
                ->latest('id')
                ->limit(50)
                ->get(),
            'marketplaceVisuals' => $this->marketplaceVisualsForAdmin(),
            'taxRates' => CommerceTaxRate::query()->orderBy('priority')->orderBy('country_code')->get(),
            'shippingRates' => CommerceShippingRate::query()->orderBy('priority')->orderBy('country_code')->get(),
            'marketplaceCategoryCommissions' => $this->marketplaceCategoryCommissionsForAdmin(),
            'shippingCarriers' => CarrierTracking::carriers(),
            'auditLogs' => CommerceAuditLog::query()
                ->with('user:id,name,email')
                ->latest('id')
                ->limit(80)
                ->get(),
            'ossReport' => $this->ossReport(),
            'sellerReports' => $this->sellerReports(),
            'commerceSettings' => [
                'company_country' => Setting::valueFor('commerce_company_country', 'DE'),
                'company_currency' => Setting::valueFor('commerce_company_currency', 'EUR'),
                'enable_oss' => Setting::boolFor('commerce_enable_oss', true),
                'export_vat_mode' => Setting::valueFor('commerce_export_vat_mode', 'zero'),
                'reverse_charge_enabled' => Setting::boolFor('commerce_reverse_charge_enabled', true),
                'ads_cpm_cents' => (int) Setting::valueFor('ads_cpm_cents', 500),
                'ads_cpc_cents' => (int) Setting::valueFor('ads_cpc_cents', 30),
                'ads_cpl_cents' => (int) Setting::valueFor('ads_cpl_cents', 200),
                'ads_cpa_percent' => (int) Setting::valueFor('ads_cpa_percent', 10),
                'ads_min_budget_cents' => (int) Setting::valueFor('ads_min_budget_cents', 1000),
                'ads_frequency_cap_per_day' => (int) Setting::valueFor('ads_frequency_cap_per_day', 3),
                'ads_frequency_cap_feed' => (int) Setting::valueFor('ads_frequency_cap_feed', 3),
                'ads_frequency_cap_sidebar' => (int) Setting::valueFor('ads_frequency_cap_sidebar', 6),
                'ads_frequency_cap_marketplace_card' => (int) Setting::valueFor('ads_frequency_cap_marketplace_card', 3),
                'ads_frequency_cap_sponsor_section' => (int) Setting::valueFor('ads_frequency_cap_sponsor_section', 4),
                'marketplace_default_commission_percent' => (int) Setting::valueFor('marketplace_default_commission_percent', 10),
            ],
        ]);
    }

    public function storeCoupon(Request $request)
    {
        SubscriptionCoupon::create($this->couponData($request));

        return back()->with('success', 'Rabattcode erstellt.');
    }

    public function updateCoupon(Request $request, SubscriptionCoupon $coupon)
    {
        $coupon->update($this->couponData($request));

        return back()->with('success', 'Rabattcode aktualisiert.');
    }

    public function storeAddon(Request $request)
    {
        SubscriptionAddon::create($this->addonData($request));

        return back()->with('success', 'Add-on erstellt.');
    }

    public function updateAddon(Request $request, SubscriptionAddon $addon)
    {
        $addon->update($this->addonData($request));

        return back()->with('success', 'Add-on aktualisiert.');
    }

    public function storeProduct(Request $request)
    {
        MarketplaceProduct::create([
            ...$this->productData($request),
            'commission_percent' => 0,
            'payout_status' => 'not_applicable',
        ]);

        return back()->with('success', 'Marketplace-Produkt erstellt.');
    }

    public function updateProduct(Request $request, MarketplaceProduct $product)
    {
        $before = $product->only(['title', 'price_cents', 'status', 'stock_quantity', 'tax_class']);
        $data = $this->productData($request);
        if (! $product->user_id) {
            $data['commission_percent'] = 0;
            $data['payout_status'] = 'not_applicable';
        }

        $product->update($data);
        $this->audit->log('product.updated', $product, $before, $product->fresh()->only(['title', 'price_cents', 'status', 'stock_quantity', 'tax_class']));

        return back()->with('success', 'Marketplace-Produkt aktualisiert.');
    }

    public function updateSellerApplication(Request $request, MarketplaceSellerApplication $sellerApplication)
    {
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
                : ($data['review_note'] ?? 'Dein Shop-Antrag wurde geprüft.'),
            'url' => route('auth.commerce.index', ['tab' => 'create']),
        ]);

        return back()->with('success', 'Shop-Antrag wurde aktualisiert.');
    }

    public function adjustProductStock(Request $request, MarketplaceProduct $product)
    {
        $data = $request->validate([
            'quantity_delta' => ['required', 'integer', 'not_in:0'],
            'marketplace_product_inventory_id' => ['nullable', Rule::exists('marketplace_product_inventories', 'id')],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        abort_unless($product->manages_stock, 422, 'Dieses Produkt verwaltet keinen Lagerbestand.');

        DB::transaction(function () use ($product, $data) {
            $product = MarketplaceProduct::query()->lockForUpdate()->findOrFail($product->id);
            $inventory = null;
            $stockAfter = null;

            if (! empty($data['marketplace_product_inventory_id'])) {
                $inventory = MarketplaceProductInventory::query()
                    ->where('marketplace_product_id', $product->id)
                    ->lockForUpdate()
                    ->findOrFail($data['marketplace_product_inventory_id']);

                $inventory->stock_quantity = max(0, (int) $inventory->stock_quantity + (int) $data['quantity_delta']);
                $inventory->save();
                $stockAfter = $inventory->availableQuantity();

                $product->forceFill([
                    'stock_quantity' => $product->inventories()->where('is_active', true)->sum('stock_quantity'),
                    'available_countries' => $product->inventories()
                        ->where('is_active', true)
                        ->where('stock_quantity', '>', 0)
                        ->pluck('country_code')
                        ->unique()
                        ->values()
                        ->all(),
                ])->save();
            } else {
                $product->stock_quantity = max(0, (int) $product->stock_quantity + (int) $data['quantity_delta']);
                $product->save();
                $stockAfter = $product->stock_quantity;
            }

            CommerceStockMovement::create([
                'marketplace_product_id' => $product->id,
                'commerce_warehouse_id' => $inventory?->commerce_warehouse_id,
                'type' => 'manual_adjustment',
                'quantity_delta' => (int) $data['quantity_delta'],
                'stock_after' => $stockAfter,
                'note' => $data['note'] ?? null,
            ]);
            $this->audit->log('stock.adjusted', $product, [], [
                'quantity_delta' => (int) $data['quantity_delta'],
                'commerce_warehouse_id' => $inventory?->commerce_warehouse_id,
                'stock_after' => $stockAfter,
            ], $data['note'] ?? null);
        });

        return back()->with('success', 'Lagerbestand wurde angepasst.');
    }

    public function updateOrderIssue(Request $request, CommerceOrder $order)
    {
        $data = $request->validate([
            'issue_status' => ['required', Rule::in(['none', 'reported', 'reviewing', 'resolved', 'refunded', 'cancelled'])],
            'issue_note' => ['nullable', 'string', 'max:2000'],
            'order_status' => ['nullable', Rule::in(['completed', 'cancelled', 'refunded'])],
        ]);

        $order->update([
            'issue_status' => $data['issue_status'],
            'issue_note' => $data['issue_note'] ?? $order->issue_note,
            'status' => $data['order_status'] ?? $order->status,
        ]);

        if (in_array($data['order_status'] ?? null, ['cancelled', 'refunded'], true)) {
            $this->revokeLearningAccessForOrder($order, $data['order_status']);
        }

        return back()->with('success', 'Bestellproblem wurde aktualisiert.');
    }

    public function replyOrderIssue(Request $request, CommerceOrder $order)
    {
        abort_if(! $order->issue_status || $order->issue_status === 'none', 422, 'Zu dieser Bestellung gibt es keine aktive Meldung.');

        $data = $request->validate([
            'issue_response' => ['required', 'string', 'max:2000'],
            'issue_status' => ['nullable', Rule::in(['reported', 'reviewing', 'resolved'])],
        ]);

        $order->update([
            'issue_response' => $data['issue_response'],
            'issue_responded_at' => now(),
            'issue_responded_by' => $request->user()->id,
            'issue_status' => $data['issue_status'] ?? 'reviewing',
        ]);

        $freshOrder = $order->fresh(['user']);

        if ($freshOrder?->user_id) {
            AppNotification::send($freshOrder->user_id, 'commerce.order.issue_replied', [
                'title' => 'Antwort zu deiner Meldung',
                'body' => 'Airmius hat auf deine Meldung zu Bestellung #'.$freshOrder->id.' geantwortet.',
                'url' => route('auth.commerce.index', ['tab' => 'invoices']),
                'order_id' => $freshOrder->id,
            ]);
        }

        return back()->with('success', 'Antwort wurde gesendet.');
    }

    public function updateReturnRequest(Request $request, CommerceReturnRequest $returnRequest)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['requested', 'approved', 'rejected', 'received', 'refunded', 'cancelled'])],
            'resolution_note' => ['nullable', 'string', 'max:2000'],
            'approved_amount_cents' => ['nullable', 'integer', 'min:0'],
            'restock' => ['boolean'],
        ]);

        DB::transaction(function () use ($returnRequest, $data) {
            $before = $returnRequest->only(['status', 'approved_amount_cents', 'resolution_note']);
            $updates = [
                'status' => $data['status'],
                'resolution_note' => $data['resolution_note'] ?? $returnRequest->resolution_note,
                'approved_amount_cents' => $data['approved_amount_cents'] ?? $returnRequest->approved_amount_cents,
            ];

            $timestampColumn = match ($data['status']) {
                'approved' => 'approved_at',
                'rejected' => 'rejected_at',
                'received' => 'received_at',
                'refunded' => 'refunded_at',
                default => null,
            };

            if ($timestampColumn) {
                $updates[$timestampColumn] = now();
            }

            $returnRequest->update($updates);
            $this->audit->log('return.updated', $returnRequest, $before, $returnRequest->fresh()->only(['status', 'approved_amount_cents', 'resolution_note']));

            if (($data['status'] === 'refunded') && $returnRequest->order) {
                $returnRequest->order->update([
                    'issue_status' => 'refunded',
                    'status' => 'refunded',
                    'refunded_cents' => (int) ($data['approved_amount_cents'] ?? $returnRequest->approved_amount_cents ?? $returnRequest->requested_amount_cents),
                    'credit_note_number' => $returnRequest->order->credit_note_number ?: $this->nextDocumentNumber('commerce_credit_note_number_next', 'AIR-GS'),
                ]);
                $this->revokeLearningAccessForOrder($returnRequest->order, 'refunded');
            }

            if (($data['restock'] ?? false) && $returnRequest->item?->orderable instanceof MarketplaceProduct && $returnRequest->item->orderable->manages_stock) {
                $product = MarketplaceProduct::query()->lockForUpdate()->find($returnRequest->item->orderable_id);

                if ($product) {
                    $inventory = null;
                    $stockAfter = null;

                    if ($returnRequest->item->commerce_warehouse_id) {
                        $inventory = MarketplaceProductInventory::query()
                            ->where('marketplace_product_id', $product->id)
                            ->where('commerce_warehouse_id', $returnRequest->item->commerce_warehouse_id)
                            ->lockForUpdate()
                            ->first();

                        if ($inventory) {
                            $inventory->increment('stock_quantity', (int) $returnRequest->quantity);
                            $inventory->refresh();
                            $stockAfter = $inventory->availableQuantity();
                            $product->forceFill([
                                'stock_quantity' => $product->inventories()->where('is_active', true)->sum('stock_quantity'),
                            ])->save();
                        }
                    } else {
                        $product->increment('stock_quantity', (int) $returnRequest->quantity);
                        $product->refresh();
                        $stockAfter = $product->stock_quantity;
                    }

                    if ($stockAfter !== null) {
                        CommerceStockMovement::create([
                            'marketplace_product_id' => $product->id,
                            'commerce_warehouse_id' => $inventory?->commerce_warehouse_id,
                            'commerce_order_id' => $returnRequest->commerce_order_id,
                            'commerce_return_request_id' => $returnRequest->id,
                            'type' => 'return_restock',
                            'quantity_delta' => (int) $returnRequest->quantity,
                            'stock_after' => $stockAfter,
                            'note' => 'Rücksendung #'.$returnRequest->id,
                        ]);
                    }
                }
            }
        });

        $returnRequest->refresh()->load('user');
        if ($returnRequest->user) {
            $returnRequest->user->notify(new \App\Notifications\CommerceReturnStatusUpdated($returnRequest));
        } elseif ($returnRequest->guest_email) {
            Notification::route('mail', $returnRequest->guest_email)
                ->notify(new \App\Notifications\CommerceReturnStatusUpdated($returnRequest));
        }

        return back()->with('success', 'Rücksendung wurde aktualisiert.');
    }

    public function updateShipping(Request $request, CommerceOrder $order)
    {
        $data = $request->validate([
            'shipping_status' => ['required', Rule::in(['open', 'prepared', 'shipped', 'delivered'])],
            'shipping_carrier' => ['nullable', 'string', 'max:80'],
            'shipping_label_url' => ['nullable', 'url', 'max:2048'],
            'tracking_number' => ['nullable', 'string', 'max:120'],
            'tracking_url' => ['nullable', 'url', 'max:2048'],
        ]);
        $data['shipping_carrier'] = CarrierTracking::normalizeCarrier($data['shipping_carrier'] ?? null);
        $data['tracking_number'] = CarrierTracking::trackingNumber($data['tracking_number'] ?? null);
        $data['tracking_url'] = CarrierTracking::trackingUrl(
            $data['shipping_carrier'],
            $data['tracking_number'],
            $data['tracking_url'] ?? null,
        );

        $before = $order->only(['shipping_status', 'shipping_carrier', 'tracking_number', 'tracking_url']);
        $previousStatus = $order->shipping_status;
        $order->update([
            ...$data,
            'shipped_at' => $data['shipping_status'] === 'shipped' && ! $order->shipped_at ? now() : $order->shipped_at,
            'delivered_at' => $data['shipping_status'] === 'delivered' && ! $order->delivered_at ? now() : $order->delivered_at,
        ]);
        $this->audit->log('shipping.updated', $order, $before, $order->fresh()->only(['shipping_status', 'shipping_carrier', 'tracking_number', 'tracking_url']));
        $this->notifyShippingUpdated($order->fresh(), $previousStatus);

        return back()->with('success', 'Versandstatus wurde aktualisiert.');
    }

    public function refundOrder(Request $request, CommerceOrder $order)
    {
        $data = $request->validate([
            'amount_cents' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $remainingCents = max(0, (int) $order->amount_cents - (int) $order->refunded_cents);
        abort_if((int) $data['amount_cents'] > $remainingCents, 422, 'Die Erstattung darf den offenen Restbetrag nicht Übersteigen.');

        $providerRefundId = $this->refundViaProvider($order, (int) $data['amount_cents']);
        $before = $order->only(['status', 'refunded_cents', 'refund_provider_id']);
        $order->update([
            'status' => (int) $data['amount_cents'] >= $remainingCents ? 'refunded' : $order->status,
            'issue_status' => 'refunded',
            'refunded_cents' => (int) $order->refunded_cents + (int) $data['amount_cents'],
            'refund_provider_id' => $providerRefundId ?: $order->refund_provider_id,
            'credit_note_number' => $order->credit_note_number ?: $this->nextDocumentNumber('commerce_credit_note_number_next', 'AIR-GS'),
        ]);

        if ((int) $data['amount_cents'] >= (int) $order->amount_cents) {
            $this->revokeLearningAccessForOrder($order, 'refunded');
        }
        $this->audit->log('order.refunded', $order, $before, $order->fresh()->only(['status', 'refunded_cents', 'refund_provider_id']), $data['reason'] ?? null);

        return back()->with('success', $providerRefundId ? 'Erstattung wurde beim Anbieter angestoßen.' : 'Erstattung wurde dokumentiert.');
    }

    public function downloadInvoice(CommerceOrder $order)
    {
        abort_unless($order->invoice_number, 404);

        return response($this->documents->pdf($order, 'invoice'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$order->invoice_number.'.pdf"',
        ]);
    }

    public function downloadCreditNote(CommerceOrder $order)
    {
        abort_unless($order->credit_note_number, 404);

        return response($this->documents->pdf($order, 'credit_note'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$order->credit_note_number.'.pdf"',
        ]);
    }

    public function exportCsv()
    {
        $orders = CommerceOrder::query()->with(['user:id,name,email', 'items'])->latest('id')->get();
        $rows = [[
            'order_id', 'invoice_number', 'credit_note_number', 'date', 'customer_email', 'country', 'net_cents', 'tax_cents', 'gross_cents', 'currency', 'status', 'shipping_status', 'tracking_number',
        ]];

        foreach ($orders as $order) {
            $rows[] = [
                $order->id,
                $order->invoice_number,
                $order->credit_note_number,
                $order->created_at?->toDateString(),
                $order->user?->email ?: $order->guest_email,
                $order->tax_country,
                $order->net_cents,
                $order->tax_cents,
                $order->amount_cents,
                $order->currency,
                $order->status,
                $order->shipping_status,
                $order->tracking_number,
            ];
        }

        $csv = collect($rows)->map(fn ($row) => collect($row)->map(fn ($value) => '"'.str_replace('"', '""', (string) $value).'"')->implode(';'))->implode("\n");

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="airmius-commerce-export.csv"',
        ]);
    }

    public function storeCampaign(Request $request)
    {
        $data = $this->campaignData($request);
        $data['reviewed_at'] = in_array($data['status'] ?? null, ['active', 'rejected'], true) ? now() : null;

        $creativeRows = $data['_creative_rows'] ?? [];
        unset($data['_creative_rows']);

        $campaign = AdCampaign::create($data);
        $this->syncAdCreatives($campaign, $creativeRows);

        return back()->with('success', 'Kampagne erstellt.');
    }

    public function updateCampaign(Request $request, AdCampaign $campaign)
    {
        $data = $this->campaignData($request);
        $creativeRows = $data['_creative_rows'] ?? [];
        unset($data['_creative_rows']);

        if (($data['status'] ?? null) === 'active' && $campaign->user_id && ! $this->campaignPaymentCompleted($campaign)) {
            throw ValidationException::withMessages([
                'campaign_status' => 'Diese Ads-Kampagne kann erst nach Zahlung freigegeben werden.',
            ]);
        }

        if (($data['status'] ?? null) !== $campaign->status && in_array($data['status'] ?? null, ['active', 'rejected'], true)) {
            $data['reviewed_at'] = now();
        }

        $campaign->update($data);
        $this->syncAdCreatives($campaign, $creativeRows);

        return back()->with('success', 'Kampagne aktualisiert.');
    }

    public function updateCampaignStatus(Request $request, AdCampaign $campaign)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['draft', 'pending_payment', 'pending_review', 'active', 'paused', 'completed', 'rejected'])],
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($data['status'] === 'active' && $campaign->user_id && ! $this->campaignPaymentCompleted($campaign)) {
            throw ValidationException::withMessages([
                'campaign_status' => 'Diese Ads-Kampagne kann erst nach Zahlung freigegeben werden.',
            ]);
        }

        $campaign->forceFill([
            'status' => $data['status'],
            'review_note' => $data['review_note'] ?? $campaign->review_note,
            'reviewed_at' => in_array($data['status'], ['active', 'rejected'], true) ? now() : $campaign->reviewed_at,
        ])->save();

        return back()->with('success', 'Kampagnenstatus wurde aktualisiert.');
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

    private function adDiagnostics(): array
    {
        return AdCampaign::query()
            ->with(['stats' => fn ($query) => $query->where('date', today()->toDateString())])
            ->latest('id')
            ->limit(80)
            ->get()
            ->map(function (AdCampaign $campaign) {
                $reasons = [];
                $todaySpent = (int) ($campaign->stats->first()?->spent_cents ?? 0);
                $dailyBudget = (int) $campaign->daily_budget_cents;
                $minutesElapsed = max(1, now()->diffInMinutes(today()));
                $allowedSpend = $dailyBudget > 0
                    ? (int) round($dailyBudget * min(1, $minutesElapsed / 1440)) + max(100, (int) round($dailyBudget * 0.12))
                    : null;
                $audience = is_array($campaign->audience) ? $campaign->audience : [];
                $hasAudience = collect($audience)->filter(fn ($value) => filled($value) && $value !== [])->isNotEmpty();

                if ($campaign->status !== 'active') {
                    $reasons[] = 'Nicht aktiv: '.$campaign->status;
                }

                if (! $campaign->is_internal && (int) $campaign->spent_cents >= (int) $campaign->budget_cents) {
                    $reasons[] = 'Gesamtbudget verbraucht';
                }

                if ($dailyBudget > 0 && $todaySpent > $allowedSpend) {
                    $reasons[] = 'Pacing bremst: heute '.$this->money($todaySpent).' von erlaubt '.$this->money($allowedSpend);
                }

                if ($campaign->starts_at && $campaign->starts_at->isFuture()) {
                    $reasons[] = 'Startet erst am '.$campaign->starts_at->format('d.m.Y H:i');
                }

                if ($campaign->ends_at && $campaign->ends_at->isPast()) {
                    $reasons[] = 'Laufzeit beendet';
                }

                if ($hasAudience) {
                    $audienceHints = collect([
                        filled($audience['locations'] ?? []) ? 'Region' : null,
                        filled($audience['excluded_locations'] ?? []) ? 'Region-Ausschluss' : null,
                        filled($audience['interests'] ?? []) ? 'Interessen' : null,
                        filled($audience['excluded_interests'] ?? []) ? 'Interessen-Ausschluss' : null,
                        filled($audience['age_min'] ?? null) || filled($audience['age_max'] ?? null) ? 'Alter' : null,
                        filled($audience['devices'] ?? []) ? 'Gerät' : null,
                        filled($audience['languages'] ?? []) ? 'Sprache' : null,
                        filled($audience['hours'] ?? []) ? 'Zeitfenster' : null,
                    ])->filter()->implode(', ');
                    $reasons[] = 'Audience aktiv: '.$audienceHints.' können Reichweite begrenzen';
                }

                $recentEvents = AdEvent::query()
                    ->select('event_type', DB::raw('COUNT(*) as total'))
                    ->where('ad_campaign_id', $campaign->id)
                    ->where('occurred_at', '>=', now()->subDays(14))
                    ->whereIn('event_type', ['click', 'lead', 'sale'])
                    ->groupBy('event_type')
                    ->pluck('total', 'event_type');
                $recentConversions = (int) ($recentEvents['lead'] ?? 0) + (int) ($recentEvents['sale'] ?? 0);

                if (in_array($campaign->objective, ['leads', 'sales'], true)) {
                    $reasons[] = $recentConversions > 0
                        ? 'Conversion-Boost aktiv: '.$recentConversions.' Ergebnisse in 14 Tagen'
                        : 'Noch keine Conversions in 14 Tagen';
                }

                if ($campaign->is_internal && $campaign->force_priority) {
                    $reasons[] = 'Interne Prioritaet aktiv, Frequency Cap gilt trotzdem';
                }

                if ($reasons === []) {
                    $reasons[] = 'Auslieferbar';
                }

                return [
                    'id' => $campaign->id,
                    'name' => $campaign->name,
                    'placement' => $campaign->placement,
                    'status' => $campaign->status,
                    'is_internal' => (bool) $campaign->is_internal,
                    'force_priority' => (bool) $campaign->force_priority,
                    'today_spent_cents' => $todaySpent,
                    'daily_budget_cents' => $dailyBudget,
                    'allowed_spend_cents' => $allowedSpend,
                    'reasons' => $reasons,
                ];
            })
            ->all();
    }

    private function adPlacementReport(): array
    {
        $rows = AdEvent::query()
            ->select('placement', 'event_type', DB::raw('COUNT(*) as total'), DB::raw('SUM(cost_cents) as cost_cents'), DB::raw('SUM(value_cents) as value_cents'))
            ->where('occurred_at', '>=', now()->subDays(30))
            ->groupBy('placement', 'event_type')
            ->get()
            ->groupBy(fn ($row) => $row->placement ?: 'unknown');

        return $rows->map(function ($events, string $placement) {
            $byType = $events->keyBy('event_type');
            $impressions = (int) ($byType['impression']->total ?? 0);
            $clicks = (int) ($byType['click']->total ?? 0);
            $leads = (int) ($byType['lead']->total ?? 0);
            $sales = (int) ($byType['sale']->total ?? 0);

            return [
                'placement' => $placement,
                'impressions' => $impressions,
                'clicks' => $clicks,
                'leads' => $leads,
                'sales' => $sales,
                'ctr' => $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0,
                'cost_cents' => (int) $events->sum('cost_cents'),
                'value_cents' => (int) $events->sum('value_cents'),
            ];
        })->values()->all();
    }

    private function money(?int $cents): string
    {
        return number_format(((int) $cents) / 100, 2, ',', '.').' EUR';
    }

    public function markOrderPaid(CommerceOrder $order)
    {
        app(CommerceCheckoutController::class)->activate($order);

        return back()->with('success', 'Commerce-Bestellung wurde als bezahlt markiert.');
    }

    private function notifyShippingUpdated(CommerceOrder $order, ?string $previousStatus): void
    {
        if (! $order->user_id || $previousStatus === $order->shipping_status) {
            return;
        }

        $statusLabel = match ($order->shipping_status) {
            'prepared' => 'wird vorbereitet',
            'shipped' => 'wurde versendet',
            'delivered' => 'wurde zugestellt',
            default => 'wurde aktualisiert',
        };

        $tracking = $order->tracking_number
            ? ' Tracking: '.$order->tracking_number
            : '';

        AppNotification::send($order->user_id, 'commerce.order.shipping_updated', [
            'title' => 'Versand aktualisiert',
            'body' => 'Deine Bestellung #'.$order->id.' '.$statusLabel.'.'.$tracking,
            'url' => route('auth.commerce.index'),
            'order_id' => $order->id,
            'shipping_status' => $order->shipping_status,
        ]);
    }

    public function updateWebsiteRequest(Request $request, WebsiteRequest $websiteRequest)
    {
        $websiteRequest->update($request->validate([
            'status' => ['required', Rule::in(['new', 'contacted', 'quoted', 'in_progress', 'done', 'cancelled'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]));

        return back()->with('success', 'Website-Anfrage aktualisiert.');
    }

    public function createPayout(Request $request, User $user)
    {
        $data = $request->validate([
            'method' => ['required', Rule::in(['bank_transfer', 'paypal', 'manual'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $orders = $this->eligiblePayoutOrdersFor($user)->get();
        abort_if($orders->isEmpty(), 422, 'Keine auszahlbaren Verkäufe für diesen Anbieter gefunden. Auszahlungen sind erst 14 Tage nach Abschluss ohne Beschwerde oder Rücksendung möglich.');

        DB::transaction(function () use ($orders, $user, $data) {
            $payout = MarketplacePayout::create([
                'user_id' => $user->id,
                'currency' => 'EUR',
                'gross_cents' => $orders->sum('amount_cents'),
                'commission_cents' => $orders->sum('commission_cents'),
                'amount_cents' => $orders->sum('amount_cents') - $orders->sum('commission_cents'),
                'method' => $data['method'],
                'status' => 'prepared',
                'reference' => 'AIR-PAY-'.$orders->first()->created_at->format('Y').'-'.str_pad((string) (MarketplacePayout::query()->max('id') + 1), 6, '0', STR_PAD_LEFT),
                'notes' => $data['notes'] ?? null,
            ]);

            CommerceOrder::query()
                ->whereIn('id', $orders->pluck('id'))
                ->update([
                    'payout_id' => $payout->id,
                    'payout_status' => 'prepared',
                ]);
        });

        return back()->with('success', 'Auszahlung wurde vorbereitet.');
    }

    public function markPayoutPaid(Request $request, MarketplacePayout $payout)
    {
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

        return back()->with('success', 'Auszahlung wurde als ausgezahlt markiert.');
    }

    public function updatePayoutProfile(Request $request, PayoutProfile $profile)
    {
        $profile->update($request->validate([
            'status' => ['required', Rule::in(['draft', 'review', 'approved', 'blocked'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]));

        return back()->with('success', 'Auszahlungsprofil aktualisiert.');
    }

    public function updateMarketplaceVisuals(Request $request)
    {
        $definitions = $this->marketplaceVisualDefinitions();
        $data = $request->validate([
            'sources' => ['nullable', 'array'],
            'sources.*' => ['nullable', 'string', 'max:2048'],
            'dimensions' => ['nullable', 'array'],
            'dimensions.*.width' => ['nullable', 'integer', 'min:120', 'max:3840'],
            'dimensions.*.height' => ['nullable', 'integer', 'min:120', 'max:3840'],
            'uploads' => ['nullable', 'array'],
            'uploads.*' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ]);

        foreach ($definitions as $key => $definition) {
            $request->validate([
                "sources.{$key}" => ['nullable', 'string', 'max:2048'],
                "uploads.{$key}" => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            ]);

            $source = trim((string) ($data['sources'][$key] ?? ''));

            if ($request->hasFile("uploads.{$key}")) {
                $source = $this->mediaOptimizer->store($request->file("uploads.{$key}"), 'marketplace/visuals')['path'];
            }

            Setting::setValue($definition['setting_key'], $source);
            Setting::setValue($definition['setting_key'].'_width', (string) (int) ($data['dimensions'][$key]['width'] ?? $definition['default_width']));
            Setting::setValue($definition['setting_key'].'_height', (string) (int) ($data['dimensions'][$key]['height'] ?? $definition['default_height']));
        }

        return back()->with('success', 'Marketplace-Bilder wurden aktualisiert.');
    }

    public function updateCommerceSettings(Request $request)
    {
        $data = $request->validate([
            'company_country' => ['required', 'string', 'size:2'],
            'company_currency' => ['required', 'string', 'size:3'],
            'enable_oss' => ['boolean'],
            'export_vat_mode' => ['required', Rule::in(['zero', 'domestic'])],
            'reverse_charge_enabled' => ['boolean'],
            'ads_cpm_cents' => ['required', 'integer', 'min:0', 'max:100000'],
            'ads_cpc_cents' => ['required', 'integer', 'min:0', 'max:100000'],
            'ads_cpl_cents' => ['required', 'integer', 'min:0', 'max:100000'],
            'ads_cpa_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'ads_min_budget_cents' => ['required', 'integer', 'min:0', 'max:10000000'],
            'ads_frequency_cap_per_day' => ['required', 'integer', 'min:0', 'max:100'],
            'ads_frequency_cap_feed' => ['required', 'integer', 'min:0', 'max:100'],
            'ads_frequency_cap_sidebar' => ['required', 'integer', 'min:0', 'max:100'],
            'ads_frequency_cap_marketplace_card' => ['required', 'integer', 'min:0', 'max:100'],
            'ads_frequency_cap_sponsor_section' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        Setting::setValue('commerce_company_country', strtoupper($data['company_country']));
        Setting::setValue('commerce_company_currency', strtoupper($data['company_currency']));
        Setting::setValue('commerce_enable_oss', (bool) ($data['enable_oss'] ?? false));
        Setting::setValue('commerce_export_vat_mode', $data['export_vat_mode']);
        Setting::setValue('commerce_reverse_charge_enabled', (bool) ($data['reverse_charge_enabled'] ?? false));
        Setting::setValue('ads_cpm_cents', (int) $data['ads_cpm_cents']);
        Setting::setValue('ads_cpc_cents', (int) $data['ads_cpc_cents']);
        Setting::setValue('ads_cpl_cents', (int) $data['ads_cpl_cents']);
        Setting::setValue('ads_cpa_percent', (int) $data['ads_cpa_percent']);
        Setting::setValue('ads_min_budget_cents', (int) $data['ads_min_budget_cents']);
        Setting::setValue('ads_frequency_cap_per_day', (int) $data['ads_frequency_cap_per_day']);
        Setting::setValue('ads_frequency_cap_feed', (int) $data['ads_frequency_cap_feed']);
        Setting::setValue('ads_frequency_cap_sidebar', (int) $data['ads_frequency_cap_sidebar']);
        Setting::setValue('ads_frequency_cap_marketplace_card', (int) $data['ads_frequency_cap_marketplace_card']);
        Setting::setValue('ads_frequency_cap_sponsor_section', (int) $data['ads_frequency_cap_sponsor_section']);

        return back()->with('success', 'Commerce-Steuerlogik wurde aktualisiert.');
    }

    public function updateMarketplaceCommissions(Request $request)
    {
        $data = $request->validate([
            'default_commission_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'commissions' => ['required', 'array', 'max:100'],
            'commissions.*.category' => ['required', 'string', 'max:80'],
            'commissions.*.label' => ['nullable', 'string', 'max:120'],
            'commissions.*.commission_percent' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        $commissions = collect($data['commissions'])
            ->mapWithKeys(function (array $row) {
                $category = trim((string) $row['category']);

                return $category === '' ? [] : [$category => max(0, min(100, (int) $row['commission_percent']))];
            })
            ->all();
        $labels = collect($data['commissions'])
            ->mapWithKeys(function (array $row) {
                $category = trim((string) $row['category']);
                $label = trim((string) ($row['label'] ?? ''));

                return $category === '' || $label === '' ? [] : [$category => $label];
            })
            ->all();

        Setting::setValue('marketplace_default_commission_percent', (int) $data['default_commission_percent']);
        Setting::setValue('marketplace_category_commissions', json_encode($commissions));
        Setting::setValue('marketplace_category_labels', json_encode($labels));

        return back()->with('success', 'Marketplace-Provisionen wurden aktualisiert.');
    }

    public function storeTaxRate(Request $request)
    {
        CommerceTaxRate::create($this->taxRateData($request));

        return back()->with('success', 'Steuersatz wurde gespeichert.');
    }

    public function updateTaxRate(Request $request, CommerceTaxRate $taxRate)
    {
        $taxRate->update($this->taxRateData($request));

        return back()->with('success', 'Steuersatz wurde aktualisiert.');
    }

    public function storeShippingRate(Request $request)
    {
        CommerceShippingRate::create($this->shippingRateData($request));

        return back()->with('success', 'Versandkosten wurden gespeichert.');
    }

    public function updateShippingRate(Request $request, CommerceShippingRate $shippingRate)
    {
        $shippingRate->update($this->shippingRateData($request));

        return back()->with('success', 'Versandkosten wurden aktualisiert.');
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

    private function marketplaceCategoryCommissionsForAdmin(): array
    {
        $defaults = [
            'equipment' => 'Sportgeräte & Equipment',
            'apparel' => 'Bekleidung & Schuhe',
            'nutrition' => 'Ernährung & Supplements',
            'accessories' => 'Zubehoer',
            'digital_products' => 'Digitale Produkte',
            'product' => 'Sonstige Produkte',
            'course' => 'Kurse / E-Learning',
            'camp' => 'Camps',
            'service' => 'Services / Airmius intern',
            'outfit_subscription' => 'Outfit-Abo',
        ];

        $existingCategories = MarketplaceProduct::query()
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category')
            ->filter()
            ->values();

        $customLabels = $this->marketplaceCategoryLabelSettings();
        $labels = collect($defaults)
            ->merge($existingCategories->mapWithKeys(fn (string $category) => [$category => $defaults[$category] ?? str($category)->replace(['_', '-'], ' ')->title()->toString()]));
        $labels = $labels->merge($customLabels);

        $configured = $this->marketplaceCategoryCommissionSettings();

        return $labels
            ->map(fn (string $label, string $category) => [
                'category' => $category,
                'label' => $label,
                'commission_percent' => $configured[$category] ?? $this->marketplaceCommissionPercentForCategory($category),
            ])
            ->values()
            ->all();
    }

    private function marketplaceCommissionPercentForCategory(?string $category): int
    {
        $category = trim((string) $category);
        $configured = $this->marketplaceCategoryCommissionSettings();

        if ($category !== '' && array_key_exists($category, $configured)) {
            return max(0, min(100, (int) $configured[$category]));
        }

        return max(0, min(100, (int) Setting::valueFor('marketplace_default_commission_percent', 10)));
    }

    private function marketplaceCategoryCommissionSettings(): array
    {
        $raw = Setting::valueFor('marketplace_category_commissions', '{}');
        $decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function marketplaceCategoryLabelSettings(): array
    {
        $raw = Setting::valueFor('marketplace_category_labels', '{}');
        $decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function productData(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'features_text' => ['nullable', 'string', 'max:2000'],
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
            'category' => ['required', 'string', 'max:50'],
            'product_type' => ['nullable', Rule::in(['single', 'variable', 'digital'])],
            'sku' => ['nullable', 'string', 'max:80'],
            'is_shippable' => ['boolean'],
            'manages_stock' => ['boolean'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'tax_class' => ['nullable', 'string', 'max:30'],
            'return_policy_type' => ['nullable', Rule::in(['standard', 'digital', 'service', 'hygiene', 'custom'])],
            'return_window_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'digital_delivery_note' => ['nullable', 'string', 'max:2000'],
            'price_cents' => ['required', 'integer', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'status' => ['required', Rule::in(['draft', 'review', 'published', 'rejected', 'archived'])],
            'rejection_reason' => ['nullable', 'string', 'max:2000'],
            'commission_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $featuresText = (string) ($data['features_text'] ?? '');
        $attributesText = (string) ($data['attributes_text'] ?? '');
        $imageUrlsText = (string) ($data['image_urls_text'] ?? '');
        unset($data['features_text']);
        unset($data['attributes_text']);
        $attributeOptions = $this->normalizeAttributeOptions($data['attribute_options'] ?? []);
        $variants = $this->normalizeVariants($data['variants'] ?? [], $attributeOptions, (int) $data['price_cents']);
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
            $stored = $this->mediaOptimizer->store($request->file('image_upload'), 'marketplace/products');
            $data['image_url'] = UploadStorage::url($stored['path']);
        }

        foreach ($request->file('image_uploads', []) as $file) {
            if (! $file) {
                continue;
            }

            $stored = $this->mediaOptimizer->store($file, 'marketplace/products');
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

        $data['features'] = collect(preg_split('/\r\n|\r|\n/', $featuresText))
            ->map(fn (string $feature) => trim($feature))
            ->filter()
            ->take(12)
            ->values()
            ->all();
        $data['product_attributes'] = $this->productAttributesFromText($attributesText);
        $data['product_type'] = $data['product_type'] ?? 'single';
        $data['attribute_options'] = $attributeOptions;
        $data['variants'] = $data['product_type'] === 'variable' ? $variants : [];
        $data['commission_percent'] = $data['commission_percent'] ?? $this->marketplaceCommissionPercentForCategory($data['category']);
        if ($data['product_type'] === 'digital') {
            $data['is_shippable'] = false;
            $data['return_policy_type'] = $data['return_policy_type'] ?? 'digital';
            $data['return_window_days'] = 0;
        }
        $data['sku'] = filled($data['sku'] ?? null) ? trim((string) $data['sku']) : null;

        return $data;
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

    private function campaignData(Request $request): array
    {
        $minimumBudget = (int) Setting::valueFor('ads_min_budget_cents', 1000);
        $isInternal = $request->boolean('is_internal');

        $data = $request->validate([
            'is_internal' => ['boolean'],
            'force_priority' => ['boolean'],
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
            'creatives.*.id' => ['nullable', 'integer', Rule::exists('ad_creatives', 'id')],
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
            'audience_excluded_locations' => ['nullable', 'string', 'max:255'],
            'audience_excluded_interests' => ['nullable', 'string', 'max:500'],
            'audience_devices' => ['nullable', 'string', 'max:255'],
            'audience_languages' => ['nullable', 'string', 'max:255'],
            'audience_hours' => ['nullable', 'string', 'max:255'],
            'audience_age_min' => ['nullable', 'integer', 'min:13', 'max:100'],
            'audience_age_max' => ['nullable', 'integer', 'min:13', 'max:100', 'gte:audience_age_min'],
            'budget_cents' => ['required', 'integer', 'min:'.($isInternal ? 0 : $minimumBudget)],
            'daily_budget_cents' => ['nullable', 'integer', 'min:0'],
            'spent_cents' => ['nullable', 'integer', 'min:0'],
            'impressions' => ['nullable', 'integer', 'min:0'],
            'clicks' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', Rule::in(['draft', 'pending_payment', 'pending_review', 'active', 'paused', 'completed', 'rejected'])],
            'review_note' => ['nullable', 'string', 'max:2000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        $data['is_internal'] = (bool) ($data['is_internal'] ?? false);
        $data['force_priority'] = $data['is_internal'] && (bool) ($data['force_priority'] ?? false);

        if ($data['is_internal']) {
            $data['user_id'] = null;
            $data['club_id'] = null;
        }

        $data['audience'] = [
            'locations' => $this->splitCampaignList($data['audience_locations'] ?? ''),
            'interests' => $this->splitCampaignList($data['audience_interests'] ?? ''),
            'excluded_locations' => $this->splitCampaignList($data['audience_excluded_locations'] ?? ''),
            'excluded_interests' => $this->splitCampaignList($data['audience_excluded_interests'] ?? ''),
            'devices' => $this->splitCampaignList($data['audience_devices'] ?? ''),
            'languages' => $this->splitCampaignList($data['audience_languages'] ?? ''),
            'hours' => $this->splitCampaignList($data['audience_hours'] ?? ''),
            'age_min' => $data['audience_age_min'] ?? null,
            'age_max' => $data['audience_age_max'] ?? null,
        ];
        $data['_creative_rows'] = $data['creatives'] ?? [];
        unset(
            $data['audience_locations'],
            $data['audience_interests'],
            $data['audience_excluded_locations'],
            $data['audience_excluded_interests'],
            $data['audience_devices'],
            $data['audience_languages'],
            $data['audience_hours'],
            $data['audience_age_min'],
            $data['audience_age_max'],
            $data['creative_image_upload'],
            $data['creatives']
        );

        $data['daily_budget_cents'] = (int) ($data['daily_budget_cents'] ?? 0);
        $data['billing_event'] = 'impression';

        if ($request->hasFile('creative_image_upload')) {
            $data['creative_image_path'] = $this->mediaOptimizer->store($request->file('creative_image_upload'), 'ads/creatives')['path'];
            $data['creative_image_url'] = null;
        }

        return $data;
    }

    private function syncAdCreatives(AdCampaign $campaign, array $creativeRows): void
    {
        $rows = collect($creativeRows)
            ->map(fn (array $row, int $index) => [
                'id' => $row['id'] ?? null,
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

        if ($rows->isEmpty() && $campaign->creatives()->doesntExist()) {
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

        $keptIds = [];

        $rows->each(function (array $row) use ($campaign, &$keptIds) {
            $id = $row['id'] ?? null;
            unset($row['id']);

            $creative = $id
                ? $campaign->creatives()->whereKey($id)->first()
                : null;

            if ($creative) {
                $creative->update($row);
            } else {
                $creative = $campaign->creatives()->create($row);
            }

            $keptIds[] = $creative->id;
        });

        if ($keptIds) {
            $campaign->creatives()->whereNotIn('id', $keptIds)->update(['is_active' => false]);
        }
    }

    private function splitCampaignList(?string $value): array
    {
        return collect(preg_split('/[,;\n]+/', (string) $value))
            ->map(fn ($item) => trim($item))
            ->filter()
            ->values()
            ->all();
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

    private function pendingPayoutOrdersFor(User $user)
    {
        return $this->eligiblePayoutOrdersFor($user);
    }

    private function eligiblePayoutOrdersFor(User $user)
    {
        $cutoff = now()->subDays(14);

        return CommerceOrder::query()
            ->whereIn('type', ['marketplace_product', 'marketplace_cart'])
            ->where('status', 'completed')
            ->where('payout_status', 'pending')
            ->where(function ($query) {
                $query->whereNull('issue_status')->orWhere('issue_status', 'none');
            })
            ->whereDoesntHave('returnRequests')
            ->where(function ($query) use ($cutoff) {
                $query->where(function ($noShipping) use ($cutoff) {
                    $noShipping
                        ->whereDoesntHave('items', fn ($items) => $items->where('is_shippable', true))
                        ->where('completed_at', '<=', $cutoff);
                })->orWhere(function ($shipping) use ($cutoff) {
                    $shipping
                        ->whereHas('items', fn ($items) => $items->where('is_shippable', true))
                        ->where('shipping_status', 'delivered')
                        ->where('delivered_at', '<=', $cutoff);
                });
            })
            ->where(function ($query) use ($user) {
                $query->whereHasMorph('orderable', [MarketplaceProduct::class], fn ($product) => $product->where('user_id', $user->id))
                    ->orWhereHas('items', fn ($items) => $items
                        ->where('orderable_type', MarketplaceProduct::class)
                        ->whereHasMorph('orderable', [MarketplaceProduct::class], fn ($product) => $product->where('user_id', $user->id)));
            })
            ->whereDoesntHave('items', fn ($items) => $items
                ->where('orderable_type', MarketplaceProduct::class)
                ->whereHasMorph('orderable', [MarketplaceProduct::class], fn ($product) => $product->where('user_id', '!=', $user->id)));
    }

    private function payoutCandidates(): array
    {
        $orders = CommerceOrder::query()
            ->with(['orderable.user:id,name,email', 'items.orderable.user:id,name,email'])
            ->whereIn('type', ['marketplace_product', 'marketplace_cart'])
            ->where('status', 'completed')
            ->where('payout_status', 'pending')
            ->where(function ($query) {
                $query->whereNull('issue_status')->orWhere('issue_status', 'none');
            })
            ->whereDoesntHave('returnRequests')
            ->where(function ($query) {
                $cutoff = now()->subDays(14);
                $query->where(function ($noShipping) use ($cutoff) {
                    $noShipping
                        ->whereDoesntHave('items', fn ($items) => $items->where('is_shippable', true))
                        ->where('completed_at', '<=', $cutoff);
                })->orWhere(function ($shipping) use ($cutoff) {
                    $shipping
                        ->whereHas('items', fn ($items) => $items->where('is_shippable', true))
                        ->where('shipping_status', 'delivered')
                        ->where('delivered_at', '<=', $cutoff);
                });
            })
            ->where(function ($query) {
                $query->whereHasMorph('orderable', [MarketplaceProduct::class], fn ($product) => $product->whereNotNull('user_id'))
                    ->orWhereHas('items', fn ($items) => $items
                        ->where('orderable_type', MarketplaceProduct::class)
                        ->whereHasMorph('orderable', [MarketplaceProduct::class], fn ($product) => $product->whereNotNull('user_id')));
            })
            ->get()
            ->filter(function (CommerceOrder $order) {
                $sellerIds = $order->items
                    ->filter(fn ($item) => $item->orderable instanceof MarketplaceProduct)
                    ->map(fn ($item) => $item->orderable->user_id)
                    ->filter()
                    ->unique()
                    ->values();

                if ($order->orderable instanceof MarketplaceProduct && $order->orderable->user_id) {
                    $sellerIds->push($order->orderable->user_id);
                }

                return $sellerIds->unique()->count() === 1;
            });

        return $orders
            ->groupBy(fn (CommerceOrder $order) => $order->orderable?->user_id ?: $order->items->first(fn ($item) => $item->orderable instanceof MarketplaceProduct)?->orderable?->user_id)
            ->filter(fn ($group, $userId) => filled($userId))
            ->map(function ($group, $userId) {
                $seller = $group->first()->orderable?->user
                    ?: $group->first()->items->first(fn ($item) => $item->orderable instanceof MarketplaceProduct)?->orderable?->user;

                return [
                    'user_id' => (int) $userId,
                    'name' => $seller?->name,
                    'email' => $seller?->email,
                    'orders_count' => $group->count(),
                    'gross_cents' => $group->sum('amount_cents'),
                    'commission_cents' => $group->sum('commission_cents'),
                    'amount_cents' => $group->sum('amount_cents') - $group->sum('commission_cents'),
                ];
            })
            ->values()
            ->all();
    }

    private function sellerReports(): array
    {
        return MarketplaceProduct::query()
            ->with(['user:id,name,email', 'inventories.warehouse:id,name,country_code,city'])
            ->withSum('stockMovements as stock_delta_sum', 'quantity_delta')
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(function (MarketplaceProduct $product) {
                $activeInventories = $product->inventories->where('is_active', true);

                return [
                    'product_id' => $product->id,
                    'title' => $product->title,
                    'seller' => $product->user?->name ?: $product->user?->email,
                    'stock_quantity' => $product->stock_quantity,
                    'manages_stock' => $product->manages_stock,
                    'status' => $product->status,
                    'low_stock' => $product->manages_stock && (
                        ((int) $product->low_stock_threshold > 0 && (int) $product->stock_quantity <= (int) $product->low_stock_threshold)
                        || $activeInventories->contains(fn (MarketplaceProductInventory $inventory) => (int) $inventory->low_stock_threshold > 0 && $inventory->availableQuantity() <= (int) $inventory->low_stock_threshold)
                    ),
                    'inventories' => $activeInventories->map(fn (MarketplaceProductInventory $inventory) => [
                        'country_code' => $inventory->country_code,
                        'stock_quantity' => $inventory->stock_quantity,
                        'available_quantity' => $inventory->availableQuantity(),
                        'low_stock_threshold' => $inventory->low_stock_threshold,
                        'warehouse_name' => $inventory->warehouse?->name,
                    ])->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    private function adminProductResource(MarketplaceProduct $product): array
    {
        $resource = $product->toArray();
        $issues = $this->productQualityIssues($product);
        $score = max(0, 100 - (count($issues) * 14));

        return [
            ...$resource,
            'seller_name' => $product->club?->name ?: ($product->user?->name ?: $product->user?->email),
            'seller_verified' => $product->club?->verification_status === 'verified',
            'inventories' => $product->inventories
                ->map(fn (MarketplaceProductInventory $inventory) => [
                    ...$inventory->toArray(),
                    'available_quantity' => $inventory->availableQuantity(),
                    'warehouse' => $inventory->warehouse ? [
                        'id' => $inventory->warehouse->id,
                        'name' => $inventory->warehouse->name,
                        'country_code' => $inventory->warehouse->country_code,
                        'city' => $inventory->warehouse->city,
                        'postal_code' => $inventory->warehouse->postal_code,
                    ] : null,
                ])
                ->values()
                ->all(),
            'quality_score' => $score,
            'quality_issues' => $issues,
            'is_market_ready' => $score >= 72 && $issues === [],
        ];
    }

    private function productQualityIssues(MarketplaceProduct $product): array
    {
        $issues = [];

        if (! filled($product->image_url)) {
            $issues[] = 'Hauptbild fehlt';
        }

        if (mb_strlen(trim((string) $product->title)) < 8) {
            $issues[] = 'Titel zu kurz';
        }

        if (mb_strlen(trim((string) $product->description)) < 80) {
            $issues[] = 'Beschreibung zu kurz';
        }

        if (empty($product->features) || count((array) $product->features) < 2) {
            $issues[] = 'Mindestens 2 Merkmale fehlen';
        }

        if ((int) $product->price_cents <= 0) {
            $issues[] = 'Preis fehlt';
        }

        if ($product->product_type !== 'digital' && (bool) $product->manages_stock && (int) $product->stock_quantity <= 0) {
            $issues[] = 'Kein Lagerbestand';
        }

        if (! filled($product->return_policy_type)) {
            $issues[] = 'Rückgaberichtlinie fehlt';
        }

        if (! $product->club_id && ! $product->user_id) {
            $issues[] = 'Anbieter fehlt';
        }

        return $issues;
    }

    private function ossReport(): array
    {
        return CommerceOrder::query()
            ->where('status', 'completed')
            ->whereNotNull('tax_country')
            ->get()
            ->groupBy('tax_country')
            ->map(fn ($orders, string $country) => [
                'country' => $country,
                'orders_count' => $orders->count(),
                'net_cents' => $orders->sum('net_cents'),
                'tax_cents' => $orders->sum('tax_cents'),
                'gross_cents' => $orders->sum('amount_cents'),
            ])
            ->values()
            ->all();
    }

    private function refundViaProvider(CommerceOrder $order, int $amountCents): ?string
    {
        if ($order->provider === 'stripe' && filled(config('services.stripe.secret'))) {
            $paymentIntent = data_get($order->payload, 'payment_intent') ?: data_get($order->payload, 'data.object.payment_intent');
            if ($paymentIntent) {
                $response = Http::asForm()
                    ->withToken(config('services.stripe.secret'))
                    ->post('https://api.stripe.com/v1/refunds', [
                        'payment_intent' => $paymentIntent,
                        'amount' => $amountCents,
                        'metadata[commerce_order_id]' => (string) $order->id,
                    ]);

                if ($response->ok()) {
                    return $response->json('id');
                }
            }
        }

        if ($order->provider === 'paypal') {
            $captureId = data_get($order->payload, 'purchase_units.0.payments.captures.0.id')
                ?: data_get($order->payload, 'resource.id');
            if ($captureId && filled(config('services.paypal.client_id')) && filled(config('services.paypal.client_secret'))) {
                $tokenResponse = Http::asForm()
                    ->withBasicAuth(config('services.paypal.client_id'), config('services.paypal.client_secret'))
                    ->post($this->paypalBaseUrl().'/v1/oauth2/token', ['grant_type' => 'client_credentials']);

                if ($tokenResponse->ok()) {
                    $response = Http::withToken($tokenResponse->json('access_token'))
                        ->withHeaders(['PayPal-Request-Id' => (string) \Illuminate\Support\Str::uuid()])
                        ->post($this->paypalBaseUrl().'/v2/payments/captures/'.$captureId.'/refund', [
                            'amount' => [
                                'currency_code' => $order->currency,
                                'value' => number_format($amountCents / 100, 2, '.', ''),
                            ],
                        ]);

                    if ($response->ok()) {
                        return $response->json('id');
                    }
                }
            }
        }

        return null;
    }

    private function paypalBaseUrl(): string
    {
        return config('services.paypal.mode') === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    private function nextDocumentNumber(string $settingKey, string $prefix): string
    {
        return DB::transaction(function () use ($settingKey, $prefix) {
            $next = (int) Setting::valueFor($settingKey, 1);
            Setting::setValue($settingKey, (string) ($next + 1));

            return $prefix.'-'.now()->format('Y').'-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
        });
    }

    private function revokeLearningAccessForOrder(CommerceOrder $order, string $status = 'refunded'): void
    {
        $userId = $order->user_id ?: User::query()
            ->where('email', strtolower((string) $order->guest_email))
            ->value('id');

        if (! $userId) {
            return;
        }

        $order->loadMissing('items.orderable');
        $courseIds = collect([$order->orderable])
            ->concat($order->items->pluck('orderable'))
            ->filter(fn ($item) => $item instanceof MarketplaceProduct && $item->learning_course_id)
            ->pluck('learning_course_id')
            ->filter()
            ->unique()
            ->values();

        if ($courseIds->isEmpty()) {
            return;
        }

        LearningEnrollment::query()
            ->where('user_id', $userId)
            ->whereIn('learning_course_id', $courseIds)
            ->update([
                'status' => $status,
                'completed_at' => null,
            ]);
    }

    private function marketplaceVisualsForAdmin(): array
    {
        return collect($this->marketplaceVisualDefinitions())
            ->map(function (array $definition, string $key) {
                $width = (int) Setting::valueFor($definition['setting_key'].'_width', $definition['default_width']);
                $height = (int) Setting::valueFor($definition['setting_key'].'_height', $definition['default_height']);

                if ($key === 'side_banner' && $width === 306 && $height === 786) {
                    $width = $definition['default_width'];
                    $height = $definition['default_height'];
                }

                return [
                    'key' => $key,
                    'label' => $definition['label'],
                    'description' => $definition['description'],
                    'recommended_size' => $definition['recommended_size'],
                    'width' => $width,
                    'height' => $height,
                    'source' => Setting::valueFor($definition['setting_key'], $definition['default']),
                    'url' => UploadStorage::url(Setting::valueFor($definition['setting_key'], $definition['default'])),
                ];
            })
            ->values()
            ->all();
    }

    private function marketplaceVisualDefinitions(): array
    {
        return [
            'side_banner' => [
                'setting_key' => 'marketplace_visual_side_banner',
                'label' => 'Seitlicher Marketplace-Banner',
                'description' => 'Schmaler Hintergrund links und rechts. Bitte ohne Text, Logo oder wichtige Motive am Rand hochladen.',
                'recommended_size' => '192 x 1080 px oder 384 x 2160 px für Retina',
                'default_width' => 192,
                'default_height' => 1080,
                'default' => '/images/marketplace/airmius-marketplace-side-banner.png',
            ],
            'hero_banner' => [
                'setting_key' => 'marketplace_visual_hero_banner',
                'label' => 'Oberer Aktions-/Hero-Banner',
                'description' => 'Optionales Hauptbild im ersten Marketplace-Bereich. Fokus links/mittig halten, da Text darüber liegen kann.',
                'recommended_size' => '1600 x 900 px',
                'default_width' => 1600,
                'default_height' => 900,
                'default' => '',
            ],
            'sale_banner' => [
                'setting_key' => 'marketplace_visual_sale_banner',
                'label' => 'Sale-Kachel / Aktionsbild',
                'description' => 'Optionales Bild für die rechte Sale-Kachel im ersten Marketplace-Bereich.',
                'recommended_size' => '800 x 1000 px',
                'default_width' => 800,
                'default_height' => 1000,
                'default' => '',
            ],
        ];
    }
}
