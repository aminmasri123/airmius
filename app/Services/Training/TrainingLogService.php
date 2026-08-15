<?php

namespace App\Services\Training;

use App\Models\TrainingLog;
use App\Models\TrainingPlanItem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TrainingLogService
{
    public function __construct(private readonly TrainingRouteLinkService $routeLinks) {}

    public function findOrCreateDraft(User $user): TrainingLog
    {
        $draft = $this->currentDraft($user, true);

        if ($draft) {
            $draft->touch();

            return $draft;
        }

        return TrainingLog::create([
            'user_id' => $user->id,
            'created_by' => $user->id,
            'sport_type' => 'gym',
            'title' => 'Training-Entwurf',
            'status' => 'draft',
            'performed_at' => now(),
            'metrics' => [
                'source_kind' => 'training_documentation_draft',
                'training_type' => 'gym',
                'draft_started_at' => now()->toIso8601String(),
            ],
        ]);
    }

    public function currentDraft(User $user, bool $freshOnly = false): ?TrainingLog
    {
        return TrainingLog::query()
            ->where('created_by', $user->id)
            ->where('user_id', $user->id)
            ->where('status', 'draft')
            ->when($freshOnly, fn ($query) => $query->where('created_at', '>=', now()->subHours(6)))
            ->latest('updated_at')
            ->latest('id')
            ->first();
    }

    public function storeFromPayload(
        User $actor,
        array $data,
        int $athleteId,
        ?TrainingPlanItem $planItem = null,
        ?Request $request = null,
        ?string $createdFrom = null,
    ): TrainingLog {
        return DB::transaction(function () use ($request, $actor, $data, $athleteId, $planItem, $createdFrom) {
            $draft = ! empty($data['draft_log_id'])
                ? TrainingLog::query()
                    ->whereKey($data['draft_log_id'])
                    ->where('created_by', $actor->id)
                    ->where('status', 'draft')
                    ->first()
                : null;

            abort_if(! empty($data['draft_log_id']) && ! $draft, 403);

            $payload = $this->payload($actor, $data, $athleteId, $planItem, $createdFrom, $draft);

            if ($draft) {
                $draft->update($payload);
                $draft->entries()->delete();
                $log = $draft;
            } else {
                $log = TrainingLog::create($payload);
            }

            $this->createEntries($log, $data['entries'] ?? [], $request);

            return $log;
        });
    }

    public function replaceFromPayload(
        TrainingLog $log,
        User $actor,
        array $data,
        int $athleteId,
        ?TrainingPlanItem $planItem = null,
        ?Request $request = null,
        ?string $createdFrom = null,
    ): TrainingLog {
        DB::transaction(function () use ($log, $actor, $data, $athleteId, $planItem, $request, $createdFrom) {
            $payload = $this->payload($actor, $data, $athleteId, $planItem, $createdFrom);
            $existingSnapshot = data_get($log->metrics, 'plan_snapshot');
            if (is_array($existingSnapshot)) {
                $payload['metrics']['plan_snapshot'] = $existingSnapshot;
            }
            $log->update($payload);
            $log->entries()->delete();
            $this->createEntries($log, $data['entries'] ?? [], $request);
        });

        return $log->refresh();
    }

    public function updateDraftFromPayload(
        TrainingLog $log,
        User $actor,
        array $data,
        int $athleteId,
        ?TrainingPlanItem $planItem = null,
        ?Request $request = null,
    ): TrainingLog {
        DB::transaction(function () use ($request, $log, $actor, $data, $athleteId, $planItem) {
            $routeLinks = $this->routeLinks->resolveLogLinks($actor, $athleteId, $data, $planItem);

            $log->update([
                'user_id' => $athleteId,
                'created_by' => $actor->id,
                'trainer_id' => $athleteId === (int) $actor->id ? null : $actor->id,
                'team_id' => $data['team_id'] ?? $planItem?->plan?->team_id,
                'training_plan_id' => $planItem?->training_plan_id,
                'training_plan_item_id' => $planItem?->id,
                ...$routeLinks,
                'sport_type' => $data['sport_type'] ?? $planItem?->sport_type,
                'title' => trim((string) ($data['title'] ?? '')) !== '' ? $data['title'] : 'Training-Entwurf',
                'status' => 'draft',
                'performed_at' => $data['performed_at'] ?? $log->performed_at ?? now(),
                'duration_minutes' => $data['duration_minutes'] ?? null,
                'distance_meters' => isset($data['distance_km']) ? (int) round((float) $data['distance_km'] * 1000) : null,
                'calories' => $data['calories'] ?? null,
                'intensity' => $data['intensity'] ?? null,
                'notes' => $data['notes'] ?? null,
                'trainer_feedback' => $athleteId === (int) $actor->id ? null : ($data['trainer_feedback'] ?? null),
                'metrics' => [
                    'source_kind' => $planItem ? 'planned_training_draft' : 'spontaneous_training_draft',
                    'training_type' => $data['training_type'] ?? null,
                    'privacy_scope' => $data['privacy_scope'] ?? 'trainer',
                    'notify_people' => (bool) ($data['notify_people'] ?? true),
                    'wellness' => array_filter($data['wellness'] ?? [], fn ($value) => $value !== null && $value !== ''),
                    'intended_status' => $data['status'] ?? 'completed',
                    'autosaved_at' => now()->toIso8601String(),
                    'plan_snapshot' => data_get($log->metrics, 'plan_snapshot')
                        ?? $this->planSnapshot($planItem),
                ],
            ]);

            $log->entries()->delete();
            $this->createEntries($log, $data['entries'] ?? [], $request);
        });

        return $log;
    }

    private function createEntries(TrainingLog $log, array $entries, ?Request $request = null): void
    {
        collect($entries)
            ->map(fn ($entry, $inputIndex) => [
                'data' => is_array($entry) ? $entry : [],
                'input_index' => $inputIndex,
            ])
            ->filter(fn ($entry) => trim((string) ($entry['data']['title'] ?? '')) !== '')
            ->values()
            ->each(function (array $entryRow, int $index) use ($log, $request) {
                $entry = $entryRow['data'];
                $inputIndex = $entryRow['input_index'];
                $mediaFile = $request?->file("entries.$inputIndex.media_file");
                $mediaPath = $mediaFile?->store('training-log-media', 'public');

                $log->entries()->create([
                    'title' => trim((string) $entry['title']),
                    'sets' => $entry['sets'] ?? null,
                    'reps' => $entry['reps'] ?? null,
                    'weight_kg' => $entry['weight_kg'] ?? null,
                    'duration_seconds' => isset($entry['duration_minutes']) ? (int) round((float) $entry['duration_minutes'] * 60) : null,
                    'distance_meters' => isset($entry['distance_km']) ? (int) round((float) $entry['distance_km'] * 1000) : null,
                    'intensity' => $entry['intensity'] ?? null,
                    'notes' => $entry['notes'] ?? null,
                    'metrics' => array_filter([
                        'exercise_key' => $entry['exercise_key'] ?? null,
                        'substituted_for' => $entry['substituted_for'] ?? null,
                        'set_index' => $entry['set_index'] ?? null,
                        'tracking_mode' => $entry['tracking_mode'] ?? null,
                        'rest_seconds' => $entry['rest_seconds'] ?? null,
                        'rounds' => $entry['rounds'] ?? null,
                        'completed' => $entry['completed'] ?? null,
                        'skip_reason' => $entry['skip_reason'] ?? null,
                        'media_url' => $entry['media_url'] ?? null,
                        'media_path' => $mediaPath,
                        'uploaded_media_url' => $mediaPath ? Storage::disk('public')->url($mediaPath) : null,
                        'media_mime' => $mediaFile?->getClientMimeType(),
                        'media_original_name' => $mediaFile?->getClientOriginalName(),
                    ], fn ($value) => $value !== null && $value !== ''),
                    'sort_order' => $index + 1,
                ]);
            });
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(
        User $actor,
        array $data,
        int $athleteId,
        ?TrainingPlanItem $planItem,
        ?string $createdFrom,
        ?TrainingLog $draft = null,
    ): array {
        $routeLinks = $this->routeLinks->resolveLogLinks($actor, $athleteId, $data, $planItem);

        return [
            'user_id' => $athleteId,
            'created_by' => $actor->id,
            'trainer_id' => $athleteId === (int) $actor->id ? null : $actor->id,
            'team_id' => $data['team_id'] ?? $planItem?->plan?->team_id,
            'training_plan_id' => $planItem?->training_plan_id,
            'training_plan_item_id' => $planItem?->id,
            ...$routeLinks,
            'sport_type' => $data['sport_type'] ?? $planItem?->sport_type,
            'title' => trim((string) $data['title']),
            'status' => $data['status'],
            'performed_at' => $data['performed_at'] ?? now(),
            'duration_minutes' => $data['duration_minutes'] ?? null,
            'distance_meters' => isset($data['distance_km']) ? (int) round((float) $data['distance_km'] * 1000) : null,
            'calories' => $data['calories'] ?? null,
            'intensity' => $data['intensity'] ?? null,
            'notes' => $data['notes'] ?? null,
            'trainer_feedback' => $athleteId === (int) $actor->id ? null : ($data['trainer_feedback'] ?? null),
            'metrics' => array_filter([
                'source_kind' => $planItem ? 'planned_training' : 'spontaneous_training',
                'created_from' => $createdFrom,
                'training_type' => $data['training_type'] ?? null,
                'privacy_scope' => $data['privacy_scope'] ?? 'trainer',
                'notify_people' => (bool) ($data['notify_people'] ?? true),
                'wellness' => array_filter($data['wellness'] ?? [], fn ($value) => $value !== null && $value !== ''),
                'draft_log_id' => $draft?->id,
                'completed_from_draft_at' => $draft ? now()->toIso8601String() : null,
                'plan_snapshot' => data_get($draft?->metrics, 'plan_snapshot')
                    ?? $this->planSnapshot($planItem),
            ], fn ($value) => $value !== null),
        ];
    }

    /**
     * Capture the prescribed session so later plan edits cannot rewrite history.
     *
     * @return array<string, mixed>|null
     */
    private function planSnapshot(?TrainingPlanItem $planItem): ?array
    {
        if (! $planItem) {
            return null;
        }

        return [
            'captured_at' => now()->toIso8601String(),
            'plan_id' => $planItem->training_plan_id,
            'plan_item_id' => $planItem->id,
            'title' => $planItem->title,
            'sport_type' => $planItem->sport_type,
            'duration_minutes' => $planItem->duration_minutes,
            'distance_meters' => $planItem->distance_meters,
            'intensity' => $planItem->intensity,
            'todos' => $planItem->todos ?? [],
            'metrics' => $planItem->metrics ?? [],
        ];
    }
}
