<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MarketplacePayoutResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'currency' => $this->currency,
            'gross_cents' => $this->gross_cents,
            'commission_cents' => $this->commission_cents,
            'amount_cents' => $this->amount_cents,
            'adjustment_cents' => $this->adjustment_cents,
            'recovery_cents' => $this->recovery_cents,
            'method' => $this->method,
            'status' => $this->status,
            'reconciliation_status' => $this->reconciliation_status,
            'reference' => $this->reference,
            'notes' => $this->notes,
            'paid_at' => $this->paid_at?->toJSON(),
            'user' => new UserResource($this->whenLoaded('user')),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
