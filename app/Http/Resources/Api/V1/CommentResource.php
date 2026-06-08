<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'post_id' => $this->post_id,
            'user_id' => $this->user_id,
            'content' => $this->content,
            'moderation_status' => $this->moderation_status,
            'likes_count' => $this->whenCounted('likes'),
            'mine' => $request->user()?->id === $this->user_id,
            'can_delete' => (bool) (
                $request->user()?->id === $this->user_id
                || $request->user()?->id === $this->post?->user_id
                || ($request->user()?->can('delete', $this->resource) ?? false)
            ),
            'user' => new UserResource($this->whenLoaded('user')),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
