<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NutritionGoalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'goal_type' => $this->goal_type,
            'daily_calories_target' => $this->daily_calories_target,
            'protein_target_g' => $this->protein_target_g,
            'carbs_target_g' => $this->carbs_target_g,
            'fat_target_g' => $this->fat_target_g,
            'water_target_ml' => $this->water_target_ml,
            'diet_style' => $this->diet_style,
            'allergies' => $this->allergies ?? [],
            'notes' => $this->notes,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
