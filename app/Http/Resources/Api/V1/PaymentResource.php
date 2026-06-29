<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'club_id' => $this->club_id,
            'user_id' => $this->user_id,
            'invoice_id' => $this->invoice_id,
            'purpose' => $this->purpose,
            'amount' => $this->amount,
            'status' => $this->status,
            'method' => $this->method,
            'reference' => $this->reference,
            'paid_at' => $this->paid_at?->toJSON(),
            'notes' => $this->notes,
            'club' => new ClubResource($this->whenLoaded('club')),
            'invoice' => new InvoiceResource($this->whenLoaded('invoice')),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
