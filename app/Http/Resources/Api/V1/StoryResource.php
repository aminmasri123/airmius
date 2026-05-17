<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'club_id' => $this->club_id,
            'team_id' => $this->team_id,
            'publisher_type' => $this->publisher_type,
            'publisher_id' => $this->publisher_id,
            'visibility' => $this->visibility,
            'caption' => $this->caption,
            'media_url' => $this->media_url,
            'media_thumbnail_url' => $this->media_thumbnail_url,
            'media_kind' => $this->media_kind,
            'media_type' => $this->media_type,
            'media_size' => $this->media_size,
            'moderation_status' => $this->moderation_status,
            'views_count' => $this->whenCounted('views'),
            'reactions_count' => $this->whenCounted('reactions'),
            'viewed_by_me' => (bool) $this->viewed_by_me,
            'my_reaction' => $this->my_reaction,
            'can_delete' => (bool) $this->can_delete,
            'viewer_preview' => $this->viewer_preview ?? [],
            'actor' => $this->actor ?? $this->fallbackActor($request),
            'user' => new UserResource($this->whenLoaded('user')),
            'club' => new ClubResource($this->whenLoaded('club')),
            'team' => new TeamResource($this->whenLoaded('team')),
            'expires_at' => $this->expires_at?->toJSON(),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }

    private function fallbackActor(Request $request): array
    {
        return [
            'key' => 'user:'.$this->user_id,
            'type' => 'user',
            'name' => $this->user?->name,
            'profile_photo_thumb' => $this->user?->profile_photo_thumb,
            'user_card' => $this->user ? (new UserResource($this->user))->resolve($request)['user_card'] : null,
        ];
    }
}
