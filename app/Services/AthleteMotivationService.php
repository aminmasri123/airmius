<?php

namespace App\Services;

use App\Models\SportRoute;
use App\Models\SportRouteTrack;
use App\Models\TrainingLog;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AthleteMotivationService
{
    public function forUser(User $user): array
    {
        $now = now();
        $weekStart = $now->copy()->startOfWeek();
        $teamIds = $user->teams()->pluck('teams.id')->map(fn ($id) => (int) $id)->all();
        $clubIds = $user->clubs()->pluck('clubs.id')->map(fn ($id) => (int) $id)->all();

        $logs = TrainingLog::query()
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->whereNotNull('performed_at')
            ->orderByDesc('performed_at')
            ->limit(500)
            ->get();

        $tracks = SportRouteTrack::query()
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->whereNotNull('started_at')
            ->orderByDesc('started_at')
            ->limit(500)
            ->get();

        $weeklyLogs = $logs->filter(fn (TrainingLog $log) => $log->performed_at?->greaterThanOrEqualTo($weekStart));
        $weeklyTracks = $tracks->filter(fn (SportRouteTrack $track) => $track->started_at?->greaterThanOrEqualTo($weekStart));
        $weeklyDistance = (int) $weeklyLogs->sum('distance_meters') + (int) $weeklyTracks->sum('distance_meters');
        $weeklyMinutes = (int) $weeklyLogs->sum('duration_minutes') + (int) round($weeklyTracks->sum('duration_seconds') / 60);
        $weeklySessions = $weeklyLogs->count() + $weeklyTracks->count();
        $streakDays = $this->streakDays($logs, $tracks, $now);

        $goals = $this->weeklyGoals($weeklyDistance, $weeklyMinutes, $weeklySessions, $streakDays);

        return [
            'generated_at' => $now->toIso8601String(),
            'status' => 'ready',
            'summary' => [
                'motivation_score' => $this->motivationScore($goals, $streakDays),
                'weekly_distance_meters' => $weeklyDistance,
                'weekly_minutes' => $weeklyMinutes,
                'weekly_sessions' => $weeklySessions,
                'streak_days' => $streakDays,
                'next_best_action' => $this->nextBestAction($goals),
            ],
            'personal_records' => $this->personalRecords($logs, $tracks),
            'goals' => $goals,
            'segments' => $this->routeSegments($user),
            'challenges' => $this->challenges($user),
            'clubs' => $this->clubMotivation($clubIds, $weekStart),
            'rankings' => [
                'team_weekly_distance' => $this->distanceRanking(
                    $this->teamMemberIds($teamIds),
                    $weekStart,
                    'team_weekly_distance',
                    $user->id,
                    $teamIds
                ),
                'club_weekly_distance' => $this->distanceRanking(
                    $this->clubMemberIds($clubIds),
                    $weekStart,
                    'club_weekly_distance',
                    $user->id
                ),
            ],
            'contract' => [
                'version' => '2026-06-03.motivation.v1',
                'privacy_note' => 'Leaderboards use activities visible through shared teams/clubs and public route challenges.',
                'mobile_sections' => ['today_summary', 'prs', 'goals', 'segments', 'challenges', 'rankings', 'clubs'],
            ],
        ];
    }

    private function personalRecords(Collection $logs, Collection $tracks): array
    {
        $activities = collect();

        foreach ($logs as $log) {
            $activities->push([
                'source' => 'training_log',
                'source_id' => $log->id,
                'sport_type' => $log->sport_type,
                'date' => $log->performed_at,
                'distance_meters' => (int) ($log->distance_meters ?? 0),
                'duration_seconds' => (int) (($log->duration_minutes ?? 0) * 60),
                'elevation_gain_meters' => (int) data_get($log->metrics, 'elevation_gain_meters', 0),
            ]);
        }

        foreach ($tracks as $track) {
            $activities->push([
                'source' => 'sport_route_track',
                'source_id' => $track->id,
                'sport_type' => $track->sport_type,
                'date' => $track->started_at,
                'distance_meters' => (int) ($track->distance_meters ?? 0),
                'duration_seconds' => (int) ($track->duration_seconds ?? 0),
                'elevation_gain_meters' => (int) ($track->elevation_gain_meters ?? 0),
            ]);
        }

        $records = [];
        $longest = $activities->sortByDesc('distance_meters')->first();
        if ($longest && $longest['distance_meters'] > 0) {
            $records[] = $this->record('longest_activity', 'distance', $longest['distance_meters'], 'm', $longest);
        }

        $bestElevation = $activities->sortByDesc('elevation_gain_meters')->first();
        if ($bestElevation && $bestElevation['elevation_gain_meters'] > 0) {
            $records[] = $this->record('most_elevation_gain', 'elevation', $bestElevation['elevation_gain_meters'], 'm', $bestElevation);
        }

        foreach ([5000 => 'fastest_5k', 10000 => 'fastest_10k'] as $distance => $key) {
            $best = $activities
                ->filter(fn (array $activity) => $activity['distance_meters'] >= $distance && $activity['duration_seconds'] > 0)
                ->map(function (array $activity) use ($distance) {
                    $activity['estimated_seconds'] = (int) round($activity['duration_seconds'] * ($distance / max($activity['distance_meters'], 1)));

                    return $activity;
                })
                ->sortBy('estimated_seconds')
                ->first();

            if ($best) {
                $records[] = $this->record($key, 'time', $best['estimated_seconds'], 'seconds', $best);
            }
        }

        $bestSpeed = $activities
            ->filter(fn (array $activity) => $activity['distance_meters'] > 0 && $activity['duration_seconds'] > 0)
            ->map(function (array $activity) {
                $activity['speed_mps'] = round($activity['distance_meters'] / max($activity['duration_seconds'], 1), 3);

                return $activity;
            })
            ->sortByDesc('speed_mps')
            ->first();

        if ($bestSpeed) {
            $records[] = $this->record('best_average_speed', 'speed', $bestSpeed['speed_mps'], 'mps', $bestSpeed);
        }

        return array_values($records);
    }

    private function record(string $key, string $metric, int|float $value, string $unit, array $activity): array
    {
        return [
            'key' => $key,
            'metric' => $metric,
            'value' => $value,
            'unit' => $unit,
            'sport_type' => $activity['sport_type'],
            'source' => $activity['source'],
            'source_id' => $activity['source_id'],
            'achieved_at' => $activity['date']?->toIso8601String(),
            'shareable' => true,
        ];
    }

    private function weeklyGoals(int $weeklyDistance, int $weeklyMinutes, int $weeklySessions, int $streakDays): array
    {
        return [
            'weekly_distance' => $this->goal('weekly_distance', $weeklyDistance, 20000, 'm'),
            'weekly_sessions' => $this->goal('weekly_sessions', $weeklySessions, 3, 'sessions'),
            'weekly_minutes' => $this->goal('weekly_minutes', $weeklyMinutes, 150, 'minutes'),
            'streak' => $this->goal('streak', $streakDays, 5, 'days'),
        ];
    }

    private function goal(string $key, int $current, int $target, string $unit): array
    {
        return [
            'key' => $key,
            'current' => $current,
            'target' => $target,
            'unit' => $unit,
            'percent' => min(100, (int) round(($current / max($target, 1)) * 100)),
            'remaining' => max(0, $target - $current),
            'completed' => $current >= $target,
        ];
    }

    private function routeSegments(User $user): array
    {
        return SportRoute::query()
            ->visibleTo($user)
            ->whereHas('tracks', fn ($query) => $query->where('status', 'completed'))
            ->with(['tracks' => fn ($query) => $query
                ->where('status', 'completed')
                ->where('duration_seconds', '>', 0)
                ->orderBy('duration_seconds')
                ->limit(20),
                'tracks.creator:id,name',
            ])
            ->withCount(['tracks as completed_attempts_count' => fn ($query) => $query->where('status', 'completed')])
            ->orderByDesc('completed_attempts_count')
            ->limit(8)
            ->get()
            ->map(function (SportRoute $route) use ($user) {
                $leaderboard = $this->segmentLeaderboard($route->tracks, $user->id);
                $myRank = collect($leaderboard)->firstWhere('is_me', true);

                return [
                    'route_id' => $route->id,
                    'title' => $route->title,
                    'sport_type' => $route->sport_type,
                    'distance_meters' => (int) $route->distance_meters,
                    'elevation_gain_meters' => (int) $route->elevation_gain_meters,
                    'attempts_count' => (int) $route->completed_attempts_count,
                    'challenge_type' => 'route_segment',
                    'leaderboard_metric' => 'fastest_time',
                    'my_rank' => $myRank['rank'] ?? null,
                    'leaderboard' => $leaderboard,
                ];
            })
            ->values()
            ->all();
    }

    private function segmentLeaderboard(Collection $tracks, int $userId): array
    {
        return $tracks
            ->filter(fn (SportRouteTrack $track) => (int) $track->duration_seconds > 0)
            ->groupBy('user_id')
            ->map(function (Collection $attempts) {
                return $attempts->sortBy('duration_seconds')->first();
            })
            ->sortBy('duration_seconds')
            ->values()
            ->take(10)
            ->map(function (SportRouteTrack $track, int $index) use ($userId) {
                return [
                    'rank' => $index + 1,
                    'user_id' => $track->user_id,
                    'name' => $track->creator?->name,
                    'best_time_seconds' => (int) $track->duration_seconds,
                    'distance_meters' => (int) $track->distance_meters,
                    'completed_at' => $track->ended_at?->toIso8601String() ?? $track->started_at?->toIso8601String(),
                    'is_me' => (int) $track->user_id === $userId,
                ];
            })
            ->all();
    }

    private function challenges(User $user): array
    {
        $segments = $this->routeSegments($user);

        return [
            'route_challenges' => array_slice($segments, 0, 5),
            'weekly_goal_challenges' => [
                ['key' => 'complete_3_sessions', 'target' => 3, 'metric' => 'sessions', 'reward_xp' => 75],
                ['key' => 'move_20k', 'target' => 20000, 'metric' => 'distance_meters', 'reward_xp' => 120],
                ['key' => 'five_day_streak', 'target' => 5, 'metric' => 'streak_days', 'reward_xp' => 150],
            ],
        ];
    }

    private function clubMotivation(array $clubIds, CarbonInterface $weekStart): array
    {
        if ($clubIds === []) {
            return [
                'active_clubs' => 0,
                'weekly_club_rankings_available' => false,
                'items' => [],
            ];
        }

        $items = DB::table('clubs')
            ->whereIn('id', $clubIds)
            ->select(['id', 'name', 'sport_type'])
            ->get()
            ->map(function ($club) use ($weekStart) {
                $memberIds = $this->clubMemberIds([(int) $club->id]);
                $ranking = $this->distanceRanking($memberIds, $weekStart, 'club_weekly_distance', null);

                return [
                    'club_id' => (int) $club->id,
                    'name' => $club->name,
                    'sport_type' => $club->sport_type,
                    'weekly_active_members' => count($ranking['leaderboard']),
                    'leaderboard_preview' => array_slice($ranking['leaderboard'], 0, 5),
                ];
            })
            ->values()
            ->all();

        return [
            'active_clubs' => count($items),
            'weekly_club_rankings_available' => true,
            'items' => $items,
        ];
    }

    private function distanceRanking(array $userIds, CarbonInterface $weekStart, string $key, ?int $currentUserId = null, ?array $teamIds = null): array
    {
        if ($userIds === []) {
            return [
                'key' => $key,
                'window' => 'current_week',
                'my_rank' => null,
                'leaderboard' => [],
            ];
        }

        $logRows = TrainingLog::query()
            ->select(['user_id'])
            ->selectRaw('SUM(COALESCE(distance_meters, 0)) as distance_meters')
            ->whereIn('user_id', $userIds)
            ->where('status', 'completed')
            ->where('performed_at', '>=', $weekStart)
            ->when($teamIds !== null && $teamIds !== [], fn ($query) => $query->whereIn('team_id', $teamIds))
            ->groupBy('user_id')
            ->pluck('distance_meters', 'user_id');

        $trackRows = SportRouteTrack::query()
            ->select(['user_id'])
            ->selectRaw('SUM(COALESCE(distance_meters, 0)) as distance_meters')
            ->whereIn('user_id', $userIds)
            ->where('status', 'completed')
            ->where('started_at', '>=', $weekStart)
            ->when($teamIds !== null && $teamIds !== [], fn ($query) => $query->whereIn('team_id', $teamIds))
            ->groupBy('user_id')
            ->pluck('distance_meters', 'user_id');

        $names = User::query()->whereIn('id', $userIds)->pluck('name', 'id');
        $distances = collect($userIds)
            ->mapWithKeys(fn (int $id) => [
                $id => (int) ($logRows[$id] ?? 0) + (int) ($trackRows[$id] ?? 0),
            ])
            ->filter(fn (int $distance) => $distance > 0)
            ->sortDesc();

        $leaderboard = $distances
            ->values()
            ->map(function (int $distance, int $index) use ($distances, $names, $currentUserId) {
                $userId = (int) $distances->keys()[$index];

                return [
                    'rank' => $index + 1,
                    'user_id' => $userId,
                    'name' => $names[$userId] ?? null,
                    'distance_meters' => $distance,
                    'is_me' => $currentUserId !== null && $userId === $currentUserId,
                ];
            })
            ->take(25)
            ->values()
            ->all();

        $myRank = collect($leaderboard)->firstWhere('is_me', true);

        return [
            'key' => $key,
            'window' => 'current_week',
            'my_rank' => $myRank['rank'] ?? null,
            'leaderboard' => $leaderboard,
        ];
    }

    private function teamMemberIds(array $teamIds): array
    {
        if ($teamIds === []) {
            return [];
        }

        return DB::table('team_user')
            ->whereIn('team_id', $teamIds)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function clubMemberIds(array $clubIds): array
    {
        if ($clubIds === []) {
            return [];
        }

        return DB::table('club_user')
            ->whereIn('club_id', $clubIds)
            ->where(function ($query) {
                $query->whereNull('membership_status')->orWhere('membership_status', 'active');
            })
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function streakDays(Collection $logs, Collection $tracks, CarbonInterface $now): int
    {
        $dates = $logs
            ->map(fn (TrainingLog $log) => $log->performed_at?->toDateString())
            ->merge($tracks->map(fn (SportRouteTrack $track) => $track->started_at?->toDateString()))
            ->filter()
            ->unique()
            ->values();

        $cursor = $dates->contains($now->toDateString()) ? $now->copy()->startOfDay() : $now->copy()->subDay()->startOfDay();
        $streak = 0;

        while ($dates->contains($cursor->toDateString())) {
            $streak++;
            $cursor->subDay();
        }

        return $streak;
    }

    private function motivationScore(array $goals, int $streakDays): int
    {
        $goalAverage = collect($goals)->avg('percent') ?: 0;

        return min(100, (int) round(($goalAverage * 0.75) + (min(10, $streakDays) * 2.5)));
    }

    private function nextBestAction(array $goals): array
    {
        $next = collect($goals)
            ->reject(fn (array $goal) => $goal['completed'])
            ->sortBy('percent')
            ->first();

        if (! $next) {
            return [
                'key' => 'recover_or_share',
                'label_key' => 'motivation.actions.recover_or_share',
                'target_goal' => null,
            ];
        }

        return [
            'key' => 'progress_'.$next['key'],
            'label_key' => 'motivation.actions.progress_'.$next['key'],
            'target_goal' => $next['key'],
            'remaining' => $next['remaining'],
            'unit' => $next['unit'],
        ];
    }
}
