<?php

namespace App\Http\Resources\Api\V1;

use App\Services\PlanFeatureService;
use App\Support\ClubPermissions;
use App\Support\ClubProfilePermissions;
use App\Support\UploadStorage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClubResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $membershipPivot = $this->pivot;
        if (! $membershipPivot && $request->user()) {
            $membershipPivot = $this->users()
                ->whereKey($request->user()->id)
                ->first()?->pivot;
        }

        $planFeatureService = app(PlanFeatureService::class);
        $canManage = (bool) ($request->user()?->can('update', $this->resource) ?? false);
        $profileCapabilities = $request->user()
            ? ClubProfilePermissions::capabilities($this->resource, $request->user())
            : ['profile' => false, 'legal' => false, 'contact' => false, 'branding' => false];
        $canViewMetadata = (bool) ($request->user()
            && ClubPermissions::allows($this->resource, $request->user(), ClubPermissions::METADATA_VIEW));
        $canEditMetadata = (bool) ($request->user()
            && ClubPermissions::allows($this->resource, $request->user(), ClubPermissions::METADATA_EDIT));
        $canManageMembers = (bool) ($request->user()
            && ClubPermissions::allows($this->resource, $request->user(), ClubPermissions::MEMBERS_MANAGE));
        $canViewFinance = (bool) ($request->user()
            && ClubPermissions::allows($this->resource, $request->user(), ClubPermissions::FINANCE_VIEW));
        $canViewTrainingExercises = (bool) ($request->user()
            && ClubPermissions::allows($this->resource, $request->user(), ClubPermissions::TRAINING_EXERCISES_VIEW));
        $canEditTrainingExercises = (bool) ($request->user()
            && ClubPermissions::allows($this->resource, $request->user(), ClubPermissions::TRAINING_EXERCISES_EDIT));
        $canDeleteTrainingExercises = (bool) ($request->user()
            && ClubPermissions::allows($this->resource, $request->user(), ClubPermissions::TRAINING_EXERCISES_DELETE));
        $canCreateTeamsGlobally = (bool) ($request->user()
            && ClubPermissions::allows($this->resource, $request->user(), ClubPermissions::TEAMS_EDIT));
        $teamCreationDepartments = $request->user()
            ? $this->departments()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->filter(fn ($department) => $canCreateTeamsGlobally
                    || ClubPermissions::allowsInScope(
                        $this->resource,
                        $request->user(),
                        ClubPermissions::TEAMS_EDIT,
                        'department',
                        (int) $department->id,
                    ))
                ->map->only(['id', 'name'])
                ->values()
            : collect();
        $contactVisible = $profileCapabilities['contact'] || (bool) $this->contact_details_public;
        $contactPersons = collect($this->contact_persons ?? [])
            ->filter(fn (array $person) => $profileCapabilities['contact'] || (bool) ($person['is_public'] ?? false))
            ->values()->all();

        return [
            'id' => $this->id,
            'owner_id' => $this->owner_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'sport_type' => $this->sport_type,
            'description' => $this->description,
            'city' => $this->city,
            'state' => $this->state,
            'postal_code' => $this->postal_code,
            'street' => $this->street,
            'house_number' => $this->house_number,
            'country' => $this->country,
            'logo_url' => UploadStorage::url($this->logo),
            'cover_image_url' => UploadStorage::url($this->cover_image),
            'brand_primary_color' => $this->brand_primary_color,
            'brand_secondary_color' => $this->brand_secondary_color,
            'brand_accent_color' => $this->brand_accent_color,
            'is_official' => (bool) $this->is_official,
            'verification_status' => $this->verification_status,
            'membership_requests_enabled' => (bool) $this->membership_requests_enabled,
            'accepts_membership_applications' => (bool) $this->membership_requests_enabled,
            'has_pending_membership_request' => (bool) ($request->user()
                ? $this->membershipRequests()
                    ->where('user_id', $request->user()->id)
                    ->where('type', 'membership')
                    ->whereIn('status', ['pending', 'information_requested', 'waitlisted'])
                    ->exists()
                : false),
            'is_member' => (bool) ($request->user()
                ? $this->users()->where('users.id', $request->user()->id)->exists()
                : false),
            'member_pause_requests_enabled' => (bool) $this->member_pause_requests_enabled,
            'is_listed' => (bool) $this->is_listed,
            'teams_are_listed' => (bool) $this->teams_are_listed,
            'members_can_post_to_club' => (bool) $this->members_can_post_to_club,
            'members_can_post_to_teams' => (bool) $this->members_can_post_to_teams,
            'visibility' => $this->visibility,
            $this->mergeWhen($contactVisible, [
                'contact_email' => $this->contact_email,
                'contact_phone' => $this->contact_phone,
                'website_url' => $this->website_url,
                'contact_persons' => $contactPersons,
            ]),
            $this->mergeWhen($profileCapabilities['contact'], [
                'contact_details_public' => (bool) $this->contact_details_public,
            ]),
            $this->mergeWhen($profileCapabilities['branding'], [
                'letterhead_settings' => $this->letterhead_settings ?? [
                    'show_logo' => true,
                    'header' => null,
                    'address_line' => null,
                    'footer' => null,
                ],
                'document_templates' => $this->document_templates ?? [],
            ]),
            $this->mergeWhen($profileCapabilities['legal'], [
                'registry_authority' => $this->registry_authority,
                'registry_number' => $this->registry_number,
                'federation_affiliations' => $this->federation_affiliations ?? [],
                'tax_authority' => $this->tax_authority,
                'tax_number' => $this->tax_number,
                'vat_id' => $this->vat_id,
                'tax_status' => $this->tax_status,
                'tax_exemption_valid_until' => $this->tax_exemption_valid_until?->format('Y-m-d'),
            ]),
            'can_manage' => $canManage,
            'can_edit_club_profile' => $profileCapabilities['profile'],
            'can_edit_club_legal' => $profileCapabilities['legal'],
            'can_edit_club_contact' => $profileCapabilities['contact'],
            'can_edit_club_branding' => $profileCapabilities['branding'],
            'can_edit_sponsors' => (bool) ($request->user()
                && ClubPermissions::allows($this->resource, $request->user(), ClubPermissions::SPONSORS_EDIT)),
            'can_delete_sponsors' => (bool) ($request->user()
                && ClubPermissions::allows($this->resource, $request->user(), ClubPermissions::SPONSORS_DELETE)),
            'can_view_subscriptions' => (bool) ($request->user()
                && ClubPermissions::allows($this->resource, $request->user(), ClubPermissions::SUBSCRIPTIONS_VIEW)),
            'can_edit_subscriptions' => (bool) ($request->user()
                && ClubPermissions::allows($this->resource, $request->user(), ClubPermissions::SUBSCRIPTIONS_EDIT)),
            'can_edit_jobs' => (bool) ($request->user()
                && ClubPermissions::allows($this->resource, $request->user(), ClubPermissions::JOBS_EDIT)),
            'can_publish_jobs' => (bool) ($request->user()
                && ClubPermissions::allows($this->resource, $request->user(), ClubPermissions::JOBS_PUBLISH)),
            'can_delete_jobs' => (bool) ($request->user()
                && ClubPermissions::allows($this->resource, $request->user(), ClubPermissions::JOBS_DELETE)),
            'can_view_recruiting' => (bool) ($request->user()
                && ClubPermissions::allows($this->resource, $request->user(), ClubPermissions::RECRUITING_VIEW)),
            'can_view_cockpit' => (bool) ($request->user()
                && ClubPermissions::allows($this->resource, $request->user(), ClubPermissions::COCKPIT_VIEW)),
            'can_edit_teams' => $canCreateTeamsGlobally || $teamCreationDepartments->isNotEmpty(),
            'can_create_teams_globally' => $canCreateTeamsGlobally,
            'team_creation_departments' => $teamCreationDepartments,
            'can_manage_members' => $canManageMembers,
            'can_verify_member_cards' => (bool) ($request->user()
                && ((int) $this->owner_id === (int) $request->user()->id
                    || $canManageMembers
                    || ClubPermissions::allows($this->resource, $request->user(), ClubPermissions::EVENTS_EDIT))),
            'can_view_finance' => $canViewFinance,
            'can_view_metadata' => $canViewMetadata,
            'can_edit_metadata' => $canEditMetadata,
            'can_edit_announcements' => (bool) ($request->user()
                && ClubPermissions::allowsAnyScope($this->resource, $request->user(), ClubPermissions::ANNOUNCEMENTS_EDIT)),
            'can_publish_announcements' => (bool) ($request->user()
                && ClubPermissions::allowsAnyScope($this->resource, $request->user(), ClubPermissions::ANNOUNCEMENTS_PUBLISH)),
            'can_delete_announcements' => (bool) ($request->user()
                && ClubPermissions::allowsAnyScope($this->resource, $request->user(), ClubPermissions::ANNOUNCEMENTS_DELETE)),
            'can_edit_surveys' => (bool) ($request->user()
                && ClubPermissions::allowsAnyScope($this->resource, $request->user(), ClubPermissions::SURVEYS_EDIT)),
            'can_close_surveys' => (bool) ($request->user()
                && ClubPermissions::allowsAnyScope($this->resource, $request->user(), ClubPermissions::SURVEYS_CLOSE)),
            'can_delete_surveys' => (bool) ($request->user()
                && ClubPermissions::allowsAnyScope($this->resource, $request->user(), ClubPermissions::SURVEYS_DELETE)),
            'can_view_training_exercises' => $canViewTrainingExercises,
            'can_create_training_exercises' => $canEditTrainingExercises
                && $planFeatureService->allows($this->resource, 'exercise_library_custom'),
            'can_edit_training_exercises' => $canEditTrainingExercises,
            'can_delete_training_exercises' => $canDeleteTrainingExercises,
            'can_manage_training_exercises' => $canEditTrainingExercises
                && $planFeatureService->allows($this->resource, 'exercise_library_custom'),
            'can_delete' => (bool) ($request->user()?->can('delete', $this->resource) ?? false),
            'deletion_scheduled_at' => $canManage ? $this->deletion_scheduled_at?->toIso8601String() : null,
            'membership' => $membershipPivot ? [
                'role' => $membershipPivot->role ?? null,
                'status' => $membershipPivot->membership_status ?? null,
                'joined_on' => isset($membershipPivot->joined_on) ? (string) $membershipPivot->joined_on : null,
                'membership_ends_on' => isset($membershipPivot->membership_ends_on) ? (string) $membershipPivot->membership_ends_on : null,
                'club_membership_type_id' => $membershipPivot->club_membership_type_id ?? null,
                'club_department_id' => $membershipPivot->club_department_id ?? null,
                'pause_requested' => (bool) ($membershipPivot->pause_requested_at ?? false),
                'change_requested' => (bool) ($request->user()
                    ? $this->membershipRequests()
                        ->where('user_id', $request->user()->id)
                        ->where('type', 'membership_change')
                        ->whereIn('status', ['pending', 'information_requested', 'waitlisted'])
                        ->exists()
                    : false),
                'termination_requested' => (bool) ($request->user()
                    ? $this->membershipRequests()
                        ->where('user_id', $request->user()->id)
                        ->where('type', 'termination')
                        ->whereIn('status', ['pending', 'information_requested', 'waitlisted'])
                        ->exists()
                    : false),
                'paused_from' => isset($membershipPivot->paused_from) ? (string) $membershipPivot->paused_from : null,
                'paused_until' => isset($membershipPivot->paused_until) ? (string) $membershipPivot->paused_until : null,
            ] : null,
            'users_count' => $this->whenCounted('users'),
            'teams_count' => $this->whenCounted('teams'),
            'posts_count' => $this->whenCounted('posts'),
            'teams' => TeamResource::collection($this->whenLoaded('teams')),
            'subscription_capabilities' => $planFeatureService->capabilities($this->resource),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
