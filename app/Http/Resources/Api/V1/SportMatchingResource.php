<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\SportMatchingAttendance;

class SportMatchingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'mode' => $this->mode,
            'title' => $this->title,
            'description' => $this->description,
            'city' => $this->city,
            'postal_code' => $this->postal_code,
            'location_name' => $this->location_name,
            'address' => $this->address,
            'country_code' => $this->country_code,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'radius_km' => $this->radius_km,
            'starts_at' => $this->starts_at?->toJSON(),
            'ends_at' => $this->ends_at?->toJSON(),
            'participants_needed' => $this->participants_needed,
            'team_size' => $this->team_size,
            'skill_level' => $this->skill_level,
            'status' => $this->status,
            'owner' => new UserResource($this->whenLoaded('user')),
            'sport' => $this->whenLoaded('sport', fn () => [
                'id' => $this->sport->id,
                'name' => $this->sport->name,
                'slug' => $this->sport->slug,
            ]),
            'team' => new TeamResource($this->whenLoaded('team')),
            'applications' => $this->when(
                (int) $this->user_id === (int) $request->user()?->id && $this->relationLoaded('applications'),
                fn () => $this->applications->map(fn ($application) => [
                'id' => $application->id,
                'status' => $application->status,
                'message' => $application->message,
                'user' => $application->user ? [
                    'id' => $application->user->id,
                    'name' => $application->user->name,
                    'profile_photo_url' => $application->user->profile_photo_url,
                ] : null,
                'team' => $application->team ? [
                    'id' => $application->team->id,
                    'name' => $application->team->name,
                    'sport_type' => $application->team->sport_type,
                ] : null,
                'attendance' => $this->attendancePayload(
                    $application->relationLoaded('attendance')
                        ? $application->attendance
                        : $application->attendance()->first()
                ),
                ])->values(),
            ),
            'attendance' => $this->attendancePayload($this->attendanceFor($request)),
            'applications_count' => $this->whenCounted('applications'),
            'accepted_count' => (int) ($this->accepted_count ?? 0),
            'mine' => (int) $this->user_id === (int) $request->user()?->id,
            'my_application' => $this->my_application,
            'created_at' => $this->created_at?->toJSON(),
        ];
    }

    private function attendanceFor(Request $request): ?SportMatchingAttendance
    {
        $userId = $request->user()?->id;
        if (! $userId) {
            return null;
        }

        return $this->relationLoaded('attendances')
            ? $this->attendances->firstWhere('user_id', $userId)
            : $this->attendances()->where('user_id', $userId)->first();
    }

    private function attendancePayload(?SportMatchingAttendance $attendance): ?array
    {
        if (! $attendance) {
            return null;
        }

        return [
            'id' => $attendance->id,
            'user_id' => $attendance->user_id,
            'status' => $attendance->status,
            'confirmed_at' => $attendance->confirmed_at?->toJSON(),
            'checked_in_at' => $attendance->checked_in_at?->toJSON(),
            'no_show_reported_at' => $attendance->no_show_reported_at?->toJSON(),
            'no_show_reported_by' => $attendance->no_show_reported_by,
            'no_show_reason' => $attendance->no_show_reason,
        ];
    }
}
