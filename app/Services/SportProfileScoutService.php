<?php

namespace App\Services;

use App\Models\Club;
use App\Models\ProfileRecommendation;
use App\Models\User;
use App\Models\UserSport;
use App\Models\UserSportSkill;
use App\Support\ClubPermissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SportProfileScoutService
{
    public function sportCv(User $profileUser, ?User $viewer = null): array
    {
        $profileUser->loadMissing([
            'sportProfiles.sport:id,name,slug,category',
            'sportSkills.sport:id,name,slug,category',
            'sportSkills.skill:id,sport_id,key,name',
            'sportSkills.endorsements.endorser:id,name',
        ]);

        $viewerIsOwner = $viewer && (int) $viewer->id === (int) $profileUser->id;
        $roleLimited = $viewer ? $this->canViewRoleLimitedSportProfile($profileUser, $viewer) : false;
        $profileIsPublic = ($profileUser->profile_visibility ?? 'public') === 'public';
        $visible = $profileIsPublic || $viewerIsOwner || $roleLimited;
        $privacyMatrix = $this->privacyMatrix($profileUser, $viewer, $visible);

        if (! $visible) {
            return [
                'user_id' => $profileUser->id,
                'profile' => $this->safeProfilePayload($profileUser, false),
                'visibility' => 'private',
                'headline' => 'Privates Sportprofil',
                'headline_key' => 'profile.sport_cv.private_headline',
                'primary_sports' => [],
                'best_metrics' => [],
                'verified_skills' => [],
                'top_skills' => [],
                'proof' => [],
                'profile_quality' => [
                    'version' => '2026-06-03',
                    'score' => 0,
                    'level' => 'private',
                    'missing' => [],
                ],
                'career_timeline' => [],
                'recommendation_summary' => ['approved_count' => 0],
                'scout_card' => ['ready' => false],
                'next_actions' => [],
                'privacy_matrix' => $privacyMatrix,
            ];
        }

        $sportProfiles = $profileUser->sportProfiles
            ->filter(fn (UserSport $profile) => $this->viewerCanSeeVisibility($profile->visibility ?? 'private', $profileUser, $viewer))
            ->values();
        $bestMetrics = $this->bestMetrics($sportProfiles);
        $performanceSections = $this->performanceSections($sportProfiles, $profileUser, $viewer);
        $skills = $this->verifiedSkills($profileUser->sportSkills->where('is_visible', true)->values());
        $recommendationsCount = ProfileRecommendation::query()
            ->where('profile_user_id', $profileUser->id)
            ->where('status', 'approved')
            ->count();
        $score = $this->profileScore($sportProfiles, $bestMetrics, $skills, $recommendationsCount);
        $profileQuality = $this->profileQuality($sportProfiles, $bestMetrics, $skills, $recommendationsCount);

        return [
            'user_id' => $profileUser->id,
            'profile' => $this->safeProfilePayload($profileUser, true),
            'visibility' => $profileIsPublic ? 'public' : 'role_limited',
            'headline' => $this->headline($sportProfiles),
            'summary' => [
                'sports_count' => $sportProfiles->count(),
                'public_best_metrics' => count($bestMetrics),
                'verified_skills' => collect($skills)->where('verification.status', 'verified')->count(),
                'endorsed_skills' => collect($skills)->where('endorsements_count', '>', 0)->count(),
                'approved_recommendations' => $recommendationsCount,
            ],
            'primary_sports' => $sportProfiles
                ->take(5)
                ->map(fn (UserSport $profile) => [
                    'id' => $profile->id,
                    'status' => $profile->status,
                    'experience_level' => $profile->experience_level,
                    'sport' => $this->sportPayload($profile->sport),
                ])
                ->all(),
            'performance_sections' => $performanceSections,
            'best_metrics' => $bestMetrics,
            'verified_skills' => $skills,
            'top_skills' => $skills,
            'proof' => $this->proof($recommendationsCount, $skills),
            'profile_quality' => $profileQuality,
            'career_timeline' => $this->careerTimeline($sportProfiles, $bestMetrics),
            'recommendation_summary' => [
                'approved_count' => $recommendationsCount,
                'has_trainer_recommendation' => ProfileRecommendation::query()
                    ->where('profile_user_id', $profileUser->id)
                    ->where('status', 'approved')
                    ->where('relationship', 'trainer')
                    ->exists(),
            ],
            'scout_card' => [
                'version' => '2026-06-03.linkedin_sport_profile.v1',
                'ready' => $score >= 80,
                'score' => $score,
                'level' => $score >= 85 ? 'scout_ready' : ($score >= 60 ? 'strong' : 'building'),
                'contact_policy' => [
                    'profile_visibility' => $profileUser->profile_visibility ?? 'public',
                    'direct_message_privacy' => $profileUser->direct_message_privacy ?? 'everyone',
                ],
                'signals' => [
                    'public_best_metrics' => count($bestMetrics),
                    'public_performance_entries' => collect($performanceSections)->flatten(1)->where('visibility', 'public')->count(),
                    'verified_skills' => collect($skills)->where('verification.status', 'verified')->count(),
                    'recommendations' => $recommendationsCount,
                    'trust_score' => $profileUser->trust_score ?? 100,
                ],
            ],
            'next_actions' => $this->nextActions($profileQuality),
            'privacy_matrix' => $privacyMatrix,
            'privacy' => [
                'metric_visibility' => 'field_level',
                'performance_sections' => 'entry_level_private_by_default',
                'recommendations' => 'approved_only',
                'skills' => 'visible_only',
            ],
        ];
    }

    /**
     * Return only fields that are safe to use in a public profile deep link.
     * Contact and account data intentionally never leave the profile API.
     */
    private function safeProfilePayload(User $profileUser, bool $visible): array
    {
        return [
            'id' => $profileUser->id,
            'name' => $visible ? $profileUser->name : 'Privates Profil',
            'bio' => $visible ? $profileUser->bio : null,
            'profile_photo_url' => $visible ? $profileUser->profile_photo_url : null,
            'profile_visibility' => $profileUser->profile_visibility ?? 'public',
        ];
    }

    public function scoutSearch(User $viewer, array $filters): array
    {
        $limit = max(1, min(30, (int) ($filters['limit'] ?? 15)));
        $query = trim((string) ($filters['q'] ?? ''));
        $sport = trim((string) ($filters['sport'] ?? ''));
        $skill = trim((string) ($filters['skill'] ?? ''));
        $minScore = (int) ($filters['min_score'] ?? 0);

        $users = User::query()
            ->where('profile_visibility', 'public')
            ->whereKeyNot($viewer->id)
            ->when($query !== '', function (Builder $builder) use ($query) {
                $like = '%'.$query.'%';

                $builder->where(function (Builder $nested) use ($like) {
                    $nested
                        ->where('name', 'like', $like)
                        ->orWhere('bio', 'like', $like)
                        ->orWhereHas('sportProfiles.sport', fn (Builder $sports) => $sports->where('name', 'like', $like)->orWhere('slug', 'like', $like))
                        ->orWhereHas('sportSkills.skill', fn (Builder $skills) => $skills->where('name', 'like', $like)->orWhere('key', 'like', $like));
                });
            })
            ->when($sport !== '', fn (Builder $builder) => $builder->whereHas('sportProfiles.sport', fn (Builder $sports) => $sports
                ->where('slug', $sport)
                ->orWhere('name', 'like', '%'.$sport.'%')))
            ->when($skill !== '', fn (Builder $builder) => $builder->whereHas('sportSkills.skill', fn (Builder $skills) => $skills
                ->where('key', $skill)
                ->orWhere('name', 'like', '%'.$skill.'%')))
            ->with([
                'sportProfiles.sport:id,name,slug,category',
                'sportSkills.sport:id,name,slug,category',
                'sportSkills.skill:id,sport_id,key,name',
                'sportSkills.endorsements.endorser:id,name',
            ])
            ->withCount([
                'recommendationsReceived as approved_recommendations_count' => fn (Builder $recommendations) => $recommendations->where('status', 'approved'),
            ])
            ->limit($limit * 3)
            ->get()
            ->map(function (User $user) use ($viewer) {
                $cv = $this->sportCv($user, $viewer);

                return [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'bio' => $user->bio,
                        'profile_photo_url' => $user->profile_photo_url,
                    ],
                    'sport_cv' => $cv,
                    'match' => [
                        'score' => $cv['scout_card']['score'] ?? 0,
                        'ready' => $cv['scout_card']['ready'] ?? false,
                        'reason_keys' => $this->matchReasons($cv),
                    ],
                ];
            })
            ->filter(fn (array $result) => (int) $result['match']['score'] >= $minScore)
            ->sortByDesc('match.score')
            ->take($limit)
            ->values()
            ->all();

        return [
            'version' => '2026-06-03.linkedin_sport_profile.v1',
            'filters' => [
                'q' => $query,
                'sport' => $sport,
                'skill' => $skill,
                'min_score' => $minScore,
                'limit' => $limit,
            ],
            'results' => $users,
            'facets' => $this->facets($users),
            'privacy_note_key' => 'profile.scout_search.privacy_note',
        ];
    }

    private function profileQuality(Collection $sportProfiles, array $bestMetrics, array $skills, int $recommendationsCount): array
    {
        $missing = [];

        if ($sportProfiles->isEmpty()) {
            $missing[] = 'sports';
        }

        if (count($bestMetrics) === 0) {
            $missing[] = 'best_metrics';
        }

        if (count($skills) < 2) {
            $missing[] = 'skills';
        }

        if ($recommendationsCount === 0) {
            $missing[] = 'recommendations';
        }

        $score = min(100,
            ($sportProfiles->isNotEmpty() ? 25 : 0)
            + (count($bestMetrics) > 0 ? 20 : 0)
            + (count($skills) > 0 ? 15 : 0)
            + ($recommendationsCount > 0 ? 10 : 0)
            + 10
        );

        return [
            'version' => '2026-06-03',
            'score' => $score,
            'level' => $score >= 80 ? 'strong' : ($score >= 55 ? 'building' : 'starter'),
            'missing' => $missing,
        ];
    }

    private function proof(int $recommendationsCount, array $skills): array
    {
        return [
            [
                'key' => 'recommendations',
                'label_key' => 'profile.sport_cv.proof.recommendations',
                'value' => $recommendationsCount,
            ],
            [
                'key' => 'verified_skills',
                'label_key' => 'profile.sport_cv.proof.verified_skills',
                'value' => collect($skills)->where('verification.status', 'verified')->count(),
            ],
        ];
    }

    private function careerTimeline(Collection $sportProfiles, array $bestMetrics): array
    {
        $profiles = $sportProfiles->map(fn (UserSport $profile) => [
            'type' => 'sport_profile',
            'title' => $profile->sport?->name ?: 'Sportprofil',
            'subtitle' => $profile->experience_level,
            'sport' => $this->sportPayload($profile->sport),
        ]);

        $metrics = collect($bestMetrics)->map(fn (array $metric) => [
            'type' => 'best_metric',
            'title' => $metric['key'],
            'value' => $metric['value'],
            'sport' => $metric['sport'] ?? null,
        ]);

        return $profiles->concat($metrics)->values()->take(12)->all();
    }

    private function nextActions(array $profileQuality): array
    {
        return collect($profileQuality['missing'] ?? [])
            ->map(fn (string $key) => [
                'key' => $key,
                'label_key' => 'profile.sport_cv.next_actions.'.$key,
            ])
            ->values()
            ->all();
    }

    private function privacyMatrix(User $profileUser, ?User $viewer, bool $visible): array
    {
        $metricVisibility = $profileUser->sportProfiles
            ->flatMap(function (UserSport $profile) {
                $metrics = collect($profile->performance_metrics ?? [])->keys();
                $visibility = $profile->performance_visibility ?? [];

                return $metrics->map(fn (string $key) => $visibility[$key] ?? 'private');
            })
            ->values();

        $sections = [
            ['key' => 'overview', 'visibility' => $visible ? 'public' : 'private', 'visible_to_viewer' => $visible],
            ['key' => 'contact', 'visibility' => $visible ? ($profileUser->direct_message_privacy ?? 'everyone') : 'private', 'visible_to_viewer' => $visible],
            ['key' => 'sports', 'visibility' => $visible ? 'public' : 'private', 'visible_to_viewer' => $visible],
            ['key' => 'skills', 'visibility' => $visible ? 'visible_only' : 'private', 'visible_to_viewer' => $visible],
            ['key' => 'recommendations', 'visibility' => $visible ? 'approved_only' : 'private', 'visible_to_viewer' => $visible],
            ['key' => 'best_metrics', 'visibility' => $visible ? 'field_level' : 'private', 'visible_to_viewer' => $visible && $metricVisibility->contains('public')],
            ['key' => 'performance_sections', 'visibility' => $visible ? 'entry_level' : 'private', 'visible_to_viewer' => $visible],
        ];

        return [
            'version' => '2026-06-03',
            'profile_visible_to_viewer' => $visible,
            'viewer_is_owner' => $viewer ? (int) $viewer->id === (int) $profileUser->id : false,
            'viewer_has_role_limited_access' => $viewer ? $this->canViewRoleLimitedSportProfile($profileUser, $viewer) : false,
            'summary' => [
                'public_metrics' => $metricVisibility->filter(fn ($visibility) => $visibility === 'public')->count(),
                'private_metrics' => $metricVisibility->reject(fn ($visibility) => $visibility === 'public')->count(),
            ],
            'sections' => $sections,
        ];
    }
    private function verifiedSkills(Collection $skills): array
    {
        return $skills
            ->map(function (UserSportSkill $userSkill) {
                $endorsements = $userSkill->endorsements;
                $trusted = $endorsements->whereIn('relationship', ['trainer', 'coach', 'club', 'teammate']);
                $verified = $trusted->whereIn('relationship', ['trainer', 'coach', 'club'])->isNotEmpty() || $endorsements->count() >= 3;

                return [
                    'id' => $userSkill->id,
                    'name' => $userSkill->skill?->name ?: 'Skill',
                    'key' => $userSkill->skill?->key,
                    'self_level' => $userSkill->self_level,
                    'endorsements_count' => $endorsements->count(),
                    'trusted_endorsements_count' => $trusted->count(),
                    'sport' => $this->sportPayload($userSkill->sport),
                    'verification' => [
                        'status' => $verified ? 'verified' : ($endorsements->isNotEmpty() ? 'endorsed' : 'self_reported'),
                        'source' => $verified ? 'trusted_endorsement' : ($endorsements->isNotEmpty() ? 'community_endorsement' : 'self_assessment'),
                        'badge_key' => $verified ? 'profile.skills.verified' : ($endorsements->isNotEmpty() ? 'profile.skills.endorsed' : 'profile.skills.self_reported'),
                    ],
                ];
            })
            ->sortByDesc(fn (array $skill) => ($skill['verification']['status'] === 'verified' ? 100 : 0) + ($skill['endorsements_count'] * 10) + (int) $skill['self_level'])
            ->take(10)
            ->values()
            ->all();
    }

    private function bestMetrics(Collection $sportProfiles): array
    {
        return $sportProfiles
            ->flatMap(function (UserSport $profile) {
                return collect($profile->performance_metrics ?? [])
                    ->filter(fn ($value, string $key) => (($profile->performance_visibility ?? [])[$key] ?? 'private') === 'public')
                    ->filter(fn ($value, string $key) => $this->isScoutMetric($key))
                    ->map(fn ($value, string $key) => [
                        'key' => $key,
                        'value' => $value,
                        'visibility' => 'public',
                        'sport' => $this->sportPayload($profile->sport),
                    ]);
            })
            ->values()
            ->take(10)
            ->all();
    }

    private function performanceSections(Collection $sportProfiles, User $profileUser, ?User $viewer): array
    {
        return [
            'participation' => $this->visibleSectionEntries($sportProfiles, 'sport_participation', $profileUser, $viewer),
            'development_goals' => $this->visibleSectionEntries($sportProfiles, 'development_goals', $profileUser, $viewer),
            'results' => $this->visibleSectionEntries($sportProfiles, 'sport_results', $profileUser, $viewer),
            'personal_bests' => $this->visibleSectionEntries($sportProfiles, 'personal_bests', $profileUser, $viewer),
        ];
    }

    private function visibleSectionEntries(Collection $sportProfiles, string $attribute, User $profileUser, ?User $viewer): array
    {
        return $sportProfiles
            ->flatMap(function (UserSport $profile) use ($attribute, $profileUser, $viewer) {
                return collect($profile->{$attribute} ?? [])
                    ->filter(fn ($entry) => is_array($entry))
                    ->filter(fn (array $entry) => $this->viewerCanSeeVisibility($entry['visibility'] ?? 'private', $profileUser, $viewer))
                    ->map(fn (array $entry) => [
                        ...$entry,
                        'visibility' => $entry['visibility'] ?? 'private',
                        'sport' => $this->sportPayload($profile->sport),
                    ]);
            })
            ->values()
            ->take(50)
            ->all();
    }

    private function viewerCanSeeVisibility(string $visibility, User $profileUser, ?User $viewer): bool
    {
        if (! $viewer) {
            return $visibility === 'public';
        }

        if ((int) $viewer->id === (int) $profileUser->id) {
            return true;
        }

        return match ($visibility) {
            'public' => true,
            'trainer' => $this->canViewRoleLimitedSportProfile($profileUser, $viewer),
            default => false,
        };
    }

    private function canViewRoleLimitedSportProfile(User $profileUser, User $viewer): bool
    {
        if ((int) $viewer->id === (int) $profileUser->id) {
            return true;
        }

        $sharedTeam = $profileUser->teams()
            ->whereHas('users', function (Builder $users) use ($viewer): void {
                $users->where('users.id', $viewer->id)
                    ->whereIn('team_user.role', ['Coach', 'coach', 'Captain', 'captain']);
            })
            ->exists();

        if ($sharedTeam) {
            return true;
        }

        $clubs = Club::query()
            ->whereHas('users', fn (Builder $members) => $members->where('users.id', $profileUser->id))
            ->whereHas('users', fn (Builder $members) => $members->where('users.id', $viewer->id))
            ->get();

        return $clubs->contains(fn (Club $club) => ClubPermissions::allows($club, $viewer, ClubPermissions::TRAINING_SESSIONS_VIEW)
            || ClubPermissions::allows($club, $viewer, ClubPermissions::MEMBERS_VIEW));
    }

    private function profileScore(Collection $sportProfiles, array $bestMetrics, array $skills, int $recommendationsCount): int
    {
        $verifiedSkills = collect($skills)->where('verification.status', 'verified')->count();

        return min(100,
            ($sportProfiles->isNotEmpty() ? 20 : 0)
            + (count($bestMetrics) > 0 ? 20 : 0)
            + (count($skills) >= 2 ? 15 : 0)
            + ($verifiedSkills > 0 ? 20 : 0)
            + ($recommendationsCount > 0 ? 15 : 0)
            + 10
        );
    }

    private function matchReasons(array $cv): array
    {
        $reasons = [];

        if (($cv['scout_card']['ready'] ?? false) === true) {
            $reasons[] = 'scout_ready';
        }
        if (($cv['summary']['verified_skills'] ?? 0) > 0) {
            $reasons[] = 'verified_skills';
        }
        if (($cv['summary']['approved_recommendations'] ?? 0) > 0) {
            $reasons[] = 'recommendations';
        }
        if (($cv['summary']['public_best_metrics'] ?? 0) > 0) {
            $reasons[] = 'public_metrics';
        }

        return $reasons;
    }

    private function facets(array $results): array
    {
        return [
            'sports' => collect($results)
                ->flatMap(fn (array $result) => $result['sport_cv']['primary_sports'] ?? [])
                ->pluck('sport.slug')
                ->filter()
                ->countBy()
                ->sortDesc()
                ->all(),
            'skills' => collect($results)
                ->flatMap(fn (array $result) => $result['sport_cv']['verified_skills'] ?? [])
                ->pluck('key')
                ->filter()
                ->countBy()
                ->sortDesc()
                ->all(),
        ];
    }

    private function headline(Collection $sportProfiles): string
    {
        $sports = $sportProfiles->pluck('sport.name')->filter()->take(2)->values();

        return $sports->isEmpty() ? 'Sport-CV' : $sports->join(' · ');
    }

    private function sportPayload($sport): ?array
    {
        if (! $sport) {
            return null;
        }

        return [
            'id' => $sport->id,
            'name' => $sport->name,
            'slug' => $sport->slug,
            'category' => $sport->category,
        ];
    }

    private function isScoutMetric(string $key): bool
    {
        return str_contains($key, 'best')
            || str_contains($key, '1rm')
            || str_contains($key, 'ftp')
            || str_contains($key, 'vma')
            || str_contains($key, 'sprint')
            || str_contains($key, 'cooper')
            || str_contains($key, 'vertical_jump');
    }
}
