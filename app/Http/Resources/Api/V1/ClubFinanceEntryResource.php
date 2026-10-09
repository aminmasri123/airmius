<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClubFinanceEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'club_id' => $this->club_id,
            'user_id' => $this->user_id,
            'receipt_file_id' => $this->receipt_file_id,
            'type' => $this->type,
            'account' => $this->account,
            'category' => $this->category,
            'title' => $this->title,
            'amount' => $this->amount,
            'booked_on' => $this->booked_on?->toDateString(),
            'reference' => $this->reference,
            'description' => $this->description,
            'business_year_period_id' => $this->business_year_period_id,
            'business_year_period' => $this->whenLoaded('businessYearPeriod', fn () => $this->businessYearPeriod ? [
                'id' => $this->businessYearPeriod->id,
                'name' => $this->businessYearPeriod->name,
                'starts_on' => $this->businessYearPeriod->starts_on?->toDateString(),
                'ends_on' => $this->businessYearPeriod->ends_on?->toDateString(),
            ] : null),
            'receipt_file' => $this->whenLoaded('receiptFile', fn () => $this->receiptFile ? [
                'id' => $this->receiptFile->id,
                'display_name' => $this->receiptFile->display_name,
                'type' => $this->receiptFile->type,
                'size' => $this->receiptFile->size,
                'url' => $this->receiptFile->url,
                'thumbnail_url' => $this->receiptFile->thumbnail_url,
                'preview_url' => route('api.v1.files.preview', $this->receiptFile->id),
            ] : null),
            'user' => $this->whenLoaded('user', fn () => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ] : null),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
