<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'club_id' => $this->club_id,
            'team_id' => $this->team_id,
            'event_id' => $this->event_id,
            'folder_id' => $this->folder_id,
            'display_name' => $this->display_name,
            'type' => $this->type,
            'size' => $this->size,
            'url' => $this->url,
            'thumbnail_url' => $this->thumbnail_url,
            'club' => new ClubResource($this->whenLoaded('club')),
            'team' => new TeamResource($this->whenLoaded('team')),
            'event' => new EventResource($this->whenLoaded('event')),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
