<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\ClubSepaBatchItem;
use App\Models\CommerceOrder;
use App\Models\Invoice;
use App\Models\MarketplaceProduct;
use App\Models\OutfitSubscription;
use App\Models\SubscriptionInvoice;
use App\Models\User;
use App\Notifications\AdminInvoiceCreated;
use App\Notifications\AdminInvoiceStatusUpdated;
use App\Services\ClubNumberRangeService;
use App\Support\AppNotification;
use App\Support\TransactionalMail;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function __construct(private readonly ClubNumberRangeService $numberRanges) {}

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
            'invoiceTypes' => $this->manualInvoiceTypes(),
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
            ->withSum('settledPayments as paid_amount', 'amount')
            ->latest('issued_at')
            ->latest()
            ->get()
            ->map(fn (Invoice $invoice) => [
                'id' => 'membership-'.$invoice->id,
                'raw_id' => $invoice->id,
                'type' => 'membership',
                'type_label' => $this->manualInvoiceTypeLabel($invoice->source),
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
                'status_update_url' => route('invoices.status.update', $invoice),
                'delete_url' => ($invoice->source === null || in_array($invoice->source, $this->deletableInvoiceSources(), true))
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
                'type_label' => __('invoices.types.account_subscription'),
                'number' => $invoice->number,
                'title' => $invoice->title ?: ($invoice->plan?->name ?: __('invoices.fallbacks.account_subscription')),
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
                'status_update_url' => null,
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
                'status_update_url' => null,
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
        $invoicedNumbers = Invoice::query()
            ->where('source', 'outfit_subscription')
            ->pluck('number')
            ->all();

        return OutfitSubscription::query()
            ->with(['user:id,name,email', 'plan:id,name', 'sponsor:id,name'])
            ->latest()
            ->get()
            ->reject(fn (OutfitSubscription $subscription) => in_array($subscription->payment_reference ?: __('invoices.fallbacks.outfit_number', ['id' => $subscription->id]), $invoicedNumbers, true))
            ->map(function (OutfitSubscription $subscription) {
                $amountCents = max(0, (int) $subscription->monthly_price_cents);

                return [
                    'id' => 'outfit-'.$subscription->id,
                    'raw_id' => $subscription->id,
                    'type' => 'outfit',
                    'type_label' => __('invoices.types.outfit_subscription_manual'),
                    'number' => $subscription->payment_reference ?: __('invoices.fallbacks.outfit_number', ['id' => $subscription->id]),
                    'title' => $subscription->plan?->name ?: __('invoices.fallbacks.outfit_subscription'),
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
                    'status_update_url' => null,
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

    private function manualInvoiceTypeLabel(?string $source): string
    {
        $type = in_array($source, [
            'recurring_contribution',
            'account_subscription',
            'outfit_subscription_manual',
            'marketplace_purchase',
            'elearning',
            'ads',
            'agency_website',
            'agency_logo',
            'agency_branding',
            'sponsorship',
            'custom',
            'manual',
        ], true) ? $source : 'club';

        return __('invoices.types.'.$type);
    }

    private function manualInvoiceTypes(): array
    {
        return collect([
            'account_subscription',
            'outfit_subscription_manual',
            'marketplace_purchase',
            'elearning',
            'ads',
            'agency_website',
            'agency_logo',
            'agency_branding',
            'sponsorship',
            'custom',
        ])->map(fn (string $type) => [
            'value' => $type,
            'label' => __("invoices.manual_options.{$type}.label"),
            'hint' => __("invoices.manual_options.{$type}.hint"),
        ])->all();
    }

    private function deletableInvoiceSources(): array
    {
        return array_merge(['manual', 'recurring_contribution'], collect($this->manualInvoiceTypes())->pluck('value')->all());
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

    private function nextManualInvoiceNumber(?int $clubId = null, string $source = 'manual'): string
    {
        $next = Invoice::query()
            ->whereYear('created_at', now()->year)
            ->count() + 1;

        $prefix = match ($source) {
            'account_subscription' => 'KONTO',
            'outfit_subscription_manual' => 'OUTFIT',
            'marketplace_purchase' => 'SHOP',
            'elearning' => 'KURS',
            'ads' => 'ADS',
            'agency_website' => 'WEB',
            'agency_logo' => 'LOGO',
            'agency_branding' => 'BRAND',
            'sponsorship' => 'SPONSOR',
            default => 'MAN',
        };

        $owner = $clubId ? '-'.$clubId : '';

        return $prefix.$owner.'-'.now()->format('Y').'-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
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
            'source' => ['required', Rule::in(collect($this->manualInvoiceTypes())->pluck('value')->all())],
            'club_id' => ['nullable', Rule::exists('clubs', 'id')],
            'user_id' => ['nullable', 'required_without:club_id', Rule::exists('users', 'id')],
            'number' => ['nullable', 'string', 'max:120', Rule::unique('invoices', 'number')],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'status' => ['required', Rule::in(['open', 'pending', 'paid', 'overdue', 'cancelled'])],
            'due_date' => ['required', 'date'],
            'issued_at' => ['nullable', 'date'],
        ]);

        $status = $data['status'];

        $invoice = DB::transaction(function () use ($data, $status, $request) {
            $club = filled($data['club_id'] ?? null) ? Club::query()->findOrFail((int) $data['club_id']) : null;
            $allocation = null;
            if ($club && blank($data['number'] ?? null)) {
                $allocation = $this->numberRanges->allocateDefault(
                    $club,
                    'invoice',
                    $request->user(),
                    (string) Str::uuid(),
                    fn (string $number) => ! Invoice::query()->where('number', $number)->exists()
                );
            }
            $invoice = Invoice::create([
                'club_id' => $data['club_id'] ?? null,
                'user_id' => $data['user_id'] ?? null,
                'number' => $data['number'] ?: ($allocation?->formatted_number
                    ?? $this->nextManualInvoiceNumber($data['club_id'] ? (int) $data['club_id'] : null, $data['source'])),
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'amount' => $data['amount'],
                'status' => $status,
                'source' => $data['source'],
                'due_date' => $data['due_date'],
                'issued_at' => $data['issued_at'] ?? now(),
                'paid_at' => $status === 'paid' ? now() : null,
            ]);
            if ($allocation) {
                $this->numberRanges->assignTo($allocation, 'invoice', $invoice->id);
            }

            return $invoice;
        });

        $this->notifyInvoiceRecipient($invoice);

        return back()->with('success', __('invoices.flash.created'));
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

    public function updateStatus(Request $request, Invoice $invoice)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['open', 'pending', 'paid', 'overdue', 'cancelled'])],
        ]);

        $oldStatus = $invoice->status;

        $invoice->update([
            'status' => $data['status'],
            'paid_at' => $data['status'] === 'paid' ? ($invoice->paid_at ?? now()) : null,
        ]);

        if ($oldStatus !== $invoice->status) {
            $this->notifyInvoiceStatusRecipient($invoice, $oldStatus);
        }

        return back()->with('success', __('invoices.flash.status_updated'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Invoice $invoice)
    {
        if (Schema::hasTable('club_sepa_batch_items')) {
            abort_if(ClubSepaBatchItem::where('invoice_id', $invoice->id)->exists(), 422, __('sepa.reserved'));
        }
        abort_if($invoice->payments()->exists(), 422, __('invoices.errors.payments_exist'));
        abort_if($invoice->source && ! in_array($invoice->source, $this->deletableInvoiceSources(), true), 422, __('invoices.errors.type_not_deletable'));

        $invoice->delete();

        return back()->with('success', __('invoices.flash.deleted'));
    }

    private function notifyInvoiceRecipient(Invoice $invoice): void
    {
        $invoice->loadMissing(['user:id,name,email,language', 'club.owner:id,name,email,language']);

        $recipient = $invoice->user ?: $invoice->club?->owner;

        if (! $recipient) {
            return;
        }

        AppNotification::sendLocalized(
            $recipient,
            'invoice.created',
            'invoices.notifications.created_title',
            'invoices.notifications.created_body',
            [
                'invoice' => $invoice->title ?: AppNotification::translatedReplacement(
                    'invoices.notifications.new_invoice_fallback',
                    'A new invoice',
                ),
                'amount' => $this->moneyFromDecimal($invoice->amount),
            ],
            [
                'url' => route('auth.settings').'#billing',
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->number,
                'invoice_source' => $invoice->source,
            ],
        );

        $this->sendInvoiceEmail($recipient, $invoice);
    }

    private function notifyInvoiceStatusRecipient(Invoice $invoice, ?string $oldStatus): void
    {
        $invoice->loadMissing(['user:id,name,email,language', 'club.owner:id,name,email,language']);

        $recipient = $invoice->user ?: $invoice->club?->owner;

        if (! $recipient) {
            return;
        }

        $status = in_array($invoice->status, ['paid', 'open', 'pending', 'awaiting_transfer', 'overdue', 'cancelled', 'failed'], true)
            ? $invoice->status
            : 'unknown';

        AppNotification::sendLocalized(
            $recipient,
            'invoice.status_updated',
            'invoices.notifications.status_updated_title',
            'invoices.notifications.status_updated_body',
            [
                'invoice' => $invoice->number ?: ($invoice->title ?: AppNotification::translatedReplacement(
                    'invoices.notifications.invoice_fallback',
                    'Your invoice',
                )),
                'status' => AppNotification::translatedReplacement(
                    'invoices.status.'.$status,
                    (string) $invoice->status,
                ),
            ],
            [
                'url' => route('auth.settings').'#billing',
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->number,
                'old_status' => $oldStatus,
                'new_status' => $invoice->status,
            ],
        );

        $this->sendInvoiceStatusEmail($recipient, $invoice, $oldStatus);
    }

    private function sendInvoiceEmail(User $recipient, Invoice $invoice): void
    {
        $mailer = app(TransactionalMail::class);

        $mailer->notifyWithFallback(
            $recipient,
            fn (array $transport) => new AdminInvoiceCreated(
                $invoice,
                $transport['mailer'],
                $transport['address'],
                $transport['name'],
            ),
            $mailer->invoicePrimaryCategory(),
            $mailer->invoiceFallbackCategory(),
            'invoice.created:'.$invoice->id.':'.$recipient->id,
            (int) config('airmius_mail.throttle_seconds.invoice_created', 21600),
            [
                'mail_type' => 'invoice.created',
                'invoice_id' => $invoice->id,
                'recipient_id' => $recipient->id,
            ],
        );
    }

    private function sendInvoiceStatusEmail(User $recipient, Invoice $invoice, ?string $oldStatus): void
    {
        $mailer = app(TransactionalMail::class);

        $mailer->notifyWithFallback(
            $recipient,
            fn (array $transport) => new AdminInvoiceStatusUpdated(
                $invoice,
                $oldStatus,
                $transport['mailer'],
                $transport['address'],
                $transport['name'],
            ),
            $mailer->invoicePrimaryCategory(),
            $mailer->invoiceFallbackCategory(),
            'invoice.status_updated:'.$invoice->id.':'.$recipient->id.':'.$invoice->status,
            (int) config('airmius_mail.throttle_seconds.invoice_status_updated', 3600),
            [
                'mail_type' => 'invoice.status_updated',
                'invoice_id' => $invoice->id,
                'recipient_id' => $recipient->id,
                $oldStatus,
                'new_status' => $invoice->status,
            ],
        );
    }
}
