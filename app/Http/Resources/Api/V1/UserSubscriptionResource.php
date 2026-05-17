<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserSubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'subscription_plan_id' => $this->subscription_plan_id,
            'status' => $this->status,
            'cancel_at_period_end' => (bool) $this->cancel_at_period_end,
            'payment_provider' => $this->payment_provider,
            'billing_interval' => $this->billing_interval,
            'provider_subscription_id' => $this->provider_subscription_id,
            'trial_ends_at' => $this->trial_ends_at?->toJSON(),
            'current_period_ends_at' => $this->current_period_ends_at?->toJSON(),
            'next_invoice_at' => $this->next_invoice_at?->toJSON(),
            'grace_period_ends_at' => $this->grace_period_ends_at?->toJSON(),
            'access_restricted_at' => $this->access_restricted_at?->toJSON(),
            'cancels_at' => $this->cancels_at?->toJSON(),
            'cancelled_at' => $this->cancelled_at?->toJSON(),
            'last_renewed_at' => $this->last_renewed_at?->toJSON(),
            'plan' => new SubscriptionPlanResource($this->whenLoaded('plan')),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
