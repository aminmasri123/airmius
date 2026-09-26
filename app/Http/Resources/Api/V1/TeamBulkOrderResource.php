<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamBulkOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'club_id' => $this->club_id,
            'team_id' => $this->team_id,
            'marketplace_product_id' => $this->marketplace_product_id,
            'title' => $this->title,
            'status' => $this->status,
            'order_window_starts_at' => $this->order_window_starts_at?->toIso8601String(),
            'order_deadline_at' => $this->order_deadline_at?->toIso8601String(),
            'supplier_name' => $this->supplier_name,
            'supplier_reference' => $this->supplier_reference,
            'unit_price_cents' => $this->unit_price_cents,
            'funded_share_cents' => $this->funded_share_cents,
            'payable_unit_price_cents' => max(0, $this->unit_price_cents - $this->funded_share_cents),
            'currency' => $this->currency,
            'price_snapshot' => $this->price_snapshot,
            'items_count' => $this->whenCounted('items'),
            'total_quantity' => $this->when(
                $this->relationLoaded('items'),
                fn () => $this->items->sum('quantity')
            ),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'user_id' => $item->user_id,
                'user_name' => $item->user?->name,
                'quantity' => $item->quantity,
                'personalization' => $item->personalization,
                'unit_price_cents' => $item->unit_price_cents,
                'funded_share_cents' => $item->funded_share_cents,
                'payable_unit_price_cents' => $item->payable_unit_price_cents,
                'currency' => $item->currency,
            ])->values()),
        ];
    }
}
