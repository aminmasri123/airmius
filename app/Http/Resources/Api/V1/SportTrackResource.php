<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SportTrackResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sport_route_id' => $this->sport_route_id,
            'route' => $this->whenLoaded('route', fn () => $this->route ? [
                'id' => $this->route->id,
                'title' => $this->route->title,
                'distance_meters' => $this->route->distance_meters,
            ] : null),
            'sport_id' => $this->sport_id,
            'sport_type' => $this->sport_type,
            'team_id' => $this->team_id,
            'title' => $this->title,
            'status' => $this->status,
            'source' => $this->source,
            'started_at' => $this->started_at?->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
            'distance_meters' => $this->distance_meters,
            'distance_km' => round(($this->distance_meters ?? 0) / 1000, 2),
            'duration_seconds' => $this->duration_seconds,
            'elevation_gain_meters' => $this->elevation_gain_meters,
            'elevation_loss_meters' => $this->elevation_loss_meters,
            'average_speed_mps' => $this->average_speed_mps,
            'max_speed_mps' => $this->max_speed_mps,
            'track_points' => $this->track_points ?? [],
            'track_geometry' => $this->track_geometry,
            'metrics' => $this->metrics ?? [],
            'can_edit' => $request->user() && (int) $request->user()->id === (int) $this->user_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
