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
        $pendingMembershipRequests = ClubMembershipRequest::query()
            ->where('club_id', $club->id)
            ->where('user_id', $viewer->id)
            ->where('status', 'pending')
            ->whereIn('type', ['membership', 'pause', 'termination'])
            ->get(['id', 'type', 'requested_pause_from', 'requested_pause_until', 'requested_termination_on'])
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
            'clubProfile' => $this->clubProfile($club, $isMember, $canManage),
            'clubRoles' => ClubRoles::ALL,
            'posts' => $this->visiblePosts($club, $viewer, $isMember),
            'viewer' => [
                'is_member' => $isMember,
                'can_manage' => $canManage,
                'has_pending_membership_request' => ! $isMember && $pendingMembershipRequests->has('membership'),
                'has_pending_pause_request' => $pendingMembershipRequests->has('pause'),
                'has_pending_termination_request' => $pendingMembershipRequests->has('termination'),
                'requested_termination_on' => $pendingMembershipRequests->get('termination')?->requested_termination_on?->toDateString(),
                'application_prefill' => ClubMembershipApplication::prefillFor($viewer),
                'social' => $club->owner
                    ? $this->social->state($club->owner, $viewer)
                    : null,
            ],
        ];
    }

    private function clubProfile(Club $club, bool $isMember, bool $canManage): array
    {
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
            'country' => $club->country,
            'street' => $club->street,
            'house_number' => $club->house_number,
            'postal_code' => $club->postal_code,
            'city' => $club->city,
            'state' => $club->state,
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
            'members' => ($isMember || $canManage) ? $club->users : collect(),
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
