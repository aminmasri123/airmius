<?php

namespace App\Services;

use App\Models\Event;
use App\Models\File;
use App\Models\NutritionGoal;
use App\Models\NutritionMeal;
use App\Models\SportRoute;
use App\Models\TrainingLog;
use App\Models\TrainingPlan;
use App\Models\TrainingPlanItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AthleteDailyFlowService
{
    public function forUser(User $user): array
    {
        $teamIds = $user->teams()->pluck('teams.id');
        $now = now();
        $weekStart = $now->copy()->startOfWeek();
        $weekEnd = $now->copy()->endOfWeek();
        $visiblePlanIds = $this->visibleTrainingPlansQuery($user, $teamIds)->pluck('training_plans.id');
        $trainingLogs = TrainingLog::query()
            ->where('user_id', $user->id)
            ->where('status', '!=', 'draft')
            ->where('performed_at', '>=', $now->copy()->subDays(60))
            ->latest('performed_at')
            ->limit(120)
            ->get();
        $weekLogs = $trainingLogs->filter(fn (TrainingLog $log) => $log->performed_at && $log->performed_at->greaterThanOrEqualTo($weekStart));
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
        $upcomingEvents = $this->visibleEventsQuery($user, $teamIds)
            ->where('start_time', '>=', $now->copy()->startOfDay())
            ->where('status', '!=', 'cancelled')
            ->orderBy('start_time')
            ->limit(5)
            ->get(['id', 'title', 'type', 'start_time', 'location_name', 'location', 'team_id']);
        $latestNotifications = $user->appNotifications()
            ->latest()
            ->limit(4)
            ->get(['id', 'type', 'data', 'read', 'created_at']);

        return $this->fromDashboardData($user, $weekLogs, $upcomingItems, $upcomingEvents, $latestNotifications, $weekItems);
    }

    public function fromDashboardData(
        User $user,
        Collection $weekLogs,
        Collection $upcomingItems,
        Collection $upcomingEvents,
        Collection $notifications,
        ?Collection $weekItems = null,
        array $prefetched = [],
    ): array {
        $todayStart = now()->copy()->startOfDay();
        $todayEnd = now()->copy()->endOfDay();
        $todayLogs = $weekLogs->filter(fn (TrainingLog $log) => $log->performed_at && $log->performed_at->betweenIncluded($todayStart, $todayEnd));
        $todayTrainingMinutes = (int) $todayLogs->sum('duration_minutes');
        $todayTrainingDistance = (int) $todayLogs->sum('distance_meters');
        $nutrition = $this->nutritionSummary($user, $todayLogs, $prefetched);
        $nextItem = $upcomingItems->first();
        $nextEvent = $upcomingEvents->first();
        $lastRoute = array_key_exists('last_route', $prefetched)
            ? $prefetched['last_route']
            : SportRoute::visibleTo($user)
                ->latest('updated_at')
                ->first(['id', 'title', 'sport_type', 'distance_meters', 'updated_at']);
        $fileAggregate = $prefetched['files'] ?? null;
        if (! is_array($fileAggregate)) {
            $fileModel = File::query()
                ->where('user_id', $user->id)
                ->selectRaw('COUNT(*) as files_count, COALESCE(SUM(size), 0) as files_bytes')
                ->first();
            $fileAggregate = [
                'count' => (int) ($fileModel?->files_count ?? 0),
                'bytes' => (int) ($fileModel?->files_bytes ?? 0),
            ];
        }
        $fileCount = (int) ($fileAggregate['count'] ?? 0);
        $fileBytes = (int) ($fileAggregate['bytes'] ?? 0);
        $unreadCount = $notifications->where('read', false)->count();
        $waterGoal = (int) $nutrition['water_goal_ml'];
        $calorieGoal = (int) $nutrition['calorie_goal'];
        $todayPlannedItem = $upcomingItems->first(fn (TrainingPlanItem $item) => $item->scheduled_at?->isToday());
        $trainingGoalMinutes = max(20, (int) ($todayPlannedItem?->duration_minutes ?: 45));
        $progress = [
            'training' => min(100, (int) round(($todayTrainingMinutes / $trainingGoalMinutes) * 100)),
            'nutrition' => min(100, (int) round(((int) $nutrition['today_calories'] / $calorieGoal) * 100)),
            'hydration' => min(100, (int) round(((int) $nutrition['today_water_ml'] / $waterGoal) * 100)),
        ];
        $week = $this->weeklyContinuity($weekLogs, $weekItems ?? collect());
        $primaryAction = $this->primaryAction(
            $weekItems ?? collect(),
            $weekLogs,
            $nextEvent,
            $nutrition,
            $progress,
        );

        return [
            'score' => (int) round(($progress['training'] + $progress['nutrition'] + $progress['hydration']) / 3),
            'summary' => $this->dailyFlowSummary($todayTrainingMinutes, (int) $nutrition['today_calories'], (int) $nutrition['today_water_ml'], $unreadCount),
            'coach_note' => $this->dailyCoachNote($todayTrainingMinutes, (int) $nutrition['today_calories'], (int) $nutrition['today_water_ml'], $nextItem, $nextEvent),
            'files' => [
                'count' => $fileCount,
                'bytes' => $fileBytes,
            ],
            'goals' => [
                'training_minutes' => $trainingGoalMinutes,
                'calories' => $calorieGoal,
                'water_ml' => $waterGoal,
                'water_mode' => $nutrition['water_mode'],
            ],
            'week' => $week,
            'primary_action' => $primaryAction,
            'mobile_context' => $this->mobileContext($nextItem, $nextEvent),
            'steps' => [
                [
                    'key' => 'training',
                    'title' => __('athlete_today.steps.training.title'),
                    'body' => $nextItem
                        ? $nextItem->title
                        : ($todayLogs->isNotEmpty() ? __('athlete_today.steps.training.documented') : __('athlete_today.steps.training.plan')),
                    'meta' => $nextItem?->scheduled_at?->format('d.m. H:i') ?: ($todayTrainingMinutes > 0
                        ? __('athlete_today.steps.training.completed', ['minutes' => $todayTrainingMinutes, 'distance' => $this->distanceLabel($todayTrainingDistance)])
                        : __('athlete_today.steps.training.goal', ['minutes' => $trainingGoalMinutes])),
                    'progress' => $progress['training'],
                    'href' => $nextItem ? route('auth.training.logs.create', ['plan_item_id' => $nextItem->id]) : route('auth.training.logs.create'),
                    'cta' => $nextItem ? __('athlete_today.steps.training.document') : __('athlete_today.steps.training.start'),
                    'icon' => 'las la-running',
                ],
                [
                    'key' => 'route',
                    'title' => __('athlete_today.steps.route.title'),
                    'body' => $lastRoute?->title ?: __('athlete_today.steps.route.plan'),
                    'meta' => $lastRoute ? $this->distanceLabel((int) $lastRoute->distance_meters) : __('athlete_today.steps.route.meta'),
                    'progress' => $lastRoute ? 100 : 0,
                    'href' => route('auth.sport-map.index'),
                    'cta' => $lastRoute ? __('athlete_today.steps.route.open') : __('athlete_today.steps.route.create'),
                    'icon' => 'las la-route',
                ],
                [
                    'key' => 'nutrition',
                    'title' => __('athlete_today.steps.nutrition.title'),
                    'body' => __('athlete_today.steps.nutrition.meals', ['count' => (int) $nutrition['meals_today']]),
                    'meta' => __('athlete_today.steps.nutrition.meta', ['current' => (int) $nutrition['today_calories'], 'target' => $calorieGoal]),
                    'progress' => $progress['nutrition'],
                    'href' => route('auth.nutrition.index'),
                    'cta' => __('athlete_today.steps.nutrition.cta'),
                    'icon' => 'las la-utensils',
                ],
                [
                    'key' => 'hydration',
                    'title' => __('athlete_today.steps.hydration.title'),
                    'body' => __('athlete_today.steps.hydration.body', ['current' => (int) $nutrition['today_water_ml']]),
                    'meta' => __('athlete_today.steps.hydration.meta', ['target' => $waterGoal]),
                    'progress' => $progress['hydration'],
                    'href' => route('auth.nutrition.index'),
                    'cta' => __('athlete_today.steps.hydration.cta'),
                    'icon' => 'las la-tint',
                ],
                [
                    'key' => 'reminders',
                    'title' => __('athlete_today.steps.reminders.title'),
                    'body' => $nextEvent ? $nextEvent->title : ($unreadCount > 0
                        ? __('athlete_today.steps.reminders.unread', ['count' => $unreadCount])
                        : __('athlete_today.steps.reminders.none')),
                    'meta' => $nextEvent?->start_time?->format('d.m. H:i') ?: __('athlete_today.steps.reminders.meta'),
                    'progress' => ($nextEvent || $unreadCount > 0) ? 0 : 100,
                    'href' => $nextEvent ? route('auth.events.index') : route('auth.notifications.index'),
                    'cta' => $nextEvent ? __('athlete_today.steps.reminders.event_cta') : __('athlete_today.steps.reminders.inbox_cta'),
                    'icon' => 'las la-bell',
                ],
            ],
        ];
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

    private function visibleEventsQuery(User $user, Collection $teamIds): Builder
    {
        return Event::query()
            ->where(function (Builder $query) use ($user, $teamIds) {
                $query
                    ->where('user_id', $user->id)
                    ->orWhereHas('participants', fn (Builder $participantQuery) => $participantQuery->where('users.id', $user->id));

                if ($teamIds->isNotEmpty()) {
                    $query->orWhereIn('team_id', $teamIds);
                }
            });
    }

    private function mobileContext(?TrainingPlanItem $nextItem, ?Event $nextEvent): array
    {
        return [
            'shell' => [
                'navigation' => 'bottom_tabs',
                'safe_area_required' => true,
                'primary_tab' => 'today',
                'primary_action' => 'start_training',
            ],
            'quick_actions' => [
                [
                    'key' => 'start_training',
                    'label_key' => 'mobile.today.actions.start_training',
                    'href' => $nextItem ? route('auth.training.logs.create', ['plan_item_id' => $nextItem->id]) : route('auth.training.logs.create'),
                    'api_target' => '/api/v1/training/logs',
                    'deep_link' => 'airmius://training/logs/create',
                    'offline_mode' => 'queue_write',
                    'required_permissions' => [],
                ],
                [
                    'key' => 'scan_meal',
                    'label_key' => 'mobile.today.actions.scan_meal',
                    'href' => route('auth.nutrition.index'),
                    'api_target' => '/api/v1/nutrition/ai/meal-image',
                    'deep_link' => 'airmius://nutrition/scan-meal',
                    'offline_mode' => 'requires_network',
                    'required_permissions' => ['camera'],
                ],
                [
                    'key' => 'start_tracking',
                    'label_key' => 'mobile.today.actions.start_tracking',
                    'href' => route('auth.sport-map.index'),
                    'api_target' => '/api/v1/sport-tracks/start',
                    'deep_link' => 'airmius://sport-map/track/start',
                    'offline_mode' => 'queue_points',
                    'required_permissions' => ['location_foreground', 'location_background'],
                ],
                [
                    'key' => 'open_next_event',
                    'label_key' => 'mobile.today.actions.open_next_event',
                    'href' => $nextEvent ? route('auth.events.index') : route('auth.dashboard'),
                    'api_target' => $nextEvent ? "/api/v1/events/{$nextEvent->id}" : '/api/v1/events',
                    'deep_link' => $nextEvent ? "airmius://events/{$nextEvent->id}" : 'airmius://events',
                    'offline_mode' => 'read_cache',
                    'required_permissions' => ['notifications'],
                ],
            ],
            'offline_status' => [
                'read_strategy' => 'network_first_with_cache',
                'write_strategy' => 'idempotent_retry_queue',
                'retry_header' => 'Idempotency-Key',
                'sync_endpoint' => '/api/v1/mobile/sync',
                'cache_domains' => ['daily_flow', 'events', 'training', 'nutrition', 'sport_map'],
            ],
            'permission_prompts' => [
                [
                    'key' => 'notifications',
                    'reason_key' => 'mobile.permissions.notifications.reason',
                    'api_target' => '/api/v1/mobile/push-devices',
                    'deep_link' => 'airmius://settings/notifications',
                ],
                [
                    'key' => 'camera',
                    'reason_key' => 'mobile.permissions.camera.reason',
                    'api_target' => '/api/v1/nutrition/ai/meal-image',
                    'deep_link' => 'airmius://nutrition/scan-meal',
                ],
                [
                    'key' => 'location_background',
                    'reason_key' => 'mobile.permissions.location_background.reason',
                    'api_target' => '/api/v1/sport-tracks/start',
                    'deep_link' => 'airmius://sport-map/track/start',
                ],
            ],
        ];
    }

    private function nutritionSummary(User $user, Collection $todayLogs, array $prefetched = []): array
    {
        $today = now()->toDateString();
        $weekStart = now()->copy()->subDays(6)->startOfDay();
        $todayEnd = now()->copy()->endOfDay();
        $meals = $prefetched['nutrition_meals'] ?? NutritionMeal::query()
            ->where('user_id', $user->id)
            ->whereBetween('eaten_on', [$weekStart, $todayEnd])
            ->latest('eaten_on')
            ->limit(80)
            ->get();
        $todayMeals = $meals->filter(fn (NutritionMeal $meal) => $meal->eaten_on?->toDateString() === $today);
        $goal = array_key_exists('nutrition_goal', $prefetched)
            ? $prefetched['nutrition_goal']
            : NutritionGoal::query()->where('user_id', $user->id)->first();
        $calorieGoal = max(800, (int) ($goal?->daily_calories_target ?: 2200));
        $waterMode = $goal?->water_target_mode ?: 'manual';
        $manualWaterGoal = max(1000, (int) ($goal?->water_target_ml ?: 2500));
        $baseWaterGoal = $goal?->body_weight_kg
            ? $this->roundWater((float) $goal->body_weight_kg * 33)
            : 2500;
        $trainingWaterExtra = min(2000, (int) $todayLogs->sum(fn (TrainingLog $log) => $this->trainingWaterExtra($log)));
        $waterGoal = $waterMode === 'auto'
            ? min(10000, max(1000, $baseWaterGoal + $trainingWaterExtra))
            : min(10000, $manualWaterGoal);

        return [
            'today_calories' => (int) $todayMeals->sum('calories'),
            'today_water_ml' => (int) $todayMeals->sum('water_ml'),
            'meals_today' => $todayMeals->count(),
            'week_calories' => (int) $meals->sum('calories'),
            'calorie_goal' => $calorieGoal,
            'water_goal_ml' => $waterGoal,
            'water_mode' => $waterMode,
        ];
    }

    private function dailyFlowSummary(int $trainingMinutes, int $calories, int $waterMl, int $unreadCount): string
    {
        if ($trainingMinutes === 0 && $calories === 0 && $waterMl === 0) {
            return __('athlete_today.summary.empty');
        }

        $parts = [];

        if ($trainingMinutes > 0) {
            $parts[] = __('athlete_today.summary.training', ['minutes' => $trainingMinutes]);
        }

        if ($calories > 0) {
            $parts[] = __('athlete_today.summary.calories', ['calories' => $calories]);
        }

        if ($waterMl > 0) {
            $parts[] = __('athlete_today.summary.water', ['water' => $waterMl]);
        }

        if ($unreadCount > 0) {
            $parts[] = __('athlete_today.summary.notifications', ['count' => $unreadCount]);
        }

        return implode(' · ', $parts);
    }

    private function dailyCoachNote(int $trainingMinutes, int $calories, int $waterMl, ?TrainingPlanItem $nextItem, ?Event $nextEvent): string
    {
        if ($nextItem) {
            return __('athlete_today.coach.next_training');
        }

        if ($nextEvent) {
            return __('athlete_today.coach.event');
        }

        if ($trainingMinutes === 0) {
            return __('athlete_today.coach.start');
        }

        if ($waterMl < 1500) {
            return __('athlete_today.coach.water');
        }

        if ($calories === 0) {
            return __('athlete_today.coach.meal');
        }

        return __('athlete_today.coach.complete');
    }

    private function weeklyContinuity(Collection $weekLogs, Collection $weekItems): array
    {
        $weekStart = now()->copy()->startOfWeek();
        $completedLogs = $weekLogs->where('status', 'completed');
        $completedItemIds = $completedLogs
            ->pluck('training_plan_item_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique();
        $dueItems = $weekItems
            ->filter(fn (TrainingPlanItem $item) => $item->scheduled_at?->lessThanOrEqualTo(now()));
        $completedDue = $dueItems->whereIn('id', $completedItemIds)->count();
        $plannedMinutes = (int) $weekItems->sum('duration_minutes');
        $completedMinutes = (int) $completedLogs->sum('duration_minutes');
        $targetMinutes = max(1, $plannedMinutes > 0 ? $plannedMinutes : 150);
        $progressPercent = min(100, (int) round(($completedMinutes / $targetMinutes) * 100));
        $adherencePercent = $dueItems->isNotEmpty()
            ? (int) round(($completedDue / $dueItems->count()) * 100)
            : ($completedLogs->isNotEmpty() ? 100 : 0);
        $expectedProgress = max(1, (int) round((now()->dayOfWeekIso / 7) * 100));
        $pacePercent = min(200, (int) round(($progressPercent / $expectedProgress) * 100));
        $signal = $dueItems->isNotEmpty() ? $adherencePercent : $pacePercent;
        $status = $dueItems->isEmpty() && $completedLogs->isEmpty()
            ? 'open'
            : ($signal >= 80 ? 'on_track' : ($signal >= 50 ? 'watch' : 'behind'));

        $days = collect(range(0, 6))->map(function (int $offset) use ($weekStart, $weekItems, $completedLogs, $completedItemIds) {
            $day = $weekStart->copy()->addDays($offset);
            $planned = $weekItems->filter(fn (TrainingPlanItem $item) => $item->scheduled_at?->isSameDay($day));
            $completed = $completedLogs->filter(fn (TrainingLog $log) => $log->performed_at?->isSameDay($day));
            $completedPlanned = $planned->whereIn('id', $completedItemIds)->count();
            $state = match (true) {
                $planned->isNotEmpty() && $completedPlanned === $planned->count() => 'completed',
                $planned->isNotEmpty() && $completed->isNotEmpty() => 'partial',
                $planned->isNotEmpty() && $day->isBefore(now()->startOfDay()) => 'missed',
                $planned->isNotEmpty() => 'planned',
                $completed->isNotEmpty() => 'completed',
                $day->isFuture() => 'future',
                default => 'rest',
            };

            return [
                'date' => $day->toDateString(),
                'is_today' => $day->isToday(),
                'planned_sessions' => $planned->count(),
                'completed_planned_sessions' => $completedPlanned,
                'completed_sessions' => $completed->count(),
                'minutes' => (int) $completed->sum('duration_minutes'),
                'state' => $state,
            ];
        })->values();

        return [
            'status' => $status,
            'progress_percent' => $progressPercent,
            'adherence_percent' => $adherencePercent,
            'pace_percent' => $pacePercent,
            'planned_sessions' => $weekItems->count(),
            'due_sessions' => $dueItems->count(),
            'completed_planned_sessions' => $completedDue,
            'completed_sessions' => $completedLogs->count(),
            'active_days' => $completedLogs
                ->pluck('performed_at')
                ->filter()
                ->map(fn ($date) => $date->toDateString())
                ->unique()
                ->count(),
            'completed_minutes' => $completedMinutes,
            'target_minutes' => $targetMinutes,
            'days' => $days,
        ];
    }

    private function primaryAction(
        Collection $weekItems,
        Collection $weekLogs,
        ?Event $nextEvent,
        array $nutrition,
        array $progress,
    ): array {
        $completedItemIds = $weekLogs
            ->where('status', 'completed')
            ->pluck('training_plan_item_id')
            ->filter()
            ->map(fn ($id) => (int) $id);
        $openItems = $weekItems->reject(fn (TrainingPlanItem $item) => $completedItemIds->contains((int) $item->id));
        $overdueItem = $openItems->first(fn (TrainingPlanItem $item) => $item->scheduled_at?->lessThan(now()->startOfDay()));
        $todayItem = $openItems->first(fn (TrainingPlanItem $item) => $item->scheduled_at?->isToday());
        $hasRecentTraining = $weekLogs->contains(fn (TrainingLog $log) => $log->performed_at && (
            $log->performed_at->isToday()
            || $log->performed_at->greaterThanOrEqualTo(now()->subHours(4))
        ));

        if ($overdueItem) {
            return $this->action('catch_up_training', route('auth.training.logs.create', ['plan_item_id' => $overdueItem->id]), 'las la-history', '/api/v1/training/logs', "airmius://training/logs/create?plan_item_id={$overdueItem->id}");
        }

        if ($todayItem) {
            return $this->action('complete_training', route('auth.training.logs.create', ['plan_item_id' => $todayItem->id]), 'las la-running', '/api/v1/training/logs', "airmius://training/logs/create?plan_item_id={$todayItem->id}");
        }

        if ($hasRecentTraining && $progress['hydration'] < 60) {
            return $this->action('hydrate', route('auth.nutrition.index'), 'las la-tint', '/api/v1/nutrition/water', 'airmius://nutrition/water');
        }

        if ($nextEvent?->start_time?->isToday()) {
            return $this->action('event', route('auth.events.index'), 'las la-calendar-check', "/api/v1/events/{$nextEvent->id}", "airmius://events/{$nextEvent->id}");
        }

        if ((int) $nutrition['meals_today'] === 0) {
            return $this->action('meal', route('auth.nutrition.index'), 'las la-utensils', '/api/v1/nutrition/meals', 'airmius://nutrition/meals/create');
        }

        if (! $hasRecentTraining) {
            return $this->action('start_training', route('auth.training.logs.create'), 'las la-running', '/api/v1/training/logs', 'airmius://training/logs/create');
        }

        return $this->action('review', route('auth.training.index'), 'las la-chart-line', '/api/v1/training', 'airmius://training');
    }

    private function action(string $key, string $href, string $icon, string $apiTarget, string $deepLink): array
    {
        return [
            'key' => $key,
            'label' => __("athlete_today.actions.{$key}.label"),
            'reason' => __("athlete_today.actions.{$key}.reason"),
            'href' => $href,
            'icon' => $icon,
            'api_target' => $apiTarget,
            'deep_link' => $deepLink,
        ];
    }

    private function trainingWaterExtra(TrainingLog $log): int
    {
        $minutes = max(0, (int) $log->duration_minutes);
        $multiplier = match ((string) $log->intensity) {
            'hart', 'hard' => 1.25,
            'locker', 'easy', 'recovery' => 0.75,
            default => 1.0,
        };

        return min(1500, $this->roundWater(($minutes / 60) * 500 * $multiplier));
    }

    private function roundWater(float|int $millilitres): int
    {
        return (int) (round($millilitres / 50) * 50);
    }

    private function distanceLabel(int $meters): string
    {
        if ($meters <= 0) {
            return '0 km';
        }

        return number_format($meters / 1000, $meters >= 10000 ? 0 : 1, ',', '.').' km';
    }
}
