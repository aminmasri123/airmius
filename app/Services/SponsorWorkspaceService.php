<?php

namespace App\Services;

use App\Models\AdCampaign;
use App\Models\AdCreative;
use App\Models\AdEvent;
use App\Models\Club;
use App\Models\Sponsor;
use App\Models\SponsorMembership;
use App\Models\SponsorPartnership;
use App\Models\User;
use App\Models\UserRoleApplication;
use App\Models\WebsiteRequest;
use App\Support\ClubPermissions;
use App\Support\Roles;
use App\Support\UploadStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SponsorWorkspaceService
{
    public function __construct(
        private RevenueTrustService $revenueTrust,
        private SponsorOrganizationService $organizations,
        private MediaOptimizer $mediaOptimizer,
        private SponsorProfileReviewNotifier $profileReviewNotifier,
        private SponsorProfileReviewService $profileReviews,
    ) {}

    public function canOpen(User $user): bool
    {
        return $user->hasAnyRole(array_merge(Roles::FULL_ACCESS, Roles::SPONSOR, ['sponsor_manager']))
            || $user->can('sponsor.workspace.view')
            || $user->can('system.manage')
            || $this->organizations->canAccess($user)
            || $user->roleApplications()
                ->where('type', UserRoleApplication::TYPE_SPONSOR)
                ->where('status', UserRoleApplication::STATUS_APPROVED)
                ->exists()
            || ClubPermissions::allowsAnyClub($user, [
                ClubPermissions::SPONSORS_EDIT,
                ClubPermissions::SPONSORS_DELETE,
                ClubPermissions::ADVERTISING_EDIT,
                ClubPermissions::WEBSITE_REQUEST_CREATE,
            ]);
    }

    public function payload(User $user, ?int $requestedSponsorId = null): array
    {
        $this->organizations->ensureApprovedOrganization($user);

        $clubs = $user->clubs()->get();
        $sponsorClubIds = $this->clubIdsFor($clubs, $user, [
            ClubPermissions::SPONSORS_EDIT,
            ClubPermissions::SPONSORS_DELETE,
        ]);
        $campaignClubIds = $this->clubIdsFor($clubs, $user, [ClubPermissions::ADVERTISING_EDIT]);
        $agencyClubIds = $this->clubIdsFor($clubs, $user, [ClubPermissions::WEBSITE_REQUEST_CREATE]);
        $global = $user->hasAnyRole(array_merge(Roles::FULL_ACCESS, ['sponsor_manager']))
            || $user->can('system.manage');
        $availableOrganizations = $this->organizations->organizations($user);
        $currentOrganization = $this->organizations->resolve($user, $requestedSponsorId);
        $organizationRole = $currentOrganization
            ? $this->organizations->role($user, $currentOrganization)
            : null;
        $organizationScoped = $currentOrganization !== null && ! $global;

        $sponsorQuery = Sponsor::query();
        if ($organizationScoped) {
            $sponsorQuery->whereKey($currentOrganization->id);
        } elseif (! $global) {
            $sponsorQuery->where(function ($query) use ($user, $sponsorClubIds) {
                $query->where('owner_user_id', $user->id);
                if ($sponsorClubIds->isNotEmpty()) {
                    $query->orWhereIn('club_id', $sponsorClubIds);
                }
            });
        }

        $sponsorIds = (clone $sponsorQuery)->select('id');
        $partnershipQuery = SponsorPartnership::query();
        if ($currentOrganization) {
            $partnershipQuery->where('sponsor_id', $currentOrganization->id);
        } elseif (! $global) {
            $partnershipQuery->whereIn('sponsor_id', $sponsorIds);
        }
        $partnersCount = (clone $partnershipQuery)->count();
        $activePartners = (clone $partnershipQuery)
            ->where('status', SponsorPartnership::STATUS_ACTIVE)
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhereDate('starts_at', '<=', today()))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhereDate('ends_at', '>=', today()))
            ->count();
        $partnerships = (clone $partnershipQuery)
            ->with([
                'club:id,name,logo,website_url',
                'sponsor:id,name,logo,logo_light,website',
                'sponsor.deliverables.responsible:id,name',
            ])
            ->latest('id')
            ->limit(50)
            ->get();
        $partnershipClubs = $currentOrganization
            ? Club::query()
                ->visibleTo($user)
                ->select(['id', 'name', 'logo'])
                ->orderBy('name')
                ->limit(150)
                ->get()
            : collect();

        $campaignQuery = AdCampaign::query();
        if ($organizationScoped) {
            $campaignQuery->where('sponsor_id', $currentOrganization->id);
            if ($availableOrganizations->count() === 1 && (int) $currentOrganization->owner_user_id === (int) $user->id) {
                $campaignQuery->orWhere(function ($query) use ($user) {
                    $query->whereNull('sponsor_id')->where('user_id', $user->id);
                });
            }
        } elseif (! $global) {
            $campaignQuery->where(function ($query) use ($user, $campaignClubIds, $sponsorIds) {
                $query->where('user_id', $user->id);
                $query->orWhereIn('sponsor_id', $sponsorIds);
                if ($campaignClubIds->isNotEmpty()) {
                    $query->orWhereIn('club_id', $campaignClubIds);
                }
            });
        }
        $campaignSummary = (clone $campaignQuery)
            ->selectRaw('COUNT(*) as campaigns_count')
            ->selectRaw("SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_campaigns_count")
            ->selectRaw('COALESCE(SUM(impressions), 0) as impressions_sum')
            ->selectRaw('COALESCE(SUM(clicks), 0) as clicks_sum')
            ->selectRaw('COALESCE(SUM(budget_cents), 0) as budget_sum')
            ->selectRaw('COALESCE(SUM(spent_cents), 0) as spent_sum')
            ->first();
        $campaigns = (clone $campaignQuery)->latest('id')->limit(12)->get();
        $campaignIds = $campaigns->pluck('id');
        $outcomeTo = now();
        $outcomeFrom = $outcomeTo->copy()->subDays(28);
        $creativeRows = $campaignIds->isEmpty()
            ? collect()
            : AdCreative::query()
                ->select([
                    'id',
                    'ad_campaign_id',
                    'ad_name',
                    'name',
                    'headline',
                    'creative_format',
                    'creative_image_path',
                    'creative_image_url',
                    'is_active',
                    'impressions',
                    'clicks',
                    'created_at',
                ])
                ->whereIn('ad_campaign_id', $campaignIds)
                ->latest('id')
                ->limit(24)
                ->get();
        $eventRows = $campaignIds->isEmpty()
            ? collect()
            : AdEvent::query()
                ->select('ad_campaign_id', 'event_type', DB::raw('COUNT(*) as total'), DB::raw('SUM(cost_cents) as cost_cents'), DB::raw('SUM(value_cents) as value_cents'))
                ->whereIn('ad_campaign_id', $campaignIds)
                ->whereBetween('occurred_at', [$outcomeFrom, $outcomeTo])
                ->whereIn('event_type', ['impression', 'click', 'lead', 'sale'])
                ->groupBy('ad_campaign_id', 'event_type')
                ->get();
        $metricsByCampaign = $eventRows->groupBy('ad_campaign_id');
        $dailyEvents = $campaignIds->isEmpty()
            ? collect()
            : AdEvent::query()
                ->selectRaw('DATE(occurred_at) as event_date, event_type, COUNT(*) as total, SUM(cost_cents) as cost_cents, SUM(value_cents) as value_cents')
                ->whereIn('ad_campaign_id', $campaignIds)
                ->whereBetween('occurred_at', [$outcomeFrom, $outcomeTo])
                ->whereIn('event_type', ['impression', 'click', 'lead', 'sale'])
                ->groupBy('event_date', 'event_type')
                ->orderBy('event_date')
                ->get();
        $ownProfile = $currentOrganization
            ?? Sponsor::query()->where('owner_user_id', $user->id)->latest('id')->first();
        $agencyQuery = WebsiteRequest::query();
        if (! $global) {
            $agencyQuery->where(function ($query) use ($user, $agencyClubIds) {
                $query->where('user_id', $user->id);
                if ($agencyClubIds->isNotEmpty()) {
                    $query->orWhereIn('club_id', $agencyClubIds);
                }
            });
        }
        $agencySummary = (clone $agencyQuery)
            ->selectRaw('COUNT(*) as briefs_count')
            ->selectRaw("SUM(CASE WHEN status NOT IN ('done', 'cancelled') THEN 1 ELSE 0 END) as open_briefs_count")
            ->first();
        $agencyBriefs = (clone $agencyQuery)
            ->with('club:id,name')
            ->latest('id')
            ->limit(8)
            ->get();
        $impressions = (int) ($campaignSummary?->impressions_sum ?? 0);
        $clicks = (int) ($campaignSummary?->clicks_sum ?? 0);
        $outcomes = $this->outcomeMetrics($eventRows);
        $assets = $this->creativeAssets($campaigns, $creativeRows);
        $assetsByCampaign = $assets->groupBy('campaign_id');
        $campaignsBySponsor = $campaigns->whereNotNull('sponsor_id')->groupBy('sponsor_id');
        $deals = $partnerships
            ->take(12)
            ->map(function (SponsorPartnership $partnership) use ($campaignsBySponsor, $assetsByCampaign, $metricsByCampaign): array {
                $dealCampaigns = $campaignsBySponsor->get($partnership->sponsor_id, collect());
                $dealEvents = $dealCampaigns
                    ->flatMap(fn (AdCampaign $campaign) => $metricsByCampaign->get($campaign->id, collect()));
                $dealOutcome = $this->outcomeMetrics($dealEvents);
                $assetCount = $dealCampaigns
                    ->sum(fn (AdCampaign $campaign) => $assetsByCampaign->get($campaign->id, collect())->count());

                return [
                    'key' => 'deal:'.$partnership->id,
                    'id' => $partnership->id,
                    'partner_name' => $partnership->club?->name,
                    'club' => $partnership->club?->only(['id', 'name']),
                    'status' => $this->partnershipStatus($partnership),
                    'starts_at' => $partnership->starts_at?->toDateString(),
                    'ends_at' => $partnership->ends_at?->toDateString(),
                    'package_code' => $partnership->package_code,
                    'rights_package' => $partnership->rights_package ?? [],
                    'contract_version' => $partnership->contract_version,
                    'renewal_deadline' => $partnership->renewal_deadline?->toDateString(),
                    'contract_approval_status' => $partnership->status,
                    'campaigns_count' => $dealCampaigns->count(),
                    'active_campaigns_count' => $dealCampaigns->where('status', 'active')->count(),
                    'assets_count' => $assetCount,
                    'outcomes_28d' => $dealOutcome['leads'] + $dealOutcome['sales'],
                    'value_cents_28d' => $dealOutcome['value_cents'],
                    'workflow_state' => match (true) {
                        $dealCampaigns->isEmpty() => 'needs_campaign',
                        $assetCount === 0 => 'needs_asset',
                        ($dealOutcome['leads'] + $dealOutcome['sales']) > 0 => 'measuring',
                        default => 'ready',
                    },
                    'action_url' => route('auth.commerce.index', ['tab' => 'ads']),
                ];
            })
            ->values();
        $briefsCount = (int) ($agencySummary?->briefs_count ?? 0);
        $openBriefsCount = (int) ($agencySummary?->open_briefs_count ?? 0);
        $outcomesCount = $outcomes['leads'] + $outcomes['sales'];
        $team = $currentOrganization
            ? $currentOrganization->memberships()
                ->with('user:id,name,email')
                ->whereIn('status', [SponsorMembership::STATUS_ACTIVE, SponsorMembership::STATUS_PENDING])
                ->orderByRaw("CASE role WHEN 'owner' THEN 0 WHEN 'administrator' THEN 1 ELSE 2 END")
                ->orderBy('email')
                ->get()
            : collect();
        $activities = $currentOrganization
            ? $currentOrganization->activityLogs()->with('actor:id,name')->latest('id')->limit(12)->get()
            : collect();
        $pendingInvitations = $this->organizations->pendingInvitations($user);

        return [
            'organizations' => $availableOrganizations->map(fn (Sponsor $organization) => [
                'id' => $organization->id,
                'name' => $organization->name,
                'logo_url' => UploadStorage::url($organization->logo_light ?: $organization->logo),
                'role' => $this->organizations->role($user, $organization),
                'is_current' => $currentOrganization?->id === $organization->id,
            ])->values(),
            'current_organization' => $currentOrganization ? [
                'id' => $currentOrganization->id,
                'name' => $currentOrganization->name,
                'role' => $organizationRole,
            ] : null,
            'pending_invitations' => $pendingInvitations->map(fn (SponsorMembership $membership) => [
                'id' => $membership->id,
                'sponsor' => $membership->sponsor?->only(['id', 'name']),
                'role' => $membership->role,
                'invited_at' => $membership->created_at?->toIso8601String(),
            ])->values(),
            'partnership_clubs' => $partnershipClubs->map(fn (Club $club) => [
                'id' => $club->id,
                'name' => $club->name,
                'logo_url' => UploadStorage::url($club->logo),
            ])->values(),
            'summary' => [
                'partners' => $partnersCount,
                'active_partners' => $activePartners,
                'campaigns' => (int) ($campaignSummary?->campaigns_count ?? 0),
                'active_campaigns' => (int) ($campaignSummary?->active_campaigns_count ?? 0),
                'impressions' => $impressions,
                'clicks' => $clicks,
                'ctr' => $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0,
                'budget_cents' => (int) ($campaignSummary?->budget_sum ?? 0),
                'spent_cents' => (int) ($campaignSummary?->spent_sum ?? 0),
                'open_budget_cents' => max(0, (int) ($campaignSummary?->budget_sum ?? 0) - (int) ($campaignSummary?->spent_sum ?? 0)),
                'pending_approvals' => $campaigns->where('status', 'pending_review')->count()
                    + ($ownProfile?->verification_status === 'pending_review' ? 1 : 0),
                'new_club_requests' => $openBriefsCount,
                'leads_28d' => $outcomes['leads'],
                'sales_28d' => $outcomes['sales'],
                'conversion_rate_28d' => $outcomes['conversion_rate'],
                'conversion_value_cents_28d' => $outcomes['value_cents'],
                'cpa_cents_28d' => $outcomes['cpa_cents'],
                'roas_28d' => $outcomes['roas'],
                'assets' => $assets->count(),
                'agency_briefs' => $briefsCount,
                'open_agency_briefs' => $openBriefsCount,
            ],
            'partners' => $partnerships->map(fn (SponsorPartnership $partnership) => [
                'id' => $partnership->id,
                'name' => $partnership->club?->name,
                'sponsor' => $partnership->sponsor?->only(['id', 'name']),
                'club' => $partnership->club?->only(['id', 'name']),
                'website' => $partnership->club?->website_url,
                'logo_url' => UploadStorage::url($partnership->club?->logo),
                'budget_cents' => $partnership->budget_cents,
                'package_code' => $partnership->package_code,
                'rights_package' => $partnership->rights_package ?? [],
                'individual_offer_terms' => $partnership->individual_offer_terms,
                'contract_version' => $partnership->contract_version,
                'renewal_notice_days' => $partnership->renewal_notice_days,
                'renewal_deadline' => $partnership->renewal_deadline?->toDateString(),
                'response_note' => $partnership->response_note,
                'starts_at' => $partnership->starts_at?->toDateString(),
                'ends_at' => $partnership->ends_at?->toDateString(),
                'status' => $this->partnershipStatus($partnership),
                'can_withdraw' => $this->organizations->can($user, $partnership->sponsor, 'partnerships')
                    && $partnership->status === SponsorPartnership::STATUS_PENDING,
                'can_edit_deliverables' => $partnership->status === SponsorPartnership::STATUS_ACTIVE
                    && ($this->organizations->can($user, $partnership->sponsor, 'partnerships')
                        || $global
                        || ClubPermissions::allows($partnership->club, $user, ClubPermissions::SPONSORS_EDIT)),
                'can_delete_deliverables' => $partnership->status === SponsorPartnership::STATUS_ACTIVE
                    && ($this->organizations->can($user, $partnership->sponsor, 'partnerships')
                        || $global
                        || ClubPermissions::allows($partnership->club, $user, ClubPermissions::SPONSORS_DELETE)),
                'deliverables' => $partnership->sponsor->deliverables
                    ->where('club_id', $partnership->club_id)
                    ->map(fn ($deliverable) => [
                        'id' => $deliverable->id,
                        'title' => $deliverable->title,
                        'location' => $deliverable->location,
                        'starts_at' => $deliverable->starts_at?->toDateString(),
                        'ends_at' => $deliverable->ends_at?->toDateString(),
                        'due_at' => $deliverable->due_at?->toDateString(),
                        'status' => $deliverable->status,
                        'responsible' => $deliverable->responsible?->only(['id', 'name']),
                        'fulfillment_evidence' => $deliverable->fulfillment_evidence,
                        'fulfilled_at' => $deliverable->fulfilled_at?->toIso8601String(),
                        'is_overdue' => $deliverable->isOverdue(),
                    ])->values(),
            ])->values(),
            'own_profile' => $ownProfile ? [
                'id' => $ownProfile->id,
                'name' => $ownProfile->name,
                'legal_name' => $ownProfile->legal_name,
                'country_code' => $ownProfile->country_code,
                'registration_number' => $ownProfile->registration_number,
                'vat_id' => $ownProfile->vat_id,
                'contact_name' => $ownProfile->contact_name,
                'email' => $ownProfile->email,
                'website' => $ownProfile->website,
                'logo' => UploadStorage::url($ownProfile->logo),
                'logo_light' => UploadStorage::url($ownProfile->logo_light),
                'logo_dark' => UploadStorage::url($ownProfile->logo_dark),
                'logo_url' => UploadStorage::url($ownProfile->logo_light ?: $ownProfile->logo),
                'verification_status' => $ownProfile->verification_status,
                'verification_note' => $ownProfile->verification_note,
                'readiness' => $this->revenueTrust->sponsorReadiness($ownProfile),
                'legal_accuracy_accepted' => (bool) ($ownProfile->accepted_rules['legal_accuracy'] ?? false),
                'data_privacy_accepted' => (bool) ($ownProfile->accepted_rules['data_privacy'] ?? false),
            ] : null,
            'campaigns' => $campaigns->map(function (AdCampaign $campaign) use ($metricsByCampaign) {
                $outcome = $this->outcomeMetrics($metricsByCampaign->get($campaign->id, collect()));

                return [
                    'id' => $campaign->id,
                    'sponsor_id' => $campaign->sponsor_id,
                    'name' => $campaign->name,
                    'headline' => $campaign->headline,
                    'status' => $campaign->status,
                    'objective' => $campaign->objective,
                    'placement' => $campaign->placement,
                    'impressions' => (int) $campaign->impressions,
                    'clicks' => (int) $campaign->clicks,
                    'ctr' => $campaign->impressions > 0
                        ? round(($campaign->clicks / $campaign->impressions) * 100, 2)
                        : 0,
                    'budget_cents' => (int) $campaign->budget_cents,
                    'spent_cents' => (int) $campaign->spent_cents,
                    'leads_28d' => $outcome['leads'],
                    'sales_28d' => $outcome['sales'],
                    'conversion_rate_28d' => $outcome['conversion_rate'],
                    'value_cents_28d' => $outcome['value_cents'],
                    'cpa_cents_28d' => $outcome['cpa_cents'],
                    'roas_28d' => $outcome['roas'],
                    'starts_at' => $campaign->starts_at?->toIso8601String(),
                    'ends_at' => $campaign->ends_at?->toIso8601String(),
                ];
            })->values(),
            'growth' => [
                'contract' => 'growth-workspace.v1',
                'stages' => [
                    ['key' => 'brief', 'count' => $briefsCount, 'open_count' => $openBriefsCount, 'action_url' => route('auth.commerce.index', ['tab' => 'create'])],
                    ['key' => 'deal', 'count' => $partnersCount, 'open_count' => $activePartners, 'action_url' => route('guest.sponsors')],
                    ['key' => 'asset', 'count' => $assets->count(), 'open_count' => $assets->where('is_active', true)->count(), 'action_url' => route('auth.commerce.index', ['tab' => 'ads'])],
                    ['key' => 'campaign', 'count' => (int) ($campaignSummary?->campaigns_count ?? 0), 'open_count' => (int) ($campaignSummary?->active_campaigns_count ?? 0), 'action_url' => route('auth.commerce.index', ['tab' => 'ads'])],
                    ['key' => 'outcome', 'count' => $outcomesCount, 'open_count' => $outcomes['sales'], 'action_url' => route('auth.commerce.index', ['tab' => 'ads'])],
                ],
                'attention' => [
                    'deals_without_campaign' => $deals->where('workflow_state', 'needs_campaign')->count(),
                    'campaigns_without_assets' => $campaigns->filter(fn (AdCampaign $campaign) => $assetsByCampaign->get($campaign->id, collect())->isEmpty())->count(),
                    'open_agency_briefs' => $openBriefsCount,
                ],
            ],
            'deals' => $deals,
            'assets' => $assets->values(),
            'agency_briefs' => $agencyBriefs->map(fn (WebsiteRequest $brief): array => [
                'key' => 'brief:'.$brief->id,
                'id' => $brief->id,
                'status' => $brief->status,
                'package' => $brief->package,
                'club' => $brief->club?->only(['id', 'name']),
                'has_domain' => filled($brief->domain),
                'submitted_at' => $brief->created_at?->toIso8601String(),
                'status_changed_at' => $brief->status_changed_at?->toIso8601String(),
                'action_url' => route('auth.commerce.index', ['tab' => 'create']),
            ])->values(),
            'outcome_timeline' => $this->outcomeTimeline($dailyEvents),
            'team' => $team->map(fn (SponsorMembership $membership) => [
                'id' => $membership->id,
                'name' => $membership->user?->name,
                'email' => $membership->email,
                'role' => $membership->role,
                'status' => $membership->status,
                'accepted_at' => $membership->accepted_at?->toIso8601String(),
                'is_current_user' => (int) $membership->user_id === (int) $user->id,
            ])->values(),
            'activities' => $activities->map(fn ($activity) => [
                'id' => $activity->id,
                'action' => $activity->action,
                'actor_name' => $activity->actor?->name,
                'metadata' => $activity->metadata ?? [],
                'created_at' => $activity->created_at?->toIso8601String(),
            ])->values(),
            'capabilities' => [
                'manage_profiles' => $global
                    || $user->can('sponsor.profile.edit')
                    || $sponsorClubIds->isNotEmpty(),
                'edit_own_profile' => $currentOrganization
                    ? $this->organizations->can($user, $currentOrganization, 'profile')
                    : ($user->hasRole('sponsor') || $ownProfile !== null),
                'manage_campaigns' => $currentOrganization
                    ? $this->organizations->can($user, $currentOrganization, 'campaigns')
                    : true,
                'manage_finance' => $currentOrganization
                    ? $this->organizations->can($user, $currentOrganization, 'finance')
                    : $global,
                'manage_team' => $currentOrganization
                    ? $this->organizations->can($user, $currentOrganization, 'team')
                    : false,
                'manage_partnerships' => $currentOrganization
                    ? $this->organizations->can($user, $currentOrganization, 'partnerships')
                    : false,
                'view_agency_briefs' => true,
                'global_management' => $global,
            ],
            'limits' => [
                'partners' => 50,
                'campaigns' => 12,
                'assets' => 24,
                'agency_briefs' => 8,
            ],
        ];
    }

    public function saveOwnProfile(User $user, array $data, ?int $sponsorId = null): Sponsor
    {
        $profile = $this->organizations->resolve($user, $sponsorId)
            ?? Sponsor::query()->firstOrNew(['owner_user_id' => $user->id]);
        $creatingProfile = ! $profile->exists;
        $wasPendingReview = $profile->exists && $profile->verification_status === 'pending_review';
        $reviewRequestedAt = $wasPendingReview && $profile->verification_requested_at
            ? $profile->verification_requested_at
            : now();
        if ($profile->exists) {
            abort_unless($this->organizations->can($user, $profile, 'profile'), 403);
        }
        $before = $this->profileReviews->snapshot($profile);
        $fallbackLogo = $data['logo'] ?? $data['logo_light'] ?? $data['logo_dark'] ?? $profile->logo;
        $acceptedRules = is_array($profile->accepted_rules) ? $profile->accepted_rules : [];
        foreach (['legal_accuracy', 'data_privacy'] as $rule) {
            $input = 'rule_'.$rule;
            if (array_key_exists($input, $data)) {
                $acceptedRules[$rule] = (bool) $data[$input];
            }
            unset($data[$input]);
        }

        $profile->fill([
            ...$data,
            'country_code' => filled($data['country_code'] ?? null) ? strtoupper((string) $data['country_code']) : null,
            'accepted_rules' => $acceptedRules,
            'verification_version' => RevenueTrustService::CONTRACT_VERSION,
            'verification_status' => 'pending_review',
            'verification_note' => null,
            'verification_requested_at' => $reviewRequestedAt,
            'verified_by' => null,
            'verified_at' => null,
            'club_id' => $profile->exists ? $profile->club_id : null,
            'scope' => $profile->exists ? ($profile->scope ?: 'platform') : 'platform',
            'owner_user_id' => $profile->exists ? $profile->owner_user_id : $user->id,
            'email' => $data['email'] ?? $profile->email ?? $user->email,
            'logo' => $fallbackLogo,
            'logo_light' => $data['logo_light'] ?? $fallbackLogo,
            'logo_dark' => $data['logo_dark'] ?? $data['logo_light'] ?? $fallbackLogo,
        ]);
        $profile->save();
        if ($creatingProfile || (int) $profile->owner_user_id === (int) $user->id) {
            SponsorMembership::query()->updateOrCreate(
                ['sponsor_id' => $profile->id, 'email' => mb_strtolower($user->email)],
                [
                    'user_id' => $user->id,
                    'role' => SponsorMembership::ROLE_OWNER,
                    'status' => SponsorMembership::STATUS_ACTIVE,
                    'accepted_at' => now(),
                ],
            );
        }
        $revision = $this->organizations->audit($profile, $user, 'profile.updated', [
            'changes' => $this->profileReviews->changes($before, $profile),
        ]);
        $this->profileReviewNotifier->reviewRequested($profile, $user, $revision->id);

        return $profile;
    }

    public function uploadOwnLogo(
        User $user,
        UploadedFile $file,
        string $variant,
        ?int $sponsorId = null,
    ): Sponsor {
        $profile = $this->organizations->resolve($user, $sponsorId);
        abort_unless($profile, 404, 'Sponsor-Arbeitsbereich nicht gefunden.');
        abort_unless($this->organizations->can($user, $profile, 'profile'), 403);

        $field = match ($variant) {
            'light' => 'logo_light',
            'dark' => 'logo_dark',
            default => 'logo',
        };
        $before = $this->profileReviews->snapshot($profile);
        $wasPendingReview = $profile->verification_status === 'pending_review';
        $reviewRequestedAt = $wasPendingReview && $profile->verification_requested_at
            ? $profile->verification_requested_at
            : now();
        $stored = $this->mediaOptimizer->store($file, 'sponsors/'.$profile->id.'/profile');
        $profile->update([
            $field => $stored['path'],
            'verification_status' => 'pending_review',
            'verification_note' => null,
            'verification_requested_at' => $reviewRequestedAt,
            'verified_by' => null,
            'verified_at' => null,
        ]);
        $revision = $this->organizations->audit($profile, $user, 'profile.logo_updated', [
            'variant' => $variant,
            'changes' => $this->profileReviews->changes($before, $profile),
        ]);
        $this->profileReviewNotifier->reviewRequested($profile, $user, $revision->id);

        return $profile->fresh();
    }

    private function outcomeMetrics($events): array
    {
        $byType = collect($events)->keyBy('event_type');
        $impressions = (int) ($byType->get('impression')?->total ?? 0);
        $clicks = (int) ($byType->get('click')?->total ?? 0);
        $leads = (int) ($byType->get('lead')?->total ?? 0);
        $sales = (int) ($byType->get('sale')?->total ?? 0);
        $conversions = $leads + $sales;
        $costCents = (int) collect($events)->sum('cost_cents');
        $valueCents = (int) collect($events)->sum('value_cents');

        return [
            'impressions' => $impressions,
            'clicks' => $clicks,
            'leads' => $leads,
            'sales' => $sales,
            'conversion_rate' => $clicks > 0 ? round(($conversions / $clicks) * 100, 2) : 0,
            'cost_cents' => $costCents,
            'value_cents' => $valueCents,
            'cpa_cents' => $conversions > 0 ? (int) round($costCents / $conversions) : null,
            'roas' => $costCents > 0 ? round($valueCents / $costCents, 2) : null,
        ];
    }

    private function outcomeTimeline($events): array
    {
        return collect($events)
            ->groupBy('event_date')
            ->map(function ($rows, string $date) {
                return ['date' => $date, ...$this->outcomeMetrics($rows)];
            })
            ->values()
            ->all();
    }

    private function creativeAssets($campaigns, $creativeRows)
    {
        $campaignAssets = collect($campaigns)
            ->filter(fn (AdCampaign $campaign) => filled($campaign->creative_image_path) || filled($campaign->creative_image_url))
            ->map(fn (AdCampaign $campaign): array => [
                'key' => 'campaign-asset:'.$campaign->id,
                'id' => null,
                'campaign_id' => $campaign->id,
                'campaign_name' => $campaign->name,
                'name' => $campaign->headline ?: $campaign->name,
                'format' => $campaign->creative_format ?: 'feed_square',
                'preview_url' => UploadStorage::url($campaign->creative_image_path) ?: $campaign->creative_image_url,
                'is_active' => $campaign->status === 'active',
                'impressions' => (int) $campaign->impressions,
                'clicks' => (int) $campaign->clicks,
                'action_url' => route('auth.commerce.index', ['tab' => 'ads']),
            ]);
        $campaignNames = collect($campaigns)->pluck('name', 'id');
        $creativeAssets = collect($creativeRows)->map(fn (AdCreative $creative): array => [
            'key' => 'creative:'.$creative->id,
            'id' => $creative->id,
            'campaign_id' => $creative->ad_campaign_id,
            'campaign_name' => $campaignNames->get($creative->ad_campaign_id),
            'name' => $creative->ad_name ?: ($creative->name ?: ($creative->headline ?: __('sponsor_workspace.asset_fallback'))),
            'format' => $creative->creative_format,
            'preview_url' => UploadStorage::url($creative->creative_image_path) ?: $creative->creative_image_url,
            'is_active' => (bool) $creative->is_active,
            'impressions' => (int) $creative->impressions,
            'clicks' => (int) $creative->clicks,
            'action_url' => route('auth.commerce.index', ['tab' => 'ads']),
        ]);

        return $campaignAssets->concat($creativeAssets)->take(24)->values();
    }

    private function clubIdsFor(Collection $clubs, User $user, array $permissions): Collection
    {
        return $clubs
            ->filter(fn ($club) => collect($permissions)->contains(
                fn (string $permission) => ClubPermissions::allows($club, $user, $permission)
            ))
            ->pluck('id')
            ->values();
    }

    private function partnershipStatus(SponsorPartnership $partnership): string
    {
        if ($partnership->status !== SponsorPartnership::STATUS_ACTIVE) {
            return $partnership->status;
        }
        if ($partnership->starts_at?->isFuture()) {
            return 'upcoming';
        }
        if ($partnership->ends_at?->isPast()) {
            return 'ended';
        }

        return 'active';
    }
}
