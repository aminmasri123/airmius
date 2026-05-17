<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SportPlaceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'description' => $this->description,
            'sport_id' => $this->sport_id,
            'sport' => $this->whenLoaded('sport', fn () => $this->sport ? [
                'id' => $this->sport->id,
                'name' => $this->sport->name,
                'slug' => $this->sport->slug,
                'category' => $this->sport->category,
            ] : null),
            'sport_types' => $this->sport_types ?? [],
            'team_id' => $this->team_id,
            'location' => [
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
                'address' => $this->address,
                'city' => $this->city,
                'country_code' => $this->country_code,
            ],
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'visibility' => $this->visibility,
            'status' => $this->status,
            'amenities' => $this->amenities ?? [],
            'surfaces' => $this->surfaces ?? [],
            'opening_hours' => $this->opening_hours,
            'gallery_images' => $this->gallery_images ?? [],
            'rating' => [
                'avg' => $this->rating_avg,
                'count' => $this->rating_count,
            ],
            'geojson' => [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [$this->longitude, $this->latitude],
                ],
                'properties' => [
                    'id' => $this->id,
                    'name' => $this->name,
                    'type' => $this->type,
                ],
            ],
            'distance_meters' => $this->when(isset($this->distance_meters), fn () => $this->distance_meters),
            'metrics' => $this->metrics ?? [],
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
