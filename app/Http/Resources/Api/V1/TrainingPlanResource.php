<?php

namespace App\Http\Resources\Api\V1;

use App\Models\User;
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
            'can_write' => $viewer ? $this->canWrite($viewer) : false,
            'can_delete' => $viewer ? (int) $this->created_by === (int) $viewer->id : false,
            'creator' => new UserResource($this->whenLoaded('creator')),
            'team' => new TeamResource($this->whenLoaded('team')),
            'assignments_count' => $this->whenCounted('assignments'),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
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

    private function canWrite(User $user): bool
    {
        if ((int) $this->created_by === (int) $user->id) {
            return true;
        }

        $teamIds = $user->teams()->pluck('teams.id');

        return $this->assignments()
            ->where('permission', 'write')
            ->where(function ($query) use ($user, $teamIds) {
                $query
                    ->where('user_id', $user->id)
                    ->orWhereIn('team_id', $teamIds);
            })
            ->exists();
    }
}
