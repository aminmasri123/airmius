<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewerId = (int) $request->user()?->id;
        $viewerRole = (int) $this->owner_id === $viewerId && $viewerId > 0
            ? Conversation::ROLE_OWNER
            : ($this->relationLoaded('users')
                ? $this->users->firstWhere('id', $viewerId)?->pivot?->role
                : $this->resource->roleFor($viewerId));
        $isOwner = $viewerRole === Conversation::ROLE_OWNER;
        $canManage = $isOwner || $viewerRole === Conversation::ROLE_MODERATOR;

        return [
            'id' => $this->id,
            'club_id' => $this->club_id,
            'team_id' => $this->team_id,
            'owner_id' => $this->owner_id,
            'type' => $this->type,
            'name' => $this->name,
            'description' => $this->description,
            'posting_policy' => $this->posting_policy ?? 'all',
            'scheduled_at' => $this->scheduled_at?->toJSON(),
            'owner' => new UserResource($this->whenLoaded('owner')),
            'team' => new TeamResource($this->whenLoaded('team')),
            'users' => UserResource::collection($this->whenLoaded('users')),
            'viewer_role' => $this->when(
                $request->user() && $this->type === 'group',
                fn () => $viewerRole,
            ),
            'permissions' => $this->when(
                $request->user() && $this->type === 'group',
                fn () => [
                    'can_edit_group' => $isOwner,
                    'can_manage_members' => $canManage,
                    'can_manage_roles' => $isOwner,
                    'can_delete_group' => $isOwner,
                    'can_send_messages' => $this->posting_policy !== Conversation::POSTING_MANAGEMENT || $canManage,
                ],
            ),
            'direct_peer_has_blocked' => $this->when(
                $this->type === 'direct'
                    && $request->route('conversation')
                    && $request->user()
                    && $this->resource->relationLoaded('users'),
                fn () => (bool) $request->user()->hasBlocked(
                    $this->users->first(fn ($user) => (int) $user->id !== (int) $request->user()->id)
                ),
            ),
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
            'cleared_at' => $this->pivot?->cleared_at,
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
