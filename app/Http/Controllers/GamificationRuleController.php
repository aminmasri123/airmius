<?php

namespace App\Http\Controllers;

use App\Models\GamificationRule;
use App\Models\GamificationXpEvent;
use App\Services\GamificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class GamificationRuleController extends Controller
{
    public function index()
    {
        $rules = GamificationRule::query()
            ->orderBy('actor_type')
            ->orderBy('category')
            ->orderBy('is_penalty')
            ->orderBy('label')
            ->get()
            ->groupBy('actor_type')
            ->map(fn ($items) => $items->groupBy('category'))
            ->toArray();

        return Inertia::render('Auth/Dashboard/GamificationRules/Index', [
            'rules' => $rules,
            'actorTypes' => GamificationService::ACTOR_TYPES,
            'health' => $this->healthSummary(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'rules' => ['required', 'array'],
            'rules.*.id' => ['required', 'exists:gamification_rules,id'],
            'rules.*.label' => ['required', 'string', 'max:120'],
            'rules.*.description' => ['nullable', 'string', 'max:1000'],
            'rules.*.xp_amount' => ['required', 'integer', 'min:-500', 'max:500'],
            'rules.*.daily_limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'rules.*.trust_delta' => ['required', 'integer', 'min:-50', 'max:50'],
            'rules.*.is_active' => ['boolean'],
            'rules.*.actor_type' => ['required', Rule::in(GamificationService::ACTOR_TYPES)],
        ]);

        foreach ($data['rules'] as $ruleData) {
            $rule = GamificationRule::query()->findOrFail($ruleData['id']);

            if ($rule->actor_type !== $ruleData['actor_type']) {
                throw ValidationException::withMessages([
                    'rules' => 'Actor-Type und Regel passen nicht zusammen.',
                ]);
            }

            $this->validateRuleBalance($rule, $ruleData);

            $rule->update([
                'label' => $ruleData['label'],
                'description' => $ruleData['description'] ?? null,
                'xp_amount' => $ruleData['xp_amount'],
                'daily_limit' => $ruleData['daily_limit'] ?? null,
                'trust_delta' => $ruleData['trust_delta'],
                'is_active' => $ruleData['is_active'] ?? false,
            ]);
        }

        return back()->with('message', 'Gamification-Regeln wurden aktualisiert.');
    }

    private function healthSummary(): array
    {
        return GamificationRule::query()
            ->get()
            ->groupBy('actor_type')
            ->map(function ($rules, string $actorType) {
                $positiveRules = $rules->where('is_penalty', false);
                $limitedRules = $positiveRules->filter(fn (GamificationRule $rule) => $rule->daily_limit !== null);
                $activeRules = $rules->where('is_active', true);
                $eventsToday = GamificationXpEvent::query()
                    ->where('actor_type', $actorType)
                    ->whereDate('created_at', now()->toDateString());

                return [
                    'rules' => $rules->count(),
                    'active_rules' => $activeRules->count(),
                    'penalties' => $rules->where('is_penalty', true)->count(),
                    'daily_limited_rules' => $limitedRules->count(),
                    'max_positive_xp' => (int) max(0, $positiveRules->max('xp_amount') ?? 0),
                    'avg_positive_xp' => (int) round(max(0, $positiveRules->avg('xp_amount') ?? 0)),
                    'events_today' => (int) (clone $eventsToday)->count(),
                    'xp_today' => (int) (clone $eventsToday)->sum('amount'),
                    'capped_today' => (int) (clone $eventsToday)->where('limited_by_daily_cap', true)->count(),
                    'risk_notes' => $this->riskNotesFor($rules),
                    'quality_score' => $this->qualityScoreFor($rules),
                ];
            })
            ->toArray();
    }

    private function qualityScoreFor($rules): int
    {
        $score = 10;
        $positiveRules = $rules->where('is_penalty', false);
        $penalties = $rules->where('is_penalty', true);

        if ($positiveRules->whereNull('daily_limit')->where('xp_amount', '>', 0)->count() > 3) {
            $score -= 2;
        }

        if ($positiveRules->where('xp_amount', '<', 0)->isNotEmpty() || $penalties->where('xp_amount', '>', 0)->isNotEmpty()) {
            $score -= 3;
        }

        if ($positiveRules->max('xp_amount') > 50) {
            $score -= 2;
        }

        if ($penalties->isEmpty()) {
            $score -= 1;
        }

        if ($rules->where('is_active', true)->isEmpty()) {
            $score -= 3;
        }

        return max(1, $score);
    }

    private function riskNotesFor($rules): array
    {
        $notes = [];
        $positiveRules = $rules->where('is_penalty', false);

        if ($positiveRules->whereNull('daily_limit')->where('xp_amount', '>', 0)->count() > 3) {
            $notes[] = 'Viele positive Regeln ohne Daily Limit';
        }

        if ($positiveRules->max('xp_amount') > 50) {
            $notes[] = 'Mindestens ein Reward ist sehr hoch';
        }

        if ($positiveRules->where('xp_amount', '<', 0)->isNotEmpty() || $rules->where('is_penalty', true)->where('xp_amount', '>', 0)->isNotEmpty()) {
            $notes[] = 'XP-Vorzeichen passt nicht zur Regelart';
        }

        if ($rules->where('is_penalty', true)->isEmpty()) {
            $notes[] = 'Keine Penalty-Regel fuer Missbrauch';
        }

        if ($rules->where('is_active', true)->isEmpty()) {
            $notes[] = 'Alle Regeln sind pausiert';
        }

        return $notes;
    }

    private function validateRuleBalance(GamificationRule $rule, array $ruleData): void
    {
        $xpAmount = (int) $ruleData['xp_amount'];

        if ($rule->is_penalty && $xpAmount > 0) {
            throw ValidationException::withMessages([
                'rules' => 'Strafregeln duerfen keine positiven XP vergeben.',
            ]);
        }

        if (! $rule->is_penalty && $xpAmount < 0) {
            throw ValidationException::withMessages([
                'rules' => 'Positive Regeln duerfen keine XP abziehen.',
            ]);
        }

        if ($rule->is_penalty && ($ruleData['daily_limit'] ?? null) !== null) {
            throw ValidationException::withMessages([
                'rules' => 'Daily Limits sind nur fuer positive Belohnungsregeln vorgesehen.',
            ]);
        }
    }
}
