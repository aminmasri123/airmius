<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $imageProxyUrl = $this->image
            ? route('api.v1.posts.image', $this->resource)
            : null;

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'club_id' => $this->club_id,
            'team_id' => $this->team_id,
            'sport_id' => $this->sport_id,
            'post_type' => $this->post_type,
            'content_origin' => $this->content_origin,
            'content' => $this->content,
            // Do not expose the storage path or a public disk URL. Private posts
            // must remain protected by the same visibility policy as the feed.
            'image' => $imageProxyUrl,
            'image_url' => $imageProxyUrl,
            'image_proxy_url' => $imageProxyUrl,
            'visibility' => $this->visibility,
            'moderation_status' => $this->moderation_status,
            'user' => new UserResource($this->whenLoaded('user')),
            'club' => new ClubResource($this->whenLoaded('club')),
            'team' => new TeamResource($this->whenLoaded('team')),
            'sport' => $this->whenLoaded('sport', fn () => $this->sport ? [
                'id' => $this->sport->id,
                'name' => $this->sport->name,
                'slug' => $this->sport->slug,
            ] : null),
            'sport_skills' => $this->whenLoaded('sportSkills', fn () => $this->sportSkills->map(fn ($skill) => [
                'id' => $skill->id,
                'name' => $skill->name,
            ])->values()),
            'attachments' => $this->whenLoaded('attachments', fn () => $this->attachments->map(fn ($attachment) => [
                'id' => $attachment->id,
                'file' => $attachment->file ? (new FileResource($attachment->file))->resolve($request) : null,
            ])->values()),
            'files' => $this->whenLoaded('attachments', fn () => $this->attachments
                ->map(fn ($attachment) => $attachment->file ? (new FileResource($attachment->file))->resolve($request) : null)
                ->filter()
                ->values()),
            'comments_count' => $this->whenCounted('comments'),
            'likes_count' => $this->whenCounted('likes'),
            'helpfuls_count' => $this->whenCounted('helpfuls'),
            'liked_by_me' => (bool) ($this->liked_by_me ?? false),
            'helpful_by_me' => (bool) ($this->helpful_by_me ?? false),
            'can_update' => (bool) ($this->user_id === $request->user()?->id || ($request->user()?->can('update', $this->resource) ?? false)),
            'can_delete' => (bool) ($this->user_id === $request->user()?->id || ($request->user()?->can('delete', $this->resource) ?? false)),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
