<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\ClubFundingProgram;
use App\Services\ClubFundingProgramService;
use App\Support\ClubPermissions;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClubFundingProgramController extends Controller
{
    public function __construct(private readonly ClubFundingProgramService $programs) {}

    public function index(Request $request, Club $club)
    {
        $this->programs->authorize($club, $request->user(), ClubPermissions::FINANCE_VIEW);

        $programs = ClubFundingProgram::query()
            ->where('club_id', $club->id)
            ->with(['yearPeriod', 'responsible'])
            ->orderByRaw('deadline_on IS NULL, deadline_on ASC')
            ->latest('id')
            ->get();

        return response()->json(['data' => [
            'programs' => $programs->map(fn (ClubFundingProgram $program) => $this->payload($program)),
            'statuses' => ClubFundingProgram::STATUSES,
            'can_manage' => ClubPermissions::allows($club, $request->user(), ClubPermissions::FINANCE_EDIT),
            'can_approve' => ClubPermissions::allows($club, $request->user(), ClubPermissions::FINANCE_APPROVE),
        ]]);
    }

    public function store(Request $request, Club $club)
    {
        $program = $this->programs->create($club, $request->user(), $this->validatedData($request, $club));

        return response()->json(['data' => $this->payload($program)], 201);
    }

    public function update(Request $request, Club $club, ClubFundingProgram $fundingProgram)
    {
        $this->programs->ensureClub($club, $fundingProgram);

        $program = $this->programs->update($fundingProgram, $request->user(), $this->validatedData($request, $club));

        return response()->json(['data' => $this->payload($program)]);
    }

    public function transition(Request $request, Club $club, ClubFundingProgram $fundingProgram)
    {
        $this->programs->ensureClub($club, $fundingProgram);

        $data = $request->validate([
            'status' => ['required', Rule::in(ClubFundingProgram::STATUSES)],
            'submitted_on' => ['nullable', 'date'],
            'approved_on' => ['nullable', 'date'],
            'paid_out_on' => ['nullable', 'date'],
            'approved_amount_cents' => ['nullable', 'integer', 'min:0', 'max:999999999'],
            'own_contribution_cents' => ['nullable', 'integer', 'min:0', 'max:999999999'],
            'paid_out_amount_cents' => ['nullable', 'integer', 'min:0', 'max:999999999'],
        ]);

        $status = $data['status'];
        unset($data['status']);

        $program = $this->programs->transition($fundingProgram, $request->user(), $status, $data);

        return response()->json(['data' => $this->payload($program)]);
    }

    private function validatedData(Request $request, Club $club): array
    {
        $request->merge([
            'program_name' => trim((string) $request->input('program_name')),
            'provider_name' => trim((string) $request->input('provider_name')),
        ]);

        $data = $request->validate([
            'club_year_period_id' => ['nullable', 'integer', Rule::exists('club_year_periods', 'id')->where('club_id', $club->id)],
            'responsible_user_id' => ['nullable', 'integer', Rule::exists('club_user', 'user_id')->where('club_id', $club->id)],
            'program_name' => ['required', 'string', 'max:180'],
            'provider_name' => ['required', 'string', 'max:180'],
            'status' => ['nullable', Rule::in(['draft', 'ready'])],
            'deadline_on' => ['nullable', 'date'],
            'requested_amount_cents' => ['required', 'integer', 'min:0', 'max:999999999'],
            'approved_amount_cents' => ['nullable', 'integer', 'min:0', 'max:999999999'],
            'own_contribution_cents' => ['nullable', 'integer', 'min:0', 'max:999999999'],
            'paid_out_amount_cents' => ['nullable', 'integer', 'min:0', 'max:999999999'],
            'contact_snapshot' => ['nullable', 'array'],
            'contact_snapshot.name' => ['nullable', 'string', 'max:160'],
            'contact_snapshot.role' => ['nullable', 'string', 'max:160'],
            'contact_snapshot.email' => ['nullable', 'email', 'max:190'],
            'contact_snapshot.phone' => ['nullable', 'string', 'max:80'],
            'application_snapshot' => ['nullable', 'array'],
            'application_snapshot.reference' => ['nullable', 'string', 'max:160'],
            'application_snapshot.channel' => ['nullable', 'string', 'max:80'],
            'application_snapshot.requirements' => ['nullable', 'array'],
            'internal_note' => ['nullable', 'string', 'max:2000'],
        ]);

        return array_replace([
            'status' => 'draft',
            'approved_amount_cents' => 0,
            'own_contribution_cents' => 0,
            'paid_out_amount_cents' => 0,
        ], $data);
    }

    private function payload(ClubFundingProgram $program): array
    {
        return [
            'id' => $program->id,
            'club_year_period_id' => $program->club_year_period_id,
            'year_period_name' => $program->yearPeriod?->name,
            'responsible_user_id' => $program->responsible_user_id,
            'responsible_name' => $program->responsible?->name,
            'program_name' => $program->program_name,
            'provider_name' => $program->provider_name,
            'status' => $program->status,
            'allowed_transitions' => ClubFundingProgramService::allowedTransitions($program->status),
            'deadline_on' => $program->deadline_on?->format('Y-m-d'),
            'submitted_on' => $program->submitted_on?->format('Y-m-d'),
            'approved_on' => $program->approved_on?->format('Y-m-d'),
            'paid_out_on' => $program->paid_out_on?->format('Y-m-d'),
            'requested_amount_cents' => $program->requested_amount_cents,
            'approved_amount_cents' => $program->approved_amount_cents,
            'own_contribution_cents' => $program->own_contribution_cents,
            'paid_out_amount_cents' => $program->paid_out_amount_cents,
            'contact_snapshot' => $program->contact_snapshot,
            'application_snapshot' => $program->application_snapshot,
            'internal_note' => $program->internal_note,
            'status_changed_by' => $program->status_changed_by,
            'status_changed_at' => $program->status_changed_at?->toJSON(),
        ];
    }
}
