<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ClubFinanceEntry;
use App\Models\ClubMoneyAccount;
use App\Models\Event;
use App\Models\Team;
use App\Models\TeamFee;
use App\Models\TeamPenaltyRule;
use App\Models\User;
use App\Support\ClubAuditLog;
use App\Support\ClubFinanceWorkspaceReadiness;
use App\Support\ClubPermissions;
use App\Support\TeamRoles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TeamPenaltyController extends Controller
{
    public function index(Request $request, Team $team)
    {
        $this->ensureCanViewTeamPenalties($request, $team);

        $data = $request->validate([
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
        ]);
        $event = null;
        if (! empty($data['event_id'])) {
            $event = Event::query()
                ->where('team_id', $team->id)
                ->findOrFail($data['event_id']);
        }

        $canManage = $this->canManageTeamCashbox($request->user(), $team);
        $feeQuery = $team->fees()
            ->with(['member:id,name,email,profile_photo_path', 'collector:id,name,email', 'penaltyRule'])
            ->when($event, fn ($query) => $query->where('event_id', $event->id))
            ->when(! $canManage, fn ($query) => $query->where('user_id', $request->user()->id));
        $totals = (clone $feeQuery)->selectRaw('status, COUNT(*) as count, SUM(amount) as amount')->groupBy('status')->get()->keyBy('status');
        $fees = $feeQuery
            ->latest('id')
            ->limit(100)
            ->get();

        return response()->json([
            'data' => [
                'can_manage' => $canManage,
                'money_accounts' => $canManage && ClubFinanceWorkspaceReadiness::ready()
                    ? ClubMoneyAccount::where('club_id', $team->club_id)->where('team_id', $team->id)->get(['id', 'name', 'type']) : [],
                'event' => $event ? [
                    'id' => $event->id,
                    'title' => $event->title,
                    'uses_penalty_catalog' => $event->uses_penalty_catalog,
                ] : null,
                'rules' => $team->penaltyRules()
                    ->orderBy('sort_order')
                    ->orderBy('title')
                    ->get()
                    ->map(fn (TeamPenaltyRule $rule) => $this->rulePayload($rule))
                    ->values(),
                'fees' => $fees
                    ->map(fn (TeamFee $fee) => $this->feePayload($fee))
                    ->values(),
                'summary' => [
                    'open_amount' => round((float) ($totals->get('open')?->amount ?? 0), 2),
                    'paid_amount' => round((float) ($totals->get('paid')?->amount ?? 0), 2),
                    'open_count' => (int) ($totals->get('open')?->count ?? 0),
                ],
            ],
        ]);
    }

    public function storeRule(Request $request, Team $team)
    {
        $this->ensureCanManageTeamCashbox($request, $team);

        $rule = $team->penaltyRules()->create($this->validatedRuleData($request));

        return response()->json(['data' => $this->rulePayload($rule)], 201);
    }

    public function updateRule(Request $request, Team $team, TeamPenaltyRule $penaltyRule)
    {
        $this->ensureRuleBelongsToTeam($team, $penaltyRule);
        $this->ensureCanManageTeamCashbox($request, $team);

        $penaltyRule->update($this->validatedRuleData($request, updating: true));

        return response()->json(['data' => $this->rulePayload($penaltyRule->refresh())]);
    }

    public function destroyRule(Request $request, Team $team, TeamPenaltyRule $penaltyRule)
    {
        $this->ensureRuleBelongsToTeam($team, $penaltyRule);
        $this->ensureCanManageTeamCashbox($request, $team);

        $penaltyRule->update(['is_active' => false]);

        return response()->json(['data' => $this->rulePayload($penaltyRule->refresh())]);
    }

    public function storeFee(Request $request, Team $team)
    {
        $this->ensureCanManageTeamCashbox($request, $team);

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
            'penalty_rule_id' => ['nullable', 'integer', 'exists:team_penalty_rules,id'],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'minutes' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'note' => ['nullable', 'string', 'max:2000'],
            'due_date' => ['nullable', 'date'],
        ]);

        abort_unless($team->users()->where('users.id', $data['user_id'])->exists(), 422, 'Mitglied gehört nicht zu diesem Team.');

        $event = null;
        if (! empty($data['event_id'])) {
            $event = Event::query()
                ->where('team_id', $team->id)
                ->findOrFail($data['event_id']);

            abort_unless($event->uses_penalty_catalog, 422, 'Dieses Event arbeitet nicht mit Strafkatalog.');
            abort_unless(
                $event->participantRecords()
                    ->where('user_id', $data['user_id'])
                    ->whereIn('status', ['yes', 'late'])
                    ->exists(),
                422,
                'Strafen aus einem Event können nur an anwesende oder verspätete Teilnehmer vergeben werden.'
            );
        }

        $rule = null;
        if (! empty($data['penalty_rule_id'])) {
            $rule = TeamPenaltyRule::query()->where('team_id', $team->id)->findOrFail($data['penalty_rule_id']);
            abort_unless($rule->is_active, 422, 'Diese Strafregel ist nicht aktiv.');
        }

        $amount = $rule
            ? $rule->calculateAmount($data['minutes'] ?? null, array_key_exists('amount', $data) ? (float) $data['amount'] : null)
            : round((float) ($data['amount'] ?? 0), 2);

        abort_if($amount <= 0 && (! $rule || $rule->calculation_type !== 'item'), 422, 'Betrag muss größer als 0 sein.');

        $note = trim((string) ($data['note'] ?? ''));
        if ($rule && $rule->calculation_type === 'item' && $rule->unit_label) {
            $note = trim($note === '' ? $rule->unit_label : $note.' - '.$rule->unit_label);
        }

        $fee = $team->fees()->create([
            'event_id' => $event?->id,
            'user_id' => $data['user_id'],
            'collector_id' => $request->user()->id,
            'penalty_rule_id' => $rule?->id,
            'category' => 'penalty',
            'amount' => $amount,
            'currency' => $rule?->currency ?? 'EUR',
            'status' => 'open',
            'note' => $note !== '' ? $note : $rule?->title,
            'due_date' => $data['due_date'] ?? null,
        ]);

        return response()->json(['data' => $this->feePayload($fee->load(['member', 'collector', 'penaltyRule']))], 201);
    }

    public function markFeePaid(Request $request, Team $team, TeamFee $fee)
    {
        $this->ensureFeeBelongsToTeam($team, $fee);
        $this->ensureCanManageTeamCashbox($request, $team);

        $data = $request->validate([
            'account' => ['nullable', Rule::in(['cash', 'bank'])],
            'paid_on' => ['nullable', 'date'],
            'club_money_account_id' => ['nullable', 'integer'],
        ]);
        if (! ClubFinanceWorkspaceReadiness::ready()) {
            $fee->update(['status' => 'paid', 'collector_id' => $request->user()->id, 'paid_at' => now()->toDateString()]);

            return response()->json(['data' => $this->feePayload($fee->load(['member', 'collector', 'penaltyRule']))]);
        }
        $fee = DB::transaction(function () use ($fee, $team, $request, $data) {
            $fee = TeamFee::whereKey($fee->id)->lockForUpdate()->firstOrFail();
            if ($fee->status === 'paid') {
                return $fee;
            }
            abort_unless($fee->status === 'open', 422);
            $date = $data['paid_on'] ?? now()->toDateString();
            if ($team->club_id && $fee->amount > 0) {
                abort_unless($fee->currency === 'EUR', 422, 'Vereinskassen unterstützen derzeit EUR.');
                Team::whereKey($team->id)->lockForUpdate()->firstOrFail();
                $account = ! empty($data['club_money_account_id'])
                    ? ClubMoneyAccount::where('club_id', $team->club_id)->where('team_id', $team->id)->findOrFail($data['club_money_account_id'])
                    : ClubMoneyAccount::firstOrCreate([
                        'club_id' => $team->club_id, 'team_id' => $team->id,
                        'type' => $data['account'] ?? 'cash',
                    ], ['name' => $team->name.' '.(($data['account'] ?? 'cash') === 'cash' ? 'Kasse' : 'Bank')]);
                $entry = ClubFinanceEntry::create([
                    'club_id' => $team->club_id, 'team_id' => $team->id,
                    'club_department_id' => $team->club_department_id,
                    'user_id' => $request->user()->id, 'type' => 'income',
                    'account' => $account->type, 'club_money_account_id' => $account->id, 'category' => 'team_penalty',
                    'title' => $team->name.' | '.$fee->note,
                    'amount' => $fee->amount, 'booked_on' => $date,
                    'reference' => 'TEAM-FEE-'.$fee->id,
                ]);
                $fee->club_finance_entry_id = $entry->id;
            }
            $fee->fill(['status' => 'paid', 'collector_id' => $request->user()->id, 'paid_at' => $date])->save();
            if ($team->club_id) {
                ClubAuditLog::record($team->club, $request->user(), 'club.team_fee.paid', $fee, [
                    'team_id' => $team->id, 'finance_entry_id' => $fee->club_finance_entry_id,
                ]);
            }

            return $fee;
        });

        return response()->json(['data' => $this->feePayload($fee->load(['member', 'collector', 'penaltyRule']))]);
    }

    public function cancelFee(Request $request, Team $team, TeamFee $fee)
    {
        $this->ensureFeeBelongsToTeam($team, $fee);
        $this->ensureCanManageTeamCashbox($request, $team);

        $fee = DB::transaction(function () use ($fee, $request) {
            $fee = TeamFee::whereKey($fee->id)->lockForUpdate()->firstOrFail();
            if ($fee->status === 'paid' && $fee->club_finance_entry_id) {
                abort(422, 'Eine bezahlte Strafe muss vor der Stornierung erstattet werden.');
            }
            $fee->update(['status' => 'cancelled', 'collector_id' => $request->user()->id]);

            return $fee;
        });

        return response()->json(['data' => $this->feePayload($fee->load(['member', 'collector', 'penaltyRule']))]);
    }

    public function refundFee(Request $request, Team $team, TeamFee $fee)
    {
        abort_unless(ClubFinanceWorkspaceReadiness::ready(), 503);
        $this->ensureFeeBelongsToTeam($team, $fee);
        $this->ensureCanManageTeamCashbox($request, $team);
        $data = $request->validate(['refunded_on' => ['required', 'date']]);
        $fee = DB::transaction(function () use ($fee, $team, $request, $data) {
            $fee = TeamFee::whereKey($fee->id)->lockForUpdate()->firstOrFail();
            if ($fee->status === 'cancelled' && $fee->club_finance_entry_id && ClubFinanceEntry::where('reversal_of_id', $fee->club_finance_entry_id)->exists()) {
                return $fee;
            }
            abort_unless($fee->status === 'paid' && $fee->club_finance_entry_id, 422);
            $original = ClubFinanceEntry::findOrFail($fee->club_finance_entry_id);
            ClubFinanceEntry::create([
                'club_id' => $original->club_id, 'user_id' => $request->user()->id,
                'team_id' => $original->team_id, 'club_department_id' => $original->club_department_id,
                'club_money_account_id' => $original->club_money_account_id,
                'type' => 'expense', 'account' => $original->account, 'category' => 'team_penalty_refund',
                'title' => $original->title, 'amount' => $original->amount,
                'booked_on' => $data['refunded_on'], 'reference' => 'TEAM-FEE-REFUND-'.$fee->id,
                'reversal_of_id' => $original->id,
            ]);
            $fee->update(['status' => 'cancelled', 'collector_id' => $request->user()->id]);
            ClubAuditLog::record($team->club, $request->user(), 'club.team_fee.refunded', $fee, [
                'team_id' => $team->id, 'finance_entry_id' => $fee->club_finance_entry_id,
            ]);

            return $fee;
        });

        return response()->json(['data' => $this->feePayload($fee->load(['member', 'collector', 'penaltyRule']))]);
    }

    private function validatedRuleData(Request $request, bool $updating = false): array
    {
        return $request->validate([
            'title' => [$updating ? 'sometimes' : 'required', 'string', 'max:255'],
            'trigger' => ['sometimes', Rule::in(TeamPenaltyRule::TRIGGERS)],
            'calculation_type' => ['sometimes', Rule::in(TeamPenaltyRule::CALCULATION_TYPES)],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'currency' => ['sometimes', Rule::in(['EUR', 'USD'])],
            'threshold_minutes' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'max_amount' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'unit_label' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:100000'],
        ]);
    }

    private function ensureCanViewTeamPenalties(Request $request, Team $team): void
    {
        abort_unless(
            Team::visibleTo($request->user())->whereKey($team->id)->exists(),
            404
        );
    }

    private function ensureCanManageTeamCashbox(Request $request, Team $team): void
    {
        abort_unless($this->canManageTeamCashbox($request->user(), $team), 403);
    }

    private function canManageTeamCashbox(User $user, Team $team): bool
    {
        return ClubPermissions::allowsForTeam($team, $user, ClubPermissions::TEAM_CASHBOX_MANAGE)
            || (! ClubPermissions::explicitlyDenies($team->club, $user, ClubPermissions::TEAM_CASHBOX_MANAGE)
                && ($user->can('update', $team)
                    || $team->users()
                        ->where('users.id', $user->id)
                        ->wherePivotIn('role', TeamRoles::TEAM_STAFF_ROLES)
                        ->exists()));
    }

    private function ensureRuleBelongsToTeam(Team $team, TeamPenaltyRule $rule): void
    {
        abort_unless($rule->team_id === $team->id, 404);
    }

    private function ensureFeeBelongsToTeam(Team $team, TeamFee $fee): void
    {
        abort_unless($fee->team_id === $team->id, 404);
    }

    private function rulePayload(TeamPenaltyRule $rule): array
    {
        return [
            'id' => $rule->id,
            'team_id' => $rule->team_id,
            'title' => $rule->title,
            'trigger' => $rule->trigger,
            'calculation_type' => $rule->calculation_type,
            'amount' => $rule->amount,
            'currency' => $rule->currency,
            'threshold_minutes' => $rule->threshold_minutes,
            'max_amount' => $rule->max_amount,
            'unit_label' => $rule->unit_label,
            'description' => $rule->description,
            'is_active' => $rule->is_active,
            'sort_order' => $rule->sort_order,
        ];
    }

    private function feePayload(TeamFee $fee): array
    {
        return [
            'club_finance_entry_id' => $fee->club_finance_entry_id,
            'id' => $fee->id,
            'team_id' => $fee->team_id,
            'user_id' => $fee->user_id,
            'collector_id' => $fee->collector_id,
            'penalty_rule_id' => $fee->penalty_rule_id,
            'event_id' => $fee->event_id,
            'category' => $fee->category,
            'amount' => $fee->amount,
            'currency' => $fee->currency,
            'status' => $fee->status,
            'note' => $fee->note,
            'due_date' => $fee->due_date?->toDateString(),
            'paid_at' => $fee->paid_at?->toDateString(),
            'member' => $fee->relationLoaded('member') && $fee->member ? [
                'id' => $fee->member->id,
                'name' => $fee->member->name,
                'email' => $fee->member->email,
            ] : null,
            'collector' => $fee->relationLoaded('collector') && $fee->collector ? [
                'id' => $fee->collector->id,
                'name' => $fee->collector->name,
                'email' => $fee->collector->email,
            ] : null,
            'rule' => $fee->relationLoaded('penaltyRule') && $fee->penaltyRule
                ? $this->rulePayload($fee->penaltyRule)
                : null,
        ];
    }
}
