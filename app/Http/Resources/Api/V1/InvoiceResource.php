<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'club_id' => $this->club_id,
            'user_id' => $this->user_id,
            'number' => $this->number,
            'title' => $this->title,
            'description' => $this->description,
            'amount' => $this->amount,
            ...$this->resource->balancePayload(),
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'source' => $this->source,
            'business_year_period_id' => $this->business_year_period_id,
            'business_year_period' => $this->whenLoaded('businessYearPeriod', fn () => $this->periodReference($this->businessYearPeriod)),
            'contribution_year_period_id' => $this->contribution_year_period_id,
            'contribution_year_period' => $this->whenLoaded('contributionYearPeriod', fn () => $this->periodReference($this->contributionYearPeriod)),
            'billing_period_start' => $this->billing_period_start?->toDateString(),
            'billing_period_end' => $this->billing_period_end?->toDateString(),
            'due_date' => $this->due_date?->toJSON(),
            'issued_at' => $this->issued_at?->toJSON(),
            'paid_at' => $this->paid_at?->toJSON(),
            'reminder_sent_at' => $this->reminder_sent_at?->toJSON(),
            'club' => new ClubResource($this->whenLoaded('club')),
            'user' => new UserResource($this->whenLoaded('user')),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }

    private function periodReference($period): ?array
    {
        return $period ? [
            'id' => $period->id,
            'type' => $period->type,
            'name' => $period->name,
            'starts_on' => $period->starts_on?->toDateString(),
            'ends_on' => $period->ends_on?->toDateString(),
        ] : null;
    }
}
