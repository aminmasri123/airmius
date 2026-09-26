<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\File;
use App\Models\Notification as AppNotification;
use App\Models\NutritionGoal;
use App\Models\NutritionMeal;
use App\Models\SportPlace;
use App\Models\SportRoute;
use App\Models\SportRouteTrack;
use App\Models\TrainingLog;
use App\Models\TrainingPlan;
use App\Models\TrainingPlanItem;
use App\Models\User;
use App\Services\AthleteDailyFlowService;
use App\Services\DashboardAttentionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class DashboardController extends Controller
{
    private const WIDGET_KEYS = [
        'daily_flow',
        'training',
        'focus',
        'nutrition',
        'events',
        'sport_map',
        'files',
        'notifications',
    ];

    private const QUICK_ACTION_KEYS = [
        'training_log',
        'route',
        'nutrition',
        'training_plan',
        'events',
        'teams',
        'files',
        'feed',
        'notifications',
    ];

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $teamIds = $user->teams()->pluck('teams.id');
        $now = now();
        $weekStart = $now->copy()->startOfWeek();
        $weekEnd = $now->copy()->endOfWeek();
        $previousWeekStart = $weekStart->copy()->subWeek();
        $previousWeekEnd = $weekStart->copy()->subSecond();
        $visiblePlanIds = $this->visibleTrainingPlansQuery($user, $teamIds)->pluck('training_plans.id');

        $trainingLogs = TrainingLog::query()
            ->with(['plan:id,title', 'planItem:id,title,training_plan_id,scheduled_at'])
            ->where('user_id', $user->id)
            ->where('status', '!=', 'draft')
            ->where('performed_at', '>=', $now->copy()->subDays(60))
            ->latest('performed_at')
            ->limit(120)
            ->get();

        $weekLogs = $trainingLogs->filter(fn (TrainingLog $log) => $log->performed_at && $log->performed_at->greaterThanOrEqualTo($weekStart));
        $previousWeekLogs = $trainingLogs->filter(fn (TrainingLog $log) => $log->performed_at && $log->performed_at->betweenIncluded($previousWeekStart, $previousWeekEnd));
        $planItems = $visiblePlanIds->isEmpty()
            ? collect()
            : TrainingPlanItem::query()
                ->with('plan:id,title')
                ->whereIn('training_plan_id', $visiblePlanIds)
                ->whereNotNull('scheduled_at')
                ->whereBetween('scheduled_at', [$weekStart, $now->copy()->addDays(14)->endOfDay()])
                ->orderBy('scheduled_at')
                ->limit(30)
                ->get();
        $weekItems = $planItems
            ->filter(fn (TrainingPlanItem $item) => $item->scheduled_at?->betweenIncluded($weekStart, $weekEnd))
            ->values();
        $upcomingItems = $planItems
            ->filter(fn (TrainingPlanItem $item) => $item->scheduled_at?->greaterThanOrEqualTo($now->copy()->startOfDay()))
            ->take(5)
            ->values();
        $upcomingEvents = $this->visibleEventsQuery($user)
            ->where('start_time', '>=', $now->copy()->startOfDay())
            ->where('status', '!=', 'cancelled')
            ->orderBy('start_time')
            ->limit(5)
            ->get(['id', 'title', 'type', 'start_time', 'location_name', 'location', 'team_id']);
        $latestNotifications = $user->appNotifications()
            ->latest()
            ->limit(4)
            ->get(['id', 'type', 'data', 'read', 'created_at']);
        $nutritionMeals = $this->nutritionMeals($user);
        $nutritionGoal = NutritionGoal::query()->where('user_id', $user->id)->first();
        $lastRoute = SportRoute::visibleTo($user)
            ->latest('updated_at')
            ->first(['id', 'title', 'sport_type', 'distance_meters', 'updated_at']);
        $fileModel = File::query()
            ->where('user_id', $user->id)
            ->selectRaw('COUNT(*) as files_count, COALESCE(SUM(size), 0) as files_bytes')
            ->first();
        $fileStats = [
            'count' => (int) ($fileModel?->files_count ?? 0),
            'bytes' => (int) ($fileModel?->files_bytes ?? 0),
        ];

        $dailyFlow = app(AthleteDailyFlowService::class)
            ->fromDashboardData($user, $weekLogs, $upcomingItems, $upcomingEvents, $latestNotifications, $weekItems, [
                'nutrition_meals' => $nutritionMeals,
                'nutrition_goal' => $nutritionGoal,
                'last_route' => $lastRoute,
                'files' => $fileStats,
            ]);
        unset($dailyFlow['mobile_context'], $dailyFlow['files']);

        return Inertia::render('Auth/Dashboard/Index', [
            'dashboard' => [
                'profile' => [
                    'name' => $this->displayName($user),
                    'today' => $now->toDateString(),
                ],
                'training' => [
                    'total_logs' => TrainingLog::query()
                        ->where('user_id', $user->id)
                        ->where('status', '!=', 'draft')
                        ->count(),
                    'week_count' => $weekLogs->count(),
                    'previous_week_count' => $previousWeekLogs->count(),
                    'week_minutes' => (int) $weekLogs->sum('duration_minutes'),
                    'week_distance_meters' => (int) $weekLogs->sum('distance_meters'),
                    'week_calories' => (int) $weekLogs->sum('calories'),
                    'active_days' => $weekLogs
                        ->filter(fn (TrainingLog $log) => $log->performed_at)
                        ->map(fn (TrainingLog $log) => $log->performed_at->toDateString())
                        ->unique()
                        ->values()
                        ->count(),
                    'streak_days' => $this->trainingStreak($trainingLogs),
                    'activity_score' => $this->activityScore($weekLogs),
                    'trend_percent' => $this->trendPercent($weekLogs->count(), $previousWeekLogs->count()),
                    'chart' => $this->trainingChart($trainingLogs),
                    'latest' => $trainingLogs
                        ->take(4)
                        ->map(fn (TrainingLog $log) => [
                            'id' => $log->id,
                            'title' => $log->title,
                            'status' => $log->status,
                            'sport_type' => $log->sport_type,
                            'performed_at' => $log->performed_at?->toIso8601String(),
                            'duration_minutes' => $log->duration_minutes,
                            'distance_meters' => $log->distance_meters,
                        ])
                        ->values(),
                    'upcoming_items' => $upcomingItems
                        ->map(fn (TrainingPlanItem $item) => [
                            'id' => $item->id,
                            'plan_id' => $item->training_plan_id,
                            'plan_title' => $item->plan?->title,
                            'title' => $item->title,
                            'sport_type' => $item->sport_type,
                            'scheduled_at' => $item->scheduled_at?->toIso8601String(),
                            'duration_minutes' => $item->duration_minutes,
                            'distance_meters' => $item->distance_meters,
                        ])
                        ->values(),
                    'plan_count' => $visiblePlanIds->count(),
                ],
                'events' => [
                    'upcoming_count' => $this->visibleEventsQuery($user)
                        ->where('start_time', '>=', $now->copy()->startOfDay())
                        ->where('status', '!=', 'cancelled')
                        ->count(),
                    'today_count' => $this->visibleEventsQuery($user)
                        ->whereBetween('start_time', [$now->copy()->startOfDay(), $now->copy()->endOfDay()])
                        ->where('status', '!=', 'cancelled')
                        ->count(),
                    'next' => $upcomingEvents
                        ->map(fn (Event $event) => [
                            'id' => $event->id,
                            'title' => $event->title,
                            'type' => $event->type,
                            'start_time' => $event->start_time?->toIso8601String(),
                            'location' => $event->location_name ?: $event->location,
                        ])
                        ->values(),
                ],
                'nutrition' => $this->nutritionSummary($nutritionMeals),
                'sport_map' => [
                    'routes_count' => SportRoute::visibleTo($user)->count(),
                    'tracks_count' => SportRouteTrack::visibleTo($user)->count(),
                    'places_count' => SportPlace::visibleTo($user)->count(),
                    'week_track_distance_meters' => (int) SportRouteTrack::visibleTo($user)
                        ->where('started_at', '>=', $weekStart)
                        ->sum('distance_meters'),
                    'last_route' => $lastRoute,
                ],
                'files' => $fileStats,
                'notifications' => [
                    'unread_count' => AppNotification::query()
                        ->where('user_id', $user->id)
                        ->where('read', false)
                        ->count(),
                    'latest' => $latestNotifications
                        ->map(fn (AppNotification $notification) => [
                            'id' => $notification->id,
                            'type' => $notification->type,
                            'title' => $notification->data['title'] ?? $notification->data['headline'] ?? 'Benachrichtigung',
                            'body' => $notification->data['body'] ?? $notification->data['message'] ?? '',
                            'read' => $notification->read,
                            'created_at' => $notification->created_at?->toIso8601String(),
                        ])
                        ->values(),
                ],
                'daily_flow' => $dailyFlow,
                'attention' => app(DashboardAttentionService::class)->forUser($user),
                'focus' => $this->focusItems($weekLogs, $upcomingItems, $upcomingEvents, $latestNotifications),
                'preferences' => [
                    'widgets' => $user->dashboard_widget_keys,
                    'quick_actions' => $user->dashboard_quick_action_keys,
                ],
            ],
        ]);
    }

    public function updatePreferences(Request $request)
    {
        $data = $request->validate([
            'widget_keys' => ['sometimes', 'array', 'max:'.count(self::WIDGET_KEYS), 'required_without:quick_action_keys'],
            'widget_keys.*' => ['string', Rule::in(self::WIDGET_KEYS)],
            'quick_action_keys' => ['sometimes', 'array', 'max:4', 'required_without:widget_keys'],
            'quick_action_keys.*' => ['string', Rule::in(self::QUICK_ACTION_KEYS)],
        ]);

        $updates = [];
        if (array_key_exists('widget_keys', $data) && Schema::hasColumn('users', 'dashboard_widget_keys')) {
            $updates['dashboard_widget_keys'] = collect($data['widget_keys'])->unique()->values()->all();
        }
        if (array_key_exists('quick_action_keys', $data) && Schema::hasColumn('users', 'dashboard_quick_action_keys')) {
            $updates['dashboard_quick_action_keys'] = collect($data['quick_action_keys'])->unique()->values()->all();
        }
        if ($updates !== []) {
            $request->user()->forceFill($updates)->save();
        }

        return response()->noContent();
    }

    public function maturity()
    {
        return Inertia::render('Auth/Dashboard/Maturity/Index');
    }

    private function visibleTrainingPlansQuery(User $user, Collection $teamIds): Builder
    {
        return TrainingPlan::query()
            ->where(function (Builder $query) use ($user, $teamIds) {
                $query
                    ->where('created_by', $user->id)
                    ->orWhereHas('assignments', function (Builder $assignmentQuery) use ($user, $teamIds) {
                        $assignmentQuery->where('user_id', $user->id);

                        if ($teamIds->isNotEmpty()) {
                            $assignmentQuery->orWhereIn('team_id', $teamIds);
                        }
                    });
            });
    }

    private function visibleEventsQuery(User $user): Builder
    {
        return Event::query()->visibleTo($user);
    }

    private function trainingChart(Collection $logs): array
    {
        return collect(range(6, 0))
            ->map(function (int $offset) use ($logs) {
                $day = now()->copy()->subDays($offset)->startOfDay();
                $dayLogs = $logs->filter(fn (TrainingLog $log) => $log->performed_at && $log->performed_at->isSameDay($day));

                return [
                    'date' => $day->toDateString(),
                    'label' => $day->format('d.m.'),
                    'count' => $dayLogs->count(),
                    'minutes' => (int) $dayLogs->sum('duration_minutes'),
                ];
            })
            ->values()
            ->all();
    }

    private function trainingStreak(Collection $logs): int
    {
        $dates = $logs
            ->filter(fn (TrainingLog $log) => $log->status === 'completed' && $log->performed_at)
            ->map(fn (TrainingLog $log) => $log->performed_at->toDateString())
            ->unique()
            ->flip();

        if ($dates->isEmpty()) {
            return 0;
        }

        $cursor = now()->copy()->startOfDay();
        if (! $dates->has($cursor->toDateString())) {
            $cursor->subDay();
        }

        $streak = 0;
        while ($dates->has($cursor->toDateString())) {
            $streak++;
            $cursor->subDay();
        }

        return $streak;
    }

    private function activityScore(Collection $weekLogs): int
    {
        $completedCount = $weekLogs->where('status', 'completed')->count();
        $activeDays = $weekLogs
            ->filter(fn (TrainingLog $log) => $log->performed_at)
            ->map(fn (TrainingLog $log) => $log->performed_at->toDateString())
            ->unique()
            ->count();
        $minutes = (int) $weekLogs->sum('duration_minutes');

        return min(100, (int) round(
            min(45, $completedCount * 15)
            + min(30, ($minutes / 240) * 30)
            + min(25, $activeDays * 5)
        ));
    }

    private function trendPercent(int $current, int $previous): int
    {
        if ($previous === 0) {
            return $current > 0 ? 100 : 0;
        }

        return (int) round((($current - $previous) / $previous) * 100);
    }

    private function nutritionMeals(User $user): Collection
    {
        $weekStart = now()->copy()->subDays(6)->startOfDay();
        $todayEnd = now()->copy()->endOfDay();

        return NutritionMeal::query()
            ->where('user_id', $user->id)
            ->whereBetween('eaten_on', [$weekStart, $todayEnd])
            ->latest('eaten_on')
            ->limit(80)
            ->get();
    }

    private function nutritionSummary(Collection $meals): array
    {
        $today = now()->toDateString();
        $todayMeals = $meals->filter(fn (NutritionMeal $meal) => $meal->eaten_on?->toDateString() === $today);

        return [
            'today_calories' => (int) $todayMeals->sum('calories'),
            'today_protein_g' => (float) $todayMeals->sum('protein_g'),
            'today_water_ml' => (int) $todayMeals->sum('water_ml'),
            'week_calories' => (int) $meals->sum('calories'),
            'meals_today' => $todayMeals->count(),
            'chart' => collect(range(6, 0))
                ->map(function (int $offset) use ($meals) {
                    $day = now()->copy()->subDays($offset)->toDateString();
                    $dayMeals = $meals->filter(fn (NutritionMeal $meal) => $meal->eaten_on?->toDateString() === $day);

                    return [
                        'date' => $day,
                        'label' => now()->copy()->subDays($offset)->format('d.m.'),
                        'calories' => (int) $dayMeals->sum('calories'),
                    ];
                })
                ->values(),
        ];
    }

    private function focusItems(Collection $weekLogs, Collection $upcomingItems, Collection $upcomingEvents, Collection $notifications): array
    {
        $items = [];
        $nextItem = $upcomingItems->first();
        $nextEvent = $upcomingEvents->first();
        $unreadCount = $notifications->where('read', false)->count();

        if ($nextItem) {
            $items[] = [
                'title' => __('platform.dashboard.next_training_title'),
                'body' => $nextItem->title,
                'meta' => $nextItem->scheduled_at?->format('d.m. H:i'),
                'href' => route('auth.training.logs.create', ['plan_item_id' => $nextItem->id]),
                'cta' => 'Dokumentieren',
                'icon' => 'las la-clipboard-check',
            ];
        }

        if ($nextEvent) {
            $items[] = [
                'title' => __('platform.dashboard.next_event_title'),
                'body' => $nextEvent->title,
                'meta' => $nextEvent->start_time?->format('d.m. H:i'),
                'href' => route('auth.events.index'),
                'cta' => 'Kalender öffnen',
                'icon' => 'las la-calendar-check',
            ];
        }

        if ($unreadCount > 0) {
            $items[] = [
                'title' => 'Benachrichtigungen prüfen',
                'body' => $unreadCount.' ungelesen',
                'meta' => 'Inbox',
                'href' => route('auth.notifications.index'),
                'cta' => 'Öffnen',
                'icon' => 'las la-bell',
            ];
        }

        if ($weekLogs->isEmpty()) {
            $items[] = [
                'title' => 'Erstes Training der Woche',
                'body' => 'Noch kein Training diese Woche dokumentiert.',
                'meta' => 'Schnellstart',
                'href' => route('auth.training.logs.create'),
                'cta' => 'Jetzt starten',
                'icon' => 'las la-running',
            ];
        }

        return array_slice($items, 0, 4);
    }

    private function displayName(User $user): string
    {
        return trim((string) ($user->first_name ?: $user->name ?: $user->email));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
