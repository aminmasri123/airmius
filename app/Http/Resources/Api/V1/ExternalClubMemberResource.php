<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExternalClubMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'club_id' => $this->club_id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'country' => $this->country,
            'street' => $this->street,
            'house_number' => $this->house_number,
            'postal_code' => $this->postal_code,
            'city' => $this->city,
            'role' => $this->role,
            'membership_status' => $this->membership_status,
            'member_number' => $this->member_number,
            'athlete_license_number' => $this->athlete_license_number,
            'athlete_license_valid_until' => $this->athlete_license_valid_until?->toDateString(),
            'joined_on' => $this->joined_on?->toDateString(),
            'membership_ends_on' => $this->membership_ends_on?->toDateString(),
            'membership_ended_at' => $this->membership_ended_at?->toJSON(),
            'linked_user_id' => $this->linked_user_id,
            'invitation_status' => $this->invitation_status,
            'invited_at' => $this->invited_at?->toJSON(),
            'linked_at' => $this->linked_at?->toJSON(),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
