<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'club_id' => $this->club_id,
            'team_id' => $this->team_id,
            'user_id' => $this->user_id,
            'conversation_id' => $this->conversation_id,
            'title' => $this->title,
            'notes' => $this->notes,
            'type' => $this->type,
            'visibility' => $this->visibility,
            'status' => $this->status,
            'start_time' => $this->start_time?->toJSON(),
            'end_time' => $this->end_time?->toJSON(),
            'location' => $this->location,
            'location_name' => $this->location_name,
            'location_street' => $this->location_street,
            'location_house_number' => $this->location_house_number,
            'location_postal_code' => $this->location_postal_code,
            'location_city' => $this->location_city,
            'location_country' => $this->location_country,
            'location_latitude' => $this->location_latitude,
            'location_longitude' => $this->location_longitude,
            'max_participants' => $this->max_participants,
            'uses_penalty_catalog' => (bool) $this->uses_penalty_catalog,
            'recurring' => (bool) $this->recurring,
            'recurrence_days' => $this->recurrence_days,
            'recurrence_ends_at' => $this->recurrence_ends_at?->toJSON(),
            'club' => new ClubResource($this->whenLoaded('club')),
            'team' => new TeamResource($this->whenLoaded('team')),
            'user' => new UserResource($this->whenLoaded('user')),
            'participants' => $this->whenLoaded('participants', fn () => $this->participants
                ->map(fn ($participant) => [
                    'id' => $participant->id,
                    'name' => $participant->name,
                    'email' => $participant->email,
                    'profile_photo_url' => $participant->profile_photo_url,
                    'pivot' => [
                        'status' => $participant->pivot?->status,
                        'response_reason' => $participant->pivot?->response_reason,
                    ],
                ])
                ->values()),
            'participants_count' => $this->whenCounted('participants'),
            'yes_count' => (int) ($this->yes_count ?? 0),
            'maybe_count' => (int) ($this->maybe_count ?? 0),
            'no_count' => (int) ($this->no_count ?? 0),
            'comments_count' => $this->whenCounted('comments'),
            'my_participation_status' => $this->my_participation_status,
            'can_join' => (bool) ($request->user()?->can('join', $this->resource) ?? false),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
