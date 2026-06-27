<?php

namespace App\Http\Resources\Api\V1;

use App\Support\UploadStorage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewer = $request->user();
        $viewerIsMember = $viewer
            ? ($this->relationLoaded('users')
                ? $this->users->contains('id', $viewer->id)
                : $this->users()->where('users.id', $viewer->id)->exists())
            : false;
        $viewerIsClubMember = $viewer && $this->club_id
            ? ($this->relationLoaded('club') && $this->club?->relationLoaded('users')
                ? $this->club->users->contains('id', $viewer->id)
                : $this->club?->users()->where('users.id', $viewer->id)->exists())
            : false;
        $pendingJoinRequest = $viewer
            ? ($this->relationLoaded('joinRequests')
                ? $this->joinRequests->firstWhere('user_id', $viewer->id)
                : $this->joinRequests()->where('user_id', $viewer->id)->where('status', 'pending')->first())
            : null;
        $viewerPendingJoinRequestId = $this->getAttribute('viewer_pending_join_request_id') ?? ($pendingJoinRequest?->status === 'pending' ? $pendingJoinRequest->id : null);
        $canRequestJoin = $this->getAttribute('can_request_join');
        if ($canRequestJoin === null) {
            $canRequestJoin = $viewerIsClubMember && ! $viewerIsMember && ! $viewerPendingJoinRequestId;
        }
        $canManageTeam = (bool) ($request->user()?->can('update', $this->resource) ?? false);
        $pendingJoinRequests = collect();
        if ($canManageTeam) {
            $pendingJoinRequests = $this->relationLoaded('joinRequests')
                ? $this->joinRequests->where('status', 'pending')->values()
                : $this->joinRequests()->with('user')->where('status', 'pending')->get();
        }

        return [
            'id' => $this->id,
            'club_id' => $this->club_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'sport_type' => $this->sport_type,
            'age_group' => $this->age_group,
            'gender' => $this->gender,
            'visibility' => $this->visibility,
            'logo_url' => UploadStorage::url($this->logo),
            'cover_image_url' => UploadStorage::url($this->cover_image),
            'can_manage' => $canManageTeam,
            'can_delete' => (bool) ($request->user()?->can('delete', $this->resource) ?? false),
            'viewer_is_member' => $viewerIsMember,
            'viewer_pending_join_request_id' => $viewerPendingJoinRequestId,
            'can_request_join' => (bool) $canRequestJoin,
            'pending_join_requests' => $pendingJoinRequests->map(fn ($joinRequest) => [
                'id' => $joinRequest->id,
                'team_id' => $joinRequest->team_id,
                'user_id' => $joinRequest->user_id,
                'name' => $joinRequest->user?->name ?? 'Mitglied',
                'email' => $joinRequest->user?->email,
                'status' => $joinRequest->status,
                'created_at' => $joinRequest->created_at?->toJSON(),
            ])->values(),
            'membership' => $this->pivot ? [
                'role' => $this->pivot->role ?? null,
            ] : null,
            'club' => new ClubResource($this->whenLoaded('club')),
            'users' => UserResource::collection($this->whenLoaded('users')),
            'users_count' => $this->whenCounted('users'),
            'events_count' => $this->whenCounted('events'),
            'attendance_stats' => $this->when($this->getAttribute('attendance_stats') !== null, $this->getAttribute('attendance_stats')),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
