<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if ($this->resource->relationLoaded('invoice') && $this->invoice) {
            $this->invoice->loadMissing(['paymentHistory', 'membershipUser', 'externalMember']);
        }

        return [
            'id' => $this->id,
            'club_id' => $this->club_id,
            'user_id' => $this->user_id,
            'club_external_member_id' => $this->club_external_member_id,
            'invoice_id' => $this->invoice_id,
            'purpose' => $this->purpose,
            'amount' => $this->amount,
            'status' => $this->status,
            'method' => $this->method,
            'reference' => $this->reference,
            'receipt_number' => $this->receipt_number,
            'donation_number' => $this->donation_number,
            'donation_type' => $this->donation_type,
            'donation_restriction' => $this->donation_restriction,
            'donation_campaign' => $this->donation_campaign,
            'donor_type' => $this->donor_type,
            'donor_snapshot' => $this->donor_snapshot,
            'club_business_partner_id' => $this->club_business_partner_id,
            'sponsor_id' => $this->sponsor_id,
            'paid_at' => $this->paid_at?->toJSON(),
            'notes' => $this->notes,
            'club' => new ClubResource($this->whenLoaded('club')),
            'invoice' => new InvoiceResource($this->whenLoaded('invoice')),
            'user' => $this->whenLoaded('user', fn () => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ] : null),
            'external_member' => $this->whenLoaded('externalMember', fn () => $this->externalMember ? [
                'id' => $this->externalMember->id,
                'name' => $this->externalMember->name,
                'email' => $this->externalMember->email,
                'is_external' => true,
            ] : null),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
