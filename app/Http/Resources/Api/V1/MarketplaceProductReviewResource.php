<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MarketplaceProductReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'marketplace_product_id' => $this->marketplace_product_id,
            'user_id' => $this->user_id,
            'rating' => $this->rating,
            'title' => $this->title,
            'body' => $this->body,
            'status' => $this->status,
            'verified_purchase' => (bool) $this->verified_purchase,
            'author' => new UserResource($this->whenLoaded('user')),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
