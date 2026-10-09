<?php

namespace App\Services;

use App\Models\Club;
use App\Models\ClubFinanceEntry;
use App\Models\ClubMoneyAccount;
use App\Models\ClubSepaSettlement;
use App\Models\ClubYearPeriod;
use App\Models\Payment;
use App\Support\ClubAuditLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ClubFinanceYearCloseService
{
    public function available(): bool
    {
        return Schema::hasColumn('club_year_periods', 'finance_closed_at');
    }

    public function assertOpen(int $clubId, mixed $date): void
    {
        if (! $date || ! $this->available()) {
            return;
        }
        if (ClubYearPeriod::where('club_id', $clubId)->where('type', 'business')
            ->whereNotNull('finance_closed_at')->whereDate('ends_on', '>=', Carbon::parse($date)->toDateString())->exists()) {
            throw ValidationException::withMessages(['finance_year' => 'Das Geschäftsjahr ist abgeschlossen. Korrekturen bitte im offenen Jahr buchen.']);
        }
    }

    public function assertPeriodOpen(int $clubId, ?int $periodId): void
    {
        if ($periodId && $this->available() && ClubYearPeriod::where('club_id', $clubId)->whereKey($periodId)->whereNotNull('finance_closed_at')->exists()) {
            throw ValidationException::withMessages(['finance_year' => 'Das zugeordnete Geschäftsjahr ist abgeschlossen.']);
        }
    }

    public function close(Club $club, ClubYearPeriod $period, ClubYearPeriod $next, $actor): ClubYearPeriod
    {
        abort_unless($this->available(), 503);

        return DB::transaction(function () use ($club, $period, $next, $actor) {
            Club::whereKey($club->id)->lockForUpdate()->firstOrFail();
            $period = ClubYearPeriod::whereKey($period->id)->lockForUpdate()->firstOrFail();
            $next = ClubYearPeriod::whereKey($next->id)->lockForUpdate()->firstOrFail();
            abort_unless($period->club_id === $club->id && $next->club_id === $club->id && $period->type === 'business' && $next->type === 'business', 422);
            if ($period->finance_closed_at) {
                abort_unless((int) $period->finance_next_period_id === (int) $next->id, 422);

                return $period;
            }
            abort_unless($period->ends_on->lt(today()) && $next->starts_on->equalTo($period->ends_on->copy()->addDay()) && ! $next->finance_closed_at, 422);
            abort_if(ClubYearPeriod::where('club_id', $club->id)->where('type', 'business')
                ->where('ends_on', '<', $period->starts_on)->whereNull('finance_closed_at')->exists(), 422, 'Bitte zuerst frühere Geschäftsjahre abschließen.');
            $report = app(ClubYearPeriodReportService::class)->report($club, 'business', $period);
            $entries = ClubFinanceEntry::where('club_id', $club->id)->whereDate('booked_on', '<=', $period->ends_on)->get();
            $payments = Payment::where('club_id', $club->id)->whereIn('status', ['paid', 'returned'])
                ->whereRaw('DATE(COALESCE(paid_at, created_at)) <= ?', [$period->ends_on->toDateString()])->get();
            $returns = ClubSepaSettlement::where('club_id', $club->id)->where('status', 'returned')
                ->whereDate('returned_on', '<=', $period->ends_on)->whereIn('payment_id', $payments->pluck('id'))->get()->keyBy('payment_id');
            $cash = 0;
            $bank = 0;
            $unassigned = 0;
            $accounts = ClubMoneyAccount::where('club_id', $club->id)->get()->mapWithKeys(fn ($a) => [$a->id => ['id' => $a->id, 'name' => $a->name, 'type' => $a->type, 'balance_cents' => 0]])->all();
            foreach ($entries as $entry) {
                $amount = (int) round((float) $entry->amount * 100) * ($entry->type === 'income' ? 1 : -1);
                if ($entry->account === 'cash') {
                    $cash += $amount;
                } else {
                    $bank += $amount;
                }
                if (isset($accounts[$entry->club_money_account_id])) {
                    $accounts[$entry->club_money_account_id]['balance_cents'] += $amount;
                }
            }
            foreach ($payments as $payment) {
                $amount = isset($returns[$payment->id]) ? 0 : (int) round((float) $payment->amount * 100);
                if ($payment->method === 'cash') {
                    $cash += $amount;
                } elseif (in_array($payment->method, ['bank_transfer', 'bank_import', 'sepa_debit'], true)) {
                    $bank += $amount;
                } else {
                    $unassigned += $amount;
                }
                if (isset($accounts[$payment->club_money_account_id])) {
                    $accounts[$payment->club_money_account_id]['balance_cents'] += $amount;
                }
            }
            // Balances already accumulate across years. Store the carry-forward, do
            // not create another opening entry that would count the same money twice.
            $period->forceFill([
                'finance_closed_at' => now(), 'finance_closed_by' => $actor->id, 'finance_next_period_id' => $next->id,
                'finance_closing_snapshot' => ['report' => $report, 'cash_cents' => $cash, 'bank_cents' => $bank, 'unassigned_cents' => $unassigned, 'total_cents' => $cash + $bank + $unassigned, 'accounts' => array_values($accounts)],
            ])->save();
            ClubAuditLog::record($club, $actor, 'club.finance.year_closed', $period, ['next_period_id' => $next->id, 'total_cents' => $cash + $bank + $unassigned]);

            return $period;
        });
    }
}
