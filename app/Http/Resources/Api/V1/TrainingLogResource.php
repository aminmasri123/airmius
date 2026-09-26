<?php

namespace App\Http\Resources\Api\V1;

use App\Services\Training\TrainingLogAccessService;
use App\Services\Training\TrainingResourceService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrainingLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $canViewProtectedCaseFile = $request->user()
            ? app(TrainingLogAccessService::class)->canViewProtectedCaseFile($request->user(), $this->resource)
            : false;

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'created_by' => $this->created_by,
            'trainer_id' => $this->trainer_id,
            'team_id' => $this->team_id,
            'training_plan_id' => $this->training_plan_id,
            'training_plan_item_id' => $this->training_plan_item_id,
            'sport_route_id' => $this->relationLoaded('sportRoute') && $this->sportRoute ? $this->sportRoute->id : null,
            'sport_route_track_id' => $this->relationLoaded('sportRouteTrack') && $this->sportRouteTrack ? $this->sportRouteTrack->id : null,
            'sport_type' => $this->sport_type,
            'title' => $this->title,
            'status' => $this->status,
            'performed_at' => $this->performed_at?->toJSON(),
            'duration_minutes' => $this->duration_minutes,
            'distance_meters' => $this->distance_meters,
            'calories' => $this->calories,
            'intensity' => $this->intensity,
            'notes' => $this->notes,
            'trainer_feedback' => $canViewProtectedCaseFile ? $this->trainer_feedback : null,
            'metrics' => $this->metrics,
            'athlete' => new UserResource($this->whenLoaded('athlete')),
            'trainer' => new UserResource($this->whenLoaded('trainer')),
            'team' => new TeamResource($this->whenLoaded('team')),
            'plan' => new TrainingPlanResource($this->whenLoaded('plan')),
            'plan_item' => $this->relationLoaded('planItem') && $this->planItem ? [
                'id' => $this->planItem->id,
                'title' => $this->planItem->title,
                'sport_type' => $this->planItem->sport_type,
                'duration_minutes' => $this->planItem->duration_minutes,
                'distance_meters' => $this->planItem->distance_meters,
                'metrics' => $this->planItem->metrics,
            ] : null,
            'sport_route' => $this->relationLoaded('sportRoute') && $this->sportRoute
                ? app(TrainingResourceService::class)->routeReference($this->sportRoute)
                : null,
            'sport_route_track' => $this->relationLoaded('sportRouteTrack') && $this->sportRouteTrack
                ? app(TrainingResourceService::class)->trackReference($this->sportRouteTrack)
                : null,
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
            'feedbacks' => $canViewProtectedCaseFile ? $this->whenLoaded('feedbacks', fn () => $this->feedbacks->map(fn ($feedback) => [
                'id' => $feedback->id,
                'body' => $feedback->body,
                'role' => $feedback->role,
                'classification' => $feedback->classification,
                'retention_until' => $feedback->retention_until?->toJSON(),
                'created_at' => $feedback->created_at?->toJSON(),
                'author' => $feedback->author ? [
                    'id' => $feedback->author->id,
                    'name' => trim(($feedback->author->first_name ?? '').' '.($feedback->author->last_name ?? '')) ?: $feedback->author->name,
                ] : null,
            ])->values()) : [],
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
