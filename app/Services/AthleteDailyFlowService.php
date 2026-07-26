<?php

namespace App\Services;

use App\Models\Event;
use App\Models\File;
use App\Models\Notification as AppNotification;
use App\Models\NutritionMeal;
use App\Models\SportPlace;
use App\Models\SportRoute;
use App\Models\SportRouteTrack;
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
        $visiblePlanIds = $this->visibleTrainingPlansQuery($user, $teamIds)->pluck('training_plans.id');
        $trainingLogs = TrainingLog::query()
            ->where('user_id', $user->id)
            ->where('status', '!=', 'draft')
            ->where('performed_at', '>=', $now->copy()->subDays(60))
            ->latest('performed_at')
            ->limit(120)
            ->get();
        $weekLogs = $trainingLogs->filter(fn (TrainingLog $log) => $log->performed_at && $log->performed_at->greaterThanOrEqualTo($weekStart));
        $upcomingItems = $visiblePlanIds->isEmpty()
            ? collect()
            : TrainingPlanItem::query()
                ->with('plan:id,title')
                ->whereIn('training_plan_id', $visiblePlanIds)
                ->whereNotNull('scheduled_at')
                ->where('scheduled_at', '>=', $now->copy()->startOfDay())
                ->orderBy('scheduled_at')
                ->limit(5)
                ->get();
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

        return $this->fromDashboardData($user, $weekLogs, $upcomingItems, $upcomingEvents, $latestNotifications);
    }

    public function fromDashboardData(User $user, Collection $weekLogs, Collection $upcomingItems, Collection $upcomingEvents, Collection $notifications): array
    {
        $todayStart = now()->copy()->startOfDay();
        $todayEnd = now()->copy()->endOfDay();
        $todayLogs = $weekLogs->filter(fn (TrainingLog $log) => $log->performed_at && $log->performed_at->betweenIncluded($todayStart, $todayEnd));
        $todayTrainingMinutes = (int) $todayLogs->sum('duration_minutes');
        $todayTrainingDistance = (int) $todayLogs->sum('distance_meters');
        $nutrition = $this->nutritionSummary($user);
        $nextItem = $upcomingItems->first();
        $nextEvent = $upcomingEvents->first();
        $lastRoute = SportRoute::visibleTo($user)
            ->latest('updated_at')
            ->first(['id', 'title', 'sport_type', 'distance_meters', 'updated_at']);
        $fileCount = File::query()->where('user_id', $user->id)->count();
        $fileBytes = (int) File::query()->where('user_id', $user->id)->sum('size');
        $unreadCount = $notifications->where('read', false)->count();
        $waterGoal = 2500;
        $calorieGoal = 2200;
        $progress = [
            'training' => min(100, (int) round(($todayTrainingMinutes / 45) * 100)),
            'nutrition' => min(100, (int) round(((int) $nutrition['today_calories'] / $calorieGoal) * 100)),
            'hydration' => min(100, (int) round(((int) $nutrition['today_water_ml'] / $waterGoal) * 100)),
        ];

        return [
            'score' => (int) round(($progress['training'] + $progress['nutrition'] + $progress['hydration']) / 3),
            'summary' => $this->dailyFlowSummary($todayTrainingMinutes, (int) $nutrition['today_calories'], (int) $nutrition['today_water_ml'], $unreadCount),
            'coach_note' => $this->dailyCoachNote($todayTrainingMinutes, (int) $nutrition['today_calories'], (int) $nutrition['today_water_ml'], $nextItem, $nextEvent),
            'files' => [
                'count' => $fileCount,
                'bytes' => $fileBytes,
            ],
            'mobile_context' => $this->mobileContext($nextItem, $nextEvent),
            'steps' => [
                [
                    'key' => 'training',
                    'title' => 'Training',
                    'body' => $nextItem
                        ? $nextItem->title
                        : ($todayLogs->isNotEmpty() ? 'Training heute dokumentiert' : 'Heute eine kurze Einheit planen'),
                    'meta' => $nextItem?->scheduled_at?->format('d.m. H:i') ?: ($todayTrainingMinutes > 0 ? $todayTrainingMinutes.' min · '.$this->distanceLabel($todayTrainingDistance) : '45 min Ziel'),
                    'progress' => $progress['training'],
                    'href' => $nextItem ? route('auth.training.logs.create', ['plan_item_id' => $nextItem->id]) : route('auth.training.logs.create'),
                    'cta' => $nextItem ? 'Dokumentieren' : 'Training starten',
                    'icon' => 'las la-running',
                ],
                [
                    'key' => 'route',
                    'title' => 'Route',
                    'body' => $lastRoute?->title ?: 'Neue Route oder Strecke planen',
                    'meta' => $lastRoute ? $this->distanceLabel((int) $lastRoute->distance_meters) : 'GPX, Tracking, Sportkarte',
                    'progress' => $lastRoute ? 100 : 0,
                    'href' => route('auth.sport-map.index'),
                    'cta' => $lastRoute ? 'Route öffnen' : 'Route planen',
                    'icon' => 'las la-route',
                ],
                [
                    'key' => 'nutrition',
                    'title' => 'Ernährung',
                    'body' => ((int) $nutrition['meals_today']).' Mahlzeiten heute',
                    'meta' => ((int) $nutrition['today_calories']).' / '.$calorieGoal.' kcal',
                    'progress' => $progress['nutrition'],
                    'href' => route('auth.nutrition.index'),
                    'cta' => 'Eintragen',
                    'icon' => 'las la-utensils',
                ],
                [
                    'key' => 'hydration',
                    'title' => 'Wasser',
                    'body' => ((int) $nutrition['today_water_ml']).' ml getrunken',
                    'meta' => $waterGoal.' ml Tagesziel',
                    'progress' => $progress['hydration'],
                    'href' => route('auth.nutrition.index'),
                    'cta' => 'Wasser loggen',
                    'icon' => 'las la-tint',
                ],
                [
                    'key' => 'reminders',
                    'title' => 'Erinnerungen',
                    'body' => $nextEvent ? $nextEvent->title : ($unreadCount > 0 ? $unreadCount.' ungelesene Hinweise' : 'Keine offenen Termine'),
                    'meta' => $nextEvent?->start_time?->format('d.m. H:i') ?: 'Inbox & Kalender',
                    'progress' => ($nextEvent || $unreadCount > 0) ? 60 : 100,
                    'href' => $nextEvent ? route('auth.events.index') : route('auth.notifications.index'),
                    'cta' => $nextEvent ? 'Zum Termin' : 'Inbox öffnen',
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

    private function nutritionSummary(User $user): array
    {
        $today = now()->toDateString();
        $weekStart = now()->copy()->subDays(6)->toDateString();
        $meals = NutritionMeal::query()
            ->where('user_id', $user->id)
            ->whereBetween('eaten_on', [$weekStart, $today])
            ->latest('eaten_on')
            ->limit(80)
            ->get();
        $todayMeals = NutritionMeal::query()
            ->where('user_id', $user->id)
            ->whereDate('eaten_on', $today)
            ->get();

        return [
            'today_calories' => (int) $todayMeals->sum('calories'),
            'today_water_ml' => (int) $todayMeals->sum('water_ml'),
            'meals_today' => $todayMeals->count(),
            'week_calories' => (int) $meals->sum('calories'),
        ];
    }

    private function dailyFlowSummary(int $trainingMinutes, int $calories, int $waterMl, int $unreadCount): string
    {
        if ($trainingMinutes === 0 && $calories === 0 && $waterMl === 0) {
            return 'Dein Tag ist noch offen. Starte mit Training, Ernährung oder Wasser.';
        }

        $parts = [];

        if ($trainingMinutes > 0) {
            $parts[] = $trainingMinutes.' Trainingsminuten';
        }

        if ($calories > 0) {
            $parts[] = $calories.' kcal';
        }

        if ($waterMl > 0) {
            $parts[] = $waterMl.' ml Wasser';
        }

        if ($unreadCount > 0) {
            $parts[] = $unreadCount.' Hinweise';
        }

        return implode(' · ', $parts);
    }

    private function dailyCoachNote(int $trainingMinutes, int $calories, int $waterMl, ?TrainingPlanItem $nextItem, ?Event $nextEvent): string
    {
        if ($nextItem) {
            return 'KI-Coach: Deine nächste Einheit ist geplant. Dokumentiere sie direkt danach, damit Belastung und Fortschritt sauber bleiben.';
        }

        if ($nextEvent) {
            return 'KI-Coach: Heute steht ein Termin an. Halte Ernährung und Wasser stabil, damit du vorbereitet reingehst.';
        }

        if ($trainingMinutes === 0) {
            return 'KI-Coach: Plane heute eine kleine, realistische Einheit. Schon 20 bis 30 Minuten halten deinen Rhythmus aktiv.';
        }

        if ($waterMl < 1500) {
            return 'KI-Coach: Training ist drin. Dein nächster sinnvoller Schritt ist Wasser nachziehen und die Regeneration sichern.';
        }

        if ($calories === 0) {
            return 'KI-Coach: Training und Wasser sind sichtbar. Trage noch eine Mahlzeit ein, damit der Tagesflow vollständig wird.';
        }

        return 'KI-Coach: Dein Tag ist gut gefüllt. Prüfe später nur noch, ob du Feedback, Route oder Notizen ergänzen möchtest.';
    }

    private function distanceLabel(int $meters): string
    {
        if ($meters <= 0) {
            return '0 km';
        }

        return number_format($meters / 1000, $meters >= 10000 ? 0 : 1, ',', '.').' km';
    }
}
