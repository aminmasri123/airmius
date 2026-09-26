<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Team;
use App\Models\TeamFee;
use App\Models\TeamPenaltyRule;
use App\Models\User;
use App\Support\ClubPermissions;
use App\Support\TeamRoles;
use Illuminate\Http\Request;
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
        $fees = $team->fees()
            ->with(['member:id,name,email,profile_photo_path', 'collector:id,name,email', 'penaltyRule'])
            ->when($event, fn ($query) => $query->where('event_id', $event->id))
            ->when(! $canManage, fn ($query) => $query->where('user_id', $request->user()->id))
            ->latest('id')
            ->limit(100)
            ->get();

        return response()->json([
            'data' => [
                'can_manage' => $canManage,
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
                    'open_amount' => round((float) $fees->where('status', 'open')->sum('amount'), 2),
                    'paid_amount' => round((float) $fees->where('status', 'paid')->sum('amount'), 2),
                    'open_count' => $fees->where('status', 'open')->count(),
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

        $fee->update([
            'status' => 'paid',
            'collector_id' => $request->user()->id,
            'paid_at' => now()->toDateString(),
        ]);

        return response()->json(['data' => $this->feePayload($fee->load(['member', 'collector', 'penaltyRule']))]);
    }

    public function cancelFee(Request $request, Team $team, TeamFee $fee)
    {
        $this->ensureFeeBelongsToTeam($team, $fee);
        $this->ensureCanManageTeamCashbox($request, $team);

        $fee->update([
            'status' => 'cancelled',
            'collector_id' => $request->user()->id,
        ]);

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
