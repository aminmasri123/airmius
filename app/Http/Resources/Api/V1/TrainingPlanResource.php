<?php

namespace App\Http\Resources\Api\V1;

use App\Services\Training\TrainingResourceService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class TrainingPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewer = $request->user();

        return [
            'id' => $this->id,
            'created_by' => $this->created_by,
            'team_id' => $this->team_id,
            'club_training_group_id' => $this->club_training_group_id,
            'template_source_id' => $this->template_source_id ?? data_get($this->settings, 'template_source_id'),
            'title' => $this->title,
            'description' => $this->description,
            'cadence' => $this->cadence,
            'period_type' => $this->period_type,
            'period_index' => $this->period_index,
            'season_label' => $this->season_label,
            'starts_on' => $this->starts_on?->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            'status' => $this->status,
            'share_permission' => $this->share_permission,
            'settings' => $this->settings,
            'target_type' => data_get($this->settings, 'target_type', $this->team_id ? 'team' : 'self'),
            'team_mode' => data_get($this->settings, 'team_mode'),
            'is_template' => (bool) ($this->is_template ?? data_get($this->settings, 'is_template', false)),
            'is_template_copy' => (bool) data_get($this->settings, 'is_template_copy', false),
            'template_source_id' => data_get($this->settings, 'template_source_id'),
            'created_from_template_id' => data_get($this->settings, 'created_from_template_id'),
            'can_write' => $viewer ? app(TrainingResourceService::class)->canWritePlan($viewer, $this->resource) : false,
            'can_delete' => $viewer ? app(TrainingResourceService::class)->canDeletePlan($viewer, $this->resource) : false,
            'can_manage_audience' => $viewer ? app(TrainingResourceService::class)->canManageTrainingPlans($viewer) : false,
            'creator' => new UserResource($this->whenLoaded('creator')),
            'team' => new TeamResource($this->whenLoaded('team')),
            'training_group' => $this->whenLoaded('trainingGroup', fn () => $this->trainingGroup ? [
                'id' => $this->trainingGroup->id,
                'name' => $this->trainingGroup->name,
                'sport_type' => $this->trainingGroup->sport_type,
            ] : null),
            'assignments_count' => $this->whenCounted('assignments'),
            'assignments' => $this->whenLoaded('assignments', fn () => $this->assignments->map(fn ($assignment) => [
                'id' => $assignment->id,
                'permission' => $assignment->permission,
                'user' => $assignment->user ? [
                    'id' => $assignment->user->id,
                    'name' => $assignment->user->name,
                ] : null,
                'team' => $assignment->team ? [
                    'id' => $assignment->team->id,
                    'name' => $assignment->team->name,
                ] : null,
                'training_group' => $assignment->trainingGroup ? [
                    'id' => $assignment->trainingGroup->id,
                    'name' => $assignment->trainingGroup->name,
                ] : null,
            ])->values()),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'source_exercise_id' => $item->source_exercise_id,
                'training_session_id' => $item->training_session_id,
                'sport_route_id' => $item->sport_route_id,
                'title' => $item->title,
                'sport_type' => $item->sport_type,
                'description' => $item->description,
                'scheduled_at' => $item->scheduled_at?->toJSON(),
                'period_week' => $item->period_week,
                'period_month' => $item->period_month,
                'duration_minutes' => $item->duration_minutes,
                'distance_meters' => $item->distance_meters,
                'calories' => $item->calories,
                'intensity' => $item->intensity,
                'image_path' => $item->image_path,
                'image_url' => $item->image_path ? Storage::disk('public')->url($item->image_path) : null,
                'video_url' => $item->video_url,
                'todos' => $item->todos,
                'metrics' => $item->metrics,
                'sort_order' => $item->sort_order,
                'sport_route' => $item->relationLoaded('sportRoute') && $item->sportRoute
                    ? app(TrainingResourceService::class)->routeReference($item->sportRoute)
                    : null,
                'training_session' => $item->relationLoaded('trainingSession') && $item->trainingSession ? [
                    'id' => $item->trainingSession->id,
                    'title' => $item->trainingSession->title,
                    'revision' => $item->trainingSession->revision,
                    'status' => $item->trainingSession->status,
                ] : null,
            ])->values()),
            'handovers' => $this->whenLoaded('handovers', fn () => $this->handovers->map(fn ($handover) => [
                'id' => $handover->id,
                'from_user' => $handover->fromUser ? ['id' => $handover->fromUser->id, 'name' => $handover->fromUser->name] : null,
                'to_user' => $handover->toUser ? ['id' => $handover->toUser->id, 'name' => $handover->toUser->name] : null,
                'created_by' => $handover->creator ? ['id' => $handover->creator->id, 'name' => $handover->creator->name] : null,
                'starts_at' => $handover->starts_at?->toJSON(),
                'ends_at' => $handover->ends_at?->toJSON(),
                'status' => $handover->status,
                'handover_note' => $handover->handover_note,
                'responsibilities' => $handover->responsibilities ?? [],
            ])->values()),
            'history' => $this->whenLoaded('historyEntries', fn () => $this->historyEntries->map(fn ($entry) => [
                'id' => $entry->id,
                'training_plan_item_id' => $entry->training_plan_item_id,
                'event' => $entry->event,
                'before' => $entry->before,
                'after' => $entry->after,
                'note' => $entry->note,
                'actor' => $entry->actor ? ['id' => $entry->actor->id, 'name' => $entry->actor->name] : null,
                'created_at' => $entry->created_at?->toJSON(),
            ])->values()),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
