<?php

namespace App\Http\Controllers;

use App\Models\AdCampaign;
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
                ->with(['stats' => fn ($query) => $query->latest('date')->limit(30)])
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
        AdCampaign::create($this->campaignData($request));

        return back()->with('success', 'Kampagne erstellt.');
    }

    public function updateCampaign(Request $request, AdCampaign $campaign)
    {
        $campaign->update($this->campaignData($request));

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
        ]);

        Setting::setValue('commerce_company_country', strtoupper($data['company_country']));
        Setting::setValue('commerce_company_currency', strtoupper($data['company_currency']));
        Setting::setValue('commerce_enable_oss', (bool) ($data['enable_oss'] ?? false));
        Setting::setValue('commerce_export_vat_mode', $data['export_vat_mode']);
        Setting::setValue('commerce_reverse_charge_enabled', (bool) ($data['reverse_charge_enabled'] ?? false));

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
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'image_url' => ['nullable', 'url', 'max:2048'],
            'category' => ['required', 'string', 'max:50'],
            'sku' => ['nullable', 'string', 'max:80'],
            'is_shippable' => ['boolean'],
            'manages_stock' => ['boolean'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'tax_class' => ['nullable', 'string', 'max:30'],
            'return_policy_type' => ['nullable', Rule::in(['standard', 'digital', 'service', 'hygiene', 'custom'])],
            'return_window_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'price_cents' => ['required', 'integer', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'status' => ['required', Rule::in(['draft', 'review', 'published', 'rejected', 'archived'])],
            'rejection_reason' => ['nullable', 'string', 'max:2000'],
            'commission_percent' => ['required', 'integer', 'min:0', 'max:100'],
        ]);
    }

    private function campaignData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'target_url' => ['nullable', 'url', 'max:255'],
            'budget_cents' => ['required', 'integer', 'min:0'],
            'spent_cents' => ['nullable', 'integer', 'min:0'],
            'impressions' => ['nullable', 'integer', 'min:0'],
            'clicks' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', Rule::in(['draft', 'active', 'paused', 'completed'])],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
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
                'default' => '/images/marketplace/airmius-marketplace-side-banner.png',
            ],
            'hero_banner' => [
                'setting_key' => 'marketplace_visual_hero_banner',
                'label' => 'Oberer Aktions-/Hero-Banner',
                'description' => 'Optionales Hauptbild im ersten Marketplace-Bereich. Wenn leer, wird ein Produktbild verwendet.',
                'recommended_size' => '1600 x 900 px',
                'default' => '',
            ],
            'sale_banner' => [
                'setting_key' => 'marketplace_visual_sale_banner',
                'label' => 'Sale-Kachel / Aktionsbild',
                'description' => 'Optionales Bild für die rechte Sale-Kachel im ersten Marketplace-Bereich.',
                'recommended_size' => '800 x 1000 px',
                'default' => '',
            ],
        ];
    }
}
