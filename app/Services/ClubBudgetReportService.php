<?php

namespace App\Services;

use App\Models\Club;
use App\Models\ClubBudget;
use App\Models\ClubFinanceEntry;
use App\Models\ClubProcurementRequest;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\ClubFinanceWorkspaceReadiness;
use Illuminate\Support\Collection;

class ClubBudgetReportService
{
    public function reports(Club $club, Collection $budgets): array
    {
        if ($budgets->isEmpty()) {
            return [];
        }
        $periodIds = $budgets->pluck('club_year_period_id')->unique();
        $periods = $budgets->pluck('yearPeriod')->filter();
        $start = $periods->min('starts_on');
        $end = $periods->max('ends_on');
        $entries = ClubFinanceEntry::where('club_id', $club->id)
            ->whereIn('business_year_period_id', $periodIds)
            ->when(ClubFinanceWorkspaceReadiness::ready(), fn ($q) => $q->where('entry_kind', 'operating'))->get();
        $payments = Payment::where('club_id', $club->id)->where('status', 'paid')
            ->where(fn ($q) => $q->whereBetween('paid_at', [$start?->copy()->startOfDay(), $end?->copy()->endOfDay()])
                ->orWhere(fn ($fallback) => $fallback->whereNull('paid_at')->whereBetween('created_at', [$start, $end?->copy()->endOfDay()])))
            ->with('invoice')->get();
        $invoices = Invoice::where('club_id', $club->id)->whereNotIn('status', ['paid', 'cancelled', 'waived'])
            ->whereIn('business_year_period_id', $periodIds)
            ->withSum('settledPayments', 'amount')->get();
        $procurements = ClubProcurementRequest::where('club_id', $club->id)
            ->whereHas('budget', fn ($q) => $q->whereIn('club_year_period_id', $periodIds))
            ->whereIn('status', ['approved', 'ordered', 'partially_received', 'received'])
            ->with(['budget', 'receipts.financeEntry'])->get();

        return $budgets->mapWithKeys(function (ClubBudget $budget) use ($budgets, $entries, $payments, $invoices, $procurements) {
            $ids = $this->descendantIds($budget, $budgets);
            $period = $budget->yearPeriod;
            $inPeriod = fn ($date) => $date && $period && substr((string) $date, 0, 10) >= $period->starts_on->toDateString()
                && substr((string) $date, 0, 10) <= $period->ends_on->toDateString();
            $scopedEntries = $entries->filter(fn ($entry) => (int) $entry->business_year_period_id === (int) $budget->club_year_period_id && $this->matches($entry, $budget, $ids));
            $income = $scopedEntries->where('type', 'income')->sum(fn ($entry) => $this->cents($entry->amount));
            $expense = $scopedEntries->where('type', 'expense')->sum(fn ($entry) => $this->cents($entry->amount));
            $income += $payments->filter(function ($payment) use ($budget, $ids, $inPeriod) {
                $scope = $payment->club_budget_id || $payment->team_id || $payment->club_department_id || $payment->club_project_id
                    ? $payment : ($payment->invoice ?? $payment);

                return $inPeriod($payment->paid_at ?? $payment->created_at) && $this->matches($scope, $budget, $ids);
            })->sum(fn ($payment) => $this->cents($payment->amount));
            $receivables = $invoices->filter(fn ($invoice) => (int) $invoice->business_year_period_id === (int) $budget->club_year_period_id
                && $this->matches($invoice, $budget, $ids))->sum(fn ($invoice) => $invoice->outstandingCents());
            $reserved = $procurements->filter(fn ($order) => $order->budget
                && (int) $order->budget->club_year_period_id === (int) $budget->club_year_period_id
                && $this->matches($order->budget, $budget, $ids, true))->sum(function ($order) {
                    $total = in_array($order->status, ['approved'], true) ? $order->estimated_total_cents : $order->ordered_total_cents;
                    $paid = $order->receipts->sum(fn ($receipt) => $receipt->financeEntry ? $this->cents($receipt->financeEntry->amount) : 0);

                    return max(0, $total - $paid);
                });

            return [$budget->id => [
                'actual_income_cents' => $income, 'actual_expense_cents' => $expense,
                'open_receivables_cents' => $receivables, 'open_commitments_cents' => $reserved,
                'reserved_expense_cents' => $reserved,
                'remaining_budget_cents' => $budget->planned_expense_cents - $expense - $reserved,
                'liquidity_forecast_cents' => $income - $expense + $receivables - $reserved,
                'budget_variance_cents' => ($income - $expense + $receivables - $reserved)
                    - ($budget->planned_income_cents - $budget->planned_expense_cents),
            ]];
        })->all();
    }

    private function descendantIds(ClubBudget $budget, Collection $budgets): array
    {
        $ids = [$budget->id];
        do {
            $previous = count($ids);
            $ids = array_values(array_unique(array_merge($ids, $budgets->whereIn('parent_id', $ids)->pluck('id')->all())));
        } while (count($ids) > $previous);

        return $ids;
    }

    private function matches($row, ClubBudget $budget, array $ids, bool $isBudget = false): bool
    {
        if (in_array($isBudget ? $row->id : $row->club_budget_id, $ids, true)) {
            return true;
        }

        return match ($budget->scope_type) {
            'club' => true,
            'team' => $budget->team_id && (int) $row->team_id === (int) $budget->team_id,
            'department' => $budget->club_department_id && (int) $row->club_department_id === (int) $budget->club_department_id,
            'project' => $budget->club_project_id && (int) $row->club_project_id === (int) $budget->club_project_id,
            default => false,
        };
    }

    private function cents($amount): int
    {
        return (int) round((float) $amount * 100);
    }
}
