<?php

namespace App\Services;

use App\Models\AdCampaign;
use App\Models\AdCreative;
use App\Models\AdEvent;
use App\Models\Sponsor;
use App\Models\User;
use App\Models\WebsiteRequest;
use App\Support\ClubRoles;
use App\Support\Roles;
use App\Support\UploadStorage;
use Illuminate\Support\Facades\DB;

class SponsorWorkspaceService
{
    public function __construct(private RevenueTrustService $revenueTrust) {}

    public function canOpen(User $user): bool
    {
        return $user->hasAnyRole(array_merge(Roles::FULL_ACCESS, Roles::SPONSOR, ['sponsor_manager']))
            || $user->can('sponsor.workspace.view')
            || $user->can('system.manage');
    }

    public function payload(User $user): array
    {
        $managedClubIds = $this->managedClubIds($user);
        $global = $user->hasAnyRole(array_merge(Roles::FULL_ACCESS, ['sponsor_manager']))
            || $user->can('system.manage');

        $sponsorQuery = Sponsor::query();
        if (! $global) {
            $sponsorQuery->where(function ($query) use ($user, $managedClubIds) {
                $query->where('owner_user_id', $user->id);
                if ($managedClubIds->isNotEmpty()) {
                    $query->orWhereIn('club_id', $managedClubIds);
                }
            });
        }

        $sponsorIds = (clone $sponsorQuery)->select('id');
        $partnersCount = (clone $sponsorQuery)->count();
        $activePartners = (clone $sponsorQuery)
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhereDate('starts_at', '<=', today()))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhereDate('ends_at', '>=', today()))
            ->count();
        $sponsors = (clone $sponsorQuery)
            ->with('club:id,name')
            ->latest('id')
            ->limit(50)
            ->get();

        $campaignQuery = AdCampaign::query();
        if (! $global) {
            $campaignQuery->where(function ($query) use ($user, $managedClubIds, $sponsorIds) {
                $query->where('user_id', $user->id);
                $query->orWhereIn('sponsor_id', $sponsorIds);
                if ($managedClubIds->isNotEmpty()) {
                    $query->orWhereIn('club_id', $managedClubIds);
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
                ->where('occurred_at', '>=', now()->subDays(28))
                ->whereIn('event_type', ['impression', 'click', 'lead', 'sale'])
                ->groupBy('ad_campaign_id', 'event_type')
                ->get();
        $metricsByCampaign = $eventRows->groupBy('ad_campaign_id');
        $dailyEvents = $campaignIds->isEmpty()
            ? collect()
            : AdEvent::query()
                ->selectRaw('DATE(occurred_at) as event_date, event_type, COUNT(*) as total, SUM(cost_cents) as cost_cents, SUM(value_cents) as value_cents')
                ->whereIn('ad_campaign_id', $campaignIds)
                ->where('occurred_at', '>=', now()->subDays(28))
                ->whereIn('event_type', ['impression', 'click', 'lead', 'sale'])
                ->groupBy('event_date', 'event_type')
                ->orderBy('event_date')
                ->get();
        $ownProfile = Sponsor::query()->where('owner_user_id', $user->id)->latest('id')->first();
        $agencyQuery = WebsiteRequest::query();
        if (! $global) {
            $agencyQuery->where(function ($query) use ($user, $managedClubIds) {
                $query->where('user_id', $user->id);
                if ($managedClubIds->isNotEmpty()) {
                    $query->orWhereIn('club_id', $managedClubIds);
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
        $deals = $sponsors
            ->take(12)
            ->map(function (Sponsor $sponsor) use ($campaignsBySponsor, $assetsByCampaign, $metricsByCampaign): array {
                $dealCampaigns = $campaignsBySponsor->get($sponsor->id, collect());
                $dealEvents = $dealCampaigns
                    ->flatMap(fn (AdCampaign $campaign) => $metricsByCampaign->get($campaign->id, collect()));
                $dealOutcome = $this->outcomeMetrics($dealEvents);
                $assetCount = $dealCampaigns
                    ->sum(fn (AdCampaign $campaign) => $assetsByCampaign->get($campaign->id, collect())->count());

                return [
                    'key' => 'deal:'.$sponsor->id,
                    'id' => $sponsor->id,
                    'partner_name' => $sponsor->name,
                    'scope' => $sponsor->scope,
                    'club' => $sponsor->club?->only(['id', 'name']),
                    'status' => $this->partnershipStatus($sponsor),
                    'starts_at' => $sponsor->starts_at?->toDateString(),
                    'ends_at' => $sponsor->ends_at?->toDateString(),
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

        return [
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
            'partners' => $sponsors->map(fn (Sponsor $sponsor) => [
                'id' => $sponsor->id,
                'name' => $sponsor->name,
                'scope' => $sponsor->scope,
                'club' => $sponsor->club?->only(['id', 'name']),
                'website' => $sponsor->website,
                'logo_url' => UploadStorage::url($sponsor->logo_light ?: $sponsor->logo),
                'amount' => $sponsor->amount,
                'starts_at' => $sponsor->starts_at?->toDateString(),
                'ends_at' => $sponsor->ends_at?->toDateString(),
                'status' => $this->partnershipStatus($sponsor),
                'owned_by_user' => (int) $sponsor->owner_user_id === $user->id,
                'verification_status' => $sponsor->verification_status,
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
            'capabilities' => [
                'manage_profiles' => $global
                    || $user->can('sponsor.profile.edit')
                    || $managedClubIds->isNotEmpty(),
                'edit_own_profile' => $user->hasRole('sponsor') || $ownProfile !== null,
                'manage_campaigns' => true,
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

    public function saveOwnProfile(User $user, array $data): Sponsor
    {
        $profile = Sponsor::query()->firstOrNew(['owner_user_id' => $user->id]);
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
            'verification_requested_at' => now(),
            'verified_by' => null,
            'verified_at' => null,
            'club_id' => $profile->exists ? $profile->club_id : null,
            'scope' => $profile->exists ? ($profile->scope ?: 'platform') : 'platform',
            'owner_user_id' => $user->id,
            'email' => $data['email'] ?? $profile->email ?? $user->email,
            'logo' => $fallbackLogo,
            'logo_light' => $data['logo_light'] ?? $fallbackLogo,
            'logo_dark' => $data['logo_dark'] ?? $data['logo_light'] ?? $fallbackLogo,
        ]);
        $profile->save();

        return $profile;
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

    private function managedClubIds(User $user)
    {
        return ClubRoles::whereAny($user->clubs(), ClubRoles::ELEVATED)
            ->pluck('clubs.id');
    }

    private function partnershipStatus(Sponsor $sponsor): string
    {
        if ($sponsor->starts_at?->isFuture()) {
            return 'upcoming';
        }
        if ($sponsor->ends_at?->isPast()) {
            return 'ended';
        }

        return 'active';
    }
}
