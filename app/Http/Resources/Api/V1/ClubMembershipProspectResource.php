<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClubMembershipProspectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'club_id' => $this->club_id,
            'user_id' => $this->user_id,
            'club_membership_request_id' => $this->club_membership_request_id,
            'team_id' => $this->team_id,
            'club_membership_type_id' => $this->club_membership_type_id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'status' => $this->status,
            'source' => $this->source,
            'trial_at' => $this->trial_at?->toJSON(),
            'trial_outcome' => $this->trial_outcome,
            'notes' => $this->notes,
            'converted_at' => $this->converted_at?->toJSON(),
            'team' => $this->whenLoaded('team'),
            'membership_type' => $this->whenLoaded('membershipType'),
            'user' => new UserResource($this->whenLoaded('user')),
            'membership_request' => new ClubMembershipRequestResource($this->whenLoaded('membershipRequest')),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
