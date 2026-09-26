<?php

namespace App\Services;

use App\Models\Club;
use App\Models\ClubRoleAssignment;
use App\Models\ClubServiceHourCorrection;
use App\Models\ClubServiceHourExemption;
use App\Models\ClubServiceHourRecord;
use App\Models\ClubServiceHourRequirement;
use App\Models\ClubYearPeriod;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClubServiceHourLedger
{
    public function summary(Club $club, ClubYearPeriod $period): array
    {
        $requirements = ClubServiceHourRequirement::query()
            ->where('club_id', $club->id)
            ->where('club_year_period_id', $period->id)
            ->with('roleDefinition')
            ->get();

        $members = $club->users()->get();
        $memberIds = $members->pluck('id');
        $assignments = ClubRoleAssignment::query()
            ->where('club_id', $club->id)
            ->whereIn('user_id', $memberIds)
            ->get()
            ->groupBy('user_id');

        $confirmed = ClubServiceHourRecord::query()
            ->where('club_id', $club->id)
            ->where('club_year_period_id', $period->id)
            ->where('status', 'confirmed')
            ->selectRaw('user_id, COALESCE(SUM(minutes), 0) as minutes')
            ->groupBy('user_id')
            ->pluck('minutes', 'user_id');

        $corrections = ClubServiceHourCorrection::query()
            ->where('club_id', $club->id)
            ->where('club_year_period_id', $period->id)
            ->selectRaw('user_id, COALESCE(SUM(minutes_delta), 0) as minutes')
            ->groupBy('user_id')
            ->pluck('minutes', 'user_id');

        $exemptions = ClubServiceHourExemption::query()
            ->where('club_id', $club->id)
            ->where('club_year_period_id', $period->id)
            ->selectRaw('user_id, COALESCE(SUM(minutes), 0) as minutes')
            ->groupBy('user_id')
            ->pluck('minutes', 'user_id');

        return [
            'period' => [
                'id' => $period->id,
                'type' => $period->type,
                'name' => $period->name,
                'starts_on' => $period->starts_on->format('Y-m-d'),
                'ends_on' => $period->ends_on->format('Y-m-d'),
            ],
            'requirements' => $requirements->map(fn (ClubServiceHourRequirement $requirement) => $this->requirementPayload($requirement))->values(),
            'members' => $members->map(function (User $member) use ($requirements, $assignments, $confirmed, $corrections, $exemptions) {
                $required = $this->requiredMinutes($requirements, $assignments->get($member->id, collect()));
                $confirmedMinutes = (int) ($confirmed[$member->id] ?? 0);
                $correctionMinutes = (int) ($corrections[$member->id] ?? 0);
                $exemptedMinutes = (int) ($exemptions[$member->id] ?? 0);
                $credited = $confirmedMinutes + $correctionMinutes + $exemptedMinutes;

                return [
                    'user_id' => $member->id,
                    'name' => $member->name,
                    'required_minutes' => $required,
                    'confirmed_minutes' => $confirmedMinutes,
                    'correction_minutes' => $correctionMinutes,
                    'exempted_minutes' => $exemptedMinutes,
                    'credited_minutes' => $credited,
                    'remaining_minutes' => max(0, $required - $credited),
                    'fulfilled' => $credited >= $required,
                ];
            })->values(),
        ];
    }

    public function confirm(ClubServiceHourRecord $record, User $actor): ClubServiceHourRecord
    {
        if ($record->status === 'confirmed') {
            return $record;
        }

        return DB::transaction(function () use ($record, $actor) {
            $record->forceFill([
                'status' => 'confirmed',
                'confirmed_by' => $actor->id,
                'confirmed_at' => now(),
                'confirmation_snapshot' => [
                    'minutes' => (int) $record->minutes,
                    'served_on' => $record->served_on->format('Y-m-d'),
                    'kind' => $record->kind,
                    'replacement_for_user_id' => $record->replacement_for_user_id,
                    'confirmed_by' => $actor->id,
                ],
            ])->save();

            return $record->refresh();
        });
    }

    public function correct(ClubServiceHourRecord $record, User $actor, int $delta, string $reason): ClubServiceHourCorrection
    {
        if ($record->status !== 'confirmed') {
            throw ValidationException::withMessages(['record' => 'Only confirmed records can be corrected.']);
        }

        return DB::transaction(function () use ($record, $actor, $delta, $reason) {
            $previous = (int) $record->minutes + (int) $record->corrections()->sum('minutes_delta');
            $corrected = $previous + $delta;
            if ($corrected < 0) {
                throw ValidationException::withMessages(['minutes_delta' => 'Correction cannot reduce credited minutes below zero.']);
            }

            return ClubServiceHourCorrection::query()->create([
                'club_service_hour_record_id' => $record->id,
                'club_id' => $record->club_id,
                'club_year_period_id' => $record->club_year_period_id,
                'user_id' => $record->user_id,
                'corrected_by' => $actor->id,
                'minutes_delta' => $delta,
                'previous_total_minutes' => $previous,
                'corrected_total_minutes' => $corrected,
                'reason' => $reason,
            ]);
        });
    }

    public function requirementPayload(ClubServiceHourRequirement $requirement): array
    {
        return [
            'id' => $requirement->id,
            'period_id' => $requirement->club_year_period_id,
            'role_definition_id' => $requirement->club_role_definition_id,
            'role_name' => $requirement->roleDefinition?->name,
            'required_minutes' => $requirement->required_minutes,
            'replacement_rate_cents' => $requirement->replacement_rate_cents,
            'replacement_currency' => $requirement->replacement_currency,
            'locked' => $requirement->locked,
        ];
    }

    private function requiredMinutes(Collection $requirements, Collection $assignments): int
    {
        $roleIds = $assignments->pluck('club_role_definition_id')->map(fn ($id) => (int) $id)->all();

        return (int) $requirements
            ->whereIn('club_role_definition_id', $roleIds)
            ->max('required_minutes');
    }
}
