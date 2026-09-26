<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClubMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'country' => $this->country,
            'street' => $this->street,
            'house_number' => $this->house_number,
            'postal_code' => $this->postal_code,
            'city' => $this->city,
            'athlete_license_number' => $this->athlete_license_number,
            'athlete_license_valid_until' => $this->athlete_license_valid_until?->toDateString(),
            'profile_photo_url' => $this->profile_photo_url,
            'profile_photo_thumb' => $this->profile_photo_thumb,
            'membership' => [
                'role' => $this->pivot?->role,
                'roles' => $this->pivot?->roles ?? [],
                'status' => $this->pivot?->membership_status,
                'club_membership_type_id' => $this->pivot?->club_membership_type_id,
                'family_group_key' => $this->pivot?->family_group_key,
                'contribution_payer_user_id' => $this->pivot?->contribution_payer_user_id
                    ? (int) $this->pivot->contribution_payer_user_id
                    : null,
                'member_number' => $this->pivot?->member_number,
                'contribution_amount' => $this->pivot?->contribution_amount,
                'contribution_interval' => $this->pivot?->contribution_interval,
                'contribution_next_invoice_on' => $this->pivot?->contribution_next_invoice_on
                    ? (string) $this->pivot->contribution_next_invoice_on
                    : null,
                'joined_on' => $this->pivot?->joined_on ? (string) $this->pivot->joined_on : null,
                'membership_ends_on' => $this->pivot?->membership_ends_on ? (string) $this->pivot->membership_ends_on : null,
                'membership_ended_at' => $this->pivot?->membership_ended_at?->toJSON(),
                'paused_from' => $this->pivot?->paused_from ? (string) $this->pivot->paused_from : null,
                'paused_until' => $this->pivot?->paused_until ? (string) $this->pivot->paused_until : null,
            ],
            'invoices_count' => $this->whenCounted('invoices'),
            'payments_count' => $this->whenCounted('payments'),
        ];
    }
}
