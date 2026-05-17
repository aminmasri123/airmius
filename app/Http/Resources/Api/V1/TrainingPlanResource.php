<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrainingPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'created_by' => $this->created_by,
            'team_id' => $this->team_id,
            'title' => $this->title,
            'description' => $this->description,
            'cadence' => $this->cadence,
            'starts_on' => $this->starts_on?->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            'status' => $this->status,
            'share_permission' => $this->share_permission,
            'settings' => $this->settings,
            'creator' => new UserResource($this->whenLoaded('creator')),
            'team' => new TeamResource($this->whenLoaded('team')),
            'assignments_count' => $this->whenCounted('assignments'),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'title' => $item->title,
                'description' => $item->description,
                'scheduled_at' => $item->scheduled_at?->toJSON(),
                'duration_minutes' => $item->duration_minutes,
                'intensity' => $item->intensity,
                'todos' => $item->todos,
                'metrics' => $item->metrics,
                'sort_order' => $item->sort_order,
            ])->values()),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
