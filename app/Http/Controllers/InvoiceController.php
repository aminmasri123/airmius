<?php

namespace App\Http\Controllers;

use App\Models\CommerceOrder;
use App\Models\Club;
use App\Models\Invoice;
use App\Models\MarketplaceProduct;
use App\Models\OutfitSubscription;
use App\Models\SubscriptionInvoice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $records = collect()
            ->merge($this->membershipInvoices())
            ->merge($this->subscriptionInvoices())
            ->merge($this->commerceInvoices())
            ->merge($this->outfitInvoices())
            ->sortByDesc(fn (array $invoice) => $invoice['sort_date'] ?? '')
            ->values();

        $page = LengthAwarePaginator::resolveCurrentPage();
        $perPage = 25;
        $invoices = new LengthAwarePaginator(
            $records->forPage($page, $perPage)->values(),
            $records->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ],
        );

        $summary = [
            'count' => $records->count(),
            'open' => $records->whereIn('status', ['open', 'pending', 'awaiting_transfer', 'overdue'])->count(),
            'paid' => $records->where('status', 'paid')->count(),
            'overdue' => $records->where('status', 'overdue')->count(),
            'revenue' => $this->money($records->where('status', 'paid')->sum('amount_cents')),
        ];

        return Inertia::render('Auth/Dashboard/Admin/Invoices/Index', [
            'invoices' => $invoices,
            'summary' => $summary,
            'clubs' => Club::query()
                ->select('id', 'name')
                ->orderBy('name')
                ->limit(250)
                ->get(),
            'users' => User::query()
                ->select('id', 'name', 'email')
                ->orderBy('name')
                ->limit(250)
                ->get(),
        ]);
    }

    private function membershipInvoices()
    {
        return Invoice::query()
            ->with(['club:id,name', 'user:id,name,email'])
            ->withSum('payments as paid_amount', 'amount')
            ->latest('issued_at')
            ->latest()
            ->get()
            ->map(fn (Invoice $invoice) => [
                'id' => 'membership-'.$invoice->id,
                'raw_id' => $invoice->id,
                'type' => 'membership',
                'type_label' => $this->membershipTypeLabel($invoice->source),
                'number' => $invoice->number,
                'title' => $invoice->title,
                'description' => $invoice->description,
                'amount' => $this->moneyFromDecimal($invoice->amount),
                'amount_cents' => $this->centsFromDecimal($invoice->amount),
                'paid_amount' => $this->moneyFromDecimal($invoice->paid_amount ?? 0),
                'status' => $invoice->status,
                'source' => $invoice->source,
                'due_date' => $invoice->due_date?->format('Y-m-d'),
                'issued_at' => $invoice->issued_at?->format('Y-m-d'),
                'paid_at' => $invoice->paid_at?->format('Y-m-d H:i'),
                'sort_date' => optional($invoice->issued_at ?: $invoice->created_at)->toIso8601String(),
                'download_url' => null,
                'delete_url' => ($invoice->source === null || in_array($invoice->source, ['manual', 'recurring_contribution'], true))
                    && ! (float) ($invoice->paid_amount ?? 0)
                        ? route('invoices.destroy', $invoice)
                        : null,
                'club' => $invoice->club ? [
                    'id' => $invoice->club->id,
                    'name' => $invoice->club->name,
                ] : null,
                'user' => $invoice->user ? [
                    'id' => $invoice->user->id,
                    'name' => $invoice->user->name,
                    'email' => $invoice->user->email,
                ] : null,
            ]);
    }

    private function subscriptionInvoices()
    {
        return SubscriptionInvoice::query()
            ->with(['user:id,name,email', 'club:id,name', 'plan:id,name'])
            ->latest('issued_at')
            ->latest()
            ->get()
            ->map(fn (SubscriptionInvoice $invoice) => [
                'id' => 'subscription-'.$invoice->id,
                'raw_id' => $invoice->id,
                'type' => 'subscription',
                'type_label' => 'Konto-Abo',
                'number' => $invoice->number,
                'title' => $invoice->title ?: ($invoice->plan?->name ?: 'Airmius Abo'),
                'description' => $invoice->description,
                'amount' => $this->money($invoice->amount_cents, $invoice->currency),
                'amount_cents' => (int) $invoice->amount_cents,
                'paid_amount' => $invoice->status === 'paid' ? $this->money($invoice->amount_cents, $invoice->currency) : $this->money(0, $invoice->currency),
                'status' => $invoice->status,
                'source' => 'subscription_invoice',
                'due_date' => $invoice->due_at?->format('Y-m-d'),
                'issued_at' => $invoice->issued_at?->format('Y-m-d'),
                'paid_at' => $invoice->paid_at?->format('Y-m-d H:i'),
                'sort_date' => optional($invoice->issued_at ?: $invoice->created_at)->toIso8601String(),
                'download_url' => route('admin.subscription-invoices.download', $invoice),
                'club' => $invoice->club ? [
                    'id' => $invoice->club->id,
                    'name' => $invoice->club->name,
                ] : null,
                'user' => $invoice->user ? [
                    'id' => $invoice->user->id,
                    'name' => $invoice->user->name,
                    'email' => $invoice->user->email,
                ] : null,
            ]);
    }

    private function commerceInvoices()
    {
        return CommerceOrder::query()
            ->with(['user:id,name,email', 'club:id,name', 'orderable', 'items.orderable'])
            ->latest('completed_at')
            ->latest()
            ->get()
            ->map(fn (CommerceOrder $order) => [
                'id' => 'commerce-'.$order->id,
                'raw_id' => $order->id,
                'type' => 'commerce',
                'type_label' => $this->commerceTypeLabel($order),
                'number' => $order->invoice_number ?: 'Bestellung #'.$order->id,
                'title' => $this->commerceTitle($order),
                'description' => $order->payment_reference,
                'amount' => $this->money($order->amount_cents, $order->currency),
                'amount_cents' => (int) $order->amount_cents,
                'paid_amount' => $order->status === 'completed' ? $this->money($order->amount_cents, $order->currency) : $this->money(0, $order->currency),
                'status' => $this->commerceStatus($order->status),
                'source' => $order->type,
                'due_date' => $order->due_at?->format('Y-m-d'),
                'issued_at' => $order->created_at?->format('Y-m-d'),
                'paid_at' => $order->completed_at?->format('Y-m-d H:i'),
                'sort_date' => optional($order->completed_at ?: $order->created_at)->toIso8601String(),
                'download_url' => $order->invoice_number ? route('admin.commerce.orders.invoice', $order) : null,
                'club' => $order->club ? [
                    'id' => $order->club->id,
                    'name' => $order->club->name,
                ] : null,
                'user' => $order->user ? [
                    'id' => $order->user->id,
                    'name' => $order->user->name,
                    'email' => $order->user->email,
                ] : [
                    'id' => null,
                    'name' => $order->guest_name,
                    'email' => $order->guest_email,
                ],
            ]);
    }

    private function outfitInvoices()
    {
        return OutfitSubscription::query()
            ->with(['user:id,name,email', 'plan:id,name', 'sponsor:id,name'])
            ->latest()
            ->get()
            ->map(function (OutfitSubscription $subscription) {
                $amountCents = max(0, (int) $subscription->monthly_price_cents - (int) $subscription->sponsor_discount_cents);

                return [
                    'id' => 'outfit-'.$subscription->id,
                    'raw_id' => $subscription->id,
                    'type' => 'outfit',
                    'type_label' => 'Outfit-Abo',
                    'number' => $subscription->payment_reference ?: 'Outfit-Abo #'.$subscription->id,
                    'title' => $subscription->plan?->name ?: 'Outfit-Abo',
                    'description' => $subscription->sponsor?->name ? 'Sponsor: '.$subscription->sponsor->name : null,
                    'amount' => $this->money($amountCents, $subscription->currency),
                    'amount_cents' => $amountCents,
                    'paid_amount' => $subscription->payment_status === 'paid' ? $this->money($amountCents, $subscription->currency) : $this->money(0, $subscription->currency),
                    'status' => $this->outfitStatus($subscription->payment_status),
                    'source' => 'outfit_subscription',
                    'due_date' => $subscription->payment_due_at?->format('Y-m-d'),
                    'issued_at' => $subscription->created_at?->format('Y-m-d'),
                    'paid_at' => $subscription->payment_status === 'paid' ? $subscription->updated_at?->format('Y-m-d H:i') : null,
                    'sort_date' => optional($subscription->payment_due_at ?: $subscription->created_at)->toIso8601String(),
                    'download_url' => null,
                    'club' => null,
                    'user' => $subscription->user ? [
                        'id' => $subscription->user->id,
                        'name' => $subscription->user->name,
                        'email' => $subscription->user->email,
                    ] : null,
                ];
            });
    }

    private function commerceTypeLabel(CommerceOrder $order): string
    {
        if ($order->type === 'ads_campaign') {
            return 'ADS';
        }

        if ($order->type === 'addon') {
            return 'Add-on';
        }

        if ($this->commerceOrderContainsCourse($order)) {
            return 'E-Learning';
        }

        return 'Marketplace';
    }

    private function commerceOrderContainsCourse(CommerceOrder $order): bool
    {
        $products = collect([$order->orderable])
            ->merge($order->items->pluck('orderable'))
            ->filter(fn ($item) => $item instanceof MarketplaceProduct);

        return $products->contains(fn (MarketplaceProduct $product) => $product->category === 'course'
            || in_array($product->offer_type, ['online_course', 'training_plan'], true));
    }

    private function commerceTitle(CommerceOrder $order): string
    {
        $firstItem = $order->items->first();

        if ($firstItem?->title) {
            return $order->items->count() > 1
                ? $firstItem->title.' + '.($order->items->count() - 1).' weitere'
                : $firstItem->title;
        }

        return match ($order->type) {
            'ads_campaign' => data_get($order->payload, 'campaign_name', 'ADS Kampagne'),
            'addon' => 'Airmius Add-on',
            'marketplace_cart' => 'Marketplace Warenkorb',
            'marketplace_product' => 'Marketplace Bestellung',
            default => 'Bestellung #'.$order->id,
        };
    }

    private function membershipTypeLabel(?string $source): string
    {
        return match ($source) {
            'recurring_contribution' => 'Mitgliedsbeitrag',
            'manual' => 'Manuelle Rechnung',
            default => 'Vereinsrechnung',
        };
    }

    private function commerceStatus(?string $status): string
    {
        return match ($status) {
            'completed' => 'paid',
            'awaiting_transfer' => 'awaiting_transfer',
            'refunded' => 'cancelled',
            default => $status ?: 'pending',
        };
    }

    private function outfitStatus(?string $status): string
    {
        return match ($status) {
            'paid', 'active' => 'paid',
            'failed' => 'failed',
            'cancelled' => 'cancelled',
            default => $status ?: 'pending',
        };
    }

    private function centsFromDecimal(mixed $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    private function moneyFromDecimal(mixed $amount, string $currency = 'EUR'): string
    {
        return $this->money($this->centsFromDecimal($amount), $currency);
    }

    private function money(mixed $cents, string $currency = 'EUR'): string
    {
        return number_format(((int) $cents) / 100, 2, ',', '.').' '.$currency;
    }

    private function nextManualInvoiceNumber(int $clubId): string
    {
        $next = Invoice::query()
            ->where('club_id', $clubId)
            ->whereYear('created_at', now()->year)
            ->count() + 1;

        return 'MAN-'.$clubId.'-'.now()->format('Y').'-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'club_id' => ['required', Rule::exists('clubs', 'id')],
            'user_id' => ['nullable', Rule::exists('users', 'id')],
            'number' => ['nullable', 'string', 'max:120', Rule::unique('invoices', 'number')],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'status' => ['required', Rule::in(['open', 'pending', 'paid', 'overdue', 'cancelled'])],
            'due_date' => ['required', 'date'],
            'issued_at' => ['nullable', 'date'],
        ]);

        $status = $data['status'];

        Invoice::create([
            'club_id' => $data['club_id'],
            'user_id' => $data['user_id'] ?? null,
            'number' => $data['number'] ?: $this->nextManualInvoiceNumber((int) $data['club_id']),
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'amount' => $data['amount'],
            'status' => $status,
            'source' => 'manual',
            'due_date' => $data['due_date'],
            'issued_at' => $data['issued_at'] ?? now(),
            'paid_at' => $status === 'paid' ? now() : null,
        ]);

        return back()->with('success', 'Rechnung wurde erstellt.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Invoice $invoice)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Invoice $invoice)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Invoice $invoice)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Invoice $invoice)
    {
        abort_if($invoice->payments()->exists(), 422, 'Rechnungen mit Zahlungen koennen nicht geloescht werden.');
        abort_if($invoice->source && ! in_array($invoice->source, ['manual', 'recurring_contribution'], true), 422, 'Diese Rechnungsart kann hier nicht geloescht werden.');

        $invoice->delete();

        return back()->with('success', 'Rechnung wurde geloescht.');
    }
}
