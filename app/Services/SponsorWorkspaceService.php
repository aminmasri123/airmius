<?php

namespace App\Services;

use App\Models\AdCampaign;
use App\Models\Sponsor;
use App\Models\User;
use App\Support\ClubRoles;
use App\Support\Roles;
use App\Support\UploadStorage;

class SponsorWorkspaceService
{
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

        $sponsorQuery = Sponsor::query()->with('club:id,name');
        if (! $global) {
            $sponsorQuery->where(function ($query) use ($user, $managedClubIds) {
                $query->where('owner_user_id', $user->id);
                if ($managedClubIds->isNotEmpty()) {
                    $query->orWhereIn('club_id', $managedClubIds);
                }
            });
        }
        $sponsors = $sponsorQuery->latest('id')->get();
        $sponsorIds = $sponsors->pluck('id');

        $campaignQuery = AdCampaign::query();
        if (! $global) {
            $campaignQuery->where(function ($query) use ($user, $managedClubIds, $sponsorIds) {
                $query->where('user_id', $user->id);
                if ($sponsorIds->isNotEmpty()) {
                    $query->orWhereIn('sponsor_id', $sponsorIds);
                }
                if ($managedClubIds->isNotEmpty()) {
                    $query->orWhereIn('club_id', $managedClubIds);
                }
            });
        }
        $campaigns = $campaignQuery->latest('id')->get();
        $ownProfile = $sponsors->firstWhere('owner_user_id', $user->id);

        $impressions = (int) $campaigns->sum('impressions');
        $clicks = (int) $campaigns->sum('clicks');
        $activePartners = $sponsors->filter(fn (Sponsor $sponsor) => $this->partnershipStatus($sponsor) === 'active')->count();

        return [
            'summary' => [
                'partners' => $sponsors->count(),
                'active_partners' => $activePartners,
                'campaigns' => $campaigns->count(),
                'active_campaigns' => $campaigns->where('status', 'active')->count(),
                'impressions' => $impressions,
                'clicks' => $clicks,
                'ctr' => $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0,
                'budget_cents' => (int) $campaigns->sum('budget_cents'),
                'spent_cents' => (int) $campaigns->sum('spent_cents'),
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
            ])->values(),
            'own_profile' => $ownProfile ? [
                'id' => $ownProfile->id,
                'name' => $ownProfile->name,
                'contact_name' => $ownProfile->contact_name,
                'email' => $ownProfile->email,
                'website' => $ownProfile->website,
                'logo' => UploadStorage::url($ownProfile->logo),
                'logo_light' => UploadStorage::url($ownProfile->logo_light),
                'logo_dark' => UploadStorage::url($ownProfile->logo_dark),
                'logo_url' => UploadStorage::url($ownProfile->logo_light ?: $ownProfile->logo),
            ] : null,
            'campaigns' => $campaigns->take(12)->map(fn (AdCampaign $campaign) => [
                'id' => $campaign->id,
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
                'starts_at' => $campaign->starts_at?->toIso8601String(),
                'ends_at' => $campaign->ends_at?->toIso8601String(),
            ])->values(),
            'capabilities' => [
                'manage_profiles' => $global
                    || $user->can('sponsor.profile.edit')
                    || $managedClubIds->isNotEmpty(),
                'edit_own_profile' => $user->hasRole('sponsor') || $ownProfile !== null,
                'manage_campaigns' => true,
                'global_management' => $global,
            ],
        ];
    }

    public function saveOwnProfile(User $user, array $data): Sponsor
    {
        $profile = Sponsor::query()->firstOrNew(['owner_user_id' => $user->id]);
        $fallbackLogo = $data['logo'] ?? $data['logo_light'] ?? $data['logo_dark'] ?? $profile->logo;

        $profile->fill([
            ...$data,
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
