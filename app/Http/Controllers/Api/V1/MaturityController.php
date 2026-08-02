<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PostResource;
use App\Http\Resources\Api\V1\SportRouteResource;
use App\Models\AccountWarning;
use App\Models\Club;
use App\Models\ContentReport;
use App\Models\Follow;
use App\Models\FriendInvitation;
use App\Models\GamificationXpEvent;
use App\Models\ModerationFlag;
use App\Models\Post;
use App\Models\SportRoute;
use App\Models\SportRouteTrack;
use App\Models\Story;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\TrainingLog;
use App\Models\TrainingPlan;
use App\Models\User;
use App\Services\AthleteMotivationService;
use App\Support\Api\V1\ApiPagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MaturityController extends Controller
{
    public function motivation(Request $request, AthleteMotivationService $motivation)
    {
        return response()->json([
            'data' => $motivation->forUser($request->user()),
        ]);
    }

    public function overview(Request $request)
    {
        $user = $request->user();

        $onboarding = $this->onboardingChecklist($user);
        $recentWindowStart = now()->subDays(7);
        $lastWeek = TrainingLog::query()
            ->where('user_id', $user->id)
            ->whereNotNull('performed_at')
            ->where('performed_at', '>=', $recentWindowStart)
            ->count();

        $friendCount = $user->friendships()->count();
        $communityReach = $user->followers()->count() + $friendCount;
        $routeCompletions = SportRoute::query()->where('user_id', $user->id)->where('status', 'completed')->count();
        $trackCompletions = $user->sportRouteTracks()->where('status', 'completed')->count();
        $approvedPosts = $user->posts()->where('moderation_status', 'approved')->count();

        $xpTotal = (int) GamificationXpEvent::query()->where('user_id', $user->id)->sum('amount');
        $warnings = (int) AccountWarning::query()->where('user_id', $user->id)->sum('points');

        $scores = [
            'onboarding' => $onboarding['completion_percent'],
            'social' => min(100, 20 + $communityReach * 6),
            'training' => min(100, 20 + ($lastWeek * 12)),
            'maps' => min(100, 20 + ($routeCompletions * 20) + min(20, $trackCompletions * 2)),
            'content' => min(100, 20 + ($approvedPosts * 10)),
            'safety' => max(20, 100 - min(80, $warnings * 5)),
            'xp' => min(100, 10 + $xpTotal / 10),
        ];

        $maturityScore = (int) round(array_sum($scores) / max(count($scores), 1));

        $challengeCount = SportRoute::query()
            ->visibleTo($user)
            ->whereHas('tracks', fn ($query) => $query->where('status', 'completed'))
            ->count();

        return response()->json([
            'data' => [
                'maturity_score' => $maturityScore,
                'overview' => [
                    'user_id' => $user->id,
                    'weekly_trainings' => $lastWeek,
                    'friend_connections' => $friendCount,
                    'community_reach' => $communityReach,
                    'completed_routes' => $routeCompletions,
                    'completed_tracks' => $trackCompletions,
                    'approved_posts' => $approvedPosts,
                    'challenge_candidates' => $challengeCount,
                    'xp_total' => $xpTotal,
                ],
                'scores' => $scores,
                'next_actions' => $onboarding['items'],
            ],
        ]);
    }

    public function feedDiscovery(Request $request)
    {
        $posts = $this->postDiscoveryQuery($request)->paginate(ApiPagination::perPage($request));

        return PostResource::collection($posts);
    }

    public function feedTrending(Request $request)
    {
        $request->merge([
            'sort' => 'trending',
            'trend_days' => $request->input('trend_days', 14),
        ]);

        $posts = $this->postDiscoveryQuery($request, true)->paginate(ApiPagination::perPage($request));

        return PostResource::collection($posts);
    }

    public function search(Request $request)
    {
        $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:120'],
            'type' => ['nullable', Rule::in(['all', 'user', 'club', 'team', 'route', 'training_plan', 'post'])],
            'page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $term = trim((string) $request->input('q'));
        $like = '%'.$term.'%';
        $user = $request->user();
        $searchType = $request->input('type', 'all');
        $page = max(1, (int) $request->integer('page', 1));
        $limit = max(1, (int) $request->integer('limit', 12));

        if ($limit > 50) {
            $limit = 50;
        }

        $clubIds = $user->clubs()->pluck('clubs.id')->all();
        $teamIds = $user->teams()->pluck('teams.id')->all();
        $bucketLimit = $searchType === 'all'
            ? max(6, (int) floor($limit / 6))
            : $limit;

        $searchMap = [
            'user' => [
                'query' => fn () => User::query()
                    ->where('id', '!=', $user->id)
                    ->where(function ($query) use ($like) {
                        $query->where('name', 'like', $like)
                            ->orWhere('email', 'like', $like);
                    })
                    ->orderByDesc('updated_at')
                    ->limit($bucketLimit)
                    ->get(['id', 'name', 'email', 'profile_photo_path', 'updated_at'])
                    ->map(fn (User $match) => [
                        'type' => 'user',
                        'id' => $match->id,
                        'title' => $match->name,
                        'subtitle' => $match->email,
                        'avatar_url' => $match->profile_photo_thumb ?: $match->profile_photo_url,
                        'updated_at' => $match->updated_at?->toIso8601String(),
                    ]),
                'count' => fn () => User::query()
                    ->where('id', '!=', $user->id)
                    ->where(function ($query) use ($like) {
                        $query->where('name', 'like', $like)
                            ->orWhere('email', 'like', $like);
                    })
                    ->count(),
            ],
            'club' => [
                'query' => fn () => Club::query()
                    ->visibleTo($user)
                    ->where(function ($query) use ($user) {
                        $query->verified()
                            ->orWhereHas('users', fn ($memberQuery) => $memberQuery->where('users.id', $user->id));
                    })
                    ->where('name', 'like', $like)
                    ->orderByDesc('updated_at')
                    ->limit($bucketLimit)
                    ->get(['id', 'name', 'updated_at'])
                    ->map(fn (Club $club) => [
                        'type' => 'club',
                        'id' => $club->id,
                        'title' => $club->name,
                        'subtitle' => 'Verein',
                        'updated_at' => $club->updated_at?->toIso8601String(),
                    ]),
                'count' => fn () => Club::query()
                    ->visibleTo($user)
                    ->where(function ($query) use ($user) {
                        $query->verified()
                            ->orWhereHas('users', fn ($memberQuery) => $memberQuery->where('users.id', $user->id));
                    })
                    ->where('name', 'like', $like)
                    ->count(),
            ],
            'team' => [
                'query' => fn () => Team::query()
                    ->visibleTo($user)
                    ->where(function ($query) use ($user, $like) {
                        $query->where('name', 'like', $like)
                            ->orWhere('sport_type', 'like', $like);
                    })
                    ->with('club:id,name')
                    ->orderByDesc('updated_at')
                    ->limit($bucketLimit)
                    ->get(['id', 'club_id', 'name', 'sport_type', 'updated_at'])
                    ->map(fn (Team $team) => [
                        'type' => 'team',
                        'id' => $team->id,
                        'title' => $team->name,
                        'subtitle' => trim(($team->club?->name ?? 'Team').' - '.($team->sport_type ?? '')),
                        'updated_at' => $team->updated_at?->toIso8601String(),
                    ]),
                'count' => fn () => Team::query()
                    ->visibleTo($user)
                    ->where(function ($query) use ($user, $like) {
                        $query->where('name', 'like', $like)
                            ->orWhere('sport_type', 'like', $like);
                    })
                    ->count(),
            ],
            'route' => [
                'query' => fn () => SportRoute::query()
                    ->visibleTo($user)
                    ->where(function ($query) use ($like) {
                        $query->where('title', 'like', $like)
                            ->orWhere('description', 'like', $like);
                    })
                    ->orderByDesc('updated_at')
                    ->limit($bucketLimit)
                    ->get(['id', 'title', 'distance_meters', 'difficulty', 'status', 'sport_type', 'user_id', 'updated_at'])
                    ->map(fn (SportRoute $route) => [
                        'type' => 'route',
                        'id' => $route->id,
                        'title' => $route->title,
                        'subtitle' => $route->sport_type ? trim(($route->sport_type).' - '.$route->difficulty) : 'Route',
                        'distance_km' => round((($route->distance_meters ?? 0) / 1000), 2),
                        'updated_at' => $route->updated_at?->toIso8601String(),
                    ]),
                'count' => fn () => SportRoute::query()
                    ->visibleTo($user)
                    ->where(function ($query) use ($like) {
                        $query->where('title', 'like', $like)
                            ->orWhere('description', 'like', $like);
                    })
                    ->count(),
            ],
            'training_plan' => [
                'query' => fn () => TrainingPlan::query()
                    ->where(function ($planQuery) use ($like) {
                        $planQuery->where('title', 'like', $like)
                            ->orWhere('description', 'like', $like);
                    })
                    ->orderByDesc('updated_at')
                    ->limit($bucketLimit)
                    ->get(['id', 'title', 'status', 'updated_at'])
                    ->map(fn (TrainingPlan $plan) => [
                        'type' => 'training_plan',
                        'id' => $plan->id,
                        'title' => $plan->title,
                        'subtitle' => 'Training Plan',
                        'status' => $plan->status,
                        'updated_at' => $plan->updated_at?->toIso8601String(),
                    ]),
                'count' => fn () => TrainingPlan::query()
                    ->where(function ($planQuery) use ($like) {
                        $planQuery->where('title', 'like', $like)
                            ->orWhere('description', 'like', $like);
                    })
                    ->count(),
            ],
            'post' => [
                'query' => fn () => Post::query()
                    ->where(function ($query) use ($user, $clubIds, $teamIds) {
                        $query
                            ->where('visibility', 'public')
                            ->orWhere('user_id', $user->id)
                            ->when($clubIds !== [], fn (Builder $clubQuery) => $clubQuery->orWhereIn('club_id', $clubIds))
                            ->when($teamIds !== [], fn (Builder $teamQuery) => $teamQuery->orWhereIn('team_id', $teamIds));
                    })
                    ->where(function ($query) use ($user) {
                        $query
                            ->where('moderation_status', 'approved')
                            ->orWhere('user_id', $user->id);
                    })
                    ->where('content', 'like', $like)
                    ->with('user:id,name,profile_photo_path')
                    ->orderByDesc('created_at')
                    ->limit($bucketLimit)
                    ->get(['id', 'user_id', 'content', 'post_type', 'created_at'])
                    ->map(fn (Post $post) => [
                        'type' => 'post',
                        'id' => $post->id,
                        'title' => $post->post_type ? ucfirst((string) $post->post_type) : 'Post',
                        'subtitle' => mb_substr(strip_tags($post->content ?? ''), 0, 110),
                        'post_id' => $post->id,
                        'updated_at' => $post->created_at?->toIso8601String(),
                    ]),
                'count' => fn () => Post::query()
                    ->where(function ($query) use ($user, $clubIds, $teamIds) {
                        $query
                            ->where('visibility', 'public')
                            ->orWhere('user_id', $user->id)
                            ->when($clubIds !== [], fn (Builder $clubQuery) => $clubQuery->orWhereIn('club_id', $clubIds))
                            ->when($teamIds !== [], fn (Builder $teamQuery) => $teamQuery->orWhereIn('team_id', $teamIds));
                    })
                    ->where(function ($query) use ($user) {
                        $query
                            ->where('moderation_status', 'approved')
                            ->orWhere('user_id', $user->id);
                    })
                    ->where('content', 'like', $like)
                    ->count(),
            ],
        ];

        $types = $searchType === 'all' ? array_keys($searchMap) : [$searchType];
        $counts = [];
        $results = collect();

        foreach ($types as $type) {
            $counts[$type] = (int) $searchMap[$type]['count']();
            $results = $results->concat($searchMap[$type]['query']());
        }

        $totalResults = array_sum($counts);
        $orderedResults = $results
            ->sortByDesc('updated_at')
            ->values();
        $pagination = $searchType === 'all'
            ? [
                'page' => 1,
                'per_page' => $limit,
                'total' => $orderedResults->count(),
                'total_pages' => 1,
                'has_more' => false,
            ]
            : [
                'page' => $page,
                'per_page' => $limit,
                'total' => $totalResults,
                'total_pages' => (int) ceil($totalResults / $limit),
                'has_more' => $page < max(1, (int) ceil($totalResults / $limit)),
            ];

        if ($searchType !== 'all') {
            $orderedResults = $orderedResults
                ->forPage($page, $limit);
        } else {
            $orderedResults = $orderedResults->take($limit);
        }

        $allCounts = [
            'users' => $counts['user'] ?? 0,
            'clubs' => $counts['club'] ?? 0,
            'teams' => $counts['team'] ?? 0,
            'routes' => $counts['route'] ?? 0,
            'training_plans' => $counts['training_plan'] ?? 0,
            'posts' => $counts['post'] ?? 0,
        ];

        $countsByType = array_merge([
            'users' => 0,
            'clubs' => 0,
            'teams' => 0,
            'routes' => 0,
            'training_plans' => 0,
            'posts' => 0,
        ], $allCounts);

        return response()->json([
            'data' => [
                'term' => $term,
                'results' => $orderedResults,
                'counts' => $countsByType,
                'pagination' => $pagination,
            ],
        ]);
    }

    public function challenges(Request $request)
    {
        $user = $request->user();

        $routes = SportRoute::query()
            ->visibleTo($user)
            ->where('status', '!=', 'archived')
            ->whereHas('tracks', fn ($query) => $query->where('status', 'completed'))
            ->withCount('tracks')
            ->orderByDesc('tracks_count')
            ->limit((int) $request->integer('limit', 6))
            ->get();

        $challenges = $routes->map(function (SportRoute $route) use ($user) {
            $leaderboard = $this->routeChallengeLeaderboard($route->id);
            $myRank = collect($leaderboard)->firstWhere('user.id', $user->id);

            return [
                'id' => $route->id,
                'title' => $route->title,
                'subtitle' => trim(($route->sport_type ? $route->sport_type.' - ' : '').($route->difficulty ?? '')),
                'distance_km' => round((($route->distance_meters ?? 0) / 1000), 2),
                'estimated_duration_seconds' => (int) $route->estimated_duration_seconds,
                'participant_count' => count($leaderboard),
                'my_rank' => $myRank ? (int) $myRank['rank'] : null,
                'leaderboard' => $leaderboard,
            ];
        });

        return response()->json([
            'data' => [
                'challenges' => $challenges->values(),
                'total' => $challenges->count(),
            ],
        ]);
    }

    public function routeAnalytics(Request $request, SportRoute $sportRoute)
    {
        $user = $request->user();
        abort_unless(SportRoute::query()->visibleTo($user)->whereKey($sportRoute->id)->exists(), 404);

        $attempts = $sportRoute->tracks()
            ->where('status', 'completed')
            ->with('creator:id,name,profile_photo_path')
            ->get([
                'id',
                'user_id',
                'distance_meters',
                'duration_seconds',
                'started_at',
                'ended_at',
                'elevation_gain_meters',
                'average_speed_mps',
                'max_speed_mps',
            ]);

        $attemptCount = $attempts->count();
        $totalDistance = $attempts->sum('distance_meters');
        $totalDuration = $attempts->sum('duration_seconds');
        $totalElevation = $attempts->sum('elevation_gain_meters');

        $topAttempts = $attempts
            ->sortByDesc(fn (SportRouteTrack $track) => $track->distance_meters ?? 0)
            ->take(10)
            ->map(fn (SportRouteTrack $track) => [
                'user' => [
                    'id' => (int) $track->user_id,
                    'name' => $track->creator?->name,
                    'avatar' => $track->creator?->profile_photo_thumb ?: $track->creator?->profile_photo_url,
                ],
                'distance_km' => round((($track->distance_meters ?? 0) / 1000), 2),
                'duration_seconds' => (int) $track->duration_seconds,
                'started_at' => $track->started_at?->toIso8601String(),
                'ended_at' => $track->ended_at?->toIso8601String(),
                'average_speed_mps' => $track->average_speed_mps,
                'max_speed_mps' => $track->max_speed_mps,
            ])
            ->values();
        $leaderboard = $this->routeChallengeLeaderboard((int) $sportRoute->id);

        return response()->json([
            'data' => [
                'route' => (new SportRouteResource($sportRoute))->resolve(),
                'analytics' => [
                    'attempts' => (int) $attemptCount,
                    'distance_m' => (int) $totalDistance,
                    'distance_km' => round(($totalDistance / 1000), 2),
                    'duration_seconds' => (int) $totalDuration,
                    'duration_min' => round(($totalDuration / 60), 1),
                    'elevation_gain_m' => (int) $totalElevation,
                    'average_speed_mps' => $attemptCount > 0 ? round($attempts->avg('average_speed_mps') ?? 0, 2) : 0,
                    'avg_distance_km_per_attempt' => $attemptCount > 0 ? round((($totalDistance / $attemptCount) / 1000), 2) : 0,
                    'top_attempts' => $topAttempts,
                    'leaderboard' => array_slice($leaderboard, 0, 5),
                ],
            ],
        ]);
    }

    public function coachWeekly(Request $request)
    {
        $user = $request->user();
        $currentStart = now()->startOfDay()->subDays(6);
        $previousStart = now()->startOfDay()->subDays(13);
        $previousEnd = now()->startOfDay()->subDays(7)->endOfDay();

        $current = TrainingLog::query()
            ->where('user_id', $user->id)
            ->whereNotNull('performed_at')
            ->where('performed_at', '>=', $currentStart)
            ->get();

        $previous = TrainingLog::query()
            ->where('user_id', $user->id)
            ->whereNotNull('performed_at')
            ->whereBetween('performed_at', [$previousStart, $previousEnd])
            ->get();

        $currentStats = $this->trainingWindowStats($current);
        $previousStats = $this->trainingWindowStats($previous);

        $distanceTrend = $this->trendPercent((float) $currentStats['distance_km'], (float) $previousStats['distance_km']);
        $durationTrend = $this->trendPercent((float) $currentStats['duration_minutes'], (float) $previousStats['duration_minutes']);
        $sessionTrend = $this->trendPercent((float) $currentStats['session_count'], (float) $previousStats['session_count']);

        $intensities = $current->count() > 0
            ? $current->pluck('intensity')->filter()->values()->countBy()->map(fn (int $count) => $count)->toArray()
            : [];

        $recommendations = ['text' => []];
        if ($currentStats['session_count'] === 0) {
            $recommendations['text'][] = 'Starte diese Woche mindestens eine Trainingseinheit, damit die KI sinnvolle Fortschritte analysieren kann.';
        } else {
            if ($distanceTrend < -10) {
                $recommendations['text'][] = 'Deine Strecke war diese Woche deutlich niedriger als letzte Woche. Ergänze 1 zusatzliche kurze Einheit.';
            }
            if ($durationTrend >= 15) {
                $recommendations['text'][] = 'Sehr gute Steigerung der Trainingszeit - erhöhe die Erholung zwischen intensiven Einheiten.';
            }
            if (($intensities['high'] ?? 0) > (($intensities['low'] ?? 0) + ($intensities['medium'] ?? 0))) {
                $recommendations['text'][] = 'Zu viele harte Einheiten in Folge: plane einen lockeren Tag ein, damit die Qualität steigt.';
            }
        }

        if (empty($recommendations['text'])) {
            $recommendations['text'][] = 'Bleib konsistent: Ziel sind 3 Einheiten/Woche in ähnlicher Intensität.';
        }

        return response()->json([
            'data' => [
                'current_week' => $currentStats,
                'previous_week' => $previousStats,
                'trend' => [
                    'sessions_percent' => $sessionTrend,
                    'distance_percent' => $distanceTrend,
                    'duration_percent' => $durationTrend,
                ],
                'insights' => [
                    'dominant_intensity' => $intensities ? array_key_first($intensities) : null,
                    'recommendations' => $recommendations['text'],
                ],
            ],
        ]);
    }

    public function onboarding(Request $request)
    {
        $user = $request->user();
        $checklist = $this->onboardingChecklist($user);

        return response()->json(['data' => $checklist]);
    }

    public function viral(Request $request)
    {
        $user = $request->user();
        $friendInvites = FriendInvitation::query()->where('sender_id', $user->id);
        $teamInvites = TeamInvitation::query()->where('inviter_id', $user->id);

        $sentFriendInvites = (clone $friendInvites)->count();
        $acceptedFriendInvites = (clone $friendInvites)->where('status', 'accepted')->count();
        $pendingFriendInvites = (clone $friendInvites)->where('status', 'pending')->count();
        $sentTeamInvites = (clone $teamInvites)->count();
        $acceptedTeamInvites = (clone $teamInvites)->where('status', 'accepted')->count();

        $baseUrl = rtrim((string) config('app.url', url('/')), '/');
        $shareCode = strtoupper(substr(hash_hmac('sha256', (string) $user->id.'|'.(string) $user->created_at?->timestamp, (string) config('app.key', 'airmius')), 0, 16));
        $shareUrl = $baseUrl.'/register?ref='.$shareCode;

        return response()->json([
            'data' => [
                'share_code' => $shareCode,
                'share_url' => $shareUrl,
                'invitation_metrics' => [
                    'friend_invites_sent' => $sentFriendInvites,
                    'friend_invites_accepted' => $acceptedFriendInvites,
                    'friend_invites_pending' => $pendingFriendInvites,
                    'team_invites_sent' => $sentTeamInvites,
                    'team_invites_accepted' => $acceptedTeamInvites,
                    'friend_conversion_rate' => $sentFriendInvites > 0 ? round(($acceptedFriendInvites / max($sentFriendInvites, 1)) * 100, 1) : 0,
                    'team_conversion_rate' => $sentTeamInvites > 0 ? round(($acceptedTeamInvites / max($sentTeamInvites, 1)) * 100, 1) : 0,
                ],
            ],
        ]);
    }

    public function safety(Request $request)
    {
        $user = $request->user();

        $openReports = $this->openReportsForUserContent($user);
        $openFlags = $this->openFlagsForUserContent($user);
        $warnings = (int) AccountWarning::query()->where('user_id', $user->id)->sum('points');
        $friendWarnings = $user->followers()->count() < 1 ? 5 : 0;

        $penalty = min(85, ($openReports * 6) + ($openFlags * 5) + $warnings + $friendWarnings);
        $trustScore = max(10, 100 - $penalty);
        $risk = $trustScore >= 85 ? 'low' : ($trustScore >= 60 ? 'medium' : 'high');

        $recommendations = [];
        if ($openReports > 0) {
            $recommendations[] = 'Aktuell gibt es offene Inhaltsmeldungen. Bitte prüfe betroffene Beiträge im Moderationsbereich.';
        }
        if ($openFlags > 0) {
            $recommendations[] = 'Automatische Sicherheitskennzeichen wurden ausgelöst. Bitte Beitragsregeln und Sprache überprüfen.';
        }
        if ($warnings >= 3) {
            $recommendations[] = 'Es wurden mehrere Account-Warnungen vermerkt. Ein kurzer Vertrauens-Check reduziert Risiko und Sichtbarkeit.';
        }
        if (empty($recommendations)) {
            $recommendations[] = 'Profil wirkt stabil. Weiterhin ansprechenden Inhalt ohne Regelverletzungen posten.';
        }

        return response()->json([
            'data' => [
                'trust_score' => $trustScore,
                'risk_level' => $risk,
                'risk_factors' => [
                    'open_reports' => $openReports,
                    'open_flags' => $openFlags,
                    'account_warnings' => $warnings,
                ],
                'recommendations' => $recommendations,
            ],
        ]);
    }

    private function postDiscoveryQuery(Request $request, bool $trending = false): Builder
    {
        $validated = $request->validate([
            'scope' => ['nullable', 'in:all,mine,club,team,friends'],
            'q' => ['nullable', 'string', 'max:200'],
            'post_type' => ['nullable', Rule::in(Post::TYPES)],
            'sport_id' => ['nullable', 'integer', 'exists:sports,id'],
            'sport_type' => ['nullable', 'string', 'max:80'],
            'visibility' => ['nullable', Rule::in(Post::VISIBILITIES)],
            'hashtag' => ['nullable', 'string', 'max:80'],
            'sort' => ['nullable', 'in:latest,trending'],
            'trend_days' => ['nullable', 'integer', 'min:1', 'max:60'],
        ]);

        $scope = $validated['scope'] ?? 'all';
        $term = trim((string) ($validated['q'] ?? ''));
        $postType = $validated['post_type'] ?? null;
        $sportId = isset($validated['sport_id']) ? (int) $validated['sport_id'] : null;
        $sportType = $validated['sport_type'] ?? null;
        $visibility = $validated['visibility'] ?? null;
        $hashtag = isset($validated['hashtag']) ? ltrim((string) $validated['hashtag'], '#') : null;
        $sort = $validated['sort'] ?? 'latest';

        $user = $request->user();
        $clubIds = $user->clubs()->pluck('clubs.id')->all();
        $teamIds = $user->teams()->pluck('teams.id')->all();
        $followingIds = Follow::query()->where('follower_id', $user->id)->pluck('followed_id')->all();

        $query = Post::query()
            ->with(['user:id,name,first_name,last_name,email,profile_photo_path', 'club:id,name', 'team:id,name'])
            ->withCount(['comments', 'likes', 'helpfuls']);

        if ($scope === 'mine') {
            $query->where('user_id', $user->id);
        } elseif ($scope === 'friends') {
            $query->where(function ($scopeQuery) use ($user, $followingIds) {
                $scopeQuery->where('visibility', 'public')->orWhere('user_id', $user->id);
                if ($followingIds !== []) {
                    $scopeQuery->orWhereIn('user_id', $followingIds);
                }
            });
        } else {
            $query->where(function ($scopeQuery) use ($user, $clubIds, $teamIds, $scope) {
                $scopeQuery->where('visibility', 'public')->orWhere('user_id', $user->id);

                if (in_array($scope, ['all', 'club'], true) && $clubIds !== []) {
                    $scopeQuery->orWhereIn('club_id', $clubIds);
                }
                if (in_array($scope, ['all', 'team'], true) && $teamIds !== []) {
                    $scopeQuery->orWhereIn('team_id', $teamIds);
                }
            });
        }

        $query->where(function ($query) use ($user) {
            $query
                ->where('moderation_status', 'approved')
                ->orWhere('user_id', $user->id);
        });

        if ($postType !== null) {
            $query->where('post_type', $postType);
        }
        if ($sportId !== null) {
            $query->where('sport_id', $sportId);
        } elseif ($sportType !== null && trim($sportType) !== '') {
            $query->whereHas('sport', function (Builder $sportQuery) use ($sportType) {
                $like = '%'.$sportType.'%';
                $sportQuery->where('name', 'like', $like)->orWhere('slug', 'like', $like);
            });
        }
        if ($visibility !== null) {
            $query->where('visibility', $visibility);
        }
        if ($term !== '') {
            $like = '%'.$term.'%';
            $query->where(function ($searchQuery) use ($like) {
                $searchQuery->where('content', 'like', $like)
                    ->orWhereHas('user', fn ($userQuery) => $userQuery->where('name', 'like', $like));
            });
        }
        if ($hashtag !== null) {
            $query->where('content', 'like', '%#'.str_replace('%', '', $hashtag).'%');
        }

        if ($sort === 'trending' || $trending) {
            $trendWindow = now()->subDays((int) ($validated['trend_days'] ?? 14));
            $query
                ->withCount([
                    'comments as trending_comments' => fn ($countQuery) => $countQuery->where('created_at', '>=', $trendWindow),
                    'likes as trending_likes' => fn ($countQuery) => $countQuery->where('created_at', '>=', $trendWindow),
                    'helpfuls as trending_helpfuls' => fn ($countQuery) => $countQuery->where('created_at', '>=', $trendWindow),
                ])
                ->orderByRaw('(COALESCE(trending_likes, 0) * 4 + COALESCE(trending_comments, 0) * 2 + COALESCE(trending_helpfuls, 0) * 3) DESC')
                ->latest();
        } else {
            $query->latest();
        }

        return $query;
    }

    private function onboardingChecklist(User $user): array
    {
        $items = [
            ['key' => 'name', 'label' => 'Name vervollständigt', 'done' => (bool) ($user->first_name && $user->last_name)],
            ['key' => 'email_verified', 'label' => 'E-Mail verifiziert', 'done' => (bool) $user->email_verified_at],
            ['key' => 'photo', 'label' => 'Profilbild gesetzt', 'done' => (bool) $user->profile_photo_path],
            ['key' => 'bio', 'label' => 'Bio gesetzt', 'done' => (bool) $user->bio],
            ['key' => 'country', 'label' => 'Standort hinterlegt', 'done' => (bool) $user->country],
            ['key' => 'first_route', 'label' => 'Erste Route erstellt', 'done' => (bool) $user->sportRoutes()->exists()],
            ['key' => 'first_track', 'label' => 'Ersten Track abgeschlossen', 'done' => (bool) $user->sportRouteTracks()->where('status', 'completed')->exists()],
            ['key' => 'first_training_log', 'label' => 'Erstes Training geloggt', 'done' => (bool) $user->trainingLogs()->exists()],
            ['key' => 'first_post', 'label' => 'Ersten Post geteilt', 'done' => (bool) $user->posts()->exists()],
            ['key' => 'first_friendship', 'label' => 'Ersten Kontakt hinzugefügt', 'done' => (bool) $user->friendships()->exists()],
        ];

        $completed = count(array_filter($items, fn ($item) => $item['done']));

        return [
            'completion_percent' => (int) round(($completed / max(count($items), 1)) * 100),
            'items' => $items,
            'next_actions' => array_values(array_filter($items, fn ($item) => ! $item['done'])),
        ];
    }

    private function routeChallengeLeaderboard(int $routeId): array
    {
        $attempts = SportRouteTrack::query()
            ->where('sport_route_id', $routeId)
            ->where('status', 'completed')
            ->whereNotNull('user_id')
            ->with('creator:id,name,profile_photo_path')
            ->get(['id', 'user_id', 'distance_meters', 'duration_seconds', 'started_at']);

        if ($attempts->isEmpty()) {
            return [];
        }

        $players = $attempts
            ->groupBy('user_id')
            ->map(fn ($records) => [
                'user' => [
                    'id' => (int) ($records->first()?->user_id ?? 0),
                    'name' => $records->first()?->creator?->name,
                    'avatar' => $records->first()?->creator?->profile_photo_thumb ?: $records->first()?->creator?->profile_photo_url,
                ],
                'attempts' => $records->count(),
                'total_distance_m' => (int) $records->sum('distance_meters'),
                'total_distance_km' => round((($records->sum('distance_meters') ?? 0) / 1000), 2),
                'total_duration_seconds' => (int) $records->sum('duration_seconds'),
                'best_distance_m' => (int) $records->max('distance_meters'),
                'best_time_seconds' => (int) $records->filter(fn (SportRouteTrack $track) => (int) $track->duration_seconds > 0)->min('duration_seconds'),
                'last_attempt_at' => optional($records->sortByDesc('started_at')->first())->started_at?->toIso8601String(),
            ])
            ->sortByDesc('total_distance_m')
            ->values()
            ->map(function (array $player, int $index) {
                $player['rank'] = $index + 1;
                return $player;
            })
            ->values()
            ->toArray();

        return array_slice($players, 0, 25);
    }

    private function trainingWindowStats(Collection|array $logs): array
    {
        if ($logs instanceof Collection) {
            $distanceMeters = $logs->sum('distance_meters');
            $durationMinutes = (int) $logs->sum('duration_minutes');
            return [
                'session_count' => $logs->count(),
                'distance_m' => (int) $distanceMeters,
                'distance_km' => round(($distanceMeters / 1000), 2),
                'duration_minutes' => $durationMinutes,
                'calories' => (int) $logs->sum('calories'),
            ];
        }

        return [
            'session_count' => 0,
            'distance_m' => 0,
            'distance_km' => 0,
            'duration_minutes' => 0,
            'calories' => 0,
        ];
    }

    private function trendPercent(float $current, float $previous): float
    {
        if ($previous <= 0) {
            return $current > 0 ? 100 : 0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    private function openReportsForUserContent(User $user): int
    {
        $count = 0;
        foreach ($this->userContentModels() as $modelClass => $ownerColumn) {
            $ids = $modelClass::query()
                ->where($ownerColumn, $user->id)
                ->pluck('id')
                ->all();

            if ($ids === []) {
                continue;
            }

            $count += ContentReport::query()
                ->where('reportable_type', $modelClass)
                ->whereNotIn('status', ['resolved', 'closed'])
                ->whereIn('reportable_id', $ids)
                ->count();
        }

        return (int) $count;
    }

    private function openFlagsForUserContent(User $user): int
    {
        $count = 0;

        foreach ($this->userContentModels() as $modelClass => $ownerColumn) {
            $ids = $modelClass::query()
                ->where($ownerColumn, $user->id)
                ->pluck('id')
                ->all();

            if ($ids === []) {
                continue;
            }

            $count += ModerationFlag::query()
                ->where('flaggable_type', $modelClass)
                ->whereNotIn('status', ['resolved', 'cleared'])
                ->whereIn('flaggable_id', $ids)
                ->count();
        }

        return (int) $count;
    }

    private function userContentModels(): array
    {
        return [
            Post::class => 'user_id',
            Story::class => 'user_id',
            TrainingLog::class => 'user_id',
            SportRoute::class => 'user_id',
            SportRouteTrack::class => 'user_id',
        ];
    }
}







