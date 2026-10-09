<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\ClubBudget;
use App\Services\ClubBudgetReportService;
use App\Support\ClubAuditLog;
use App\Support\ClubFinanceWorkspaceReadiness;
use App\Support\ClubPermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClubBudgetController extends Controller
{
    public function index(Request $request, Club $club)
    {
        $this->authorizeBudget($request, $club, ClubPermissions::FINANCE_VIEW);

        $budgets = ClubBudget::query()
            ->where('club_id', $club->id)
            ->with(['yearPeriod', 'department', 'team', 'responsible'])
            ->orderBy('club_year_period_id')
            ->orderByRaw("CASE scope_type WHEN 'club' THEN 1 WHEN 'department' THEN 2 WHEN 'team' THEN 3 ELSE 4 END")
            ->orderBy('name')
            ->get();
        $reports = app(ClubBudgetReportService::class)->reports($club, $budgets);

        return response()->json(['data' => [
            'budgets' => $budgets->map(fn (ClubBudget $budget) => $this->payload($budget, $reports[$budget->id] ?? null)),
            'can_manage' => (bool) $request->user() && ClubPermissions::allows($club, $request->user(), ClubPermissions::FINANCE_EDIT),
            'can_approve' => (bool) $request->user() && ClubPermissions::allows($club, $request->user(), ClubPermissions::FINANCE_APPROVE),
        ]]);
    }

    public function store(Request $request, Club $club)
    {
        $this->authorizeBudget($request, $club, ClubPermissions::FINANCE_EDIT);

        $budget = ClubBudget::query()->create(array_replace($this->validatedData($request, $club), [
            'club_id' => $club->id,
        ]));
        $this->audit($club, $request, 'club.budget.created', $budget);

        return response()->json(['data' => $this->payload($budget->load(['yearPeriod', 'department', 'team', 'responsible']))], 201);
    }

    public function update(Request $request, Club $club, ClubBudget $budget)
    {
        $this->authorizeExistingBudget($request, $club, $budget, ClubPermissions::FINANCE_EDIT);

        $budget->update($this->validatedData($request, $club, $budget));
        $this->audit($club, $request, 'club.budget.updated', $budget);

        return response()->json(['data' => $this->payload($budget->refresh()->load(['yearPeriod', 'department', 'team', 'responsible']))]);
    }

    public function approve(Request $request, Club $club, ClubBudget $budget)
    {
        $this->authorizeExistingBudget($request, $club, $budget, ClubPermissions::FINANCE_APPROVE);

        $data = $request->validate([
            'approval_status' => ['required', Rule::in(['submitted', 'approved', 'rejected', 'archived'])],
        ]);
        $budget->forceFill([
            'approval_status' => $data['approval_status'],
            'approved_at' => $data['approval_status'] === 'approved' ? now() : null,
        ])->save();
        $this->audit($club, $request, 'club.budget.approval_updated', $budget);

        return response()->json(['data' => $this->payload($budget->refresh()->load(['yearPeriod', 'department', 'team', 'responsible']))]);
    }

    private function validatedData(Request $request, Club $club, ?ClubBudget $budget = null): array
    {
        $request->merge([
            'name' => trim((string) $request->input('name')),
            'project_name' => $request->filled('project_name') ? trim((string) $request->input('project_name')) : null,
        ]);

        $data = $request->validate([
            'parent_id' => ['nullable', 'integer', Rule::exists('club_budgets', 'id')->where('club_id', $club->id)],
            'club_year_period_id' => ['required', 'integer', Rule::exists('club_year_periods', 'id')->where(fn ($query) => $query->where('club_id', $club->id)->where('type', 'business'))],
            'responsible_user_id' => ['nullable', 'integer', Rule::exists('club_user', 'user_id')->where('club_id', $club->id)],
            'scope_type' => ['required', Rule::in(ClubBudget::SCOPE_TYPES)],
            'club_department_id' => ['nullable', 'integer', Rule::exists('club_departments', 'id')->where('club_id', $club->id)],
            'team_id' => ['nullable', 'integer', Rule::exists('teams', 'id')->where('club_id', $club->id)],
            'project_name' => ['nullable', 'string', 'max:160'],
            'club_project_id' => ['nullable', 'integer', Rule::exists('club_projects', 'id')->where('club_id', $club->id)],
            'name' => ['required', 'string', 'max:160'],
            'version' => ['required', 'integer', 'min:1', 'max:999'],
            'approval_status' => ['required', Rule::in(ClubBudget::APPROVAL_STATUSES)],
            'planned_income_cents' => ['required', 'integer', 'min:0', 'max:999999999'],
            'planned_expense_cents' => ['required', 'integer', 'min:0', 'max:999999999'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if (in_array($data['approval_status'], ['approved', 'rejected', 'archived'], true)
            || ($budget && in_array($budget->approval_status, ['approved', 'archived'], true))) {
            $this->authorizeBudget($request, $club, ClubPermissions::FINANCE_APPROVE);
        }
        if (! empty($data['parent_id'])) {
            $parent = ClubBudget::findOrFail($data['parent_id']);
            if ((int) $parent->club_year_period_id !== (int) $data['club_year_period_id']) {
                throw ValidationException::withMessages(['parent_id' => __('validation.invalid')]);
            }
            $seen = [];
            while ($parent) {
                if (($budget && $parent->id === $budget->id) || in_array($parent->id, $seen, true)) {
                    throw ValidationException::withMessages(['parent_id' => __('validation.invalid')]);
                }
                $seen[] = $parent->id;
                $parent = $parent->parent;
            }
        }

        if ($budget && isset($data['parent_id']) && (int) $data['parent_id'] === (int) $budget->id) {
            throw ValidationException::withMessages(['parent_id' => __('validation.invalid')]);
        }

        $this->validateScope($data);
        $this->validateUniqueVersion($club, $data, $budget);

        $result = array_replace($data, [
            'club_department_id' => $data['scope_type'] === 'department' ? $data['club_department_id'] : null,
            'team_id' => $data['scope_type'] === 'team' ? $data['team_id'] : null,
            'project_name' => $data['scope_type'] === 'project' ? $data['project_name'] : null,
            'club_project_id' => $data['scope_type'] === 'project' ? ($data['club_project_id'] ?? null) : null,
            'approved_at' => $data['approval_status'] === 'approved' ? now() : null,
        ]);

        return ClubFinanceWorkspaceReadiness::ready() ? $result : Arr::except($result, ['club_project_id']);
    }

    private function validateScope(array $data): void
    {
        if ($data['scope_type'] === 'club' && (($data['club_department_id'] ?? null) || ($data['team_id'] ?? null) || ($data['project_name'] ?? null))) {
            throw ValidationException::withMessages(['scope_type' => __('validation.invalid')]);
        }
        if ($data['scope_type'] === 'department' && empty($data['club_department_id'])) {
            throw ValidationException::withMessages(['club_department_id' => __('validation.required')]);
        }
        if ($data['scope_type'] === 'team' && empty($data['team_id'])) {
            throw ValidationException::withMessages(['team_id' => __('validation.required')]);
        }
        if ($data['scope_type'] === 'project' && empty($data['project_name'])) {
            throw ValidationException::withMessages(['project_name' => __('validation.required')]);
        }
    }

    private function validateUniqueVersion(Club $club, array $data, ?ClubBudget $budget): void
    {
        $exists = ClubBudget::query()
            ->where('club_id', $club->id)
            ->where('club_year_period_id', $data['club_year_period_id'])
            ->where('scope_type', $data['scope_type'])
            ->where('name', $data['name'])
            ->where('version', $data['version'])
            ->when($budget, fn ($query) => $query->whereKeyNot($budget->id))
            ->when($data['scope_type'] === 'department', fn ($query) => $query->where('club_department_id', $data['club_department_id']))
            ->when($data['scope_type'] !== 'department', fn ($query) => $query->whereNull('club_department_id'))
            ->when($data['scope_type'] === 'team', fn ($query) => $query->where('team_id', $data['team_id']))
            ->when($data['scope_type'] !== 'team', fn ($query) => $query->whereNull('team_id'))
            ->when($data['scope_type'] === 'project', fn ($query) => $query->where('project_name', $data['project_name']))
            ->when($data['scope_type'] !== 'project', fn ($query) => $query->whereNull('project_name'))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['name' => __('validation.unique')]);
        }
    }

    private function authorizeBudget(Request $request, Club $club, string $permission): void
    {
        abort_unless($request->user() && ClubPermissions::allows($club, $request->user(), $permission), 403);
    }

    private function authorizeExistingBudget(Request $request, Club $club, ClubBudget $budget, string $permission): void
    {
        $this->authorizeBudget($request, $club, $permission);
        abort_unless((int) $budget->club_id === (int) $club->id, 404);
    }

    private function payload(ClubBudget $budget, ?array $financialReport = null): array
    {
        return [
            'id' => $budget->id,
            'parent_id' => $budget->parent_id,
            'club_year_period_id' => $budget->club_year_period_id,
            'year_period' => $budget->yearPeriod ? [
                'id' => $budget->yearPeriod->id,
                'name' => $budget->yearPeriod->name,
                'starts_on' => $budget->yearPeriod->starts_on->format('Y-m-d'),
                'ends_on' => $budget->yearPeriod->ends_on->format('Y-m-d'),
            ] : null,
            'scope_type' => $budget->scope_type,
            'club_department_id' => $budget->club_department_id,
            'department_name' => $budget->department?->name,
            'team_id' => $budget->team_id,
            'team_name' => $budget->team?->name,
            'project_name' => $budget->project_name,
            'club_project_id' => $budget->club_project_id,
            'name' => $budget->name,
            'version' => $budget->version,
            'responsible_user_id' => $budget->responsible_user_id,
            'responsible_name' => $budget->responsible?->name,
            'approval_status' => $budget->approval_status,
            'planned_income_cents' => $budget->planned_income_cents,
            'planned_expense_cents' => $budget->planned_expense_cents,
            'financial_report' => $financialReport ?? app(ClubBudgetReportService::class)->reports(
                $budget->club, ClubBudget::where('club_id', $budget->club_id)->with('yearPeriod')->get(),
            )[$budget->id],
            'approved_at' => $budget->approved_at?->toISOString(),
            'notes' => $budget->notes,
        ];
    }

    private function audit(Club $club, Request $request, string $type, ClubBudget $budget): void
    {
        ClubAuditLog::record($club, $request->user(), $type, $budget, [
            'entity_type' => 'budget',
            'scope_type' => $budget->scope_type,
            'approval_status' => $budget->approval_status,
        ]);
    }
}
