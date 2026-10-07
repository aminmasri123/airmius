<?php

namespace App\Console\Commands;

use App\Models\Club;
use App\Models\Invoice;
use App\Models\User;
use App\Notifications\ClubInvoiceCreated;
use App\Services\ClubContributionCalculator;
use App\Services\ClubContributionInvoiceRunService;
use App\Services\ClubNumberRangeService;
use App\Services\PlanFeatureService;
use App\Support\AppNotification;
use App\Support\TransactionalMail;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GenerateRecurringContributionInvoices extends Command
{
    protected $signature = 'airmius:generate-recurring-contribution-invoices
        {--date= : Stichtag im Format YYYY-MM-DD, Standard ist heute}';

    protected $description = 'Erstellt wiederkehrende Mitgliedsbeitrags-Rechnungen für Pro/Elite-Vereine.';

    public function __construct(
        private PlanFeatureService $planFeatures,
        private ClubNumberRangeService $numberRanges,
        private ClubContributionCalculator $contributionCalculator,
        private ClubContributionInvoiceRunService $invoiceRuns,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))->startOfDay()
            : now()->startOfDay();
        $created = 0;
        $skippedByPlan = 0;

        Club::query()->with('owner')->orderBy('id')->cursor()->each(
            function (Club $club) use ($date, &$created, &$skippedByPlan) {
                if (! $this->planFeatures->allows($club, 'recurring_invoices')) {
                    $skippedByPlan++;

                    return;
                }

                $actor = $club->owner ?: User::query()->whereKey($club->owner_id)->first();
                if (! $actor) {
                    $skippedByPlan++;

                    return;
                }

                $result = $this->invoiceRuns->create($club, $actor, $date);
                $created += (int) $result['created_count'];
            }
        );

        $this->info("Wiederkehrende Rechnungen erstellt: {$created}. Übersprungen wegen Plan: {$skippedByPlan}.");

        return self::SUCCESS;
    }

    private function advanceMembership(object $membership, Carbon $currentDueDate): void
    {
        $nextDate = match ($membership->contribution_interval) {
            'monthly' => $currentDueDate->copy()->addMonthNoOverflow(),
            'quarterly' => $currentDueDate->copy()->addMonthsNoOverflow(3),
            'four_monthly' => $currentDueDate->copy()->addMonthsNoOverflow(4),
            'semi_yearly' => $currentDueDate->copy()->addMonthsNoOverflow(6),
            'yearly' => $currentDueDate->copy()->addYearNoOverflow(),
            'once' => null,
            default => null,
        };

        DB::table('club_user')
            ->where('club_id', $membership->club_id)
            ->where('user_id', $membership->user_id)
            ->update([
                'contribution_next_invoice_on' => $nextDate?->toDateString(),
                'contribution_last_invoice_at' => now(),
            ]);
    }

    private function periodEnd(Carbon $start, string $interval): ?Carbon
    {
        return match ($interval) {
            'monthly' => $start->copy()->addMonthNoOverflow()->subDay(),
            'quarterly' => $start->copy()->addMonthsNoOverflow(3)->subDay(),
            'four_monthly' => $start->copy()->addMonthsNoOverflow(4)->subDay(),
            'semi_yearly' => $start->copy()->addMonthsNoOverflow(6)->subDay(),
            'yearly' => $start->copy()->addYearNoOverflow()->subDay(),
            'once' => $start->copy(),
            default => null,
        };
    }

    private function contributionSnapshot(Club $club, object $membership, Carbon $periodStart, ?Carbon $periodEnd): array
    {
        $fullAmount = number_format(round((float) $membership->contribution_amount, 2), 2, '.', '');
        $activeFrom = filled($membership->joined_on ?? null)
            ? Carbon::parse($membership->joined_on)->startOfDay()
            : $periodStart->copy();
        $rule = $this->matchingContributionRule($club, $membership, $periodStart);
        $prorationPolicy = $rule?->proration_policy ?: 'prorate_days';

        if ($periodEnd && $prorationPolicy === 'next_period' && $activeFrom->greaterThan($periodStart) && $activeFrom->lessThanOrEqualTo($periodEnd)) {
            return [
                'version' => 1,
                'membership_user_id' => (int) $membership->user_id,
                'payer_user_id' => (int) ($membership->contribution_payer_user_id ?: $membership->user_id),
                'membership_type_id' => $membership->club_membership_type_id ? (int) $membership->club_membership_type_id : null,
                'rule_id' => $rule?->id,
                'interval' => $membership->contribution_interval,
                'full_amount' => $fullAmount,
                'amount' => '0.00',
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'period_days' => $periodStart->diffInDays($periodEnd) + 1,
                'billable_days' => 0,
                'active_from' => $activeFrom->toDateString(),
                'prorated' => false,
                'proration_policy' => $prorationPolicy,
                'skip_invoice' => true,
                'rounded_cents' => 0,
                'captured_at' => now()->toIso8601String(),
            ];
        }

        $proration = $periodEnd && $prorationPolicy === 'prorate_days'
            ? $this->contributionCalculator->prorateForPeriod($fullAmount, $periodStart, $periodEnd, $activeFrom)
            : [
                'amount' => $fullAmount,
                'full_amount' => $fullAmount,
                'period_days' => $periodEnd ? $periodStart->diffInDays($periodEnd) + 1 : 1,
                'billable_days' => $periodEnd ? $periodStart->diffInDays($periodEnd) + 1 : 1,
                'active_from' => $activeFrom->greaterThan($periodStart) ? $activeFrom->toDateString() : $periodStart->toDateString(),
                'prorated' => false,
            ];

        return array_merge([
            'version' => 1,
            'membership_user_id' => (int) $membership->user_id,
            'payer_user_id' => (int) ($membership->contribution_payer_user_id ?: $membership->user_id),
            'membership_type_id' => $membership->club_membership_type_id ? (int) $membership->club_membership_type_id : null,
            'rule_id' => $rule?->id,
            'interval' => $membership->contribution_interval,
            'full_amount' => $proration['full_amount'],
            'amount' => $proration['amount'],
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd?->toDateString(),
            'period_days' => $proration['period_days'],
            'billable_days' => $proration['billable_days'],
            'active_from' => $proration['active_from'],
            'prorated' => $proration['prorated'],
            'proration_policy' => $prorationPolicy,
            'skip_invoice' => false,
            'rounded_cents' => (int) round((float) $proration['amount'] * 100),
            'captured_at' => now()->toIso8601String(),
        ], $this->contributionBreakdown($club, $membership, $periodStart));
    }

    private function contributionBreakdown(Club $club, object $membership, Carbon $date): array
    {
        $member = User::query()->find((int) $membership->user_id);
        $membershipTypeId = $membership->club_membership_type_id ? (int) $membership->club_membership_type_id : null;
        $resolved = $this->contributionCalculator->resolve(
            $club,
            $member,
            $membershipTypeId,
            $date,
            $membership->family_group_key ?? null,
        );

        if (! $resolved) {
            return [];
        }

        return [
            'rule_id' => $resolved['rule_id'] ?? null,
            'base_amount' => $resolved['base_amount'] ?? null,
            'component_amount' => $resolved['component_amount'] ?? null,
            'discount_amount' => $resolved['discount_amount'] ?? null,
            'components' => $resolved['components'] ?? [],
            'discounts' => $resolved['discounts'] ?? [],
            'preview_lines' => $resolved['preview_lines'] ?? [],
        ];
    }

    private function matchingContributionRule(Club $club, object $membership, Carbon $date): ?\App\Models\ClubContributionRule
    {
        $membershipTypeId = $membership->club_membership_type_id ? (int) $membership->club_membership_type_id : null;

        return $club->contributionRules()
            ->effectiveOn($date->toDateString())
            ->where('billing_interval', $membership->contribution_interval)
            ->when($membershipTypeId, fn ($query) => $query->where(function ($query) use ($membershipTypeId) {
                $query->where('club_membership_type_id', $membershipTypeId)
                    ->orWhereNull('club_membership_type_id');
            }))
            ->when(! $membershipTypeId, fn ($query) => $query->whereNull('club_membership_type_id'))
            ->orderByRaw('CASE WHEN club_membership_type_id IS NULL THEN 1 ELSE 0 END')
            ->orderBy('priority')
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->first();
    }

    private function titleFor(Carbon $date, string $interval): string
    {
        return match ($interval) {
            'monthly' => 'Mitgliedsbeitrag '.$date->translatedFormat('F Y'),
            'quarterly' => 'Mitgliedsbeitrag Quartal '.$date->quarter.'/'.$date->year,
            'yearly' => 'Mitgliedsbeitrag '.$date->year,
            default => 'Mitgliedsbeitrag',
        };
    }

    private function nextInvoiceNumber(Club $club): string
    {
        $next = Invoice::query()
            ->where('club_id', $club->id)
            ->whereYear('created_at', now()->year)
            ->count() + 1;

        return 'AIR-'.$club->id.'-'.now()->format('Y').'-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
