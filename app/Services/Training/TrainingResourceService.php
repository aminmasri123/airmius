<?php

namespace App\Services\Training;

use App\Models\TrainingLog;
use App\Models\TrainingLogFeedback;
use App\Models\TrainingPlan;
use App\Models\TrainingPlanItem;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class TrainingResourceService
{
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
            'creator' => $plan->creator ? $this->user($plan->creator) : null,
            'team' => $plan->team ? ['id' => $plan->team->id, 'name' => $plan->team->name] : null,
            'can_write' => $this->canWritePlan($viewer, $plan),
            'progress' => [
                'completed' => $completedCount,
                'missed' => $missedCount,
                'open' => max(0, $items->count() - $completedCount - $missedCount),
                'percent' => $items->count() ? (int) round(($completedCount / $itemCount) * 100) : 0,
            ],
            'items' => $items->map(fn ($item) => [
                ...$item->toArray(),
                'image_url' => $item->image_path ? Storage::disk('public')->url($item->image_path) : null,
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
            ] : null,
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
            ...$item->toArray(),
            'image_url' => $item->image_path ? Storage::disk('public')->url($item->image_path) : null,
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

    public function canWritePlan(User $user, TrainingPlan $plan): bool
    {
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
