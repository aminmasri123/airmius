<?php

namespace App\Services;

use App\Models\BankTransaction;
use App\Models\Club;
use App\Models\ClubExternalMember;
use App\Models\ClubFinanceEntry;
use App\Models\ClubYearPeriod;
use App\Models\Event;
use App\Models\Invoice;
use App\Support\BillingOverview;
use App\Support\ClubPermissions;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ClubYearPeriodReportService
{
    private const PRIVACY_MIN_GROUP_SIZE = 5;

    public function report(Club $club, string $type, ?ClubYearPeriod $period): array
    {
        $generatedAt = now();

        return [
            'type' => $type,
            'selection' => $period ? 'period' : 'unassigned',
            'period' => $period ? $this->periodPayload($period) : null,
            'generated_at' => $generatedAt->toJSON(),
            'catalog' => $this->catalog($type),
            'summary' => $this->summary($club, $type, $period?->id),
            'unassigned' => $this->summary($club, $type, null),
            'membership_development' => $period
                ? $this->membershipDevelopment($club, $period)
                : $this->suppressedMembershipDevelopment(),
            'standard_reports' => $this->standardReports($club, $type, $period, $generatedAt),
        ];
    }

    private function catalog(string $type): array
    {
        $common = [
            [
                'key' => 'membership_development.people_total',
                'definition' => 'Distinct natural persons in member records, with linked external records deduplicated against users.',
                'source' => 'club_user, club_external_members',
                'period' => 'Selected club year period when available',
                'club_year' => true,
                'permission' => ClubPermissions::MEMBERS_VIEW,
                'privacy_min_group_size' => self::PRIVACY_MIN_GROUP_SIZE,
            ],
            [
                'key' => 'membership_development.membership_rows_total',
                'definition' => 'All membership affiliations, including multiple rows for one person when they exist in more than one member source.',
                'source' => 'club_user, club_external_members',
                'period' => 'Selected club year period when available',
                'club_year' => true,
                'permission' => ClubPermissions::MEMBERS_VIEW,
                'privacy_min_group_size' => self::PRIVACY_MIN_GROUP_SIZE,
            ],
        ];

        $typeSpecific = match ($type) {
            'business' => [
                [
                    'key' => 'summary.finance_entries.net_amount',
                    'definition' => 'Income minus expenses booked into the frozen business year assignment.',
                    'source' => 'club_finance_entries.business_year_period_id',
                    'period' => 'Business year',
                    'club_year' => true,
                    'permission' => ClubPermissions::FINANCE_VIEW,
                    'privacy_min_group_size' => 0,
                ],
            ],
            'contribution' => [
                [
                    'key' => 'summary.invoices.total_count',
                    'definition' => 'Contribution invoices assigned to the selected contribution year.',
                    'source' => 'invoices.contribution_year_period_id',
                    'period' => 'Contribution year',
                    'club_year' => true,
                    'permission' => ClubPermissions::FINANCE_VIEW,
                    'privacy_min_group_size' => 0,
                ],
            ],
            'sport' => [
                [
                    'key' => 'summary.events.total_count',
                    'definition' => 'Club events assigned to the selected sport year.',
                    'source' => 'events.sport_year_period_id',
                    'period' => 'Sport year',
                    'club_year' => true,
                    'permission' => ClubPermissions::FINANCE_VIEW,
                    'privacy_min_group_size' => 0,
                ],
            ],
        };

        return [
            'privacy_min_group_size' => self::PRIVACY_MIN_GROUP_SIZE,
            'metrics' => [...$common, ...$typeSpecific],
        ];
    }

    private function summary(Club $club, string $type, ?int $periodId): array
    {
        return match ($type) {
            'business' => $this->businessSummary($club, $periodId),
            'contribution' => $this->contributionSummary($club, $periodId),
            'sport' => $this->sportSummary($club, $periodId),
        };
    }

    private function businessSummary(Club $club, ?int $periodId): array
    {
        $invoices = $this->forPeriod(
            Invoice::query()->where('club_id', $club->id),
            'business_year_period_id',
            $periodId,
        )->get();
        $transactions = $this->forPeriod(
            BankTransaction::query()->where('club_id', $club->id),
            'business_year_period_id',
            $periodId,
        )->get(['id', 'amount']);
        $entries = $this->forPeriod(
            ClubFinanceEntry::query()->where('club_id', $club->id),
            'business_year_period_id',
            $periodId,
        )->get(['id', 'type', 'amount']);

        $credits = (float) $transactions->where('amount', '>=', 0)->sum(fn (BankTransaction $item) => (float) $item->amount);
        $debits = (float) abs($transactions->where('amount', '<', 0)->sum(fn (BankTransaction $item) => (float) $item->amount));
        $income = (float) $entries->where('type', 'income')->sum(fn (ClubFinanceEntry $item) => (float) $item->amount);
        $expenses = (float) $entries->where('type', 'expense')->sum(fn (ClubFinanceEntry $item) => (float) $item->amount);

        return [
            'invoices' => BillingOverview::clubInvoiceSummary($invoices),
            'bank_transactions' => [
                'total_count' => $transactions->count(),
                'credit_amount' => $credits,
                'debit_amount' => $debits,
                'net_amount' => $credits - $debits,
            ],
            'finance_entries' => [
                'total_count' => $entries->count(),
                'income_amount' => $income,
                'expense_amount' => $expenses,
                'net_amount' => $income - $expenses,
            ],
        ];
    }

    private function contributionSummary(Club $club, ?int $periodId): array
    {
        $invoices = $this->forPeriod(
            Invoice::query()
                ->where('club_id', $club->id)
                ->whereIn('source', ['recurring_contribution', 'membership_contribution']),
            'contribution_year_period_id',
            $periodId,
        )->get();

        return ['invoices' => BillingOverview::clubInvoiceSummary($invoices)];
    }

    private function sportSummary(Club $club, ?int $periodId): array
    {
        $events = $this->forPeriod(
            Event::query()->where('club_id', $club->id),
            'sport_year_period_id',
            $periodId,
        );
        $byStatus = (clone $events)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $byType = (clone $events)
            ->selectRaw('type, COUNT(*) as aggregate')
            ->groupBy('type')
            ->pluck('aggregate', 'type');

        return [
            'events' => [
                'total_count' => (clone $events)->count(),
                'scheduled_count' => (int) ($byStatus['scheduled'] ?? 0),
                'cancelled_count' => (int) ($byStatus['cancelled'] ?? 0),
                'by_type' => collect(Event::TYPES)->mapWithKeys(
                    fn (string $eventType) => [$eventType => (int) ($byType[$eventType] ?? 0)]
                )->all(),
            ],
        ];
    }

    private function membershipDevelopment(Club $club, ClubYearPeriod $period): array
    {
        $startsOn = CarbonImmutable::parse($period->starts_on)->startOfDay();
        $endsOn = CarbonImmutable::parse($period->ends_on)->endOfDay();

        $internal = DB::table('club_user')
            ->where('club_id', $club->id)
            ->get(['user_id', 'membership_status', 'joined_on', 'membership_ended_at']);
        $external = ClubExternalMember::query()
            ->where('club_id', $club->id)
            ->get(['id', 'linked_user_id', 'email', 'membership_status', 'joined_on', 'membership_ended_at']);

        $rows = collect();
        foreach ($internal as $member) {
            $rows->push([
                'person_key' => 'user:'.$member->user_id,
                'joined_on' => $member->joined_on,
                'membership_ended_at' => $member->membership_ended_at,
                'status' => $member->membership_status,
            ]);
        }
        foreach ($external as $member) {
            $rows->push([
                'person_key' => $member->linked_user_id ? 'user:'.$member->linked_user_id : 'external:'.strtolower((string) $member->email),
                'joined_on' => $member->joined_on,
                'membership_ended_at' => $member->membership_ended_at,
                'status' => $member->membership_status,
            ]);
        }

        $activeInPeriod = $rows->filter(fn (array $row) => $this->activeInPeriod($row, $startsOn, $endsOn));
        $joined = $rows->filter(fn (array $row) => $this->dateInPeriod($row['joined_on'] ?? null, $startsOn, $endsOn));
        $ended = $rows->filter(fn (array $row) => $this->dateInPeriod($row['membership_ended_at'] ?? null, $startsOn, $endsOn));

        $peopleTotal = $activeInPeriod->pluck('person_key')->unique()->count();
        if ($peopleTotal < self::PRIVACY_MIN_GROUP_SIZE) {
            return $this->suppressedMembershipDevelopment($peopleTotal);
        }

        return [
            'suppressed' => false,
            'privacy_min_group_size' => self::PRIVACY_MIN_GROUP_SIZE,
            'people_total' => $peopleTotal,
            'membership_rows_total' => $activeInPeriod->count(),
            'multiple_membership_rows' => max(0, $activeInPeriod->count() - $peopleTotal),
            'joined_people_count' => $joined->pluck('person_key')->unique()->count(),
            'ended_people_count' => $ended->pluck('person_key')->unique()->count(),
            'sources' => ['club_user', 'club_external_members'],
            'period' => $this->periodPayload($period),
        ];
    }

    private function suppressedMembershipDevelopment(int $observed = 0): array
    {
        return [
            'suppressed' => true,
            'reason' => 'privacy_min_group_size',
            'privacy_min_group_size' => self::PRIVACY_MIN_GROUP_SIZE,
            'observed_group_size' => $observed,
        ];
    }

    private function activeInPeriod(array $row, CarbonImmutable $startsOn, CarbonImmutable $endsOn): bool
    {
        $joined = $row['joined_on'] ? CarbonImmutable::parse($row['joined_on'])->startOfDay() : null;
        $ended = $row['membership_ended_at'] ? CarbonImmutable::parse($row['membership_ended_at'])->endOfDay() : null;

        return ($joined === null || $joined->lte($endsOn))
            && ($ended === null || $ended->gte($startsOn))
            && ($row['status'] ?? 'active') !== 'rejected';
    }

    private function dateInPeriod(mixed $value, CarbonImmutable $startsOn, CarbonImmutable $endsOn): bool
    {
        if (! $value) {
            return false;
        }

        $date = CarbonImmutable::parse($value);

        return $date->betweenIncluded($startsOn, $endsOn);
    }

    private function standardReports(Club $club, string $type, ?ClubYearPeriod $period, $generatedAt): array
    {
        $sourceStatus = [
            'source' => match ($type) {
                'business' => 'Invoices, bank transactions and finance entries assigned by business year period.',
                'contribution' => 'Contribution invoices assigned by contribution year period.',
                'sport' => 'Events assigned by sport year period.',
            },
            'as_of' => $generatedAt->toJSON(),
            'period' => $period ? $this->periodPayload($period) : null,
        ];

        return [
            'board' => [
                'label' => 'Vorstand',
                'permission' => ClubPermissions::FINANCE_VIEW,
                ...$sourceStatus,
                'sections' => ['summary', 'membership_development', 'unassigned'],
            ],
            'assembly' => [
                'label' => 'Mitgliederversammlung',
                'permission' => ClubPermissions::FINANCE_VIEW,
                ...$sourceStatus,
                'sections' => ['summary', 'membership_development'],
            ],
            'funder' => [
                'label' => 'Fördergeber',
                'permission' => ClubPermissions::FINANCE_EXPORT,
                ...$sourceStatus,
                'sections' => ['summary'],
            ],
            'association_cutoff' => [
                'label' => 'Verbandsstichtag',
                'permission' => ClubPermissions::MEMBERS_EXPORT,
                ...$sourceStatus,
                'sections' => ['membership_development'],
            ],
        ];
    }

    private function forPeriod(Builder $query, string $column, ?int $periodId): Builder
    {
        return $periodId === null
            ? $query->whereNull($column)
            : $query->where($column, $periodId);
    }

    private function periodPayload(ClubYearPeriod $period): array
    {
        return [
            'id' => $period->id,
            'type' => $period->type,
            'name' => $period->name,
            'starts_on' => $period->starts_on->toDateString(),
            'ends_on' => $period->ends_on->toDateString(),
        ];
    }
}
