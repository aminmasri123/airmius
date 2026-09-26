<?php

namespace App\Services;

use App\Models\Club;
use App\Models\ClubContributionRule;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Resolves a club contribution for an application without exposing raw rule
 * internals to the mobile client. A specific membership type wins over the
 * club-wide fallback; at most one matching discount is applied to the base
 * rule so stacked discounts cannot accidentally reduce a fee twice.
 */
class ClubContributionCalculator
{
    private const BASE_COMPONENTS = ['standard', 'base', 'family', 'youth', 'supporting'];

    private const ADDITIVE_COMPONENTS = ['department', 'admission', 'allocation', 'service', 'special'];

    private const DISCOUNT_COMPONENTS = ['discount', 'sibling_discount', 'reduction', 'exemption'];

    public function resolve(
        Club $club,
        ?User $user,
        ?int $membershipTypeId,
        Carbon|string|null $date = null,
        ?string $familyGroupKey = null,
    ): ?array {
        $effectiveDate = $date instanceof Carbon
            ? $date
            : Carbon::parse($date ?: now()->toDateString());
        $age = $user?->birth_date?->age;

        $familyGroupKey = $this->normalizeFamilyGroupKey($familyGroupKey);
        $familyMemberCount = $this->familyMemberCount($club, $familyGroupKey);

        $rules = $club->contributionRules()
            ->effectiveOn($effectiveDate->toDateString())
            ->when($membershipTypeId, fn ($query) => $query->where(function ($query) use ($membershipTypeId) {
                $query->where('club_membership_type_id', $membershipTypeId)
                    ->orWhereNull('club_membership_type_id');
            }))
            ->when(! $membershipTypeId, fn ($query) => $query->whereNull('club_membership_type_id'))
            ->where(function ($query) use ($age) {
                $query->whereNull('age_min');
                if ($age !== null) {
                    $query->orWhere('age_min', '<=', $age);
                }
            })
            ->where(function ($query) use ($age) {
                $query->whereNull('age_max');
                if ($age !== null) {
                    $query->orWhere('age_max', '>=', $age);
                }
            })
            ->orderByRaw('CASE WHEN club_membership_type_id IS NULL THEN 1 ELSE 0 END')
            ->orderBy('priority')
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->get();

        if ($familyMemberCount < 2) {
            $rules = $rules->reject(fn (ClubContributionRule $rule) => $rule->factor_key === 'family');
        }

        /** @var ClubContributionRule|null $baseRule */
        $baseRule = $rules->first(fn (ClubContributionRule $rule) => in_array($rule->factor_key ?: 'standard', self::BASE_COMPONENTS, true));
        if (! $baseRule) {
            return null;
        }

        $baseCents = $this->moneyToCents($baseRule->amount);
        /** @var ClubContributionRule|null $discountRule */
        $discountRule = $rules->first(fn (ClubContributionRule $rule) => $this->isApplicableDiscountRule($rule, $familyMemberCount));
        $discountCents = $discountRule ? $this->discountCents($baseCents, $discountRule) : 0;
        $componentRows = [[
            'rule_id' => $baseRule->id,
            'name' => $baseRule->name,
            'type' => $baseRule->factor_key ?: 'standard',
            'label' => ClubContributionRule::RULE_TYPE_LABELS[$baseRule->factor_key ?: 'standard'] ?? 'Standardbeitrag',
            'amount' => $this->formatCents($baseCents),
            'calculation' => 'base',
            'priority' => (int) ($baseRule->priority ?? 100),
            'tax_account' => $baseRule->tax_account,
            'accounting_account' => $baseRule->accounting_account,
        ]];
        $additiveCents = 0;

        foreach ($rules as $rule) {
            $componentType = $rule->factor_key ?: 'standard';
            if (! in_array($componentType, self::ADDITIVE_COMPONENTS, true)) {
                continue;
            }

            $amountCents = $this->moneyToCents($rule->amount);
            $additiveCents += $amountCents;
            $componentRows[] = [
                'rule_id' => $rule->id,
                'name' => $rule->name,
                'type' => $componentType,
                'label' => ClubContributionRule::RULE_TYPE_LABELS[$componentType] ?? $componentType,
                'amount' => $this->formatCents($amountCents),
                'calculation' => 'additive',
                'priority' => (int) ($rule->priority ?? 100),
                'tax_account' => $rule->tax_account,
                'accounting_account' => $rule->accounting_account,
            ];
        }

        $totalCents = max(0, $baseCents + $additiveCents - $discountCents);
        $discountRows = $discountRule ? [[
            'rule_id' => $discountRule->id,
            'name' => $discountRule->name,
            'type' => $discountRule->factor_key,
            'label' => ClubContributionRule::RULE_TYPE_LABELS[$discountRule->factor_key] ?? 'Rabatt',
            'amount' => $this->formatCents($discountCents),
            'calculation' => 'subtractive',
            'operator' => $discountRule->factor_operator,
            'value' => $discountRule->factor_value,
            'priority' => (int) ($discountRule->priority ?? 100),
        ]] : [];

        return [
            'amount' => $this->formatCents($totalCents),
            'base_amount' => $this->formatCents($baseCents),
            'discount_amount' => $this->formatCents($discountCents),
            'component_amount' => $this->formatCents($additiveCents),
            'interval' => $discountRule?->billing_interval ?: $baseRule->billing_interval,
            'rule_type' => $baseRule->factor_key ?: 'standard',
            'rule_id' => $baseRule->id,
            'discount_rule_id' => $discountRule?->id,
            'components' => $componentRows,
            'discounts' => $discountRows,
            'preview_lines' => [
                ...$componentRows,
                ...$discountRows,
                [
                    'type' => 'total',
                    'label' => 'Vorschau gesamt',
                    'amount' => $this->formatCents($totalCents),
                    'calculation' => 'total',
                ],
            ],
            'snapshot' => [
                'effective_on' => $effectiveDate->toDateString(),
                'base_rule_id' => $baseRule->id,
                'discount_rule_id' => $discountRule?->id,
                'base_rule_type' => $baseRule->factor_key ?: 'standard',
                'discount_rule_type' => $discountRule?->factor_key,
                'amount' => $this->formatCents($totalCents),
                'base_amount' => $this->formatCents($baseCents),
                'component_amount' => $this->formatCents($additiveCents),
                'discount_amount' => $this->formatCents($discountCents),
                'components' => $componentRows,
                'discounts' => $discountRows,
            ],
            'family_group_key' => $familyGroupKey,
            'family_member_count' => $familyMemberCount,
        ];
    }

    public function normalizeFamilyGroupKey(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return mb_strtolower($value);
    }

    public function prorateForPeriod(float|string $amount, Carbon|string $periodStart, Carbon|string $periodEnd, Carbon|string|null $activeFrom): array
    {
        $start = $periodStart instanceof Carbon ? $periodStart->copy()->startOfDay() : Carbon::parse($periodStart)->startOfDay();
        $end = $periodEnd instanceof Carbon ? $periodEnd->copy()->startOfDay() : Carbon::parse($periodEnd)->startOfDay();
        $active = $activeFrom instanceof Carbon ? $activeFrom->copy()->startOfDay() : ($activeFrom ? Carbon::parse($activeFrom)->startOfDay() : $start->copy());

        if ($active->lessThan($start)) {
            $active = $start->copy();
        }

        if ($active->greaterThan($end)) {
            return [
                'amount' => '0.00',
                'full_amount' => number_format(round((float) $amount, 2), 2, '.', ''),
                'period_days' => $start->diffInDays($end) + 1,
                'billable_days' => 0,
                'active_from' => $active->toDateString(),
                'prorated' => true,
            ];
        }

        $periodDays = $start->diffInDays($end) + 1;
        $billableDays = $active->diffInDays($end) + 1;
        $fullCents = (int) round((float) $amount * 100);
        $proratedCents = (int) round($fullCents * ($billableDays / $periodDays));

        return [
            'amount' => number_format($proratedCents / 100, 2, '.', ''),
            'full_amount' => number_format($fullCents / 100, 2, '.', ''),
            'period_days' => $periodDays,
            'billable_days' => $billableDays,
            'active_from' => $active->toDateString(),
            'prorated' => $billableDays !== $periodDays,
        ];
    }

    private function familyMemberCount(Club $club, ?string $familyGroupKey): int
    {
        if ($familyGroupKey === null) {
            return 0;
        }

        $statuses = ['active', 'pending', 'paused'];

        return (int) DB::table('club_user')
            ->where('club_id', $club->id)
            ->where('family_group_key', $familyGroupKey)
            ->whereIn('membership_status', $statuses)
            ->count()
            + (int) DB::table('club_external_members')
                ->where('club_id', $club->id)
                ->where('family_group_key', $familyGroupKey)
                ->whereIn('membership_status', $statuses)
                ->count();
    }

    private function discountCents(int $baseCents, ClubContributionRule $rule): int
    {
        if ($rule->factor_key === 'exemption') {
            return $baseCents;
        }

        $value = max(0, (float) $rule->factor_value);

        return min($baseCents, match ($rule->factor_operator) {
            'percent' => (int) round($baseCents * min(100, $value) / 100),
            'fixed' => $this->moneyToCents($value),
            default => 0,
        });
    }

    private function isApplicableDiscountRule(ClubContributionRule $rule, int $familyMemberCount): bool
    {
        $type = $rule->factor_key ?: 'standard';

        if (! in_array($type, self::DISCOUNT_COMPONENTS, true)) {
            return false;
        }

        return $type !== 'sibling_discount' || $familyMemberCount >= 2;
    }

    private function moneyToCents(float|string|null $amount): int
    {
        return (int) round((float) $amount * 100);
    }

    private function formatCents(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
