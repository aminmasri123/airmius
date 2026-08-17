<?php

namespace App\Http\Resources\Api\V1;

use App\Support\NotificationRouting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = NotificationRouting::normalizeActionData(
            $this->type,
            $this->data ?: [],
        );
        $actionUrl = $data['action_url'] ?? ($data['url'] ?? null);

        return [
            'id' => $this->id,
            'type' => $this->type,
            'category' => $this->category,
            'priority' => $this->priority,
            'title' => $data['title'] ?? null,
            'body' => $data['body'] ?? ($data['message'] ?? null),
            'url' => $actionUrl,
            'action_url' => $actionUrl,
            'data' => $data,
            'read' => (bool) $this->read,
            'unread' => ! (bool) $this->read,
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
