<?php

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use App\Services\Training\TrainingResourceService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class TrainingPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewer = $request->user();

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
            'is_template' => (bool) data_get($this->settings, 'is_template', false),
            'template_source_id' => data_get($this->settings, 'template_source_id'),
            'can_write' => $viewer ? app(TrainingResourceService::class)->canWritePlan($viewer, $this->resource) : false,
            'can_delete' => $viewer ? app(TrainingResourceService::class)->canDeletePlan($viewer, $this->resource) : false,
            'creator' => new UserResource($this->whenLoaded('creator')),
            'team' => new TeamResource($this->whenLoaded('team')),
            'assignments_count' => $this->whenCounted('assignments'),
            'assignments' => $this->whenLoaded('assignments', fn () => $this->assignments->map(fn ($assignment) => [
                'id' => $assignment->id,
                'permission' => $assignment->permission,
                'user' => $assignment->user ? [
                    'id' => $assignment->user->id,
                    'name' => $assignment->user->name,
                ] : null,
                'team' => $assignment->team ? [
                    'id' => $assignment->team->id,
                    'name' => $assignment->team->name,
                ] : null,
            ])->values()),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'source_exercise_id' => $item->source_exercise_id,
                'title' => $item->title,
                'sport_type' => $item->sport_type,
                'description' => $item->description,
                'scheduled_at' => $item->scheduled_at?->toJSON(),
                'duration_minutes' => $item->duration_minutes,
                'distance_meters' => $item->distance_meters,
                'calories' => $item->calories,
                'intensity' => $item->intensity,
                'image_path' => $item->image_path,
                'image_url' => $item->image_path ? Storage::disk('public')->url($item->image_path) : null,
                'video_url' => $item->video_url,
                'todos' => $item->todos,
                'metrics' => $item->metrics,
                'sort_order' => $item->sort_order,
            ])->values()),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }

}
