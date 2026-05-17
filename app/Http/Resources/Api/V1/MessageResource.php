<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'sender_id' => $this->sender_id,
            'message' => $this->message,
            'kind' => $this->kind,
            'metadata' => $this->metadata,
            'status' => $this->status,
            'moderation_status' => $this->moderation_status,
            'delivery_status' => $this->delivery_status,
            'read_at' => $this->read_at?->toJSON(),
            'sender' => new UserResource($this->whenLoaded('sender')),
            'attachments' => $this->whenLoaded('attachments', fn () => $this->attachments->map(fn ($attachment) => [
                'id' => $attachment->id,
                'file_id' => $attachment->file_id,
                'display_name' => $attachment->file?->display_name,
                'type' => $attachment->file?->type,
                'size' => $attachment->file?->size,
                'url' => $attachment->file?->url,
                'thumbnail_url' => $attachment->file?->thumbnail_url,
            ])->values()),
            'reactions' => $this->whenLoaded('reactions', fn () => $this->reactions->map(fn ($reaction) => [
                'id' => $reaction->id,
                'emoji' => $reaction->emoji,
                'user' => new UserResource($reaction->user),
                'created_at' => $reaction->created_at?->toJSON(),
            ])->values()),
            'receipts' => $this->whenLoaded('receipts', fn () => $this->receipts->map(fn ($receipt) => [
                'id' => $receipt->id,
                'user_id' => $receipt->user_id,
                'delivered_at' => $receipt->delivered_at?->toJSON(),
                'read_at' => $receipt->read_at?->toJSON(),
            ])->values()),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
