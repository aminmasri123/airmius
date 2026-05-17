<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $country = strtoupper((string) ($request->query('country') ?: $request->user()?->country));
        $price = $this->priceForCountry($country);

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'target_actor' => $this->target_actor,
            'name' => $this->name,
            'description' => $this->description,
            'monthly_price_cents' => $this->monthly_price_cents,
            'yearly_price_cents' => $this->yearly_price_cents,
            'currency' => $this->currency,
            'localized_price' => $price,
            'member_limit' => $this->member_limit,
            'team_limit' => $this->team_limit,
            'storage_gb' => $this->storage_gb,
            'features' => $this->features,
            'minimum_term_months' => $this->minimum_term_months,
            'cancellation_notice_days' => $this->cancellation_notice_days,
            'cta_label' => $this->cta_label,
            'badge' => $this->badge,
            'sort_order' => $this->sort_order,
            'is_public' => (bool) $this->is_public,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
