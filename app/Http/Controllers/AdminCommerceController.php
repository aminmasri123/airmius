<?php

namespace App\Http\Controllers;

use App\Models\AdCampaign;
use App\Models\AdCreative;
use App\Models\CommerceOrder;
use App\Models\CommerceReturnRequest;
use App\Models\CommerceAuditLog;
use App\Models\CommerceStockMovement;
use App\Models\CommerceShippingRate;
use App\Models\CommerceTaxRate;
use App\Models\MarketplacePayout;
use App\Models\MarketplaceProduct;
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
use App\Support\UploadStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
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
                'campaigns' => AdCampaign::query()->count(),
                'orders' => CommerceOrder::query()->count(),
                'commission_cents' => CommerceOrder::query()->where('status', 'completed')->sum('commission_cents'),
                'payout_cents' => CommerceOrder::query()->where('status', 'completed')->sum('amount_cents')
                    - CommerceOrder::query()->where('status', 'completed')->sum('commission_cents'),
                'website_requests' => WebsiteRequest::query()->count(),
            ],
            'coupons' => SubscriptionCoupon::query()->latest('id')->get(),
            'addons' => SubscriptionAddon::query()->withCount('purchases')->latest('id')->get(),
            'products' => MarketplaceProduct::query()->latest('id')->limit(100)->get(),
            'campaigns' => AdCampaign::query()
                ->with(['creatives', 'stats' => fn ($query) => $query->latest('date')->limit(30)])
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
            'orders' => CommerceOrder::query()
                ->with(['user:id,name,email', 'club:id,name', 'orderable', 'items', 'returnRequests'])
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
        MarketplaceProduct::create($this->productData($request));

        return back()->with('success', 'Marketplace-Produkt erstellt.');
    }

    public function updateProduct(Request $request, MarketplaceProduct $product)
    {
        $before = $product->only(['title', 'price_cents', 'status', 'stock_quantity', 'tax_class']);
        $product->update($this->productData($request));
        $this->audit->log('product.updated', $product, $before, $product->fresh()->only(['title', 'price_cents', 'status', 'stock_quantity', 'tax_class']));

        return back()->with('success', 'Marketplace-Produkt aktualisiert.');
    }

    public function adjustProductStock(Request $request, MarketplaceProduct $product)
    {
        $data = $request->validate([
            'quantity_delta' => ['required', 'integer', 'not_in:0'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        abort_unless($product->manages_stock, 422, 'Dieses Produkt verwaltet keinen Lagerbestand.');

        DB::transaction(function () use ($product, $data) {
            $product = MarketplaceProduct::query()->lockForUpdate()->findOrFail($product->id);
            $product->stock_quantity = max(0, (int) $product->stock_quantity + (int) $data['quantity_delta']);
            $product->save();

            CommerceStockMovement::create([
                'marketplace_product_id' => $product->id,
                'type' => 'manual_adjustment',
                'quantity_delta' => (int) $data['quantity_delta'],
                'stock_after' => $product->stock_quantity,
                'note' => $data['note'] ?? null,
            ]);
            $this->audit->log('stock.adjusted', $product, [], [
                'quantity_delta' => (int) $data['quantity_delta'],
                'stock_after' => $product->stock_quantity,
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

        return back()->with('success', 'Bestellproblem wurde aktualisiert.');
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
            }

            if (($data['restock'] ?? false) && $returnRequest->item?->orderable instanceof MarketplaceProduct && $returnRequest->item->orderable->manages_stock) {
                $product = MarketplaceProduct::query()->lockForUpdate()->find($returnRequest->item->orderable_id);

                if ($product) {
                    $product->increment('stock_quantity', (int) $returnRequest->quantity);
                    $product->refresh();

                    CommerceStockMovement::create([
                        'marketplace_product_id' => $product->id,
                        'commerce_order_id' => $returnRequest->commerce_order_id,
                        'commerce_return_request_id' => $returnRequest->id,
                        'type' => 'return_restock',
                        'quantity_delta' => (int) $returnRequest->quantity,
                        'stock_after' => $product->stock_quantity,
                        'note' => 'Ruecksendung #'.$returnRequest->id,
                    ]);
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

        return back()->with('success', 'Ruecksendung wurde aktualisiert.');
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

        $before = $order->only(['shipping_status', 'shipping_carrier', 'tracking_number']);
        $order->update([
            ...$data,
            'shipped_at' => $data['shipping_status'] === 'shipped' && ! $order->shipped_at ? now() : $order->shipped_at,
            'delivered_at' => $data['shipping_status'] === 'delivered' && ! $order->delivered_at ? now() : $order->delivered_at,
        ]);
        $this->audit->log('shipping.updated', $order, $before, $order->fresh()->only(['shipping_status', 'shipping_carrier', 'tracking_number']));

        return back()->with('success', 'Versandstatus wurde aktualisiert.');
    }

    public function refundOrder(Request $request, CommerceOrder $order)
    {
        $data = $request->validate([
            'amount_cents' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        abort_if((int) $data['amount_cents'] > (int) $order->amount_cents, 422, 'Die Erstattung darf die Bestellung nicht uebersteigen.');

        $providerRefundId = $this->refundViaProvider($order, (int) $data['amount_cents']);
        $before = $order->only(['status', 'refunded_cents', 'refund_provider_id']);
        $order->update([
            'status' => (int) $data['amount_cents'] >= (int) $order->amount_cents ? 'refunded' : $order->status,
            'issue_status' => 'refunded',
            'refunded_cents' => (int) $order->refunded_cents + (int) $data['amount_cents'],
            'refund_provider_id' => $providerRefundId ?: $order->refund_provider_id,
            'credit_note_number' => $order->credit_note_number ?: $this->nextDocumentNumber('commerce_credit_note_number_next', 'AIR-GS'),
        ]);
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

        if (($data['status'] ?? null) !== $campaign->status && in_array($data['status'] ?? null, ['active', 'rejected'], true)) {
            $data['reviewed_at'] = now();
        }

        $campaign->update($data);
        $this->syncAdCreatives($campaign, $creativeRows);

        return back()->with('success', 'Kampagne aktualisiert.');
    }

    public function markOrderPaid(CommerceOrder $order)
    {
        app(CommerceCheckoutController::class)->activate($order);

        return back()->with('success', 'Commerce-Bestellung wurde als bezahlt markiert.');
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

        $orders = $this->pendingPayoutOrdersFor($user)->get();
        abort_if($orders->isEmpty(), 422, 'Keine offenen Auszahlungen für diesen Anbieter gefunden.');

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

        return back()->with('success', 'Commerce-Steuerlogik wurde aktualisiert.');
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
            'commission_percent' => ['required', 'integer', 'min:0', 'max:100'],
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
            'audience_age_min' => ['nullable', 'integer', 'min:13', 'max:100'],
            'audience_age_max' => ['nullable', 'integer', 'min:13', 'max:100', 'gte:audience_age_min'],
            'budget_cents' => ['required', 'integer', 'min:'.$minimumBudget],
            'daily_budget_cents' => ['nullable', 'integer', 'min:0'],
            'spent_cents' => ['nullable', 'integer', 'min:0'],
            'impressions' => ['nullable', 'integer', 'min:0'],
            'clicks' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', Rule::in(['draft', 'pending_review', 'active', 'paused', 'completed', 'rejected'])],
            'review_note' => ['nullable', 'string', 'max:2000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        $data['audience'] = [
            'locations' => $this->splitCampaignList($data['audience_locations'] ?? ''),
            'interests' => $this->splitCampaignList($data['audience_interests'] ?? ''),
            'age_min' => $data['audience_age_min'] ?? null,
            'age_max' => $data['audience_age_max'] ?? null,
        ];
        $data['_creative_rows'] = $data['creatives'] ?? [];
        unset($data['audience_locations'], $data['audience_interests'], $data['audience_age_min'], $data['audience_age_max'], $data['creative_image_upload'], $data['creatives']);

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
            'tax_class' => $data['tax_class'] ?: 'standard',
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
            'country_code' => filled($data['country_code'] ?? null) ? strtoupper($data['country_code']) : null,
            'currency' => strtoupper($data['currency']),
            'is_active' => (bool) ($data['is_active'] ?? false),
            'priority' => (int) ($data['priority'] ?? 100),
        ];
    }

    private function pendingPayoutOrdersFor(User $user)
    {
        return CommerceOrder::query()
            ->whereIn('type', ['marketplace_product', 'marketplace_cart'])
            ->where('status', 'completed')
            ->where('payout_status', 'pending')
            ->where(function ($query) use ($user) {
                $query->whereHasMorph('orderable', [MarketplaceProduct::class], fn ($product) => $product->where('user_id', $user->id))
                    ->orWhereHas('items', fn ($items) => $items
                        ->where('orderable_type', MarketplaceProduct::class)
                        ->whereHasMorph('orderable', [MarketplaceProduct::class], fn ($product) => $product->where('user_id', $user->id)));
            });
    }

    private function payoutCandidates(): array
    {
        $orders = CommerceOrder::query()
            ->with(['orderable.user:id,name,email', 'items.orderable.user:id,name,email'])
            ->whereIn('type', ['marketplace_product', 'marketplace_cart'])
            ->where('status', 'completed')
            ->where('payout_status', 'pending')
            ->where(function ($query) {
                $query->whereHasMorph('orderable', [MarketplaceProduct::class], fn ($product) => $product->whereNotNull('user_id'))
                    ->orWhereHas('items', fn ($items) => $items
                        ->where('orderable_type', MarketplaceProduct::class)
                        ->whereHasMorph('orderable', [MarketplaceProduct::class], fn ($product) => $product->whereNotNull('user_id')));
            })
            ->get();

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
            ->with('user:id,name,email')
            ->withSum('stockMovements as stock_delta_sum', 'quantity_delta')
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(fn (MarketplaceProduct $product) => [
                'product_id' => $product->id,
                'title' => $product->title,
                'seller' => $product->user?->name ?: $product->user?->email,
                'stock_quantity' => $product->stock_quantity,
                'manages_stock' => $product->manages_stock,
                'status' => $product->status,
                'low_stock' => $product->manages_stock && (int) $product->low_stock_threshold > 0 && (int) $product->stock_quantity <= (int) $product->low_stock_threshold,
            ])
            ->values()
            ->all();
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

    private function marketplaceVisualsForAdmin(): array
    {
        return collect($this->marketplaceVisualDefinitions())
            ->map(fn (array $definition, string $key) => [
                'key' => $key,
                'label' => $definition['label'],
                'description' => $definition['description'],
                'recommended_size' => $definition['recommended_size'],
                'width' => (int) Setting::valueFor($definition['setting_key'].'_width', $definition['default_width']),
                'height' => (int) Setting::valueFor($definition['setting_key'].'_height', $definition['default_height']),
                'source' => Setting::valueFor($definition['setting_key'], $definition['default']),
                'url' => UploadStorage::url(Setting::valueFor($definition['setting_key'], $definition['default'])),
            ])
            ->values()
            ->all();
    }

    private function marketplaceVisualDefinitions(): array
    {
        return [
            'side_banner' => [
                'setting_key' => 'marketplace_visual_side_banner',
                'label' => 'Seitlicher Marketplace-Banner',
                'description' => 'Wird links und rechts im Marketplace als hoher Seitenbanner verwendet.',
                'recommended_size' => '306 x 786 px oder 768 x 1920 px',
                'default_width' => 306,
                'default_height' => 786,
                'default' => '/images/marketplace/airmius-marketplace-side-banner.png',
            ],
            'hero_banner' => [
                'setting_key' => 'marketplace_visual_hero_banner',
                'label' => 'Oberer Aktions-/Hero-Banner',
                'description' => 'Optionales Hauptbild im ersten Marketplace-Bereich. Wenn leer, wird ein Produktbild verwendet.',
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
