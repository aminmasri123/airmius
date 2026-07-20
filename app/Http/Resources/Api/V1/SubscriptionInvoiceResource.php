<?php

namespace App\Http\Resources\Api\V1;

use App\Support\BillingOverview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionInvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_checkout_id' => $this->payment_checkout_id,
            'user_id' => $this->user_id,
            'club_id' => $this->club_id,
            'subscription_plan_id' => $this->subscription_plan_id,
            'subscription_type' => $this->subscription_type,
            'subscription_id' => $this->subscription_id,
            'number' => $this->number,
            'title' => $this->title,
            'description' => $this->description,
            'amount_cents' => $this->amount_cents,
            'currency' => $this->currency,
            'status' => $this->status,
            'status_label' => BillingOverview::statusLabel($this->status),
            'payment_method' => $this->payment_method,
            'payment_reference' => $this->payment_reference,
            'billing_period_start' => $this->billing_period_start?->toDateString(),
            'billing_period_end' => $this->billing_period_end?->toDateString(),
            'issued_at' => $this->issued_at?->toJSON(),
            'due_at' => $this->due_at?->toJSON(),
            'paid_at' => $this->paid_at?->toJSON(),
            'meta' => $this->meta,
            'club' => new ClubResource($this->whenLoaded('club')),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
