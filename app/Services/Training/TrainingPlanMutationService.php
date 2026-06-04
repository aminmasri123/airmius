<?php

namespace App\Services\Training;

use App\Models\TrainingPlan;
use App\Models\TrainingPlanItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TrainingPlanMutationService
{
    public function createWithFirstItem(User $actor, array $data, ?string $imagePath = null): TrainingPlan
    {
        $userIds = $this->assignmentUserIds($data['user_ids'] ?? [], $actor);

        return DB::transaction(function () use ($actor, $data, $imagePath, $userIds) {
            $plan = TrainingPlan::create([
                'created_by' => $actor->id,
                'team_id' => $data['team_id'] ?? null,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'cadence' => $data['cadence'],
                'starts_on' => $data['starts_on'] ?? null,
                'ends_on' => $data['ends_on'] ?? null,
                'status' => $data['status'],
                'share_permission' => $data['share_permission'],
                'settings' => [
                    'created_from' => 'training_module',
                    ...$this->settingsPayload($data),
                ],
            ]);

            $plan->items()->create($this->firstItemPayload($data, $imagePath));
            $this->syncAssignments($plan, $data, $userIds);

            return $plan;
        });
    }

    public function updatePlan(User $actor, TrainingPlan $plan, array $data): TrainingPlan
    {
        $userIds = $this->assignmentUserIds($data['user_ids'] ?? [], $actor);

        DB::transaction(function () use ($plan, $data, $userIds) {
            $plan->update([
                'team_id' => $data['team_id'] ?? null,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'cadence' => $data['cadence'],
                'starts_on' => $data['starts_on'] ?? null,
                'ends_on' => $data['ends_on'] ?? null,
                'status' => $data['status'],
                'share_permission' => $data['share_permission'],
                'settings' => [
                    ...($plan->settings ?? []),
                    ...$this->settingsPayload($data),
                ],
            ]);

            $plan->assignments()->delete();
            $this->syncAssignments($plan, $data, $userIds);
        });

        return $plan;
    }

    public function createItem(TrainingPlan $plan, array $data, ?string $imagePath = null): TrainingPlanItem
    {
        return $plan->items()->create([
            ...$this->itemPayload($data, $imagePath),
            'sort_order' => $plan->items()->count() + 1,
        ]);
    }

    public function updateItem(TrainingPlanItem $item, array $data, ?string $imagePath = null): TrainingPlanItem
    {
        $item->update($this->itemPayload($data, $imagePath));

        return $item;
    }

    public function duplicateItem(TrainingPlan $plan, TrainingPlanItem $item): TrainingPlanItem
    {
        $copy = $item->replicate();
        $copy->title = Str::limit($item->title.' Kopie', 160, '');
        $copy->sort_order = $plan->items()->count() + 1;
        $copy->scheduled_at = null;
        $copy->save();

        return $copy;
    }

    public function duplicatePlan(User $actor, TrainingPlan $plan): TrainingPlan
    {
        return DB::transaction(function () use ($actor, $plan) {
            $plan->load(['items', 'assignments']);

            $copy = $plan->replicate();
            $copy->created_by = $actor->id;
            $copy->title = Str::limit($plan->title.' Vorlage', 160, '');
            $copy->status = 'draft';
            $copy->settings = [
                ...($plan->settings ?? []),
                'created_from_template_id' => $plan->id,
                'is_template_copy' => true,
            ];
            $copy->save();

            $plan->items->each(function (TrainingPlanItem $item) use ($copy) {
                $itemCopy = $item->replicate();
                $itemCopy->training_plan_id = $copy->id;
                $itemCopy->scheduled_at = null;
                $itemCopy->save();
            });

            $plan->assignments->each(fn ($assignment) => $copy->assignments()->create([
                'user_id' => $assignment->user_id,
                'team_id' => $assignment->team_id,
                'permission' => $assignment->permission,
            ]));

            return $copy;
        });
    }

    public function deleteItemImageIfUnused(TrainingPlanItem $item): void
    {
        if (! $item->image_path) {
            return;
        }

        $uses = TrainingPlanItem::query()
            ->where('image_path', $item->image_path)
            ->whereKeyNot($item->id)
            ->exists();

        if (! $uses) {
            Storage::disk('public')->delete($item->image_path);
        }
    }

    private function firstItemPayload(array $data, ?string $imagePath = null): array
    {
        return [
            'title' => $data['item_title'],
            'sport_type' => $data['item_sport_type'] ?? null,
            'description' => $data['item_description'] ?? null,
            'scheduled_at' => $data['item_scheduled_at'] ?? null,
            'duration_minutes' => $data['item_duration_minutes'] ?? null,
            'distance_meters' => isset($data['item_distance_km']) ? (int) round((float) $data['item_distance_km'] * 1000) : null,
            'calories' => $data['item_calories'] ?? null,
            'intensity' => $data['item_intensity'] ?? null,
            'image_path' => $imagePath,
            'video_url' => $data['item_video_url'] ?? null,
            'todos' => $this->todosFromText($data['item_todos'] ?? ''),
            'metrics' => [
                ...$this->cleanMetrics($data['item_metrics'] ?? []),
                ...array_filter([
                    'Woche' => $data['item_week'] ?? null,
                    'Belastung' => $data['item_load'] ?? null,
                    'Fokus' => $data['item_focus'] ?? null,
                ], fn ($value) => $value !== null && $value !== ''),
            ],
        ];
    }

    private function itemPayload(array $data, ?string $imagePath = null): array
    {
        return [
            'title' => $data['title'],
            'sport_type' => $data['sport_type'] ?? null,
            'description' => $data['description'] ?? null,
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'duration_minutes' => $data['duration_minutes'] ?? null,
            'distance_meters' => isset($data['distance_km']) ? (int) round((float) $data['distance_km'] * 1000) : null,
            'calories' => $data['calories'] ?? null,
            'intensity' => $data['intensity'] ?? null,
            'image_path' => $imagePath,
            'video_url' => $data['video_url'] ?? null,
            'todos' => $this->todosFromText($data['todos'] ?? ''),
            'metrics' => [
                ...$this->cleanMetrics($data['metrics'] ?? []),
                ...array_filter([
                    'Woche' => $data['week'] ?? null,
                    'Belastung' => $data['load'] ?? null,
                    'Fokus' => $data['focus'] ?? null,
                ], fn ($value) => $value !== null && $value !== ''),
            ],
        ];
    }

    private function syncAssignments(TrainingPlan $plan, array $data, $userIds): void
    {
        if (! empty($data['team_id'])) {
            $plan->assignments()->create([
                'team_id' => $data['team_id'],
                'permission' => $data['share_permission'],
            ]);
        }

        collect($userIds)->each(fn ($userId) => $plan->assignments()->create([
            'user_id' => $userId,
            'permission' => $data['share_permission'],
        ]));
    }

    private function assignmentUserIds(array $userIds, User $actor)
    {
        return collect($userIds)
            ->map(fn ($id) => (int) $id)
            ->reject(fn ($id) => $id === (int) $actor->id)
            ->unique()
            ->values();
    }

    private function settingsPayload(array $data): array
    {
        return [
            'goal' => $data['goal'] ?? null,
            'phase' => $data['phase'] ?? null,
            'level' => $data['level'] ?? null,
            'weeks' => $data['weeks'] ?? null,
            'weekly_sessions' => $data['weekly_sessions'] ?? null,
            'macrocycle' => $data['macrocycle'] ?? null,
            'mesocycle' => $data['mesocycle'] ?? null,
            'deload_week' => $data['deload_week'] ?? null,
            'competition_date' => $data['competition_date'] ?? null,
        ];
    }

    private function todosFromText(?string $text): array
    {
        return collect(preg_split('/\r\n|\r|\n/', $text ?? ''))
            ->map(fn ($todo) => trim($todo))
            ->filter()
            ->values()
            ->all();
    }

    private function cleanMetrics(array $metrics): array
    {
        return collect($metrics)
            ->map(fn ($value) => is_string($value) ? trim($value) : $value)
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();
    }
}
