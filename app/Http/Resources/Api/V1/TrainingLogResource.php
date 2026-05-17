<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrainingLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'created_by' => $this->created_by,
            'trainer_id' => $this->trainer_id,
            'team_id' => $this->team_id,
            'training_plan_id' => $this->training_plan_id,
            'training_plan_item_id' => $this->training_plan_item_id,
            'sport_type' => $this->sport_type,
            'title' => $this->title,
            'status' => $this->status,
            'performed_at' => $this->performed_at?->toJSON(),
            'duration_minutes' => $this->duration_minutes,
            'distance_meters' => $this->distance_meters,
            'calories' => $this->calories,
            'intensity' => $this->intensity,
            'notes' => $this->notes,
            'trainer_feedback' => $this->trainer_feedback,
            'metrics' => $this->metrics,
            'athlete' => new UserResource($this->whenLoaded('athlete')),
            'trainer' => new UserResource($this->whenLoaded('trainer')),
            'team' => new TeamResource($this->whenLoaded('team')),
            'plan' => new TrainingPlanResource($this->whenLoaded('plan')),
            'entries' => $this->whenLoaded('entries', fn () => $this->entries->map(fn ($entry) => [
                'id' => $entry->id,
                'title' => $entry->title,
                'sets' => $entry->sets,
                'reps' => $entry->reps,
                'weight_kg' => $entry->weight_kg,
                'duration_seconds' => $entry->duration_seconds,
                'distance_meters' => $entry->distance_meters,
                'intensity' => $entry->intensity,
                'notes' => $entry->notes,
                'metrics' => $entry->metrics,
                'sort_order' => $entry->sort_order,
            ])->values()),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
