<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\ClubRoleDefinition;
use App\Models\ClubServiceHourExemption;
use App\Models\ClubServiceHourRecord;
use App\Models\ClubServiceHourRequirement;
use App\Models\ClubYearPeriod;
use App\Models\User;
use App\Services\ClubServiceHourLedger;
use App\Support\ClubPermissions;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClubServiceHourController extends Controller
{
    public function index(Request $request, Club $club, ClubServiceHourLedger $ledger)
    {
        $this->authorizeServiceHours($request, $club, ClubPermissions::MEMBERS_VIEW);
        $data = $request->validate(['period_id' => ['required', 'integer']]);
        $period = $this->period($club, (int) $data['period_id']);

        return response()->json(['data' => $ledger->summary($club, $period)]);
    }

    public function storeRequirement(Request $request, Club $club, ClubServiceHourLedger $ledger)
    {
        $this->authorizeServiceHours($request, $club, ClubPermissions::MEMBERS_MANAGE);
        $data = $request->validate([
            'period_id' => ['required', 'integer'],
            'role_definition_id' => ['required', 'integer'],
            'required_minutes' => ['required', 'integer', 'min:0', 'max:60000'],
            'replacement_rate_cents' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'replacement_currency' => ['nullable', 'string', 'size:3'],
        ]);
        $period = $this->period($club, (int) $data['period_id']);
        $role = $this->role($club, (int) $data['role_definition_id']);
        $this->ensurePeriodOpen($period);

        $requirement = ClubServiceHourRequirement::query()->updateOrCreate(
            ['club_year_period_id' => $period->id, 'club_role_definition_id' => $role->id],
            [
                'club_id' => $club->id,
                'required_minutes' => $data['required_minutes'],
                'replacement_rate_cents' => $data['replacement_rate_cents'] ?? null,
                'replacement_currency' => strtoupper($data['replacement_currency'] ?? 'EUR'),
                'created_by' => $request->user()->id,
            ],
        );

        return response()->json(['data' => $ledger->requirementPayload($requirement->load('roleDefinition'))], $requirement->wasRecentlyCreated ? 201 : 200);
    }

    public function lockRequirement(Request $request, Club $club, ClubServiceHourRequirement $requirement)
    {
        $this->authorizeRequirement($request, $club, $requirement, ClubPermissions::MEMBERS_MANAGE);
        $requirement->forceFill(['locked' => true, 'locked_by' => $request->user()->id, 'locked_at' => now()])->save();

        return response()->json(['data' => ['locked' => true]]);
    }

    public function storeRecord(Request $request, Club $club)
    {
        $this->authorizeServiceHours($request, $club, ClubPermissions::MEMBERS_MANAGE);
        $data = $request->validate([
            'period_id' => ['required', 'integer'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'replacement_for_user_id' => ['nullable', 'integer', 'exists:users,id', 'different:user_id'],
            'kind' => ['nullable', Rule::in(['service', 'replacement'])],
            'served_on' => ['required', 'date_format:Y-m-d'],
            'minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'title' => ['required', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $period = $this->period($club, (int) $data['period_id']);
        $this->ensurePeriodOpen($period);
        $this->member($club, (int) $data['user_id']);
        if (! empty($data['replacement_for_user_id'])) {
            $this->member($club, (int) $data['replacement_for_user_id']);
        }

        $record = ClubServiceHourRecord::query()->create([
            'club_id' => $club->id,
            'club_year_period_id' => $period->id,
            'user_id' => $data['user_id'],
            'recorded_by' => $request->user()->id,
            'replacement_for_user_id' => $data['replacement_for_user_id'] ?? null,
            'kind' => $data['kind'] ?? (empty($data['replacement_for_user_id']) ? 'service' : 'replacement'),
            'status' => 'pending',
            'served_on' => $data['served_on'],
            'minutes' => $data['minutes'],
            'title' => $data['title'],
            'notes' => $data['notes'] ?? null,
        ]);

        return response()->json(['data' => $this->recordPayload($record)], 201);
    }

    public function confirmRecord(Request $request, Club $club, ClubServiceHourRecord $record, ClubServiceHourLedger $ledger)
    {
        $this->authorizeRecord($request, $club, $record, ClubPermissions::MEMBERS_MANAGE);

        return response()->json(['data' => $this->recordPayload($ledger->confirm($record, $request->user()))]);
    }

    public function correctRecord(Request $request, Club $club, ClubServiceHourRecord $record, ClubServiceHourLedger $ledger)
    {
        $this->authorizeRecord($request, $club, $record, ClubPermissions::MEMBERS_MANAGE);
        $data = $request->validate([
            'minutes_delta' => ['required', 'integer', 'min:-1440', 'max:1440', 'not_in:0'],
            'reason' => ['required', 'string', 'max:240'],
        ]);

        return response()->json(['data' => $ledger->correct($record, $request->user(), (int) $data['minutes_delta'], $data['reason'])], 201);
    }

    public function storeExemption(Request $request, Club $club)
    {
        $this->authorizeServiceHours($request, $club, ClubPermissions::MEMBERS_MANAGE);
        $data = $request->validate([
            'period_id' => ['required', 'integer'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'minutes' => ['required', 'integer', 'min:1', 'max:60000'],
            'reason' => ['required', 'string', 'max:240'],
        ]);
        $period = $this->period($club, (int) $data['period_id']);
        $this->ensurePeriodOpen($period);
        $this->member($club, (int) $data['user_id']);

        $exemption = ClubServiceHourExemption::query()->create([
            'club_id' => $club->id,
            'club_year_period_id' => $period->id,
            'user_id' => $data['user_id'],
            'approved_by' => $request->user()->id,
            'minutes' => $data['minutes'],
            'reason' => $data['reason'],
        ]);

        return response()->json(['data' => $exemption], 201);
    }

    private function authorizeServiceHours(Request $request, Club $club, string $permission): void
    {
        abort_unless($request->user() && ClubPermissions::allows($club, $request->user(), $permission), 403);
    }

    private function authorizeRequirement(Request $request, Club $club, ClubServiceHourRequirement $requirement, string $permission): void
    {
        $this->authorizeServiceHours($request, $club, $permission);
        abort_unless((int) $requirement->club_id === (int) $club->id, 404);
    }

    private function authorizeRecord(Request $request, Club $club, ClubServiceHourRecord $record, string $permission): void
    {
        $this->authorizeServiceHours($request, $club, $permission);
        abort_unless((int) $record->club_id === (int) $club->id, 404);
    }

    private function period(Club $club, int $id): ClubYearPeriod
    {
        return ClubYearPeriod::query()->where('club_id', $club->id)->whereKey($id)->firstOrFail();
    }

    private function role(Club $club, int $id): ClubRoleDefinition
    {
        return ClubRoleDefinition::query()->where('club_id', $club->id)->whereKey($id)->firstOrFail();
    }

    private function member(Club $club, int $id): User
    {
        return $club->users()->where('users.id', $id)->firstOrFail();
    }

    private function ensurePeriodOpen(ClubYearPeriod $period): void
    {
        $locked = ClubServiceHourRequirement::query()
            ->where('club_year_period_id', $period->id)
            ->where('locked', true)
            ->exists();
        if ($locked) {
            throw ValidationException::withMessages(['period_id' => 'Service hours for this period are locked.']);
        }
    }

    private function recordPayload(ClubServiceHourRecord $record): array
    {
        return [
            'id' => $record->id,
            'period_id' => $record->club_year_period_id,
            'user_id' => $record->user_id,
            'replacement_for_user_id' => $record->replacement_for_user_id,
            'kind' => $record->kind,
            'status' => $record->status,
            'served_on' => $record->served_on->format('Y-m-d'),
            'minutes' => $record->minutes,
            'title' => $record->title,
            'confirmed_at' => $record->confirmed_at?->toJSON(),
            'confirmation_snapshot' => $record->confirmation_snapshot,
        ];
    }
}
