<?php

namespace App\Services;

use App\Models\Club;
use App\Models\ClubContributionRule;
use App\Models\ClubMembershipRequest;
use App\Models\ClubMembershipType;
use App\Models\Post;
use App\Models\User;
use App\Models\UserBadge;
use App\Support\ClubMembershipApplication;
use App\Support\ClubPermissions;
use App\Support\ClubProfilePermissions;
use App\Support\ClubRoles;

class ClubProfilePayloadService
{
    public function __construct(
        private GamificationService $gamification,
        private UserSocialProfileService $social,
    ) {}

    public function forViewer(Club $club, User $viewer): array
    {
        $isMember = $club->users()->where('users.id', $viewer->id)->exists();
        $canManage = $viewer->can('update', $club);
        $profileCapabilities = ClubProfilePermissions::capabilities($club, $viewer);
        $canViewMetadata = ClubPermissions::allows($club, $viewer, ClubPermissions::METADATA_VIEW);
        $canEditMetadata = ClubPermissions::allows($club, $viewer, ClubPermissions::METADATA_EDIT);
        $canCreateTeamsGlobally = ClubPermissions::allows($club, $viewer, ClubPermissions::TEAMS_EDIT);
        $teamCreationDepartments = $club->departments()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->filter(fn ($department) => $canCreateTeamsGlobally
                || ClubPermissions::allowsInScope(
                    $club,
                    $viewer,
                    ClubPermissions::TEAMS_EDIT,
                    'department',
                    (int) $department->id,
                ))
            ->map->only(['id', 'name'])
            ->values();
        $pendingMembershipRequests = ClubMembershipRequest::query()
            ->where('club_id', $club->id)
            ->where('user_id', $viewer->id)
            ->whereIn('status', ['pending', 'information_requested', 'waitlisted'])
            ->whereIn('type', ['membership', 'membership_change', 'pause', 'termination'])
            ->get([
                'id',
                'type',
                'status',
                'information_request_message',
                'requested_pause_from',
                'requested_pause_until',
                'requested_termination_on',
            ])
            ->keyBy('type');

        $club->loadCount(['users', 'teams', 'posts']);
        $club->load([
            'owner:id,name,email,profile_photo_path',
            'admins:id,name,profile_photo_path',
            'users' => fn ($query) => $query
                ->select('users.id', 'name', 'profile_photo_path')
                ->orderBy('name'),
            'teams' => fn ($query) => $query
                ->withCount('users')
                ->orderBy('name')
                ->limit(12),
            'membershipTypes' => fn ($query) => $query
                ->where('is_active', true)
                ->where('is_public', true)
                ->orderBy('sort_order')
                ->orderBy('name'),
            'contributionRules' => fn ($query) => $query
                ->effectiveOn(now()->toDateString())
                ->where('is_active', true)
                ->orderByDesc('valid_from'),
        ]);

        return [
            'clubProfile' => $this->clubProfile($club, $isMember, $canManage, $profileCapabilities),
            'clubRoles' => ClubRoles::ALL,
            'posts' => $this->visiblePosts($club, $viewer, $isMember),
            'viewer' => [
                'can_delete_club' => $viewer->can('delete', $club),
                'is_member' => $isMember,
                'can_manage' => $canManage,
                'can_manage_roles' => ClubPermissions::allows($club, $viewer, ClubPermissions::MEMBERS_ROLES),
                'can_edit_sponsors' => ClubPermissions::allows($club, $viewer, ClubPermissions::SPONSORS_EDIT),
                'can_delete_sponsors' => ClubPermissions::allows($club, $viewer, ClubPermissions::SPONSORS_DELETE),
                'can_edit_club_profile' => $profileCapabilities['profile'],
                'can_edit_club_legal' => $profileCapabilities['legal'],
                'can_edit_club_contact' => $profileCapabilities['contact'],
                'can_edit_club_branding' => $profileCapabilities['branding'],
                'can_edit_teams' => $canCreateTeamsGlobally || $teamCreationDepartments->isNotEmpty(),
                'can_create_teams_globally' => $canCreateTeamsGlobally,
                'team_creation_departments' => $teamCreationDepartments,
                'can_manage_members' => ClubPermissions::allows($club, $viewer, ClubPermissions::MEMBERS_MANAGE),
                'can_view_finance' => ClubPermissions::allows($club, $viewer, ClubPermissions::FINANCE_VIEW),
                'can_view_metadata' => $canViewMetadata,
                'can_edit_metadata' => $canEditMetadata,
                'has_pending_membership_request' => ! $isMember && $pendingMembershipRequests->has('membership'),
                'membership_request' => $pendingMembershipRequests->get('membership'),
                'has_pending_pause_request' => $pendingMembershipRequests->has('pause'),
                'has_pending_membership_change_request' => $pendingMembershipRequests->has('membership_change'),
                'membership_type_id' => $isMember
                    ? $club->users()->whereKey($viewer->id)->first()?->pivot?->club_membership_type_id
                    : null,
                'club_department_id' => $isMember
                    ? $club->users()->whereKey($viewer->id)->first()?->pivot?->club_department_id
                    : null,
                'has_pending_termination_request' => $pendingMembershipRequests->has('termination'),
                'requested_termination_on' => $pendingMembershipRequests->get('termination')?->requested_termination_on?->toDateString(),
                'application_prefill' => ClubMembershipApplication::prefillFor($viewer),
                'social' => $club->owner
                    ? $this->social->state($club->owner, $viewer)
                    : null,
            ],
        ];
    }

    private function clubProfile(Club $club, bool $isMember, bool $canManage, array $profileCapabilities): array
    {
        $contactVisible = $profileCapabilities['contact'] || (bool) $club->contact_details_public;
        $contactPersons = collect($club->contact_persons ?? [])
            ->filter(fn (array $person) => $profileCapabilities['contact'] || (bool) ($person['is_public'] ?? false))
            ->values();

        return [
            'id' => $club->id,
            'owner_id' => $club->owner_id,
            'owner' => $club->owner,
            'name' => $club->name,
            'sport_type' => $club->sport_type,
            'is_official' => (bool) $club->is_official,
            'official_club_number' => $club->official_club_number,
            'verification_status' => $club->verification_status,
            'requested_official_club_number' => $club->requested_official_club_number,
            'verification_notes' => $club->verification_notes,
            'logo' => $club->logo,
            'cover_image' => $club->cover_image,
            'brand_primary_color' => $club->brand_primary_color,
            'brand_secondary_color' => $club->brand_secondary_color,
            'brand_accent_color' => $club->brand_accent_color,
            'country' => $club->country,
            'street' => $club->street,
            'house_number' => $club->house_number,
            'postal_code' => $club->postal_code,
            'city' => $club->city,
            'state' => $club->state,
            ...($contactVisible ? [
                'contact_email' => $club->contact_email,
                'contact_phone' => $club->contact_phone,
                'website_url' => $club->website_url,
                'contact_persons' => $contactPersons,
            ] : []),
            ...($profileCapabilities['contact'] ? [
                'contact_details_public' => (bool) $club->contact_details_public,
            ] : []),
            ...($profileCapabilities['branding'] ? [
                'letterhead_settings' => $club->letterhead_settings ?? [
                    'show_logo' => true,
                    'header' => null,
                    'address_line' => null,
                    'footer' => null,
                ],
                'document_templates' => $club->document_templates ?? [],
            ] : []),
            ...($profileCapabilities['legal'] ? [
                'registry_authority' => $club->registry_authority,
                'registry_number' => $club->registry_number,
                'federation_affiliations' => $club->federation_affiliations ?? [],
                'tax_authority' => $club->tax_authority,
                'tax_number' => $club->tax_number,
                'vat_id' => $club->vat_id,
                'tax_status' => $club->tax_status,
                'tax_exemption_valid_until' => $club->tax_exemption_valid_until?->format('Y-m-d'),
            ] : []),
            'users_count' => $club->users_count,
            'teams_count' => $club->teams_count,
            'posts_count' => $club->posts_count,
            'membership_requests_enabled' => $club->membership_requests_enabled,
            'member_pause_requests_enabled' => $club->member_pause_requests_enabled,
            'membership_application_fields' => ClubMembershipApplication::fieldsForClub($club->membership_application_fields),
            'membership_payment_methods' => ClubMembershipApplication::normalizePaymentMethods($club->membership_payment_methods),
            'membership_payment_method_options' => ClubMembershipApplication::paymentMethods(),
            'membership_application_document_types' => ClubMembershipApplication::documentTypes($club->membership_application_document_types),
            'membership_application_documents' => collect(ClubMembershipApplication::normalizeDocuments($club->membership_application_documents, $club->membership_application_document_types))
                ->where('is_visible', true)
                ->values(),
            'is_listed' => (bool) $club->is_listed,
            'teams_are_listed' => (bool) $club->teams_are_listed,
            'members_can_post_to_club' => (bool) $club->members_can_post_to_club,
            'members_can_post_to_teams' => (bool) $club->members_can_post_to_teams,
            'admins' => $club->admins,
            'members' => ($isMember || $canManage)
                ? $club->users->map(fn (User $member) => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'profile_photo_url' => $member->profile_photo_url,
                    'profile_photo_thumb' => $member->profile_photo_thumb,
                    'pivot' => [
                        'role' => $member->pivot?->role,
                        'roles' => ClubRoles::normalize(
                            $member->pivot?->role,
                            $member->pivot?->roles ?? [],
                        ),
                    ],
                ])->values()
                : collect(),
            'teams' => ($isMember || $canManage || $club->teams_are_listed) ? $club->teams : collect(),
            'membership_types' => $this->membershipTypes($club),
            'gamification' => $this->gamification->summaryFor($club, 'verein'),
            'badges' => UserBadge::query()
                ->where('awardable_type', Club::class)
                ->where('awardable_id', $club->id)
                ->with('badge:id,key,name,description,icon')
                ->latest('id')
                ->limit(12)
                ->get()
                ->pluck('badge')
                ->values(),
        ];
    }

    private function membershipTypes(Club $club)
    {
        return $club->membershipTypes->map(function (ClubMembershipType $type) use ($club) {
            $rule = $club->contributionRules
                ->first(fn (ClubContributionRule $rule) => $rule->club_membership_type_id === $type->id)
                ?: $club->contributionRules->first(fn (ClubContributionRule $rule) => $rule->club_membership_type_id === null);

            return [
                'id' => $type->id,
                'name' => $type->name,
                'description' => $type->description,
                'amount' => $rule?->amount,
                'billing_interval' => $rule?->billing_interval,
            ];
        })->values();
    }

    private function visiblePosts(Club $club, User $viewer, bool $isMember)
    {
        return Post::query()
            ->where('club_id', $club->id)
            ->where('moderation_status', '!=', 'removed')
            ->where(function ($query) use ($viewer, $isMember) {
                $query->where('visibility', 'public')
                    ->orWhere('user_id', $viewer->id)
                    ->when($isMember, fn ($query) => $query->orWhere('visibility', 'organization'));
            })
            ->with(['user:id,name,profile_photo_path', 'team:id,name,club_id'])
            ->withCount([
                'comments' => fn ($query) => $query->where('moderation_status', 'approved'),
                'likes',
            ])
            ->latest('id')
            ->limit(8)
            ->get();
    }
}
