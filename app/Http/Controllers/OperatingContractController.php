<?php

namespace App\Http\Controllers;

use App\Models\OperatingContract;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OperatingContractController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeView($request);

        $filters = $request->validate([
            'status' => ['nullable', Rule::in(array_merge(['all'], OperatingContract::STATUSES))],
            'category' => ['nullable', Rule::in(array_merge(['all'], OperatingContract::CATEGORIES))],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $status = $filters['status'] ?? 'active';
        $category = $filters['category'] ?? 'all';
        $search = trim((string) ($filters['q'] ?? ''));

        $query = OperatingContract::query()
            ->with('owner:id,name,email')
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->when($category !== 'all', fn ($query) => $query->where('category', $category))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('vendor', 'like', "%{$search}%")
                        ->orWhere('contract_number', 'like', "%{$search}%")
                        ->orWhere('account_reference', 'like', "%{$search}%");
                });
            });

        return Inertia::render('Auth/Dashboard/Admin/OperatingContracts/Index', [
            'contracts' => $query
                ->orderByRaw("case status when 'active' then 0 when 'paused' then 1 when 'cancelled' then 2 else 3 end")
                ->orderByRaw('next_due_on is null')
                ->orderBy('next_due_on')
                ->latest()
                ->paginate(20)
                ->withQueryString()
                ->through(fn (OperatingContract $contract) => $this->contractPayload($contract, $request)),
            'summary' => $this->summary(),
            'filters' => [
                'status' => $status,
                'category' => $category,
                'q' => $search,
            ],
            'options' => [
                'statuses' => $this->statusOptions(),
                'categories' => $this->categoryOptions(),
                'billingIntervals' => $this->billingIntervalOptions(),
                'paymentMethods' => $this->paymentMethodOptions(),
            ],
            'owners' => User::query()
                ->select('id', 'name', 'email')
                ->orderBy('name')
                ->limit(250)
                ->get(),
            'canManage' => $this->canManage($request),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeManage($request);

        $data = $this->normalizedData($request, $this->validated($request));
        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;

        OperatingContract::create($data);

        return back()->with('success', 'Vertrag wurde angelegt.');
    }

    public function update(Request $request, OperatingContract $operatingContract)
    {
        $this->authorizeManage($request);

        $data = $this->normalizedData($request, $this->validated($request));
        $data['updated_by'] = $request->user()->id;

        $operatingContract->update($data);

        return back()->with('success', 'Vertrag wurde aktualisiert.');
    }

    public function destroy(Request $request, OperatingContract $operatingContract)
    {
        $this->authorizeManage($request);

        $operatingContract->delete();

        return back()->with('success', 'Vertrag wurde gelöscht.');
    }

    private function authorizeView(Request $request): void
    {
        abort_unless(
            $request->user()?->can('finance.view')
                || $request->user()?->can('finance.edit')
                || $request->user()?->can('billing.manage')
                || $request->user()?->can('system.manage'),
            403,
        );
    }

    private function authorizeManage(Request $request): void
    {
        abort_unless($this->canManage($request), 403);
    }

    private function canManage(Request $request): bool
    {
        return (bool) (
            $request->user()?->can('finance.edit')
                || $request->user()?->can('system.manage')
        );
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'owner_user_id' => ['nullable', 'exists:users,id'],
            'name' => ['required', 'string', 'max:255'],
            'vendor' => ['nullable', 'string', 'max:255'],
            'category' => ['required', Rule::in(OperatingContract::CATEGORIES)],
            'status' => ['required', Rule::in(OperatingContract::STATUSES)],
            'amount' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'currency' => ['nullable', 'string', 'size:3'],
            'billing_interval' => ['required', Rule::in(OperatingContract::BILLING_INTERVALS)],
            'payment_method' => ['nullable', Rule::in(OperatingContract::PAYMENT_METHODS)],
            'next_due_on' => ['nullable', 'date'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'notice_until_on' => ['nullable', 'date'],
            'cancellation_period_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'auto_renews' => ['boolean'],
            'contract_number' => ['nullable', 'string', 'max:255'],
            'account_reference' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'document_url' => ['nullable', 'string', 'max:2048'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    private function normalizedData(Request $request, array $data): array
    {
        foreach ([
            'owner_user_id',
            'vendor',
            'payment_method',
            'next_due_on',
            'starts_on',
            'ends_on',
            'notice_until_on',
            'cancellation_period_days',
            'contract_number',
            'account_reference',
            'contact_email',
            'website',
            'document_url',
            'notes',
        ] as $key) {
            if (($data[$key] ?? null) === '') {
                $data[$key] = null;
            }
        }

        $data['currency'] = strtoupper($data['currency'] ?? 'EUR');
        $data['auto_renews'] = $request->boolean('auto_renews');

        if (blank($data['notice_until_on'] ?? null)
            && filled($data['ends_on'] ?? null)
            && filled($data['cancellation_period_days'] ?? null)) {
            $data['notice_until_on'] = Carbon::parse($data['ends_on'])
                ->subDays((int) $data['cancellation_period_days'])
                ->toDateString();
        }

        return $data;
    }

    private function contractPayload(OperatingContract $contract, Request $request): array
    {
        return [
            'id' => $contract->id,
            'owner_user_id' => $contract->owner_user_id,
            'name' => $contract->name,
            'vendor' => $contract->vendor,
            'category' => $contract->category,
            'category_label' => $this->categoryLabel($contract->category),
            'status' => $contract->status,
            'status_label' => $this->statusLabel($contract->status),
            'amount' => $this->money((float) $contract->amount, $contract->currency),
            'raw_amount' => (float) $contract->amount,
            'currency' => $contract->currency,
            'billing_interval' => $contract->billing_interval,
            'billing_interval_label' => $this->billingIntervalLabel($contract->billing_interval),
            'monthly_amount' => $this->money($contract->monthlyEquivalent(), $contract->currency),
            'raw_monthly_amount' => round($contract->monthlyEquivalent(), 2),
            'yearly_amount' => $this->money($contract->yearlyEquivalent(), $contract->currency),
            'raw_yearly_amount' => round($contract->yearlyEquivalent(), 2),
            'payment_method' => $contract->payment_method,
            'payment_method_label' => $this->paymentMethodLabel($contract->payment_method),
            'next_due_on' => $contract->next_due_on?->toDateString(),
            'starts_on' => $contract->starts_on?->toDateString(),
            'ends_on' => $contract->ends_on?->toDateString(),
            'notice_until_on' => $contract->notice_until_on?->toDateString(),
            'days_until_next_due' => $this->daysUntil($contract->next_due_on),
            'days_until_notice' => $this->daysUntil($contract->notice_until_on),
            'cancellation_period_days' => $contract->cancellation_period_days,
            'auto_renews' => $contract->auto_renews,
            'contract_number' => $contract->contract_number,
            'account_reference' => $contract->account_reference,
            'contact_email' => $contract->contact_email,
            'website' => $contract->website,
            'document_url' => $contract->document_url,
            'notes' => $contract->notes,
            'owner' => $contract->owner ? [
                'id' => $contract->owner->id,
                'name' => $contract->owner->name,
                'email' => $contract->owner->email,
            ] : null,
            'update_url' => $this->canManage($request) ? route('admin.operating-contracts.update', $contract) : null,
            'delete_url' => $this->canManage($request) ? route('admin.operating-contracts.destroy', $contract) : null,
        ];
    }

    private function summary(): array
    {
        $active = OperatingContract::query()
            ->where('status', 'active')
            ->get();

        $monthlyTotal = $active->sum(fn (OperatingContract $contract) => $contract->monthlyEquivalent());
        $yearlyTotal = $monthlyTotal * 12;
        $monthlyTotals = $this->totalsByCurrency($active, fn (OperatingContract $contract) => $contract->monthlyEquivalent());
        $yearlyTotals = $this->totalsByCurrency($active, fn (OperatingContract $contract) => $contract->yearlyEquivalent());

        $dueSoon = $active
            ->filter(fn (OperatingContract $contract) => $this->isWithinDays($contract->next_due_on, 30))
            ->count();

        $noticeSoon = $active
            ->filter(fn (OperatingContract $contract) => $this->isWithinDays($contract->notice_until_on, 60))
            ->count();

        $upcoming = $active
            ->filter(fn (OperatingContract $contract) => $contract->next_due_on || $contract->notice_until_on)
            ->map(function (OperatingContract $contract) {
                $nextDueDays = $this->daysUntil($contract->next_due_on);
                $noticeDays = $this->daysUntil($contract->notice_until_on);
                $nextValue = $nextDueDays ?? PHP_INT_MAX;
                $noticeValue = $noticeDays ?? PHP_INT_MAX;
                $isNotice = $noticeValue <= $nextValue;

                return [
                    'id' => $contract->id,
                    'name' => $contract->name,
                    'vendor' => $contract->vendor,
                    'type' => $isNotice ? 'notice' : 'payment',
                    'label' => $isNotice ? 'Kündigungsfrist' : 'Nächste Zahlung',
                    'date' => $isNotice ? $contract->notice_until_on?->toDateString() : $contract->next_due_on?->toDateString(),
                    'days' => $isNotice ? $noticeDays : $nextDueDays,
                    'sort' => min($nextValue, $noticeValue),
                    'amount' => $this->money((float) $contract->amount, $contract->currency),
                ];
            })
            ->sortBy('sort')
            ->take(8)
            ->values();

        return [
            'active_count' => $active->count(),
            'monthly_total' => $this->formattedTotals($monthlyTotals),
            'raw_monthly_total' => count($monthlyTotals) === 1 ? $monthlyTotals[0]['amount'] : null,
            'monthly_totals' => $monthlyTotals,
            'yearly_total' => $this->formattedTotals($yearlyTotals),
            'raw_yearly_total' => count($yearlyTotals) === 1 ? $yearlyTotals[0]['amount'] : null,
            'yearly_totals' => $yearlyTotals,
            'due_soon_count' => $dueSoon,
            'notice_soon_count' => $noticeSoon,
            'upcoming' => $upcoming,
            'categories' => $active
                ->groupBy('category')
                ->map(function ($items, $category) {
                    $monthlyTotals = $this->totalsByCurrency($items, fn (OperatingContract $contract) => $contract->monthlyEquivalent());

                    return [
                        'category' => $category,
                        'label' => $this->categoryLabel($category),
                        'count' => $items->count(),
                        'monthly_total' => $this->formattedTotals($monthlyTotals),
                        'monthly_totals' => $monthlyTotals,
                        'raw_monthly_total' => count($monthlyTotals) === 1 ? $monthlyTotals[0]['amount'] : null,
                    ];
                })
                ->sortByDesc('raw_monthly_total')
                ->values(),
        ];
    }

    private function isWithinDays($date, int $days): bool
    {
        if (! $date) {
            return false;
        }

        $daysUntil = $this->daysUntil($date);

        return $daysUntil !== null && $daysUntil <= $days;
    }

    private function daysUntil($date): ?int
    {
        if (! $date) {
            return null;
        }

        return (int) today()->diffInDays($date, false);
    }

    private function money(float $amount, string $currency = 'EUR'): string
    {
        return number_format($amount, 2, ',', '.').' '.$currency;
    }

    private function totalsByCurrency($contracts, callable $amount): array
    {
        return collect($contracts)
            ->groupBy(fn (OperatingContract $contract) => strtoupper((string) ($contract->currency ?: 'EUR')))
            ->map(fn ($items, $currency) => [
                'currency' => $currency,
                'amount' => round($items->sum($amount), 2),
            ])
            ->values()
            ->all();
    }

    private function formattedTotals(array $totals): string
    {
        return collect($totals)
            ->map(fn (array $total) => $this->money((float) $total['amount'], $total['currency']))
            ->implode(' · ');
    }

    private function statusOptions(): array
    {
        return collect(OperatingContract::STATUSES)
            ->map(fn (string $status) => ['value' => $status, 'label' => $this->statusLabel($status)])
            ->all();
    }

    private function categoryOptions(): array
    {
        return collect(OperatingContract::CATEGORIES)
            ->map(fn (string $category) => ['value' => $category, 'label' => $this->categoryLabel($category)])
            ->all();
    }

    private function billingIntervalOptions(): array
    {
        return collect(OperatingContract::BILLING_INTERVALS)
            ->map(fn (string $interval) => ['value' => $interval, 'label' => $this->billingIntervalLabel($interval)])
            ->all();
    }

    private function paymentMethodOptions(): array
    {
        return collect(OperatingContract::PAYMENT_METHODS)
            ->map(fn (string $method) => ['value' => $method, 'label' => $this->paymentMethodLabel($method)])
            ->all();
    }

    private function statusLabel(?string $status): string
    {
        return [
            'active' => 'Aktiv',
            'paused' => 'Pausiert',
            'cancelled' => 'Gekuendigt',
            'ended' => 'Beendet',
        ][$status] ?? ($status ?: '-');
    }

    private function categoryLabel(?string $category): string
    {
        return [
            'telecom' => 'WLAN & Internet',
            'mobile' => 'Handy & Mobilfunk',
            'leasing' => 'Leasing',
            'software' => 'Software',
            'hosting' => 'Hosting & Domains',
            'insurance' => 'Versicherung',
            'office' => 'Buero & Standort',
            'marketing' => 'Marketing',
            'finance' => 'Finanzen & Steuer',
            'service' => 'Dienstleister',
            'other' => 'Sonstiges',
        ][$category] ?? ($category ?: 'Sonstiges');
    }

    private function billingIntervalLabel(?string $interval): string
    {
        return [
            'weekly' => 'Woechentlich',
            'monthly' => 'Monatlich',
            'quarterly' => 'Quartalsweise',
            'yearly' => 'Jaehrlich',
            'one_time' => 'Einmalig',
        ][$interval] ?? ($interval ?: '-');
    }

    private function paymentMethodLabel(?string $method): string
    {
        return [
            'direct_debit' => 'Lastschrift',
            'bank_transfer' => 'Überweisung',
            'card' => 'Karte',
            'paypal' => 'PayPal',
            'invoice' => 'Rechnung',
            'cash' => 'Bar',
            'other' => 'Sonstiges',
        ][$method] ?? ($method ?: '-');
    }
}
