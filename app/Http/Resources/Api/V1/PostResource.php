<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'club_id' => $this->club_id,
            'team_id' => $this->team_id,
            'sport_id' => $this->sport_id,
            'post_type' => $this->post_type,
            'content_origin' => $this->content_origin,
            'content' => $this->content,
            'image' => $this->image,
            'visibility' => $this->visibility,
            'moderation_status' => $this->moderation_status,
            'user' => new UserResource($this->whenLoaded('user')),
            'club' => new ClubResource($this->whenLoaded('club')),
            'team' => new TeamResource($this->whenLoaded('team')),
            'comments_count' => $this->whenCounted('comments'),
            'likes_count' => $this->whenCounted('likes'),
            'helpfuls_count' => $this->whenCounted('helpfuls'),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
