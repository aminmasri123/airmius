<?php

namespace App\Services\Training;

use App\Models\SportRoute;
use App\Models\SportRouteTrack;
use App\Models\Team;
use App\Models\TrainingLog;
use App\Models\TrainingLogFeedback;
use App\Models\TrainingPlan;
use App\Models\TrainingPlanItem;
use App\Models\User;
use App\Support\Roles;
use App\Support\TeamRoles;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class TrainingResourceService
{
    /** @var array<int, bool> */
    private array $planManagerCache = [];

    public function __construct(private readonly TrainingRouteLinkService $routeLinks) {}

    public function plan(TrainingPlan $plan, User $viewer): array
    {
        $items = $plan->items;
        $itemCount = max(1, $items->count());
        $completedCount = $items->filter(fn ($item) => $item->relationLoaded('logs') && $item->logs->contains(fn ($log) => $log->status === 'completed'))->count();
        $missedCount = $items->filter(fn ($item) => $item->relationLoaded('logs') && $item->logs->contains(fn ($log) => $log->status === 'missed'))->count();

        return [
            'id' => $plan->id,
            'title' => $plan->title,
            'description' => $plan->description,
            'cadence' => $plan->cadence,
            'starts_on' => $plan->starts_on?->toDateString(),
            'ends_on' => $plan->ends_on?->toDateString(),
            'status' => $plan->status,
            'share_permission' => $plan->share_permission,
            'settings' => $plan->settings ?? [],
            'target_type' => $this->planTargetType($plan),
            'team_mode' => data_get($plan->settings, 'team_mode'),
            'creator' => $plan->creator ? $this->user($plan->creator) : null,
            'team' => $plan->team ? ['id' => $plan->team->id, 'name' => $plan->team->name] : null,
            'can_write' => $this->canWritePlan($viewer, $plan),
            'can_delete' => $this->canDeletePlan($viewer, $plan),
            'progress' => [
                'completed' => $completedCount,
                'missed' => $missedCount,
                'open' => max(0, $items->count() - $completedCount - $missedCount),
                'percent' => $items->count() ? (int) round(($completedCount / $itemCount) * 100) : 0,
            ],
            'items' => $items->map(fn ($item) => [
                ...$item->attributesToArray(),
                'image_url' => $item->image_path ? Storage::disk('public')->url($item->image_path) : null,
                'sport_route' => $item->relationLoaded('sportRoute') && $item->sportRoute
                    ? $this->routeLinks->routeSummary($item->sportRoute)
                    : null,
                'log_statuses' => $item->relationLoaded('logs')
                    ? $item->logs->map(fn (TrainingLog $log) => [
                        'id' => $log->id,
                        'status' => $log->status,
                        'user_id' => $log->user_id,
                        'reason' => $log->metrics['missed_reason'] ?? null,
                    ])
                    : [],
            ]),
            'assignments' => $plan->assignments->map(fn ($assignment) => [
                'id' => $assignment->id,
                'permission' => $assignment->permission,
                'user' => $assignment->user ? $this->user($assignment->user) : null,
                'team' => $assignment->team ? ['id' => $assignment->team->id, 'name' => $assignment->team->name] : null,
            ]),
        ];
    }

    public function log(TrainingLog $log): array
    {
        return [
            'id' => $log->id,
            'title' => $log->title,
            'status' => $log->status,
            'sport_type' => $log->sport_type,
            'performed_at' => $log->performed_at?->toIso8601String(),
            'created_at' => $log->created_at?->toIso8601String(),
            'updated_at' => $log->updated_at?->toIso8601String(),
            'duration_minutes' => $log->duration_minutes,
            'distance_meters' => $log->distance_meters,
            'calories' => $log->calories,
            'intensity' => $log->intensity,
            'notes' => $log->notes,
            'trainer_feedback' => $log->trainer_feedback,
            'metrics' => $log->metrics ?? [],
            'athlete' => $log->athlete ? $this->user($log->athlete) : null,
            'creator' => $log->creator ? $this->user($log->creator) : null,
            'trainer' => $log->trainer ? $this->user($log->trainer) : null,
            'team' => $log->team ? ['id' => $log->team->id, 'name' => $log->team->name] : null,
            'plan' => $log->plan ? ['id' => $log->plan->id, 'title' => $log->plan->title] : null,
            'plan_item' => $log->planItem ? [
                'id' => $log->planItem->id,
                'title' => $log->planItem->title,
                'sport_type' => $log->planItem->sport_type,
                'description' => $log->planItem->description,
                'scheduled_at' => $log->planItem->scheduled_at?->toIso8601String(),
                'duration_minutes' => $log->planItem->duration_minutes,
                'distance_meters' => $log->planItem->distance_meters,
                'calories' => $log->planItem->calories,
                'intensity' => $log->planItem->intensity,
                'todos' => $log->planItem->todos ?? [],
                'metrics' => $log->planItem->metrics ?? [],
                'sport_route' => $log->planItem->relationLoaded('sportRoute') && $log->planItem->sportRoute
                    ? $this->routeLinks->routeSummary($log->planItem->sportRoute)
                    : null,
            ] : null,
            'sport_route' => $log->relationLoaded('sportRoute') && $log->sportRoute
                ? $this->routeLinks->routeSummary($log->sportRoute)
                : null,
            'sport_route_track' => $log->relationLoaded('sportRouteTrack') && $log->sportRouteTrack
                ? $this->routeLinks->trackSummary($log->sportRouteTrack)
                : null,
            'plan_comparison' => $this->trainingPlanComparison($log),
            'entries' => $log->entries->map(fn ($entry) => [
                'id' => $entry->id,
                'title' => $entry->title,
                'sets' => $entry->sets,
                'reps' => $entry->reps,
                'weight_kg' => $entry->weight_kg,
                'duration_seconds' => $entry->duration_seconds,
                'distance_meters' => $entry->distance_meters,
                'intensity' => $entry->intensity,
                'notes' => $entry->notes,
                'metrics' => $entry->metrics ?? [],
            ]),
            'feedbacks' => $log->relationLoaded('feedbacks')
                ? $log->feedbacks->map(fn (TrainingLogFeedback $feedback) => [
                    'id' => $feedback->id,
                    'body' => $feedback->body,
                    'role' => $feedback->role,
                    'created_at' => $feedback->created_at?->toIso8601String(),
                    'author' => $feedback->author ? $this->user($feedback->author) : null,
                ])
                : [],
        ];
    }

    public function planItemDetail(TrainingPlanItem $item): array
    {
        return [
            ...$item->attributesToArray(),
            'image_url' => $item->image_path ? Storage::disk('public')->url($item->image_path) : null,
            'sport_route' => $item->relationLoaded('sportRoute') && $item->sportRoute
                ? $this->routeLinks->routeSummary($item->sportRoute)
                : null,
            'logs' => $item->relationLoaded('logs')
                ? $item->logs->map(fn (TrainingLog $log) => $this->log($log))
                : [],
            'stats' => [
                'completed' => $item->relationLoaded('logs') ? $item->logs->where('status', 'completed')->count() : 0,
                'missed' => $item->relationLoaded('logs') ? $item->logs->where('status', 'missed')->count() : 0,
                'in_progress' => $item->relationLoaded('logs') ? $item->logs->where('status', 'in_progress')->count() : 0,
            ],
        ];
    }

    public function user(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: $user->name,
            'email' => $user->email,
        ];
    }

    /** @return array<string, mixed> */
    public function routeReference(SportRoute $route): array
    {
        return $this->routeLinks->routeSummary($route);
    }

    /** @return array<string, mixed> */
    public function trackReference(SportRouteTrack $track): array
    {
        return $this->routeLinks->trackSummary($track);
    }

    public function canWritePlan(User $user, TrainingPlan $plan): bool
    {
        if (! $this->canManageTrainingPlans($user)) {
            return false;
        }

        if ((int) $plan->created_by === (int) $user->id) {
            return true;
        }

        $teamIds = $user->teams()->pluck('teams.id');

        return $plan->assignments()
            ->where('permission', 'write')
            ->where(function ($query) use ($user, $teamIds) {
                $query
                    ->where('user_id', $user->id)
                    ->orWhereIn('team_id', $teamIds);
            })
            ->exists();
    }

    /**
     * Trainingspläne sind eine fachliche Trainer-Funktion. Team-Mitgliedschaft
     * oder eine Schreibzuweisung allein darf diese Berechtigung nicht verleihen.
     */
    public function canManageTrainingPlans(User $user): bool
    {
        $userId = (int) $user->id;

        if (array_key_exists($userId, $this->planManagerCache)) {
            return $this->planManagerCache[$userId];
        }

        $allowed = false;

        if ($user->hasAnyRole(Roles::FULL_ACCESS)) {
            $allowed = true;
        } elseif ($user->hasAnyRole([
            'club_owner',
            'coach',
            'assistant_coach',
            'performance_coach',
            'fitness_coach',
        ])) {
            $allowed = true;
        } elseif (
            // Ein Club-Owner kann auch dann verwalten, wenn die globale Rolle
            // noch nicht synchronisiert wurde.
            $user->clubs()->where('clubs.owner_id', $user->id)->exists()
            || $user->clubs()->wherePivotIn('role', ['owner', 'trainer'])->exists()
        ) {
            $allowed = true;
        } else {
            // ClubPresident ist eine Team-Pivot-Rolle. Captain und TeamManager
            // sind bewusst nicht enthalten.
            $allowed = $user->teams()
                ->wherePivotIn('role', [
                    TeamRoles::COACH,
                    TeamRoles::CLUB_PRESIDENT,
                    'coach',
                    'club_president',
                ])
                ->exists();
        }

        return $this->planManagerCache[$userId] = $allowed;
    }

    public function canDeletePlan(User $user, TrainingPlan $plan): bool
    {
        return $this->canManageTrainingPlans($user)
            && (int) $plan->created_by === (int) $user->id;
    }

    /**
     * Teams, die ein berechtigter Nutzer bei der Planerstellung auswählen darf.
     * Club-Owner verwalten dabei auch Teams ihres Clubs, ohne selbst in jedem
     * einzelnen Team Mitglied sein zu müssen.
     */
    public function trainingPlanTeamIds(User $user)
    {
        $teamIds = $user->teams()->pluck('teams.id');

        $ownedClubIds = $user->clubs()
            ->where('clubs.owner_id', $user->id)
            ->pluck('clubs.id');

        if ($ownedClubIds->isNotEmpty()) {
            $teamIds = $teamIds->merge(
                Team::query()->whereIn('club_id', $ownedClubIds)->pluck('id')
            );
        }

        return $teamIds->unique()->values();
    }

    /**
     * Normalize and validate the audience of a training plan.
     *
     * A plan can be personal, shared with selected friends/private clients,
     * or scoped to one club team. Individual team members are only available
     * after a team has been selected.
     */
    public function normalizePlanAudience(User $user, array $data): array
    {
        $userIds = collect($data['user_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();
        $teamId = filled($data['team_id'] ?? null) ? (int) $data['team_id'] : null;
        $targetType = $data['target_type'] ?? ($teamId ? 'team' : ($userIds->isNotEmpty() ? 'private' : 'self'));
        $teamMode = $data['team_mode'] ?? ($targetType === 'team'
            ? ($userIds->isNotEmpty() ? 'individual' : 'all')
            : null);

        if (! in_array($targetType, ['self', 'private', 'team'], true)) {
            throw ValidationException::withMessages([
                'target_type' => 'Bitte wähle ein gültiges Plan-Ziel.',
            ]);
        }

        if ($targetType === 'self') {
            if ($teamId || $userIds->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'target_type' => 'Ein persönlicher Plan darf kein Team oder andere Personen enthalten.',
                ]);
            }

            return [
                ...$data,
                'target_type' => 'self',
                'team_mode' => null,
                'team_id' => null,
                'user_ids' => [],
            ];
        }

        if ($targetType === 'private') {
            if ($teamId) {
                throw ValidationException::withMessages([
                    'team_id' => 'Private Pläne dürfen keinem Vereinsteam zugewiesen werden.',
                ]);
            }

            $friendIds = $user->friendships()->pluck('friend_id')->map(fn ($id) => (int) $id);
            $invalidIds = $userIds->diff($friendIds);

            if ($userIds->isEmpty()) {
                throw ValidationException::withMessages([
                    'user_ids' => 'Wähle mindestens einen privaten Kunden oder Freund aus.',
                ]);
            }

            if ($invalidIds->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'user_ids' => 'Private Pläne können nur an bestätigte Freunde oder private Kunden mit Verbindung freigegeben werden.',
                ]);
            }

            return [
                ...$data,
                'target_type' => 'private',
                'team_mode' => null,
                'team_id' => null,
                'user_ids' => $userIds->all(),
            ];
        }

        if (! $teamId || ! in_array($teamMode, ['all', 'individual'], true)) {
            throw ValidationException::withMessages([
                'team_id' => 'Wähle ein Vereinsteam und ob der Plan für das ganze Team oder einzelne Sportler gilt.',
            ]);
        }

        if (! $this->trainingPlanTeamIds($user)->contains($teamId)) {
            throw ValidationException::withMessages([
                'team_id' => 'Du darfst dieses Vereinsteam nicht für Trainingspläne auswählen.',
            ]);
        }

        $teamMemberIds = Team::query()->findOrFail($teamId)->users()->pluck('users.id')->map(fn ($id) => (int) $id);

        if ($teamMode === 'all') {
            $userIds = collect();
        } elseif ($userIds->isEmpty()) {
            throw ValidationException::withMessages([
                'user_ids' => 'Wähle mindestens einen Sportler aus diesem Team aus.',
            ]);
        } elseif ($userIds->diff($teamMemberIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'user_ids' => 'Es dürfen nur Mitglieder des ausgewählten Teams zugewiesen werden.',
            ]);
        }

        return [
            ...$data,
            'target_type' => 'team',
            'team_mode' => $teamMode,
            'team_id' => $teamId,
            'user_ids' => $userIds->all(),
        ];
    }

    public function planTargetType(TrainingPlan $plan): string
    {
        $targetType = data_get($plan->settings, 'target_type');

        if (in_array($targetType, ['self', 'private', 'team'], true)) {
            return $targetType;
        }

        if ($plan->team_id) {
            return 'team';
        }

        return $plan->relationLoaded('assignments')
            && $plan->assignments->contains(fn ($assignment) => $assignment->user_id !== null)
            ? 'private'
            : 'self';
    }

    private function trainingPlanComparison(TrainingLog $log): ?array
    {
        if (! $log->planItem) {
            return null;
        }

        $planned = [
            'title' => $log->planItem->title,
            'scheduled_at' => $log->planItem->scheduled_at?->toIso8601String(),
            'duration_minutes' => $log->planItem->duration_minutes,
            'distance_meters' => $log->planItem->distance_meters,
            'calories' => $log->planItem->calories,
            'intensity' => $log->planItem->intensity,
            'todos_count' => count($log->planItem->todos ?? []),
            'metrics_count' => count($log->planItem->metrics ?? []),
        ];

        $actual = [
            'title' => $log->title,
            'performed_at' => $log->performed_at?->toIso8601String(),
            'duration_minutes' => $log->duration_minutes,
            'distance_meters' => $log->distance_meters,
            'calories' => $log->calories,
            'intensity' => $log->intensity,
            'entries_count' => $log->relationLoaded('entries') ? $log->entries->count() : null,
        ];

        return [
            'planned' => $planned,
            'actual' => $actual,
            'delta' => [
                'duration_minutes' => $actual['duration_minutes'] !== null && $planned['duration_minutes'] !== null
                    ? (int) $actual['duration_minutes'] - (int) $planned['duration_minutes']
                    : null,
                'distance_meters' => $actual['distance_meters'] !== null && $planned['distance_meters'] !== null
                    ? (int) $actual['distance_meters'] - (int) $planned['distance_meters']
                    : null,
                'calories' => $actual['calories'] !== null && $planned['calories'] !== null
                    ? (int) $actual['calories'] - (int) $planned['calories']
                    : null,
            ],
        ];
    }
}
