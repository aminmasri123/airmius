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
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->get();

        if ($familyMemberCount < 2) {
            $rules = $rules->reject(fn (ClubContributionRule $rule) => $rule->factor_key === 'family');
        }

        /** @var ClubContributionRule|null $baseRule */
        $baseRule = $rules->first(fn (ClubContributionRule $rule) => $rule->factor_key !== 'discount');
        if (! $baseRule) {
            return null;
        }

        $baseAmount = round((float) $baseRule->amount, 2);
        /** @var ClubContributionRule|null $discountRule */
        $discountRule = $rules->first(fn (ClubContributionRule $rule) => $rule->factor_key === 'discount');
        $discountAmount = $discountRule ? $this->discountAmount($baseAmount, $discountRule) : 0.0;

        return [
            'amount' => number_format(max(0, $baseAmount - $discountAmount), 2, '.', ''),
            'base_amount' => number_format($baseAmount, 2, '.', ''),
            'discount_amount' => number_format($discountAmount, 2, '.', ''),
            'interval' => $discountRule?->billing_interval ?: $baseRule->billing_interval,
            'rule_type' => $baseRule->factor_key ?: 'standard',
            'rule_id' => $baseRule->id,
            'discount_rule_id' => $discountRule?->id,
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

    private function discountAmount(float $baseAmount, ClubContributionRule $rule): float
    {
        $value = max(0, (float) $rule->factor_value);

        return min($baseAmount, match ($rule->factor_operator) {
            'percent' => round($baseAmount * min(100, $value) / 100, 2),
            'fixed' => round($value, 2),
            default => 0.0,
        });
    }
}
