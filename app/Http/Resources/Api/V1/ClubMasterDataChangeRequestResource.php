<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClubMasterDataChangeRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'club_id' => $this->club_id,
            'status' => $this->status,
            'fields' => $this->fields ?? [],
            'sensitive_fields' => array_values(array_intersect($this->fields ?? [], [
                'official_club_number',
                'registry_authority',
                'registry_number',
                'federation_affiliations',
                'tax_authority',
                'tax_number',
                'vat_id',
                'tax_status',
                'tax_exemption_valid_until',
                'sepa_account_holder',
                'sepa_iban',
                'sepa_bic',
            ])),
            'conflicts' => $this->conflicts ?? [],
            'requester' => $this->whenLoaded('requester', fn () => [
                'id' => $this->requester?->id,
                'name' => $this->requester?->name,
                'email' => $this->requester?->email,
            ]),
            'reviewer' => $this->whenLoaded('reviewer', fn () => $this->reviewer ? [
                'id' => $this->reviewer->id,
                'name' => $this->reviewer->name,
                'email' => $this->reviewer->email,
            ] : null),
            'review_note' => $this->review_note,
            'reviewed_at' => $this->reviewed_at?->toJSON(),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
