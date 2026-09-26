<?php

namespace App\Http\Resources\Api\V1;

use App\Services\PlanFeatureService;
use App\Support\ClubPermissions;
use App\Support\TeamRoles;
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
        $canManageMembers = (bool) ($viewer?->can('manageMembers', $this->resource) ?? false);
        $canUpdateMemberRoles = (bool) ($viewer?->can('updateMemberRole', $this->resource) ?? false);
        $canRemoveMembers = $this->getAttribute('can_remove_members')
            ?? (bool) ($viewer?->can('removeMember', $this->resource) ?? false);
        $canEditTrainingExercises = (bool) ($viewer && $this->club && (
            ClubPermissions::allows($this->club, $viewer, ClubPermissions::TRAINING_EXERCISES_EDIT)
            || ClubPermissions::allowsForTeam($this->resource, $viewer, ClubPermissions::TRAINING_EXERCISES_EDIT)
            || (! ClubPermissions::explicitlyDenies($this->club, $viewer, ClubPermissions::TRAINING_EXERCISES_EDIT)
                && $this->users()->whereKey($viewer->id)->wherePivotIn('role', TeamRoles::TEAM_STAFF_ROLES)->exists())
        ));
        $pendingJoinRequests = collect();
        if ($canManageMembers) {
            $pendingJoinRequests = $this->relationLoaded('joinRequests')
                ? $this->joinRequests->where('status', 'pending')->values()
                : $this->joinRequests()->with('user')->where('status', 'pending')->get();
        }

        return [
            'id' => $this->id,
            'club_id' => $this->club_id,
            'club_department_id' => $this->club_department_id,
            'club_location_id' => $this->club_location_id,
            'club_training_group_id' => $this->club_training_group_id,
            'sport_year_period_id' => $this->sport_year_period_id,
            'sport_year_period' => $this->whenLoaded('sportYearPeriod', fn () => $this->sportYearPeriod ? [
                'id' => $this->sportYearPeriod->id,
                'name' => $this->sportYearPeriod->name,
                'starts_on' => $this->sportYearPeriod->starts_on?->format('Y-m-d'),
                'ends_on' => $this->sportYearPeriod->ends_on?->format('Y-m-d'),
            ] : null),
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'sport_type' => $this->sport_type,
            'age_group' => $this->age_group,
            'gender' => $this->gender,
            'visibility' => $this->visibility,
            'birth_year_from' => $this->birth_year_from,
            'birth_year_to' => $this->birth_year_to,
            'performance_level' => $this->performance_level,
            'capacity' => $this->capacity,
            'waitlist_enabled' => (bool) $this->waitlist_enabled,
            'valid_from' => $this->valid_from?->format('Y-m-d'),
            'valid_until' => $this->valid_until?->format('Y-m-d'),
            'logo_url' => UploadStorage::url($this->logo),
            'cover_image_url' => UploadStorage::url($this->cover_image),
            'can_manage' => $canManageTeam,
            'can_manage_members' => $canManageMembers,
            'can_update_member_roles' => $canUpdateMemberRoles,
            'can_manage_metadata' => (bool) ($viewer && $this->club
                && ClubPermissions::allows($this->club, $viewer, ClubPermissions::METADATA_EDIT)),
            'can_create_training_exercises' => $canEditTrainingExercises
                && app(PlanFeatureService::class)->allows($this->club, 'exercise_library_custom'),
            'can_delete' => (bool) ($request->user()?->can('delete', $this->resource) ?? false),
            'can_remove_members' => (bool) $canRemoveMembers,
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
