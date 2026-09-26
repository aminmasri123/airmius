<?php

namespace App\Http\Resources\Api\V1;

use App\Services\Training\TrainingRouteLinkService;
use App\Support\ClubPermissions;
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
            'club_id' => $this->club_id ?? $this->team?->club_id,
            'team_id' => $this->team_id,
            'user_id' => $this->user_id,
            'conversation_id' => $this->conversation_id,
            'sport_route_id' => $this->sport_route_id,
            'sport_year_period_id' => $this->sport_year_period_id,
            'sport_year_period' => $this->whenLoaded('sportYearPeriod', fn () => $this->sportYearPeriod ? [
                'id' => $this->sportYearPeriod->id,
                'name' => $this->sportYearPeriod->name,
                'starts_on' => $this->sportYearPeriod->starts_on?->toDateString(),
                'ends_on' => $this->sportYearPeriod->ends_on?->toDateString(),
            ] : null),
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
            'event_timezone' => $this->event_timezone,
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
            'min_participants' => $this->min_participants,
            'registration_audience' => $this->registration_audience,
            'participation_requirements' => $this->participation_requirements ?? [],
            'participation_consent_required' => (bool) $this->participation_consent_required,
            'participation_consent_version' => $this->participation_consent_version,
            'member_price_cents' => (int) ($this->member_price_cents ?? 0),
            'guest_price_cents' => (int) ($this->guest_price_cents ?? 0),
            'waitlist_offer_ttl_minutes' => $this->waitlist_offer_ttl_minutes,
            'participant_response_required' => (bool) $this->participant_response_required,
            'participant_response_deadline_at' => $this->participant_response_deadline_at?->toJSON(),
            'uses_penalty_catalog' => (bool) $this->uses_penalty_catalog,
            'camp_groups' => $this->camp_groups ?? [],
            'camp_supervision' => $this->camp_supervision ?? [],
            'camp_accommodation' => $this->camp_accommodation ?? [],
            'camp_catering' => $this->camp_catering ?? [],
            'camp_emergency_contacts' => $this->camp_emergency_contacts ?? [],
            'camp_guardian_consent_required' => (bool) $this->camp_guardian_consent_required,
            'camp_travel_consent_required' => (bool) $this->camp_travel_consent_required,
            'camp_privacy_notice_version' => $this->camp_privacy_notice_version,
            'recurring' => (bool) $this->recurring,
            'recurrence_series_id' => $this->recurrence_series_id,
            'recurrence_rule_version_id' => $this->recurrence_rule_version_id,
            'recurrence_original_start_time' => $this->recurrence_original_start_time?->toJSON(),
            'recurrence_local_date' => $this->recurrence_local_date,
            'recurrence_exception_kind' => $this->recurrence_exception_kind,
            'recurrence_snapshot' => $this->recurrence_snapshot,
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
                        'lifecycle_status' => $participant->pivot?->lifecycle_status,
                        'payment_id' => $participant->pivot?->payment_id,
                        'refund_payment_id' => $participant->pivot?->refund_payment_id,
                        'replacement_for_participant_id' => $participant->pivot?->replacement_for_participant_id,
                        'rsvp_status' => $participant->pivot?->rsvp_status,
                        'attendance_status' => $participant->pivot?->attendance_status,
                        'response_reason' => $participant->pivot?->response_reason,
                        'absence_reason' => $participant->pivot?->absence_reason,
                        'camp_group_key' => $participant->pivot?->camp_group_key,
                        'camp_privacy_notice_accepted_at' => $participant->pivot?->camp_privacy_notice_accepted_at,
                        'camp_travel_consent_accepted_at' => $participant->pivot?->camp_travel_consent_accepted_at,
                        'camp_guardian_consent_verified_at' => $participant->pivot?->camp_guardian_consent_verified_at,
                        'camp_emergency_contact_snapshot' => $participant->pivot?->camp_emergency_contact_snapshot,
                        'camp_dietary_notes' => $participant->pivot?->camp_dietary_notes,
                        'response_mode' => $participant->pivot?->response_mode,
                        'responded_at' => $participant->pivot?->responded_at,
                        'waitlist_position' => $participant->pivot?->waitlist_position,
                        'waitlist_promoted_at' => $participant->pivot?->waitlist_promoted_at,
                        'waitlist_offer_expires_at' => $participant->pivot?->waitlist_offer_expires_at,
                        'cancelled_at' => $participant->pivot?->cancelled_at,
                        'refunded_at' => $participant->pivot?->refunded_at,
                        'checked_in_at' => $participant->pivot?->checked_in_at,
                        'check_in_method' => $participant->pivot?->check_in_method,
                    ],
                ])
                ->values()),
            'participants_count' => $this->whenCounted('participants'),
            'yes_count' => (int) ($this->yes_count ?? 0),
            'late_count' => (int) ($this->late_count ?? 0),
            'maybe_count' => (int) ($this->maybe_count ?? 0),
            'no_count' => (int) ($this->no_count ?? 0),
            'waitlist_count' => (int) ($this->waitlist_count ?? 0),
            'comments_count' => $this->whenCounted('comments'),
            'files_count' => $this->when(is_array($fileContext), (int) ($fileContext['count'] ?? 0)),
            'file_context' => $this->when(is_array($fileContext), $fileContext),
            'my_participation_status' => $this->my_participation_status,
            'can_join' => (bool) ($request->user()?->can('join', $this->resource) ?? false),
            'can_update' => (bool) ($request->user()?->can('update', $this->resource) ?? false),
            'can_manage_metadata' => (bool) ($request->user()
                && ($this->club ?? $this->team?->club)
                && ClubPermissions::allows(
                    $this->club ?? $this->team->club,
                    $request->user(),
                    ClubPermissions::METADATA_EDIT
                )),
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
