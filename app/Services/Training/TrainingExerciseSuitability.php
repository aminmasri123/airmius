<?php

namespace App\Services\Training;

use App\Models\Sport;
use App\Models\TrainingExercise;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

class TrainingExerciseSuitability
{
    public const CATEGORIES = ['warmup', 'strength', 'endurance', 'technique', 'mobility', 'balance', 'recovery', 'other'];

    public function rules(): array
    {
        return [
            'sport_type' => ['nullable', 'string', 'max:80'],
            'sport_types' => ['sometimes', 'array', 'max:150'],
            'sport_types.*' => ['required', 'string', 'max:80'],
            'category' => ['nullable', Rule::in(self::CATEGORIES)],
        ];
    }

    public function sports(): array
    {
        return Sport::query()->where('is_active', true)->orderBy('name')->get(['name', 'slug'])
            ->map(fn ($sport) => ['value' => $sport->name, 'label' => $sport->name, 'slug' => $sport->slug])->all();
    }

    public function sportTypes(TrainingExercise $exercise): array
    {
        return $exercise->sport_types ?? ($exercise->sport_type ? [$exercise->sport_type] : []);
    }

    public function payload(array $data, ?TrainingExercise $exercise = null): array
    {
        if (array_key_exists('sport_types', $data)) {
            $sports = collect($data['sport_types'])->map(fn ($value) => trim($value))->filter()->unique()->values()->all();
        } elseif (array_key_exists('sport_type', $data) && (! $exercise || $data['sport_type'] !== $exercise->sport_type)) {
            // Older clients can still change the single sport. An unchanged value must not discard a newer multi-selection.
            $sports = empty($data['sport_type']) ? [] : [trim($data['sport_type'])];
        } else {
            $sports = $exercise ? $this->sportTypes($exercise) : [];
        }

        return ['sport_types' => $sports, 'sport_type' => $sports[0] ?? null,
            'category' => array_key_exists('category', $data) ? $data['category'] : $exercise?->category];
    }

    public function filter(Builder $query, string $sport): void
    {
        $aliases = Sport::query()->where('name', $sport)->orWhere('slug', $sport)->get(['name', 'slug'])
            ->flatMap(fn ($item) => [$item->name, $item->slug])->push($sport)->unique()->values()->all();

        // Keep this OR group inside the caller's visibility and category constraints.
        $query->where(function ($matching) use ($aliases) {
            $matching->whereJsonLength('sport_types', 0)
                ->orWhere(function ($legacy) use ($aliases) {
                    $legacy->whereNull('sport_types')->where(fn ($value) => $value
                        ->whereNull('sport_type')->orWhere('sport_type', '')->orWhereIn('sport_type', $aliases));
                });
            foreach ($aliases as $alias) {
                $matching->orWhereJsonContains('sport_types', $alias);
            }
        });
    }
}
