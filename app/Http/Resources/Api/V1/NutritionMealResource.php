<?php

namespace App\Http\Resources\Api\V1;

use App\Services\NutritionPremiumFeatureService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NutritionMealResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'eaten_on' => $this->eaten_on?->toDateString(),
            'meal_type' => $this->meal_type,
            'title' => $this->title,
            'calories' => $this->calories,
            'protein_g' => $this->protein_g,
            'carbs_g' => $this->carbs_g,
            'fat_g' => $this->fat_g,
            'fiber_g' => $this->fiber_g,
            'sugar_g' => $this->sugar_g,
            'water_ml' => $this->water_ml,
            'source' => $this->source,
            'training_context' => $this->training_context,
            'items' => app(NutritionPremiumFeatureService::class)
                ->filterItems($this->items ?? [], $request->user()),
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
