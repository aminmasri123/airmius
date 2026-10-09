<?php

namespace App\Services;

use App\Models\Club;
use App\Models\ClubBudget;
use App\Models\ClubMoneyAccount;
use App\Models\Team;
use App\Support\ClubFinanceWorkspaceReadiness;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClubFinanceScopeService
{
    public function validate(Request $request, Club $club): array
    {
        if (! ClubFinanceWorkspaceReadiness::ready()) {
            return [];
        }
        $rules = [];
        foreach ([
            'team_id' => 'teams', 'club_budget_id' => 'club_budgets',
            'club_department_id' => 'club_departments', 'club_project_id' => 'club_projects',
            'club_cost_center_id' => 'club_cost_centers', 'club_accounting_account_id' => 'club_accounting_accounts',
            'club_business_partner_id' => 'club_business_partners',
            'club_money_account_id' => 'club_money_accounts',
        ] as $field => $table) {
            $rules[$field] = ['nullable', 'integer', Rule::exists($table, 'id')->where('club_id', $club->id)];
        }
        $data = $request->validate($rules);
        if (! empty($data['club_money_account_id'])) {
            $account = ClubMoneyAccount::findOrFail($data['club_money_account_id']);
            if ($request->filled('account') && $request->input('account') !== $account->type) {
                throw ValidationException::withMessages(['account' => __('validation.invalid')]);
            }
            if ($account->team_id) {
                if (! empty($data['team_id']) && (int) $data['team_id'] !== (int) $account->team_id) {
                    throw ValidationException::withMessages(['team_id' => __('validation.invalid')]);
                }
                $data['team_id'] = $account->team_id;
            }
        }
        $budget = ! empty($data['club_budget_id']) ? ClubBudget::findOrFail($data['club_budget_id']) : null;
        $date = $request->input('booked_on') ?? $request->input('paid_on');
        if ($budget && $date && ($date < $budget->yearPeriod->starts_on->toDateString() || $date > $budget->yearPeriod->ends_on->toDateString())) {
            throw ValidationException::withMessages(['club_budget_id' => 'Das Buchungsdatum liegt außerhalb des Budgetjahres.']);
        }
        foreach ([
            'team_id' => $budget?->team_id,
            'club_department_id' => $budget?->club_department_id,
            'club_project_id' => $budget?->club_project_id,
        ] as $field => $value) {
            if (! $value) {
                continue;
            }
            if (! empty($data[$field]) && (int) $data[$field] !== (int) $value) {
                throw ValidationException::withMessages([$field => __('validation.invalid')]);
            }
            $data[$field] = $value;
        }
        if (! empty($data['team_id'])) {
            $department = Team::findOrFail($data['team_id'])->club_department_id;
            if (! empty($data['club_department_id']) && (int) $data['club_department_id'] !== (int) $department) {
                throw ValidationException::withMessages(['club_department_id' => __('validation.invalid')]);
            }
            $data['club_department_id'] = $department;
        }

        return $data;
    }
}
