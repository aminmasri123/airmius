<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MarketplaceProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'club_id' => $this->club_id,
            'title' => $this->title,
            'description' => $this->description,
            'features' => $this->features,
            'product_attributes' => $this->product_attributes,
            'attribute_options' => $this->attribute_options,
            'variants' => $this->variants,
            'image_url' => $this->image_url,
            'gallery_images' => $this->gallery_images,
            'category' => $this->category,
            'offer_type' => $this->offer_type,
            'product_type' => $this->product_type,
            'sku' => $this->sku,
            'is_shippable' => $this->is_shippable,
            'manages_stock' => $this->manages_stock,
            'stock_quantity' => $this->stock_quantity,
            'low_stock_threshold' => $this->low_stock_threshold,
            'price_cents' => $this->price_cents,
            'currency' => $this->currency,
            'available_countries' => $this->available_countries,
            'status' => $this->status,
            'moderation_status' => $this->moderation_status,
            'commission_percent' => $this->commission_percent,
            'seller' => new UserResource($this->whenLoaded('user')),
            'club' => new ClubResource($this->whenLoaded('club')),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
