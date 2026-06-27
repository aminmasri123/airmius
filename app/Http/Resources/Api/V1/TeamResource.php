<?php

namespace App\Http\Resources\Api\V1;

use App\Support\UploadStorage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'club_id' => $this->club_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'sport_type' => $this->sport_type,
            'age_group' => $this->age_group,
            'gender' => $this->gender,
            'visibility' => $this->visibility,
            'logo_url' => UploadStorage::url($this->logo),
            'cover_image_url' => UploadStorage::url($this->cover_image),
            'can_manage' => (bool) ($request->user()?->can('update', $this->resource) ?? false),
            'can_delete' => (bool) ($request->user()?->can('delete', $this->resource) ?? false),
            'membership' => $this->pivot ? [
                'role' => $this->pivot->role ?? null,
            ] : null,
            'club' => new ClubResource($this->whenLoaded('club')),
            'users' => UserResource::collection($this->whenLoaded('users')),
            'users_count' => $this->whenCounted('users'),
            'events_count' => $this->whenCounted('events'),
            'attendance_stats' => $this->when($this->getAttribute('attendance_stats') !== null, $this->getAttribute('attendance_stats')),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
