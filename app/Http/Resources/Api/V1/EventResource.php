<?php

namespace App\Http\Resources\Api\V1;

use App\Services\Training\TrainingRouteLinkService;
use App\Support\EventAttendance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $fileContext = $this->resource->getAttribute('event_file_context');

        return [
            'id' => $this->id,
            'club_id' => $this->club_id,
            'team_id' => $this->team_id,
            'user_id' => $this->user_id,
            'conversation_id' => $this->conversation_id,
            'sport_route_id' => $this->sport_route_id,
            'sport_route' => $this->whenLoaded('sportRoute', fn () => $this->sportRoute
                ? app(TrainingRouteLinkService::class)->routeSummary($this->sportRoute)
                : null),
            'title' => $this->title,
            'notes' => $this->notes,
            'type' => $this->type,
            'visibility' => $this->visibility,
            'status' => $this->status,
            'start_time' => $this->start_time?->toJSON(),
            'end_time' => $this->end_time?->toJSON(),
            'reminder_at' => $this->reminder_at?->toJSON(),
            'cancelled_at' => $this->cancelled_at?->toJSON(),
            'cancelled_by' => $this->cancelled_by,
            'cancellation_reason' => $this->cancellation_reason,
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
            'participant_response_required' => (bool) $this->participant_response_required,
            'participant_response_deadline_at' => $this->participant_response_deadline_at?->toJSON(),
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
                        'response_mode' => $participant->pivot?->response_mode,
                        'responded_at' => $participant->pivot?->responded_at,
                    ],
                ])
                ->values()),
            'participants_count' => $this->whenCounted('participants'),
            'yes_count' => (int) ($this->yes_count ?? 0),
            'late_count' => (int) ($this->late_count ?? 0),
            'maybe_count' => (int) ($this->maybe_count ?? 0),
            'no_count' => (int) ($this->no_count ?? 0),
            'comments_count' => $this->whenCounted('comments'),
            'files_count' => $this->when(is_array($fileContext), (int) ($fileContext['count'] ?? 0)),
            'file_context' => $this->when(is_array($fileContext), $fileContext),
            'my_participation_status' => $this->my_participation_status,
            'can_join' => (bool) ($request->user()?->can('join', $this->resource) ?? false),
            'can_update' => (bool) ($request->user()?->can('update', $this->resource) ?? false),
            'can_delete' => (bool) ($request->user()?->can('delete', $this->resource) ?? false),
            'can_cancel' => (bool) ($request->user()?->can('cancel', $this->resource) ?? false),
            'can_manage_attendance' => (bool) ($request->user()
                ? EventAttendance::canManage($request->user(), $this->resource)
                : false),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
