<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'club_id' => $this->club_id,
            'team_id' => $this->team_id,
            'owner_id' => $this->owner_id,
            'type' => $this->type,
            'name' => $this->name,
            'description' => $this->description,
            'owner' => new UserResource($this->whenLoaded('owner')),
            'team' => new TeamResource($this->whenLoaded('team')),
            'users' => UserResource::collection($this->whenLoaded('users')),
            'messages_count' => $this->whenCounted('messages'),
            'unread_messages_count' => $this->when(isset($this->unread_messages_count), $this->unread_messages_count),
            'latest_message' => $this->when(
                $this->resource->relationLoaded('latestVisibleMessage'),
                fn () => $this->latestVisibleMessage
                    ? new MessageResource($this->latestVisibleMessage)
                    : null,
            ),
            'joined_at' => $this->pivot?->joined_at,
            'muted_until' => $this->pivot?->muted_until,
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
