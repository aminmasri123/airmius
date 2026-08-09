<?php

namespace App\Services;

use App\Models\OrganizationJobInterest;
use App\Models\UserSport;
use Illuminate\Support\Collection;

class RecruitingMatchExplanationService
{
    public const SHAREABLE_FIELDS = ['sports', 'experience'];

    private const EXPERIENCE_RANKS = [
        'beginner' => 1,
        'intermediate' => 2,
        'advanced' => 3,
        'expert' => 4,
        'elite' => 5,
    ];

    /** @return array<string, mixed>|null */
    public function forInterest(OrganizationJobInterest $interest): ?array
    {
        $fields = collect($interest->shared_profile_fields ?? [])
            ->intersect(self::SHAREABLE_FIELDS)
            ->values();

        if (! $interest->user_id || ! $interest->profile_consent_at || $fields->isEmpty()) {
            return null;
        }

        $interest->loadMissing([
            'job.sport:id,name,slug',
            'user.sportProfiles.sport:id,name,slug',
        ]);
        if (! $interest->user) {
            return null;
        }

        $profiles = $interest->user->sportProfiles->values();
        $candidateProfiles = $this->candidateProfiles($profiles, $fields);
        $criteria = $this->criteria($interest);
        $dimensions = collect();

        if ($interest->job?->sport_id && $fields->contains('sports')) {
            $matched = $profiles->contains(fn (UserSport $profile) => (int) $profile->sport_id === (int) $interest->job->sport_id);
            $dimensions->push([
                'key' => 'sport',
                'matched' => $matched,
                'weight' => 70,
                'candidate_value' => $profiles->pluck('sport.name')->filter()->unique()->take(5)->values()->all(),
                'target_value' => $interest->job->sport?->name,
            ]);
        }

        if ($interest->job?->minimum_experience_level && $fields->contains('experience')) {
            $relevantProfiles = $interest->job->sport_id
                ? $profiles->where('sport_id', $interest->job->sport_id)
                : $profiles;
            $candidateRank = $relevantProfiles
                ->map(fn (UserSport $profile) => self::EXPERIENCE_RANKS[$profile->experience_level] ?? 0)
                ->max() ?? 0;
            $targetRank = self::EXPERIENCE_RANKS[$interest->job->minimum_experience_level] ?? 0;
            $dimensions->push([
                'key' => 'experience',
                'matched' => $candidateRank >= $targetRank && $targetRank > 0,
                'weight' => 30,
                'candidate_value' => array_search($candidateRank, self::EXPERIENCE_RANKS, true) ?: null,
                'target_value' => $interest->job->minimum_experience_level,
            ]);
        }

        $availableWeight = (int) $dimensions->sum('weight');
        $matchedWeight = (int) $dimensions->where('matched', true)->sum('weight');

        return [
            'version' => '2026-08-09.recruiting-fit.v1',
            'score' => $availableWeight > 0 ? (int) round(($matchedWeight / $availableWeight) * 100) : null,
            'decision_policy' => 'assistive_only',
            'shared_fields' => $fields->all(),
            'profile' => $candidateProfiles,
            'criteria' => $criteria,
            'dimensions' => $dimensions->values()->all(),
            'consented_at' => $interest->profile_consent_at->toJSON(),
        ];
    }

    /** @return array<string, mixed> */
    private function candidateProfiles(Collection $profiles, Collection $fields): array
    {
        return [
            'sports' => $fields->contains('sports')
                ? $profiles->take(5)->map(fn (UserSport $profile) => [
                    'sport' => $profile->sport?->only(['id', 'name', 'slug']),
                    'status' => $profile->status,
                ])->values()->all()
                : [],
            'experience' => $fields->contains('experience')
                ? $profiles->take(5)->map(fn (UserSport $profile) => [
                    'sport_id' => $profile->sport_id,
                    'experience_level' => $profile->experience_level,
                ])->values()->all()
                : [],
        ];
    }

    /** @return array<string, mixed> */
    private function criteria(OrganizationJobInterest $interest): array
    {
        return [
            'sport' => $interest->job?->sport?->only(['id', 'name', 'slug']),
            'minimum_experience_level' => $interest->job?->minimum_experience_level,
        ];
    }
}
