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
