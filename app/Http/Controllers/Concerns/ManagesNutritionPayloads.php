<?php

namespace App\Http\Controllers\Concerns;

use App\Models\NutritionMeal;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

trait ManagesNutritionPayloads
{
    private function nutritionCatalog(): array
    {
        return [
            'meal_types' => config('nutrition.meal_types', []),
            'goal_types' => config('nutrition.goal_types', []),
            'diet_styles' => config('nutrition.diet_styles', []),
            'source_types' => config('nutrition.source_types', []),
        ];
    }

    private function nutritionRecipes(?string $goalType = null, ?string $dietStyle = null): array
    {
        $recipes = collect(config('nutrition.recipes', []));

        if ($goalType) {
            $preferred = $recipes->where('goal_type', $goalType);
            $fallback = $recipes->where('goal_type', '!=', $goalType);

            $recipes = $preferred->merge($fallback)->values();
        }

        if ($dietStyle) {
            $matchingStyle = $recipes->filter(fn (array $recipe) => in_array($dietStyle, $recipe['diet_styles'] ?? [], true));
            $fallback = $recipes->reject(fn (array $recipe) => in_array($dietStyle, $recipe['diet_styles'] ?? [], true));
            $recipes = $matchingStyle->merge($fallback)->values();
        }

        return $recipes->take(8)->values()->all();
    }

    private function nutritionTips(?string $goalType = null): array
    {
        $goal = $goalType ?: 'maintain';
        $tips = config("nutrition.tips.{$goal}", []);

        return $tips !== [] ? $tips : config('nutrition.tips.maintain', []);
    }

    private function validateGoalData(Request $request): array
    {
        $goalTypes = collect(config('nutrition.goal_types', []))->pluck('key')->all();
        $dietStyles = collect(config('nutrition.diet_styles', []))->pluck('key')->all();

        return $request->validate([
            'goal_type' => ['required', Rule::in($goalTypes)],
            'daily_calories_target' => ['nullable', 'integer', 'min:800', 'max:8000'],
            'protein_target_g' => ['nullable', 'integer', 'min:0', 'max:500'],
            'carbs_target_g' => ['nullable', 'integer', 'min:0', 'max:900'],
            'fat_target_g' => ['nullable', 'integer', 'min:0', 'max:400'],
            'water_target_ml' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'diet_style' => ['required', Rule::in($dietStyles)],
            'allergies' => ['nullable', 'array', 'max:12'],
            'allergies.*' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    private function validateMealData(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';
        $mealTypes = collect(config('nutrition.meal_types', []))->pluck('key')->all();
        $sourceTypes = collect(config('nutrition.source_types', []))->pluck('key')->all();

        return $request->validate([
            'eaten_on' => [$required, 'date'],
            'meal_type' => [$required, Rule::in($mealTypes)],
            'title' => [$required, 'string', 'max:160'],
            'calories' => [$required, 'integer', 'min:0', 'max:20000'],
            'protein_g' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'carbs_g' => ['nullable', 'numeric', 'min:0', 'max:1500'],
            'fat_g' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'fiber_g' => ['nullable', 'numeric', 'min:0', 'max:300'],
            'sugar_g' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'water_ml' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'source' => ['nullable', Rule::in($sourceTypes)],
            'training_context' => ['nullable', 'string', 'max:80'],
            'items' => ['nullable', 'array', 'max:30'],
            'items.*.name' => ['nullable', 'string', 'max:120'],
            'items.*.amount' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:1200'],
        ]);
    }

    private function mealPayload(User $user, array $data, ?NutritionMeal $meal = null): array
    {
        return [
            'user_id' => $meal?->user_id ?? $user->id,
            'eaten_on' => $data['eaten_on'] ?? $meal?->eaten_on?->toDateString() ?? now()->toDateString(),
            'meal_type' => $data['meal_type'] ?? $meal?->meal_type ?? 'snack',
            'title' => $data['title'] ?? $meal?->title,
            'calories' => (int) ($data['calories'] ?? $meal?->calories ?? 0),
            'protein_g' => (float) ($data['protein_g'] ?? $meal?->protein_g ?? 0),
            'carbs_g' => (float) ($data['carbs_g'] ?? $meal?->carbs_g ?? 0),
            'fat_g' => (float) ($data['fat_g'] ?? $meal?->fat_g ?? 0),
            'fiber_g' => array_key_exists('fiber_g', $data) ? $data['fiber_g'] : $meal?->fiber_g,
            'sugar_g' => array_key_exists('sugar_g', $data) ? $data['sugar_g'] : $meal?->sugar_g,
            'water_ml' => array_key_exists('water_ml', $data) ? $data['water_ml'] : $meal?->water_ml,
            'source' => $data['source'] ?? $meal?->source ?? 'manual',
            'training_context' => $data['training_context'] ?? $meal?->training_context,
            'items' => $this->cleanNutritionItems($data['items'] ?? $meal?->items ?? []),
            'notes' => array_key_exists('notes', $data) ? $data['notes'] : $meal?->notes,
        ];
    }

    private function cleanNutritionItems(array $items): array
    {
        return collect($items)
            ->map(fn ($item) => [
                'name' => trim((string) ($item['name'] ?? '')),
                'amount' => trim((string) ($item['amount'] ?? '')),
            ])
            ->filter(fn ($item) => $item['name'] !== '' || $item['amount'] !== '')
            ->values()
            ->all();
    }

    private function summaryForDate(User $user, string $date): array
    {
        $summary = NutritionMeal::query()
            ->forUser($user)
            ->whereDate('eaten_on', $date)
            ->selectRaw('COALESCE(SUM(calories), 0) as calories')
            ->selectRaw('COALESCE(SUM(protein_g), 0) as protein_g')
            ->selectRaw('COALESCE(SUM(carbs_g), 0) as carbs_g')
            ->selectRaw('COALESCE(SUM(fat_g), 0) as fat_g')
            ->selectRaw('COALESCE(SUM(water_ml), 0) as water_ml')
            ->first();

        return [
            'date' => $date,
            'calories' => (int) $summary->calories,
            'protein_g' => round((float) $summary->protein_g, 1),
            'carbs_g' => round((float) $summary->carbs_g, 1),
            'fat_g' => round((float) $summary->fat_g, 1),
            'water_ml' => (int) $summary->water_ml,
        ];
    }

    private function weeklySummaries(User $user, string $date): array
    {
        $end = CarbonImmutable::parse($date);
        $start = $end->subDays(6);
        $rows = NutritionMeal::query()
            ->forUser($user)
            ->whereBetween('eaten_on', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('eaten_on, COALESCE(SUM(calories), 0) as calories, COALESCE(SUM(protein_g), 0) as protein_g, COALESCE(SUM(carbs_g), 0) as carbs_g, COALESCE(SUM(fat_g), 0) as fat_g')
            ->groupBy('eaten_on')
            ->get()
            ->keyBy(fn ($row) => $row->eaten_on->toDateString());

        return collect(range(0, 6))
            ->map(function (int $offset) use ($start, $rows) {
                $day = $start->addDays($offset);
                $row = $rows->get($day->toDateString());

                return [
                    'date' => $day->toDateString(),
                    'label' => $day->locale('de')->isoFormat('dd'),
                    'calories' => (int) ($row->calories ?? 0),
                    'protein_g' => round((float) ($row->protein_g ?? 0), 1),
                    'carbs_g' => round((float) ($row->carbs_g ?? 0), 1),
                    'fat_g' => round((float) ($row->fat_g ?? 0), 1),
                ];
            })
            ->all();
    }

    private function trainingNutritionSuggestions(iterable $logs, ?string $goalType): array
    {
        $suggestions = collect($logs)
            ->take(3)
            ->map(function ($log) {
                $sportType = strtolower((string) ($log->sport_type ?? ''));
                $title = (string) ($log->title ?? 'Training');

                if (str_contains($sportType, 'gym') || str_contains($sportType, 'kraft')) {
                    return [
                        'title' => 'Protein nach Krafttraining',
                        'body' => "Nach \"{$title}\" passt eine proteinreiche Mahlzeit mit Kohlenhydraten.",
                        'meal_type' => 'lunch',
                        'training_context' => 'post_workout',
                    ];
                }

                if (str_contains($sportType, 'lauf') || str_contains($sportType, 'run') || str_contains($sportType, 'cycling') || str_contains($sportType, 'rad')) {
                    return [
                        'title' => 'Energie fuer Ausdauer',
                        'body' => "Rund um \"{$title}\" helfen leicht verdauliche Kohlenhydrate und genug Wasser.",
                        'meal_type' => 'snack',
                        'training_context' => 'pre_workout',
                    ];
                }

                return [
                    'title' => 'Regeneration sichern',
                    'body' => "Nach \"{$title}\" sind Protein, Fluessigkeit und eine einfache Mahlzeit sinnvoll.",
                    'meal_type' => 'dinner',
                    'training_context' => 'post_workout',
                ];
            });

        if ($suggestions->isEmpty()) {
            $fallback = match ($goalType) {
                'build_muscle' => 'Heute kein Training erkannt: plane trotzdem 3-5 Proteinportionen ueber den Tag.',
                'fat_loss' => 'Heute kein Training erkannt: setze auf saettigende Mahlzeiten mit Protein und Gemuese.',
                'performance' => 'Heute kein Training erkannt: halte deine Kohlenhydrate fuer die naechste Einheit bereit.',
                default => 'Heute kein Training erkannt: eine einfache, ausgewogene Mahlzeit reicht oft schon.',
            };

            return [[
                'title' => 'Tagesanker',
                'body' => $fallback,
                'meal_type' => 'snack',
                'training_context' => '',
            ]];
        }

        return $suggestions->values()->all();
    }
}
