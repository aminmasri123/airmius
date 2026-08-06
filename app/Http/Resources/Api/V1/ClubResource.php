<?php

namespace App\Http\Resources\Api\V1;

use App\Support\UploadStorage;
use App\Support\ClubPermissions;
use App\Support\Roles;
use App\Services\PlanFeatureService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClubResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $membershipPivot = $this->pivot;
        if (! $membershipPivot && $request->user()) {
            $membershipPivot = $this->users()
                ->whereKey($request->user()->id)
                ->first()?->pivot;
        }

        $planFeatureService = app(PlanFeatureService::class);

        return [
            'id' => $this->id,
            'owner_id' => $this->owner_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'sport_type' => $this->sport_type,
            'description' => $this->description,
            'city' => $this->city,
            'state' => $this->state,
            'postal_code' => $this->postal_code,
            'street' => $this->street,
            'house_number' => $this->house_number,
            'country' => $this->country,
            'logo_url' => UploadStorage::url($this->logo),
            'cover_image_url' => UploadStorage::url($this->cover_image),
            'is_official' => (bool) $this->is_official,
            'verification_status' => $this->verification_status,
            'membership_requests_enabled' => (bool) $this->membership_requests_enabled,
            'accepts_membership_applications' => (bool) $this->membership_requests_enabled,
            'has_pending_membership_request' => (bool) ($request->user()
                ? $this->membershipRequests()
                    ->where('user_id', $request->user()->id)
                    ->where('type', 'membership')
                    ->where('status', 'pending')
                    ->exists()
                : false),
            'is_member' => (bool) ($request->user()
                ? $this->users()->where('users.id', $request->user()->id)->exists()
                : false),
            'member_pause_requests_enabled' => (bool) $this->member_pause_requests_enabled,
            'is_listed' => (bool) $this->is_listed,
            'teams_are_listed' => (bool) $this->teams_are_listed,
            'members_can_post_to_club' => (bool) $this->members_can_post_to_club,
            'members_can_post_to_teams' => (bool) $this->members_can_post_to_teams,
            'visibility' => $this->visibility,
            'can_manage' => (bool) ($request->user()?->can('update', $this->resource) ?? false),
            'can_manage_training_exercises' => (bool) ($request->user()
                && app(PlanFeatureService::class)->allows($this->resource, 'exercise_library_custom')
                && ($this->owner_id === $request->user()->id
                    || $request->user()->hasAnyRole(Roles::FULL_ACCESS)
                    || ClubPermissions::allows($this->resource, $request->user(), ClubPermissions::EVENTS_MANAGE))),
            'can_delete' => (bool) ($request->user()?->can('delete', $this->resource) ?? false),
            'membership' => $membershipPivot ? [
                'role' => $membershipPivot->role ?? null,
                'status' => $membershipPivot->membership_status ?? null,
                'joined_on' => isset($membershipPivot->joined_on) ? (string) $membershipPivot->joined_on : null,
                'membership_ends_on' => isset($membershipPivot->membership_ends_on) ? (string) $membershipPivot->membership_ends_on : null,
                'pause_requested' => (bool) ($membershipPivot->pause_requested_at ?? false),
                'paused_from' => isset($membershipPivot->paused_from) ? (string) $membershipPivot->paused_from : null,
                'paused_until' => isset($membershipPivot->paused_until) ? (string) $membershipPivot->paused_until : null,
            ] : null,
            'users_count' => $this->whenCounted('users'),
            'teams_count' => $this->whenCounted('teams'),
            'posts_count' => $this->whenCounted('posts'),
            'teams' => TeamResource::collection($this->whenLoaded('teams')),
            'subscription_capabilities' => $planFeatureService->capabilities($this->resource),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
