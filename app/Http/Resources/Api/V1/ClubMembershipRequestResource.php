<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClubMembershipRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'club_id' => $this->club_id,
            'user_id' => $this->user_id,
            'club_membership_type_id' => $this->club_membership_type_id,
            'type' => $this->type,
            'status' => $this->status,
            'message' => $this->message,
            'application_data' => $this->application_data ?: [],
            'accepted_documents' => $this->accepted_documents ?: [],
            'preferred_payment_method' => $this->preferred_payment_method,
            'requested_billing_interval' => $this->requested_billing_interval,
            'requested_pause_from' => $this->requested_pause_from?->toDateString(),
            'requested_pause_until' => $this->requested_pause_until?->toDateString(),
            'preview_amount' => $this->preview_amount,
            'preview_interval' => $this->preview_interval,
            'submitted_at' => $this->created_at?->toJSON(),
            'withdrawn_at' => $this->status === 'withdrawn' ? $this->reviewed_at?->toJSON() : null,
            'reviewed_at' => $this->reviewed_at?->toJSON(),
            'review_note' => $this->review_note,
            'membership_type' => $this->whenLoaded('membershipType'),
            'club' => new ClubResource($this->whenLoaded('club')),
            'user' => new UserResource($this->whenLoaded('user')),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
