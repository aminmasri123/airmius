<?php

namespace App\Services;

use App\Models\Club;
use App\Models\ClubFundingProgram;
use App\Models\User;
use App\Support\ClubAuditLog;
use App\Support\ClubPermissions;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClubFundingProgramService
{
    private const TRANSITIONS = [
        'draft' => ['draft', 'ready', 'withdrawn'],
        'ready' => ['ready', 'submitted', 'withdrawn'],
        'submitted' => ['submitted', 'approved', 'rejected', 'withdrawn'],
        'approved' => ['approved', 'own_contribution_secured', 'closed'],
        'own_contribution_secured' => ['own_contribution_secured', 'paid_out', 'closed'],
        'paid_out' => ['paid_out', 'closed'],
        'rejected' => ['rejected', 'draft', 'closed'],
        'withdrawn' => ['withdrawn', 'draft', 'closed'],
        'closed' => ['closed'],
    ];

    public function create(Club $club, User $actor, array $data): ClubFundingProgram
    {
        $this->authorize($club, $actor, ClubPermissions::FINANCE_EDIT);

        return DB::transaction(function () use ($club, $actor, $data) {
            $program = ClubFundingProgram::query()->create(array_replace($data, [
                'club_id' => $club->id,
                'status_changed_by' => $actor->id,
                'status_changed_at' => now(),
            ]));

            $this->audit($club, $actor, 'club.funding_program.created', $program, null, $program->status);

            return $program->fresh(['yearPeriod', 'responsible']);
        });
    }

    public function update(ClubFundingProgram $program, User $actor, array $data): ClubFundingProgram
    {
        $program->loadMissing('club');
        $this->authorize($program->club, $actor, ClubPermissions::FINANCE_EDIT);

        return DB::transaction(function () use ($program, $actor, $data) {
            $locked = ClubFundingProgram::query()->with('club')->lockForUpdate()->findOrFail($program->id);
            $locked->fill($data)->save();
            $this->audit($locked->club, $actor, 'club.funding_program.updated', $locked, $locked->status, $locked->status);

            return $locked->fresh(['yearPeriod', 'responsible']);
        });
    }

    public function transition(ClubFundingProgram $program, User $actor, string $nextStatus, array $data = []): ClubFundingProgram
    {
        $program->loadMissing('club');
        $this->authorize($program->club, $actor, ClubPermissions::FINANCE_APPROVE);

        return DB::transaction(function () use ($program, $actor, $nextStatus, $data) {
            $locked = ClubFundingProgram::query()->with('club')->lockForUpdate()->findOrFail($program->id);
            $previousStatus = $locked->status;

            if (! in_array($nextStatus, self::TRANSITIONS[$previousStatus] ?? [], true)) {
                throw ValidationException::withMessages(['status' => __('validation.invalid')]);
            }

            $payload = array_replace($data, [
                'status' => $nextStatus,
                'status_changed_by' => $previousStatus !== $nextStatus ? $actor->id : $locked->status_changed_by,
                'status_changed_at' => $previousStatus !== $nextStatus ? now() : $locked->status_changed_at,
            ]);

            if ($nextStatus === 'submitted' && empty($payload['submitted_on'])) {
                $payload['submitted_on'] = now()->toDateString();
            }
            if ($nextStatus === 'approved' && empty($payload['approved_on'])) {
                $payload['approved_on'] = now()->toDateString();
            }
            if ($nextStatus === 'paid_out' && empty($payload['paid_out_on'])) {
                $payload['paid_out_on'] = now()->toDateString();
            }

            $locked->forceFill($payload)->save();
            $this->audit($locked->club, $actor, 'club.funding_program.status_changed', $locked, $previousStatus, $nextStatus);

            return $locked->fresh(['yearPeriod', 'responsible']);
        });
    }

    public function ensureClub(Club $club, ClubFundingProgram $program): void
    {
        if ((int) $program->club_id !== (int) $club->id) {
            abort(404);
        }
    }

    public function authorize(Club $club, User $actor, string $permission): void
    {
        if (! ClubPermissions::allows($club, $actor, $permission)) {
            throw new AuthorizationException;
        }
    }

    public static function allowedTransitions(string $status): array
    {
        return self::TRANSITIONS[$status] ?? [];
    }

    private function audit(Club $club, User $actor, string $type, ClubFundingProgram $program, ?string $from, string $to): void
    {
        ClubAuditLog::record($club, $actor, $type, $program, [
            'entity_type' => 'funding_program',
            'from' => $from,
            'to' => $to,
            'club_year_period_id' => $program->club_year_period_id,
            'responsible_user_id' => $program->responsible_user_id,
            'requested_amount_cents' => $program->requested_amount_cents,
            'approved_amount_cents' => $program->approved_amount_cents,
            'own_contribution_cents' => $program->own_contribution_cents,
            'paid_out_amount_cents' => $program->paid_out_amount_cents,
        ]);
    }
}
