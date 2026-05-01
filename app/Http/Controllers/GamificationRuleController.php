<?php

namespace App\Http\Controllers;

use App\Models\GamificationRule;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
            'actorTypes' => ['sportler', 'trainer', 'verein'],
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
            'rules.*.actor_type' => ['required', Rule::in(['sportler', 'trainer', 'verein'])],
        ]);

        foreach ($data['rules'] as $ruleData) {
            GamificationRule::whereKey($ruleData['id'])->update([
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
}
