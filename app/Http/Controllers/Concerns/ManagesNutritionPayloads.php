<?php

namespace App\Http\Controllers\Concerns;

use App\Models\NutritionMeal;
use App\Models\NutritionGoal;
use App\Models\TrainingLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

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
            'body_weight_kg' => ['nullable', 'numeric', 'min:20', 'max:300'],
            'water_target_mode' => ['nullable', Rule::in(['manual', 'auto'])],
            'water_reminders_per_day' => ['sometimes', 'integer', 'between:0,3'],
            'water_reminder_start_hour' => ['sometimes', 'integer', 'between:6,20'],
            'water_reminder_end_hour' => ['sometimes', 'integer', 'between:7,22', 'gt:water_reminder_start_hour'],
            'diet_style' => ['required', Rule::in($dietStyles)],
            'allergies' => ['nullable', 'array', 'max:12'],
            'allergies.*' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    private function validatedWaterReminderTimezone(Request $request): ?string
    {
        $timezone = $request->input('water_reminder_timezone');
        if ($timezone === null || (int) $request->input('water_reminders_per_day', 0) === 0) {
            return null;
        }

        if (! is_string($timezone) || strlen($timezone) > 80) {
            throw ValidationException::withMessages(['water_reminder_timezone' => 'Invalid timezone.']);
        }

        try {
            new DateTimeZone($timezone);
        } catch (Throwable) {
            throw ValidationException::withMessages(['water_reminder_timezone' => 'Invalid timezone.']);
        }

        return $timezone;
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

    private function validateWaterData(Request $request): array
    {
        return $request->validate([
            'eaten_on' => ['required', 'date'],
            'amount_ml' => ['required', 'integer', 'min:1', 'max:5000'],
            'title' => ['nullable', 'string', 'max:80'],
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

    private function waterPayload(User $user, array $data): array
    {
        $amount = (int) $data['amount_ml'];
        $title = trim((string) ($data['title'] ?? '')) ?: 'Wasser';

        return [
            'user_id' => $user->id,
            'eaten_on' => $data['eaten_on'],
            'meal_type' => 'drink',
            'title' => $title,
            'calories' => 0,
            'protein_g' => 0,
            'carbs_g' => 0,
            'fat_g' => 0,
            'fiber_g' => null,
            'sugar_g' => null,
            'water_ml' => $amount,
            'source' => 'manual',
            'training_context' => null,
            'items' => [
                ['name' => $title, 'amount' => $amount.' ml'],
            ],
            'notes' => null,
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
            ->selectRaw('eaten_on, COALESCE(SUM(calories), 0) as calories, COALESCE(SUM(protein_g), 0) as protein_g, COALESCE(SUM(carbs_g), 0) as carbs_g, COALESCE(SUM(fat_g), 0) as fat_g, COALESCE(SUM(water_ml), 0) as water_ml')
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
                    'water_ml' => (int) ($row->water_ml ?? 0),
                ];
            })
            ->all();
    }

    private function waterRecommendation(User $user, NutritionGoal $goal, string $date): array
    {
        $mode = $goal->water_target_mode ?: 'manual';
        $manualTarget = (int) ($goal->water_target_ml ?: 2500);
        $weight = $goal->body_weight_kg ? (float) $goal->body_weight_kg : null;
        $base = $weight
            ? $this->roundWaterToStep($weight * 33)
            : 2500;

        $trainingLogs = TrainingLog::query()
            ->where('user_id', $user->id)
            ->whereDate('performed_at', $date)
            ->where(fn ($query) => $query->whereNull('status')->orWhere('status', '!=', 'draft'))
            ->get(['id', 'title', 'sport_type', 'duration_minutes', 'calories', 'intensity']);

        $trainingDetails = $trainingLogs
            ->map(function (TrainingLog $log) {
                $extra = $this->waterExtraForTraining($log);

                return [
                    'id' => $log->id,
                    'title' => $log->title ?: 'Training',
                    'sport_type' => $log->sport_type,
                    'duration_minutes' => $log->duration_minutes,
                    'calories' => $log->calories,
                    'intensity' => $log->intensity,
                    'extra_ml' => $extra,
                ];
            })
            ->filter(fn (array $detail) => $detail['extra_ml'] > 0)
            ->values();

        $trainingExtra = min(2000, (int) $trainingDetails->sum('extra_ml'));
        $suggestedTarget = $this->clampWaterTarget($base + $trainingExtra);
        $activeTarget = $mode === 'auto'
            ? $suggestedTarget
            : $this->clampWaterTarget($manualTarget);

        return [
            'mode' => $mode,
            'target_ml' => $activeTarget,
            'suggested_target_ml' => $suggestedTarget,
            'manual_target_ml' => $this->clampWaterTarget($manualTarget),
            'base_ml' => $base,
            'training_extra_ml' => $trainingExtra,
            'body_weight_kg' => $weight,
            'uses_weight' => $weight !== null,
            'fallback_used' => $weight === null,
            'details' => $trainingDetails->all(),
            'source_label' => $mode === 'auto'
                ? 'Automatisch nach Gewicht und Training'
                : 'Manuell festgelegt',
        ];
    }

    private function waterExtraForTraining(TrainingLog $log): int
    {
        $duration = (int) ($log->duration_minutes ?? 0);
        $calories = (int) ($log->calories ?? 0);

        if ($duration <= 0 && $calories <= 0) {
            return 0;
        }

        if ($duration > 0) {
            $perHour = $this->waterHourlyMlForSport((string) $log->sport_type);
            $multiplier = $this->waterIntensityMultiplier((string) $log->intensity);

            return min(1500, $this->roundWaterToStep(($duration / 60) * $perHour * $multiplier));
        }

        return min(1200, $this->roundWaterToStep($calories * 0.5));
    }

    private function waterHourlyMlForSport(string $sportType): int
    {
        $sport = strtolower($sportType);

        if (str_contains($sport, 'run') || str_contains($sport, 'lauf') || str_contains($sport, 'intervall') || str_contains($sport, 'football') || str_contains($sport, 'fussball')) {
            return 650;
        }

        if (str_contains($sport, 'bike') || str_contains($sport, 'rad') || str_contains($sport, 'cycling')) {
            return 600;
        }

        if (str_contains($sport, 'swim') || str_contains($sport, 'schwimm')) {
            return 450;
        }

        if (str_contains($sport, 'gym') || str_contains($sport, 'kraft')) {
            return 400;
        }

        return 500;
    }

    private function waterIntensityMultiplier(string $intensity): float
    {
        return match ($intensity) {
            'hart' => 1.25,
            'mittel' => 1.0,
            'locker', 'recovery' => 0.75,
            default => 1.0,
        };
    }

    private function roundWaterToStep(float|int $value, int $step = 50): int
    {
        return (int) (round($value / $step) * $step);
    }

    private function clampWaterTarget(int $value): int
    {
        return max(1500, min(6000, $value));
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
                        'title' => __('nutrition.suggestions.strength.title'),
                        'body' => __('nutrition.suggestions.strength.body', ['training' => $title]),
                        'meal_type' => 'lunch',
                        'training_context' => 'post_workout',
                    ];
                }

                if (str_contains($sportType, 'lauf') || str_contains($sportType, 'run') || str_contains($sportType, 'cycling') || str_contains($sportType, 'rad')) {
                    return [
                        'title' => __('nutrition.suggestions.endurance.title'),
                        'body' => __('nutrition.suggestions.endurance.body', ['training' => $title]),
                        'meal_type' => 'snack',
                        'training_context' => 'pre_workout',
                    ];
                }

                return [
                    'title' => __('nutrition.suggestions.recovery.title'),
                    'body' => __('nutrition.suggestions.recovery.body', ['training' => $title]),
                    'meal_type' => 'dinner',
                    'training_context' => 'post_workout',
                ];
            });

        if ($suggestions->isEmpty()) {
            $fallback = match ($goalType) {
                'build_muscle' => __('nutrition.suggestions.daily_anchor.build_muscle'),
                'fat_loss' => __('nutrition.suggestions.daily_anchor.fat_loss'),
                'performance' => __('nutrition.suggestions.daily_anchor.performance'),
                default => __('nutrition.suggestions.daily_anchor.default'),
            };

            return [[
                'title' => __('nutrition.suggestions.daily_anchor.title'),
                'body' => $fallback,
                'meal_type' => 'snack',
                'training_context' => '',
            ]];
        }

        return $suggestions->values()->all();
    }
}
