<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Team;
use App\Models\TrainingLog;
use App\Models\TrainingPlan;
use App\Models\TrainingPlanItem;
use App\Models\User;
use App\Services\PlanFeatureService;
use App\Services\Training\TrainingLogAccessService;
use App\Support\ClubPermissions;
use App\Support\Roles;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TrainerCockpitController extends Controller
{
    private const GLOBAL_TRAINER_ROLES = [
        'coach',
        'assistant_coach',
        'performance_coach',
        'fitness_coach',
        'team_manager',
        'captain',
    ];

    private const TEAM_STAFF_ROLES = [
        'Coach',
        'coach',
        'Trainer',
        'trainer',
        'Captain',
        'captain',
        'Admin',
        'admin',
        'Manager',
        'manager',
    ];

    public function __construct(
        private PlanFeatureService $planFeatures,
        private TrainingLogAccessService $logAccess,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $hasTrainerAccess = $this->hasTrainerAccess($user);

        abort_unless($hasTrainerAccess, 403);

        $teamIds = $this->trainerTeamIds($user);
        $teamCount = $teamIds->count();

        $teams = Team::query()
            ->whereIn('id', $teamIds)
            ->with([
                'club:id,name',
                'club.currentSubscription.plan',
                'users' => fn ($query) => $query
                    ->select('users.id', 'name', 'first_name', 'last_name', 'profile_photo_path')
                    ->orderBy('name'),
            ])
            ->withCount(['events', 'trainingPlans'])
            ->orderBy('name')
            ->get()
            ->map(fn (Team $team) => $this->teamSummary($team))
            ->values();

        $upcomingEvents = $this->upcomingEvents($teamIds);
        $plannedItems = $this->plannedItems($teamIds);
        $recentLogs = $this->recentLogs($teamIds, $user);
        $feedbackOpen = $this->feedbackOpen($teamIds, $user);
        $overdueItems = $this->overdueItems($teamIds);
        $plans = $this->plans($teamIds, $user);
        $coachWeekly = $this->coachWeekly($teamIds, $teams, $feedbackOpen, $overdueItems, $user);

        $payload = [
            'teams' => $teams,
            'summary' => [
                'teams' => $teamCount,
                'athletes' => $teams->sum(fn (array $team) => count($team['athletes'])),
                'upcoming_events' => $upcomingEvents->count(),
                'planned_items' => $plannedItems->count(),
                'feedback_open' => $feedbackOpen->count(),
                'overdue_items' => $overdueItems->count(),
                'recent_logs' => $recentLogs->count(),
                'readiness_score' => $coachWeekly['readiness_score'],
                'risk_athletes' => count($coachWeekly['risk_athletes']),
            ],
            'upcomingEvents' => $upcomingEvents,
            'plannedItems' => $plannedItems,
            'feedbackOpen' => $feedbackOpen,
            'overdueItems' => $overdueItems,
            'recentLogs' => $recentLogs,
            'plans' => $plans,
            'coachWeekly' => $coachWeekly,
        ];

        if ($request->expectsJson()) {
            return response()->json(['data' => $payload]);
        }

        return Inertia::render('Auth/Dashboard/TrainerCockpit/Index', $payload);
    }

    public static function userCanView(User $user): bool
    {
        return $user->hasAnyRole(Roles::FULL_ACCESS)
            || $user->hasAnyRole(self::GLOBAL_TRAINER_ROLES)
            || self::hasStaffTeams($user)
            || self::hasTrainerClubs($user);
    }

    private function hasTrainerAccess(User $user): bool
    {
        return self::userCanView($user);
    }

    private static function hasStaffTeams(User $user): bool
    {
        return $user->teams()
            ->with('club')
            ->wherePivotIn('role', self::TEAM_STAFF_ROLES)
            ->get()
            ->contains(fn (Team $team) => ! $team->club
                || ! ClubPermissions::explicitlyDenies(
                    $team->club,
                    $user,
                    ClubPermissions::TRAINER_COCKPIT_VIEW,
                ));
    }

    private static function hasTrainerClubs(User $user): bool
    {
        return $user->clubs()
            ->get()
            ->contains(fn ($club) => ClubPermissions::allowsAnyScope(
                $club,
                $user,
                ClubPermissions::TRAINER_COCKPIT_VIEW,
            ));
    }

    private function trainerTeamIds(User $user)
    {
        if ($user->hasAnyRole(Roles::FULL_ACCESS)) {
            return Team::query()->pluck('id');
        }

        $staffTeamIds = $user->teams()
            ->with('club')
            ->wherePivotIn('role', self::TEAM_STAFF_ROLES)
            ->get()
            ->filter(fn (Team $team) => ! $team->club
                || ! ClubPermissions::explicitlyDenies(
                    $team->club,
                    $user,
                    ClubPermissions::TRAINER_COCKPIT_VIEW,
                ))
            ->pluck('id');

        $clubIds = $user->clubs()->pluck('clubs.id');
        $clubTeams = $clubIds->isEmpty()
            ? collect()
            : Team::query()->with('club')->whereIn('club_id', $clubIds)->get();
        $clubTeamIds = $clubTeams
            ->filter(fn (Team $team) => ClubPermissions::allowsForTeam(
                $team,
                $user,
                ClubPermissions::TRAINER_COCKPIT_VIEW,
            ))
            ->pluck('id');

        if ($staffTeamIds->isEmpty() && $clubTeamIds->isEmpty() && $user->hasAnyRole(self::GLOBAL_TRAINER_ROLES)) {
            return $user->teams()
                ->with('club')
                ->get()
                ->filter(fn (Team $team) => ! $team->club
                    || ! ClubPermissions::explicitlyDenies(
                        $team->club,
                        $user,
                        ClubPermissions::TRAINER_COCKPIT_VIEW,
                    ))
                ->pluck('id');
        }

        return $staffTeamIds
            ->merge($clubTeamIds)
            ->unique()
            ->values();
    }

    private function teamSummary(Team $team): array
    {
        $capabilities = $team->club ? $this->planFeatures->capabilities($team->club) : [];

        return [
            'id' => $team->id,
            'name' => $team->name,
            'sport_type' => $team->sport_type,
            'club' => $team->club ? [
                'id' => $team->club->id,
                'name' => $team->club->name,
                'trainer_cockpit_enabled' => (bool) ($capabilities['trainer_cockpit'] ?? false),
                'plan' => $team->club->subscriptionPlan()?->only(['name', 'slug']),
            ] : null,
            'stats' => [
                'athletes' => $team->users->count(),
                'events' => (int) $team->events_count,
                'plans' => (int) $team->training_plans_count,
            ],
            'athletes' => $team->users->map(fn (User $athlete) => [
                'id' => $athlete->id,
                'name' => $athlete->name,
                'profile_photo_url' => $athlete->profile_photo_url,
                'profile_photo_thumb' => $athlete->profile_photo_thumb,
                'role' => $athlete->pivot?->role,
            ])->values(),
        ];
    }

    private function upcomingEvents($teamIds)
    {
        if ($teamIds->isEmpty()) {
            return collect();
        }

        return Event::query()
            ->whereIn('team_id', $teamIds)
            ->where('status', 'scheduled')
            ->where('start_time', '>=', now())
            ->with('team:id,name')
            ->orderBy('start_time')
            ->limit(8)
            ->get(['id', 'team_id', 'title', 'type', 'start_time', 'end_time', 'location_name', 'location_city'])
            ->map(fn (Event $event) => [
                'id' => $event->id,
                'title' => $event->title,
                'type' => $event->type,
                'start_time' => $event->start_time,
                'end_time' => $event->end_time,
                'location' => trim(collect([$event->location_name, $event->location_city])->filter()->implode(', ')),
                'team' => $event->team ? ['id' => $event->team->id, 'name' => $event->team->name] : null,
            ])
            ->values();
    }

    private function plannedItems($teamIds)
    {
        if ($teamIds->isEmpty()) {
            return collect();
        }

        return TrainingPlanItem::query()
            ->whereHas('plan', fn ($query) => $query->whereIn('team_id', $teamIds))
            ->whereNotNull('scheduled_at')
            ->whereBetween('scheduled_at', [now()->startOfDay(), now()->addDays(14)->endOfDay()])
            ->with(['plan:id,title,team_id', 'plan.team:id,name'])
            ->withCount(['logs as completed_logs_count' => fn ($query) => $query->where('status', 'completed')])
            ->orderBy('scheduled_at')
            ->limit(12)
            ->get()
            ->map(fn (TrainingPlanItem $item) => $this->planItemSummary($item))
            ->values();
    }

    private function overdueItems($teamIds)
    {
        if ($teamIds->isEmpty()) {
            return collect();
        }

        return TrainingPlanItem::query()
            ->whereHas('plan', fn ($query) => $query->whereIn('team_id', $teamIds))
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<', now()->startOfDay())
            ->whereDoesntHave('logs', fn ($query) => $query->where('status', 'completed'))
            ->with(['plan:id,title,team_id', 'plan.team:id,name'])
            ->orderByDesc('scheduled_at')
            ->limit(8)
            ->get()
            ->map(fn (TrainingPlanItem $item) => $this->planItemSummary($item))
            ->values();
    }

    private function visibleTrainingLogs(User $user)
    {
        return $this->logAccess->visibleQuery($user);
    }

    private function feedbackOpen($teamIds, User $user)
    {
        $query = $this->visibleTrainingLogs($user)
            ->where('status', 'completed')
            ->where(function ($query) {
                $query->whereNull('performed_at')
                    ->orWhere('performed_at', '<=', now()->endOfDay());
            })
            ->where(function ($query) {
                $query->whereNull('trainer_feedback')->orWhere('trainer_feedback', '');
            });

        if ($teamIds->isNotEmpty()) {
            $query->whereIn('team_id', $teamIds);
        } else {
            $query->where('trainer_id', $user->id);
        }

        return $query
            ->with(['athlete:id,name,first_name,last_name,profile_photo_path', 'team:id,name'])
            ->latest('performed_at')
            ->limit(8)
            ->get()
            ->map(fn (TrainingLog $log) => $this->logSummary($log))
            ->values();
    }

    private function recentLogs($teamIds, User $user)
    {
        $query = $this->visibleTrainingLogs($user);

        if ($teamIds->isNotEmpty()) {
            $query->whereIn('team_id', $teamIds);
        } else {
            $query->where('trainer_id', $user->id);
        }

        return $query
            ->with(['athlete:id,name,first_name,last_name,profile_photo_path', 'team:id,name'])
            ->latest('performed_at')
            ->limit(8)
            ->get()
            ->map(fn (TrainingLog $log) => $this->logSummary($log))
            ->values();
    }

    private function plans($teamIds, User $user)
    {
        $query = TrainingPlan::query()
            ->with(['team:id,name'])
            ->withCount('items')
            ->latest('updated_at')
            ->limit(8);

        $query->where(function ($query) use ($teamIds, $user) {
            if ($teamIds->isNotEmpty()) {
                $query->whereIn('team_id', $teamIds);
            }

            $query->orWhere('created_by', $user->id);
        });

        return $query->get()
            ->map(fn (TrainingPlan $plan) => [
                'id' => $plan->id,
                'title' => $plan->title,
                'status' => $plan->status,
                'starts_on' => $plan->starts_on,
                'ends_on' => $plan->ends_on,
                'items_count' => (int) $plan->items_count,
                'team' => $plan->team ? ['id' => $plan->team->id, 'name' => $plan->team->name] : null,
            ])
            ->values();
    }

    private function coachWeekly($teamIds, $teams, $feedbackOpen, $overdueItems, User $user): array
    {
        if ($teamIds->isEmpty()) {
            return [
                'readiness_score' => 0,
                'risk_level' => 'empty',
                'current_week' => $this->trainingWindowStats(collect()),
                'previous_week' => $this->trainingWindowStats(collect()),
                'trend' => [
                    'sessions_percent' => 0,
                    'duration_percent' => 0,
                    'distance_percent' => 0,
                ],
                'team_cards' => [],
                'risk_athletes' => [],
                'actions' => [],
            ];
        }

        $currentStart = now()->startOfDay()->subDays(6);
        $previousStart = now()->startOfDay()->subDays(13);
        $previousEnd = now()->startOfDay()->subDays(7)->endOfDay();

        $logs = $this->visibleTrainingLogs($user)
            ->whereIn('team_id', $teamIds)
            ->whereNotNull('performed_at')
            ->where('performed_at', '>=', $previousStart)
            ->where('performed_at', '<=', now()->endOfDay())
            ->with(['athlete:id,name,first_name,last_name,profile_photo_path', 'team:id,name'])
            ->get();

        $current = $logs->filter(fn (TrainingLog $log) => $log->performed_at?->greaterThanOrEqualTo($currentStart))->values();
        $previous = $logs
            ->filter(fn (TrainingLog $log) => $log->performed_at?->betweenIncluded($previousStart, $previousEnd))
            ->values();

        $currentStats = $this->trainingWindowStats($current);
        $previousStats = $this->trainingWindowStats($previous);
        $teamCards = $teams
            ->map(fn (array $team) => $this->teamWeeklyCard($team, $current, $feedbackOpen, $overdueItems))
            ->values();
        $riskAthletes = $this->riskAthletes($current);
        $openFeedback = $feedbackOpen->count();
        $overdueCount = $overdueItems->count();
        $highLoadAthletes = $current
            ->groupBy('user_id')
            ->filter(fn ($athleteLogs) => $athleteLogs->where('intensity', 'high')->count() >= 2)
            ->count();

        $readinessScore = max(0, min(100, 86
            + min(8, (int) $currentStats['session_count'])
            - min(22, $openFeedback * 3)
            - min(22, $overdueCount * 4)
            - min(28, count($riskAthletes) * 7)
            - min(14, $highLoadAthletes * 4)));
        if ($current->isEmpty()) {
            $readinessScore = 0;
        }

        return [
            'readiness_score' => $readinessScore,
            'risk_level' => $current->isEmpty() ? 'empty' : $this->readinessRiskLevel($readinessScore),
            'current_week' => $currentStats,
            'previous_week' => $previousStats,
            'trend' => [
                'sessions_percent' => $this->trendPercent((float) $currentStats['session_count'], (float) $previousStats['session_count']),
                'duration_percent' => $this->trendPercent((float) $currentStats['duration_minutes'], (float) $previousStats['duration_minutes']),
                'distance_percent' => $this->trendPercent((float) $currentStats['distance_km'], (float) $previousStats['distance_km']),
            ],
            'team_cards' => $teamCards,
            'risk_athletes' => $riskAthletes,
            'actions' => $this->coachWeeklyActions($openFeedback, $overdueCount, count($riskAthletes), $currentStats),
        ];
    }

    private function teamWeeklyCard(array $team, $currentLogs, $feedbackOpen, $overdueItems): array
    {
        $teamLogs = $currentLogs->where('team_id', $team['id'])->values();
        $teamStats = $this->trainingWindowStats($teamLogs);
        $feedbackCount = $feedbackOpen->where('team.id', $team['id'])->count();
        $overdueCount = $overdueItems->where('plan.team.id', $team['id'])->count();
        $athleteCount = max(1, count($team['athletes']));
        $riskCount = count($this->riskAthletes($teamLogs));
        $score = max(0, min(100, 82
            + min(10, (int) $teamStats['session_count'])
            - min(24, $feedbackCount * 5)
            - min(24, $overdueCount * 6)
            - min(30, $riskCount * 10)));
        if ($teamLogs->isEmpty()) {
            $score = 0;
        }

        return [
            'team_id' => $team['id'],
            'name' => $team['name'],
            'sport_type' => $team['sport_type'] ?? null,
            'athletes' => $athleteCount,
            'readiness_score' => $score,
            'risk_level' => $teamLogs->isEmpty() ? 'empty' : $this->readinessRiskLevel($score),
            'sessions' => (int) $teamStats['session_count'],
            'feedback_open' => $feedbackCount,
            'overdue_items' => $overdueCount,
            'risk_athletes' => $riskCount,
        ];
    }

    private function trainingWindowStats($logs): array
    {
        $count = $logs->count();
        $distanceMeters = (int) $logs->sum(fn (TrainingLog $log) => (int) $log->distance_meters);
        $durationMinutes = (int) $logs->sum(fn (TrainingLog $log) => (int) $log->duration_minutes);
        $wellness = $logs
            ->map(fn (TrainingLog $log) => $log->metrics['wellness'] ?? [])
            ->filter(fn ($value) => is_array($value));

        return [
            'session_count' => $count,
            'duration_minutes' => $durationMinutes,
            'distance_km' => round($distanceMeters / 1000, 2),
            'average_rpe' => $this->averageMetric($wellness, 'rpe'),
            'average_energy' => $this->averageMetric($wellness, 'energy'),
            'average_pain' => $this->averageMetric($wellness, 'pain'),
            'high_intensity_count' => $logs->filter(fn (TrainingLog $log) => $this->isHighLoadLog($log))->count(),
        ];
    }

    private function riskAthletes($logs): array
    {
        return $logs
            ->groupBy('user_id')
            ->map(function ($athleteLogs) {
                $latest = $athleteLogs->sortByDesc('performed_at')->first();
                $wellness = $latest?->metrics['wellness'] ?? [];
                $highLoadCount = $athleteLogs->filter(fn (TrainingLog $log) => $this->isHighLoadLog($log))->count();
                $pain = (float) ($wellness['pain'] ?? 0);
                $rpe = (float) ($wellness['rpe'] ?? 0);

                if ($pain < 3 && $rpe < 8 && $highLoadCount < 2) {
                    return null;
                }

                return [
                    'id' => $latest->athlete?->id,
                    'name' => $latest->athlete?->name,
                    'team' => $latest->team ? ['id' => $latest->team->id, 'name' => $latest->team->name] : null,
                    'pain' => $pain,
                    'rpe' => $rpe,
                    'high_load_sessions' => $highLoadCount,
                    'latest_log_at' => $latest->performed_at,
                ];
            })
            ->filter()
            ->sortByDesc(fn (array $athlete) => ($athlete['pain'] * 10) + $athlete['rpe'] + ($athlete['high_load_sessions'] * 4))
            ->take(6)
            ->values()
            ->all();
    }

    private function coachWeeklyActions(int $openFeedback, int $overdueCount, int $riskAthletes, array $currentStats): array
    {
        $actions = [];

        if ($riskAthletes > 0) {
            $actions[] = ['key' => 'checkRiskAthletes', 'tone' => 'danger', 'count' => $riskAthletes];
        }

        if ($openFeedback > 0) {
            $actions[] = ['key' => 'answerFeedback', 'tone' => 'warning', 'count' => $openFeedback, 'href' => '#feedback'];
        }

        if ($overdueCount > 0) {
            $actions[] = ['key' => 'rescheduleOverdue', 'tone' => 'warning', 'count' => $overdueCount, 'href' => '#planning'];
        }

        if ((int) $currentStats['session_count'] === 0) {
            $actions[] = ['key' => 'planFirstSession', 'tone' => 'info', 'count' => 0, 'href' => route('auth.training.index')];
        }

        if ($actions === []) {
            $actions[] = ['key' => 'keepRhythm', 'tone' => 'success', 'count' => (int) $currentStats['session_count']];
        }

        return $actions;
    }

    private function readinessRiskLevel(int $score): string
    {
        return $score >= 78 ? 'good' : ($score >= 56 ? 'watch' : 'risk');
    }

    private function isHighLoadLog(TrainingLog $log): bool
    {
        $rpe = (float) ($log->metrics['wellness']['rpe'] ?? 0);
        $intensity = strtolower((string) $log->intensity);

        return $rpe >= 8 || in_array($intensity, ['high', 'hard', 'hoch', 'intensiv'], true);
    }

    private function averageMetric($wellness, string $key): ?float
    {
        $values = $wellness
            ->map(fn (array $metrics) => $metrics[$key] ?? null)
            ->filter(fn ($value) => is_numeric($value))
            ->values();

        return $values->isEmpty() ? null : round((float) $values->avg(), 1);
    }

    private function trendPercent(float $current, float $previous): float
    {
        if ($previous <= 0.0) {
            return $current > 0.0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    private function planItemSummary(TrainingPlanItem $item): array
    {
        return [
            'id' => $item->id,
            'title' => $item->title,
            'sport_type' => $item->sport_type,
            'scheduled_at' => $item->scheduled_at,
            'duration_minutes' => $item->duration_minutes,
            'distance_meters' => $item->distance_meters,
            'intensity' => $item->intensity,
            'completed_logs_count' => (int) ($item->completed_logs_count ?? 0),
            'plan' => $item->plan ? [
                'id' => $item->plan->id,
                'title' => $item->plan->title,
                'team' => $item->plan->team ? ['id' => $item->plan->team->id, 'name' => $item->plan->team->name] : null,
            ] : null,
            'href' => route('auth.training.plans.items.show', [$item->training_plan_id, $item->id]),
        ];
    }

    private function logSummary(TrainingLog $log): array
    {
        return [
            'id' => $log->id,
            'title' => $log->title,
            'status' => $log->status,
            'sport_type' => $log->sport_type,
            'performed_at' => $log->performed_at,
            'duration_minutes' => $log->duration_minutes,
            'distance_meters' => $log->distance_meters,
            'intensity' => $log->intensity,
            'athlete' => $log->athlete ? [
                'id' => $log->athlete->id,
                'name' => $log->athlete->name,
                'profile_photo_url' => $log->athlete->profile_photo_url,
                'profile_photo_thumb' => $log->athlete->profile_photo_thumb,
            ] : null,
            'team' => $log->team ? ['id' => $log->team->id, 'name' => $log->team->name] : null,
            'href' => route('auth.training.logs.show', $log),
        ];
    }
}
