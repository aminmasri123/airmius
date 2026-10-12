<?php

namespace App\Services\Training;

use App\Models\Event;
use App\Models\TrainingSession;
use App\Models\User;
use App\Support\EventCreationPermissions;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class TrainingSessionEventLinkService
{
    public function selectable(User $user): Collection
    {
        $teamIds = EventCreationPermissions::availableTeams($user)->pluck('id');
        $clubIds = $user->clubs()->pluck('clubs.id');

        return TrainingSession::query()
            ->where('is_active', true)
            ->where(function ($query) use ($user, $teamIds, $clubIds): void {
                $query->where('created_by', $user->id)
                    ->when($teamIds->isNotEmpty(), fn ($query) => $query->orWhereIn('team_id', $teamIds))
                    ->when($clubIds->isNotEmpty(), fn ($query) => $query->orWhereIn('club_id', $clubIds));
            })
            ->select(['id', 'club_id', 'team_id', 'title', 'duration_minutes', 'status', 'revision'])
            ->orderBy('title')
            ->get();
    }

    public function apply(User $user, array $data, ?Event $event = null): array
    {
        if (! array_key_exists('training_session_id', $data)) {
            return $data;
        }

        if (blank($data['training_session_id'])) {
            $data['training_session_id'] = null;
            $data['training_session_snapshot'] = null;

            return $data;
        }

        if (($data['type'] ?? $event?->type) !== 'training') {
            throw ValidationException::withMessages([
                'training_session_id' => 'Ein Trainingsplan kann nur mit einem Training verknüpft werden.',
            ]);
        }

        $session = $this->selectable($user)->firstWhere('id', (int) $data['training_session_id']);
        if (! $session) {
            throw ValidationException::withMessages([
                'training_session_id' => 'Diese Trainingseinheit ist nicht verfügbar.',
            ]);
        }

        $teamId = (int) ($data['team_id'] ?? $event?->team_id ?? 0);
        $clubId = (int) ($data['club_id'] ?? $event?->club_id ?? $event?->team?->club_id ?? 0);
        if ($session->team_id && (int) $session->team_id !== $teamId) {
            throw ValidationException::withMessages([
                'training_session_id' => 'Die Trainingseinheit gehört zu einem anderen Team.',
            ]);
        }
        if ($session->club_id && (int) $session->club_id !== $clubId) {
            throw ValidationException::withMessages([
                'training_session_id' => 'Die Trainingseinheit gehört zu einem anderen Verein.',
            ]);
        }

        $fullSession = TrainingSession::query()->findOrFail($session->id);
        $data['training_session_id'] = $fullSession->id;
        $data['training_session_snapshot'] = $fullSession->only([
            'id', 'title', 'description', 'goals', 'phases', 'exercises', 'materials',
            'duration_minutes', 'revision',
        ]);

        return $data;
    }
}
