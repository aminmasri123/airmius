<?php

namespace App\Services;

use App\Models\Club;
use App\Models\ClubFinanceEntry;
use App\Models\ClubSepaSettlement;
use App\Models\ClubYearPeriod;
use App\Models\Payment;
use Illuminate\Support\Facades\Schema;

class ClubFinanceBalanceService
{
    public function summary(Club $club): array
    {
        $period = ClubYearPeriod::where('club_id', $club->id)->where('type', 'business')
            ->whereDate('starts_on', '<=', now())->whereDate('ends_on', '>=', now())->first();
        $start = $period?->starts_on?->startOfDay() ?? now()->startOfYear();
        $end = $period?->ends_on?->endOfDay() ?? now()->endOfYear();
        $payments = Payment::where('club_id', $club->id)->where('status', 'paid')
            ->selectRaw("COALESCE(method, 'manual') as method, SUM(amount) as total")
            ->groupBy('method')->pluck('total', 'method');
        $cash = (float) $payments->get('cash', 0);
        $bank = (float) $payments->only(['bank_transfer', 'bank_import', 'sepa_debit'])->sum();
        $paid = (float) $payments->sum();
        $hasKinds = Schema::hasColumn('club_finance_entries', 'entry_kind');
        $entryQuery = ClubFinanceEntry::where('club_id', $club->id);
        $entries = (clone $entryQuery)->selectRaw('account, type, SUM(amount) as amount')
            ->groupBy('account', 'type')
            ->when($hasKinds, fn ($q) => $q->addSelect('entry_kind')->groupBy('entry_kind'))->get();
        $operating = $entries->filter(fn ($entry) => ($entry->entry_kind ?? 'operating') === 'operating');
        $sum = fn ($rows) => (float) $rows->sum('amount');
        $periodEntries = (clone $entryQuery)->whereBetween('booked_on', [$start->toDateString(), $end->toDateString()])
            ->when($hasKinds, fn ($q) => $q->where('entry_kind', 'operating'))
            ->selectRaw('type, SUM(amount) as amount')->groupBy('type')->get();
        $periodPaid = (float) Payment::where('club_id', $club->id)->whereIn('status', ['paid', 'returned'])
            ->where(fn ($query) => $query->whereBetween('paid_at', [$start, $end])
                ->orWhere(fn ($fallback) => $fallback->whereNull('paid_at')->whereBetween('created_at', [$start, $end])))
            ->sum('amount');
        $returns = ClubSepaSettlement::returnedAmount($club->id);
        $cash += $sum($entries->where('account', 'cash')->where('type', 'income')) - $sum($entries->where('account', 'cash')->where('type', 'expense'));
        $bank += $sum($entries->where('account', 'bank')->where('type', 'income')) - $sum($entries->where('account', 'bank')->where('type', 'expense'));
        $unassigned = max(0, $paid - (float) $payments->get('cash', 0) - (float) $payments->only(['bank_transfer', 'bank_import', 'sepa_debit'])->sum());

        return [
            'cash_balance' => $cash, 'bank_balance' => $bank, 'unassigned_balance' => $unassigned,
            'total_balance' => $cash + $bank + $unassigned,
            'income_total' => $paid + $sum($operating->where('type', 'income')) + $returns,
            'expense_total' => $sum($operating->where('type', 'expense')) + $returns,
            'income_period_total' => $periodPaid + $sum($periodEntries->where('type', 'income')),
            'expense_period_total' => $sum($periodEntries->where('type', 'expense')) + ClubSepaSettlement::returnedAmount($club->id, $start, $end),
            'finance_period' => $period ? 'business' : 'year',
            'finance_period_year' => (int) $start->year,
            'finance_period_label' => $period?->name ?? __('platform.organization.this_year'),
        ];
    }
}
