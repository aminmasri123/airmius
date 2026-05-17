<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SportRouteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'sport_id' => $this->sport_id,
            'sport_type' => $this->sport_type,
            'sport' => $this->whenLoaded('sport', fn () => [
                'id' => $this->sport?->id,
                'name' => $this->sport?->name,
                'slug' => $this->sport?->slug,
                'category' => $this->sport?->category,
            ]),
            'team_id' => $this->team_id,
            'team' => $this->whenLoaded('team', fn () => $this->team ? [
                'id' => $this->team->id,
                'name' => $this->team->name,
            ] : null),
            'visibility' => $this->visibility,
            'status' => $this->status,
            'difficulty' => $this->difficulty,
            'surface' => $this->surface,
            'start' => [
                'name' => $this->start_name,
                'latitude' => $this->start_latitude,
                'longitude' => $this->start_longitude,
            ],
            'end' => [
                'name' => $this->end_name,
                'latitude' => $this->end_latitude,
                'longitude' => $this->end_longitude,
            ],
            'distance_meters' => $this->distance_meters,
            'distance_km' => round(($this->distance_meters ?? 0) / 1000, 2),
            'estimated_duration_seconds' => $this->estimated_duration_seconds,
            'elevation_gain_meters' => $this->elevation_gain_meters,
            'elevation_loss_meters' => $this->elevation_loss_meters,
            'waypoints' => $this->waypoints ?? [],
            'route_geometry' => $this->route_geometry,
            'navigation_cues' => $this->navigation_cues ?? [],
            'metrics' => $this->metrics ?? [],
            'tracks_count' => $this->whenCounted('tracks'),
            'creator' => $this->whenLoaded('creator', fn () => $this->creator ? [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
                'user_card' => (new UserResource($this->creator))->toArray($request)['user_card'] ?? null,
            ] : null),
            'can_edit' => $request->user() && (int) $request->user()->id === (int) $this->user_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
