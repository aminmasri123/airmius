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
    ): TrainingLog {
        return DB::transaction(function () use ($request, $actor, $data, $athleteId, $planItem) {
            $draft = ! empty($data['draft_log_id'])
                ? TrainingLog::query()
                    ->whereKey($data['draft_log_id'])
                    ->where('created_by', $actor->id)
                    ->where('status', 'draft')
                    ->first()
                : null;

            abort_if(! empty($data['draft_log_id']) && ! $draft, 403);

            $payload = [
                'user_id' => $athleteId,
                'created_by' => $actor->id,
                'trainer_id' => $athleteId === (int) $actor->id ? null : $actor->id,
                'team_id' => $data['team_id'] ?? $planItem?->plan?->team_id,
                'training_plan_id' => $planItem?->training_plan_id,
                'training_plan_item_id' => $planItem?->id,
                'sport_type' => $data['sport_type'] ?? $planItem?->sport_type,
                'title' => $data['title'],
                'status' => $data['status'],
                'performed_at' => $data['performed_at'] ?? now(),
                'duration_minutes' => $data['duration_minutes'] ?? null,
                'distance_meters' => isset($data['distance_km']) ? (int) round((float) $data['distance_km'] * 1000) : null,
                'calories' => $data['calories'] ?? null,
                'intensity' => $data['intensity'] ?? null,
                'notes' => $data['notes'] ?? null,
                'trainer_feedback' => $athleteId === (int) $actor->id ? null : ($data['trainer_feedback'] ?? null),
                'metrics' => [
                    'source_kind' => $planItem ? 'planned_training' : 'spontaneous_training',
                    'training_type' => $data['training_type'] ?? null,
                    'privacy_scope' => $data['privacy_scope'] ?? 'trainer',
                    'notify_people' => (bool) ($data['notify_people'] ?? true),
                    'wellness' => array_filter($data['wellness'] ?? [], fn ($value) => $value !== null && $value !== ''),
                    'draft_log_id' => $draft?->id,
                    'completed_from_draft_at' => $draft ? now()->toIso8601String() : null,
                ],
            ];

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

    public function updateDraftFromPayload(
        TrainingLog $log,
        User $actor,
        array $data,
        int $athleteId,
        ?TrainingPlanItem $planItem = null,
        ?Request $request = null,
    ): TrainingLog {
        DB::transaction(function () use ($request, $log, $actor, $data, $athleteId, $planItem) {
            $log->update([
                'user_id' => $athleteId,
                'created_by' => $actor->id,
                'trainer_id' => $athleteId === (int) $actor->id ? null : $actor->id,
                'team_id' => $data['team_id'] ?? $planItem?->plan?->team_id,
                'training_plan_id' => $planItem?->training_plan_id,
                'training_plan_item_id' => $planItem?->id,
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
}
