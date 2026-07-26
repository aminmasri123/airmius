<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\TrainingAvailabilityStatus;
use App\Models\TrainingLog;
use App\Models\User;
use App\Support\TeamRoles;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TrainingAvailabilityController extends Controller
{
    private const STATUSES = ['available', 'limited', 'unavailable', 'injured', 'ill'];
    private const VISIBILITIES = ['private', 'trainer', 'team'];

    public function index(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $viewer = $request->user();
        $athleteId = (int) ($data['user_id'] ?? $viewer->id);
        $access = $this->accessFor($viewer, $athleteId);
        abort_unless($access !== null, 403);
        $self = $athleteId === (int) $viewer->id;

        $query = TrainingAvailabilityStatus::query()
            ->where('user_id', $athleteId)
            ->orderByDesc('starts_on')
            ->orderByDesc('id');

        if (! $self) {
            $query->whereIn('visibility', $access === 'staff' ? ['trainer', 'team'] : ['team']);
        }

        $rows = $query->limit(24)->get();
        $current = $rows->first(fn (TrainingAvailabilityStatus $status) => $status->isActive());

        return response()->json([
            'data' => [
                'athlete_id' => $athleteId,
                'can_edit' => $self,
                'current' => $current ? $this->payload($current, $self) : null,
                'history' => $rows->map(fn (TrainingAvailabilityStatus $status) => $this->payload($status, $self))->values(),
                'privacy' => [
                    'scope' => $self ? 'self' : ($access === 'staff' ? 'trainer_shared' : 'team_shared'),
                    'notes_visible' => $self,
                ],
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());
        $user = $request->user();

        TrainingAvailabilityStatus::query()
            ->where('user_id', $user->id)
            ->whereNull('cleared_at')
            ->whereDate('starts_on', '<=', $data['starts_on'])
            ->update(['cleared_at' => now()]);

        $status = TrainingAvailabilityStatus::query()->create([
            'user_id' => $user->id,
            'created_by' => $user->id,
            ...$data,
        ]);

        return response()->json(['data' => $this->payload($status, true)], 201);
    }

    public function update(Request $request, TrainingAvailabilityStatus $trainingAvailabilityStatus)
    {
        abort_unless((int) $trainingAvailabilityStatus->user_id === (int) $request->user()->id, 403);
        $data = $request->validate($this->rules());
        $trainingAvailabilityStatus->update($data);

        return response()->json(['data' => $this->payload($trainingAvailabilityStatus->fresh(), true)]);
    }

    public function destroy(Request $request, TrainingAvailabilityStatus $trainingAvailabilityStatus)
    {
        abort_unless((int) $trainingAvailabilityStatus->user_id === (int) $request->user()->id, 403);
        $trainingAvailabilityStatus->forceFill(['cleared_at' => now()])->save();

        return response()->json(['data' => ['id' => $trainingAvailabilityStatus->id, 'cleared' => true]]);
    }

    private function rules(): array
    {
        return [
            'status' => ['required', Rule::in(self::STATUSES)],
            'visibility' => ['required', Rule::in(self::VISIBILITIES)],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    private function payload(TrainingAvailabilityStatus $status, bool $self): array
    {
        return [
            'id' => $status->id,
            'status' => $status->status,
            'visibility' => $status->visibility,
            'starts_on' => $status->starts_on?->toDateString(),
            'ends_on' => $status->ends_on?->toDateString(),
            'is_active' => $status->isActive(),
            'cleared_at' => $status->cleared_at?->toIso8601String(),
            // Medical/personal notes never leave the server for a shared view.
            'note' => $self ? $status->note : null,
        ];
    }

    private function accessFor(User $viewer, int $athleteId): ?string
    {
        if ((int) $viewer->id === $athleteId) {
            return 'self';
        }

        $viewerTeams = $viewer->teams()->get();
        $allTeamIds = $viewerTeams->pluck('id');
        $staffTeamIds = $viewerTeams->filter(function (Team $team) {
            $role = strtolower((string) ($team->pivot?->role ?? ''));
            return in_array($role, array_map('strtolower', TeamRoles::TEAM_STAFF_ROLES), true);
        })->pluck('id');

        if ($staffTeamIds->isNotEmpty() && Team::query()
            ->whereIn('id', $staffTeamIds)
            ->whereHas('users', fn ($query) => $query->whereKey($athleteId))
            ->exists()) {
            return 'staff';
        }

        if ($allTeamIds->isNotEmpty() && Team::query()
            ->whereIn('id', $allTeamIds)
            ->whereHas('users', fn ($query) => $query->whereKey($athleteId))
            ->exists()) {
            return 'team';
        }

        return TrainingLog::query()
            ->where('trainer_id', $viewer->id)
            ->where('user_id', $athleteId)
            ->exists() ? 'staff' : null;
    }
}
