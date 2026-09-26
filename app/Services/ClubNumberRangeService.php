<?php

namespace App\Services;

use App\Models\Club;
use App\Models\ClubNumberAllocation;
use App\Models\ClubNumberRange;
use App\Models\User;
use App\Support\ClubAuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClubNumberRangeService
{
    public function defaultFor(Club $club, string $scope): ?ClubNumberRange
    {
        if (! in_array($scope, ClubNumberRange::SCOPES, true)) {
            return null;
        }

        return $club->numberRangeDefaults()
            ->where('scope', $scope)
            ->with('numberRange')
            ->first()?->numberRange;
    }

    public function allocateDefault(
        Club $club,
        string $scope,
        ?User $actor,
        string $allocationKey,
        ?callable $numberIsAvailable = null
    ): ?ClubNumberAllocation {
        return DB::transaction(function () use ($club, $scope, $actor, $allocationKey, $numberIsAvailable) {
            $lockedClub = Club::query()->whereKey($club->id)->lockForUpdate()->firstOrFail();
            $range = $this->defaultFor($lockedClub, $scope);
            if (! $range) {
                return null;
            }

            $allocation = $this->allocate($range, $actor, $allocationKey);
            if ($numberIsAvailable && ! $numberIsAvailable($allocation->formatted_number)) {
                throw ValidationException::withMessages([
                    'number_range' => __('validation.number_range_legacy_collision'),
                ]);
            }

            return $allocation;
        }, 3);
    }

    public function assignTo(ClubNumberAllocation $allocation, string $subjectType, int $subjectId): void
    {
        if ($allocation->assigned_subject_type !== null && (
            $allocation->assigned_subject_type !== $subjectType
            || $allocation->assigned_subject_id !== $subjectId
        )) {
            throw ValidationException::withMessages([
                'number_range' => __('validation.number_range_allocation_assigned'),
            ]);
        }
        $allocation->forceFill([
            'assigned_subject_type' => $subjectType,
            'assigned_subject_id' => $subjectId,
        ])->save();
    }

    public function allocate(ClubNumberRange $range, ?User $actor, string $allocationKey): ClubNumberAllocation
    {
        return DB::transaction(function () use ($range, $actor, $allocationKey) {
            $lockedRange = ClubNumberRange::query()->lockForUpdate()->findOrFail($range->id);

            $existing = $lockedRange->allocations()->where('allocation_key', $allocationKey)->first();
            if ($existing) {
                return $existing;
            }

            if (! $lockedRange->is_active) {
                throw ValidationException::withMessages(['number_range' => __('validation.number_range_inactive')]);
            }

            $year = (int) now()->format('Y');
            $periodKey = $lockedRange->reset_policy === 'yearly' ? $year : 0;
            if ($lockedRange->reset_policy === 'yearly' && $lockedRange->last_reset_year !== $year) {
                $lockedRange->next_number = $lockedRange->start_number;
                $lockedRange->last_reset_year = $year;
            }

            $sequence = $lockedRange->next_number;
            if ($sequence < 1 || $sequence >= PHP_INT_MAX) {
                throw ValidationException::withMessages(['number_range' => __('validation.number_range_exhausted')]);
            }

            $formatted = $this->format($lockedRange, $sequence, $year);
            if (mb_strlen($formatted) > 190) {
                throw ValidationException::withMessages(['number_range' => __('validation.number_range_too_long')]);
            }

            $allocation = $lockedRange->allocations()->create([
                'club_id' => $lockedRange->club_id,
                'period_key' => $periodKey,
                'sequence_number' => $sequence,
                'formatted_number' => $formatted,
                'allocation_key' => $allocationKey,
                'allocated_by' => $actor?->id,
            ]);

            $lockedRange->next_number = $sequence + 1;
            $lockedRange->save();

            ClubAuditLog::record(
                $lockedRange->club,
                $actor,
                'club.number_range.allocated',
                $allocation,
                ['entity_type' => 'number_allocation']
            );

            return $allocation;
        }, 3);
    }

    public function preview(ClubNumberRange $range): string
    {
        $year = (int) now()->format('Y');
        $sequence = $range->reset_policy === 'yearly' && $range->last_reset_year !== $year
            ? $range->start_number
            : $range->next_number;

        return $this->format($range, $sequence, $year);
    }

    private function format(ClubNumberRange $range, int $sequence, int $year): string
    {
        $replace = ['{YYYY}' => (string) $year, '{YY}' => substr((string) $year, -2)];

        return strtr($range->prefix, $replace)
            .str_pad((string) $sequence, $range->padding, '0', STR_PAD_LEFT)
            .strtr($range->suffix, $replace);
    }
}
