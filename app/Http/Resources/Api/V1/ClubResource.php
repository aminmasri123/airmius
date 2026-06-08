<?php

namespace App\Http\Resources\Api\V1;

use App\Support\UploadStorage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClubResource extends JsonResource
{
    public function toArray(Request $request): array
    {
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
            'membership' => $this->pivot ? [
                'role' => $this->pivot->role ?? null,
                'status' => $this->pivot->membership_status ?? null,
                'joined_on' => isset($this->pivot->joined_on) ? (string) $this->pivot->joined_on : null,
            ] : null,
            'users_count' => $this->whenCounted('users'),
            'teams_count' => $this->whenCounted('teams'),
            'teams' => TeamResource::collection($this->whenLoaded('teams')),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
