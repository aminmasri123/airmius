<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommerceOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'club_id' => $this->club_id,
            'orderable_type' => $this->orderable_type,
            'orderable_id' => $this->orderable_id,
            'type' => $this->type,
            'provider' => $this->provider,
            'billing_interval' => $this->billing_interval,
            'status' => $this->status,
            'shipping_status' => $this->shipping_status,
            'shipping_carrier' => $this->shipping_carrier,
            'shipping_label_url' => $this->shipping_label_url,
            'currency' => $this->currency,
            'amount_cents' => $this->amount_cents,
            'item_gross_cents' => $this->item_gross_cents,
            'net_cents' => $this->net_cents,
            'tax_cents' => $this->tax_cents,
            'shipping_cents' => $this->shipping_cents,
            'commission_cents' => $this->commission_cents,
            'refunded_cents' => $this->refunded_cents,
            'invoice_number' => $this->invoice_number,
            'credit_note_number' => $this->credit_note_number,
            'issue_status' => $this->issue_status,
            'issue_note' => $this->issue_note,
            'payment_reference' => $this->payment_reference,
            'tracking_number' => $this->tracking_number,
            'tracking_url' => $this->tracking_url,
            'checkout_url' => $this->checkout_url,
            'due_at' => $this->due_at?->toJSON(),
            'completed_at' => $this->completed_at?->toJSON(),
            'shipped_at' => $this->shipped_at?->toJSON(),
            'delivered_at' => $this->delivered_at?->toJSON(),
            'club' => new ClubResource($this->whenLoaded('club')),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'orderable_type' => $item->orderable_type,
                'orderable_id' => $item->orderable_id,
                'title' => $item->title,
                'sku' => $item->sku,
                'quantity' => $item->quantity,
                'unit_gross_cents' => $item->unit_gross_cents,
                'shipping_cents' => $item->shipping_cents,
                'net_cents' => $item->net_cents,
                'tax_cents' => $item->tax_cents,
                'total_cents' => $item->total_cents,
                'currency' => $item->currency,
                'tax_rate_percent' => $item->tax_rate_percent,
                'is_shippable' => $item->is_shippable,
            ])->values()),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
