<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = $this->data ?: [];

        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $data['title'] ?? null,
            'body' => $data['body'] ?? ($data['message'] ?? null),
            'url' => $data['url'] ?? null,
            'data' => $data,
            'read' => (bool) $this->read,
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
