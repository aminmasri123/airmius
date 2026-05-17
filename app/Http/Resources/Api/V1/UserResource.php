<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $roleLabel = $this->relationLoaded('roles')
            ? $this->roles->pluck('name')->first()
            : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'language' => $this->language ?? 'de',
            'theme' => $this->theme,
            'country' => $this->country,
            'street' => $this->street,
            'house_number' => $this->house_number,
            'postal_code' => $this->postal_code,
            'city' => $this->city,
            'state' => $this->state,
            'bio' => $this->bio,
            'profile_photo_url' => $this->profile_photo_url,
            'profile_photo_thumb' => $this->profile_photo_thumb,
            'user_card' => [
                'id' => $this->id,
                'display_name' => $this->name,
                'subtitle' => $roleLabel ?: 'Member',
                'role_label' => $roleLabel ?: 'Member',
                'avatar_url' => $this->profile_photo_url,
                'avatar_thumb' => $this->profile_photo_thumb,
                'initials' => $this->initialsFor($this->name),
                'status' => $this->status,
                'profile_visibility' => $this->profile_visibility ?? 'public',
            ],
            'privacy_status' => $this->privacy_status,
            'profile_visibility' => $this->profile_visibility ?? 'public',
            'direct_message_privacy' => $this->direct_message_privacy,
            'friend_request_privacy' => $this->friend_request_privacy,
            'ads_personalization_consent' => (bool) $this->ads_personalization_consent,
            'ads_measurement_consent' => (bool) $this->ads_measurement_consent,
            'event_radius_km' => $this->event_radius_km,
            'event_default_sport_ids' => $this->event_default_sport_ids ?? [],
            'event_default_filters' => $this->event_default_filters ?? [],
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')->values()),
            'permissions' => $this->whenLoaded('permissions', fn () => $this->permissions->pluck('name')->values()),
            'clubs' => ClubResource::collection($this->whenLoaded('clubs')),
            'teams' => TeamResource::collection($this->whenLoaded('teams')),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }

    private function initialsFor(?string $name): string
    {
        $parts = preg_split('/\s+/', trim((string) $name)) ?: [];

        $initials = collect($parts)
            ->filter()
            ->take(2)
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');

        return $initials !== '' ? $initials : '?';
    }
}
