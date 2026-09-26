<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\Team;
use App\Models\TrainingExercise;
use App\Models\TrainingSession;
use App\Models\User;
use App\Support\ClubPermissions;
use App\Support\TeamRoles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TrainingSessionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $clubIds = $this->viewableClubIds($user);
        $teamIds = $this->viewableTeamIds($user);

        $sessions = TrainingSession::query()
            ->with(['creator:id,name', 'club:id,name', 'team:id,name'])
            ->where('is_active', true)
            ->where(function ($query) use ($user, $clubIds, $teamIds) {
                $query
                    ->where(fn ($personal) => $personal
                        ->where('created_by', $user->id)
                        ->whereNull('club_id')
                        ->whereNull('team_id'))
                    ->orWhereIn('club_id', $clubIds)
                    ->orWhereIn('team_id', $teamIds);
            })
            ->when($request->filled('q'), function ($query) use ($request) {
                $needle = trim((string) $request->query('q'));
                $query->where(function ($search) use ($needle) {
                    $search->where('title', 'like', "%{$needle}%")
                        ->orWhere('description', 'like', "%{$needle}%");
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->latest()
            ->limit(min(100, max(1, (int) $request->query('limit', 50))))
            ->get();

        return response()->json(['data' => $sessions->map(fn (TrainingSession $session) => $this->payload($session, $user))->values()]);
    }

    public function show(Request $request, TrainingSession $trainingSession)
    {
        abort_unless($this->visibleTo($trainingSession, $request->user()), 404);

        return response()->json([
            'data' => $this->payload(
                $trainingSession->load(['creator:id,name', 'club:id,name', 'team:id,name', 'versions.creator:id,name']),
                $request->user(),
                true,
            ),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateSession($request);
        [$club, $team] = $this->resolveScope($request->user(), $data);
        $this->ensureReferencedExercisesBelongToScope($data, $club, $team, $request->user());

        $session = DB::transaction(function () use ($request, $data, $club, $team) {
            $session = TrainingSession::query()->create([
                ...$this->sessionAttributes($data),
                'created_by' => $request->user()->id,
                'club_id' => $club?->id,
                'team_id' => $team?->id,
                'revision' => 1,
                'is_active' => true,
            ]);

            $this->recordVersion($session, $request->user(), $data['change_note'] ?? 'Initial version');

            return $session;
        });

        return response()->json(['data' => $this->payload($session->load(['creator:id,name', 'club:id,name', 'team:id,name', 'versions.creator:id,name']), $request->user(), true)], 201);
    }

    public function update(Request $request, TrainingSession $trainingSession)
    {
        abort_unless($this->canEdit($trainingSession, $request->user()), 403);

        $data = $this->validateSession($request, false);
        $this->ensureReferencedExercisesBelongToScope(
            $data,
            $trainingSession->club_id ? $trainingSession->club()->firstOrFail() : null,
            $trainingSession->team_id ? $trainingSession->team()->with('club')->firstOrFail() : null,
            $request->user(),
        );

        $session = DB::transaction(function () use ($request, $trainingSession, $data) {
            $trainingSession->update([
                ...$this->sessionAttributes($data),
                'revision' => ((int) $trainingSession->revision) + 1,
            ]);
            $trainingSession->refresh();
            $this->recordVersion($trainingSession, $request->user(), $data['change_note'] ?? null);

            return $trainingSession;
        });

        return response()->json(['data' => $this->payload($session->load(['creator:id,name', 'club:id,name', 'team:id,name', 'versions.creator:id,name']), $request->user(), true)]);
    }

    public function destroy(Request $request, TrainingSession $trainingSession)
    {
        abort_unless($this->canDelete($trainingSession, $request->user()), 403);

        $trainingSession->update(['is_active' => false]);

        return response()->json(['message' => __('server.training.session_deleted')]);
    }

    private function validateSession(Request $request, bool $withScope = true): array
    {
        return $request->validate([
            'scope' => [$withScope ? 'required' : 'sometimes', Rule::in(['personal', 'club', 'team'])],
            'club_id' => ['nullable', 'integer', 'exists:clubs,id'],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'title' => ['required', 'string', 'min:2', 'max:180'],
            'description' => ['nullable', 'string', 'max:3000'],
            'goals' => ['nullable', 'array', 'max:12'],
            'goals.*' => ['string', 'max:240'],
            'phases' => ['nullable', 'array', 'max:12'],
            'phases.*.title' => ['required_with:phases', 'string', 'max:120'],
            'phases.*.goal' => ['nullable', 'string', 'max:240'],
            'phases.*.duration_minutes' => ['nullable', 'integer', 'min:0', 'max:600'],
            'phases.*.notes' => ['nullable', 'string', 'max:1200'],
            'exercises' => ['nullable', 'array', 'max:40'],
            'exercises.*.training_exercise_id' => ['nullable', 'integer', 'exists:training_exercises,id'],
            'exercises.*.title' => ['required_with:exercises', 'string', 'max:160'],
            'exercises.*.phase' => ['nullable', 'string', 'max:120'],
            'exercises.*.sets' => ['nullable', 'integer', 'min:0', 'max:100'],
            'exercises.*.reps' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'exercises.*.duration_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'exercises.*.notes' => ['nullable', 'string', 'max:1200'],
            'materials' => ['nullable', 'array', 'max:30'],
            'materials.*' => ['string', 'max:120'],
            'duration_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'status' => ['required', Rule::in(['draft', 'ready', 'archived'])],
            'change_note' => ['nullable', 'string', 'max:500'],
        ]);
    }

    private function resolveScope(User $user, array $data): array
    {
        $scope = $data['scope'];
        if ($scope === 'personal') {
            abort_if(isset($data['club_id']) || isset($data['team_id']), 422, 'Persönliche Trainingseinheiten dürfen keinen Vereins- oder Teambezug haben.');

            return [null, null];
        }

        if ($scope === 'club') {
            abort_if(empty($data['club_id']) || ! empty($data['team_id']), 422, 'Für Vereinseinheiten ist nur ein Verein erforderlich.');
            $club = Club::query()->findOrFail($data['club_id']);
            abort_unless($this->canEditClub($club, $user), 403);

            return [$club, null];
        }

        abort_if(empty($data['team_id']), 422, 'Für Mannschaftseinheiten ist ein Team erforderlich.');
        $team = Team::query()->with('club')->findOrFail($data['team_id']);
        abort_if(! empty($data['club_id']) && (int) $data['club_id'] !== (int) $team->club_id, 422, 'Verein und Team passen nicht zusammen.');
        abort_unless($this->canEditTeam($team, $user), 403);

        return [$team->club, $team];
    }

    private function sessionAttributes(array $data): array
    {
        return [
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'goals' => $this->listValue($data['goals'] ?? []),
            'phases' => $this->phaseValue($data['phases'] ?? []),
            'exercises' => $this->exerciseValue($data['exercises'] ?? []),
            'materials' => $this->listValue($data['materials'] ?? []),
            'duration_minutes' => $data['duration_minutes'] ?? null,
            'status' => $data['status'],
        ];
    }

    private function ensureReferencedExercisesBelongToScope(array $data, ?Club $club, ?Team $team, User $user): void
    {
        $exerciseIds = collect($data['exercises'] ?? [])
            ->pluck('training_exercise_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($exerciseIds->isEmpty()) {
            return;
        }

        $exercises = TrainingExercise::query()
            ->whereIn('id', $exerciseIds)
            ->where('is_active', true)
            ->get();

        abort_if($exercises->count() !== $exerciseIds->count(), 422, 'Mindestens eine Übung ist nicht verfügbar.');

        foreach ($exercises as $exercise) {
            $isPersonal = (int) $exercise->created_by === (int) $user->id && ! $exercise->club_id && ! $exercise->team_id;
            $sameTeam = $team && (int) $exercise->team_id === (int) $team->id;
            $sameClub = $club && (int) $exercise->club_id === (int) $club->id && ! $exercise->team_id;

            abort_unless($isPersonal || $sameTeam || $sameClub, 422, 'Übungen aus einem anderen Vereinsbereich dürfen nicht verwendet werden.');
        }
    }

    private function recordVersion(TrainingSession $session, User $user, ?string $changeNote): void
    {
        $session->versions()->create([
            'created_by' => $user->id,
            'revision' => $session->revision,
            'change_note' => $changeNote,
            'snapshot' => [
                'title' => $session->title,
                'description' => $session->description,
                'goals' => $session->goals ?? [],
                'phases' => $session->phases ?? [],
                'exercises' => $session->exercises ?? [],
                'materials' => $session->materials ?? [],
                'duration_minutes' => $session->duration_minutes,
                'status' => $session->status,
                'scope' => $session->team_id ? 'team' : ($session->club_id ? 'club' : 'personal'),
                'club_id' => $session->club_id,
                'team_id' => $session->team_id,
            ],
        ]);
    }

    private function visibleTo(TrainingSession $session, User $user): bool
    {
        if ((int) $session->created_by === (int) $user->id && ! $session->club_id && ! $session->team_id) {
            return true;
        }

        if ($session->team_id) {
            return $this->canViewTeam($session->team()->with('club')->firstOrFail(), $user);
        }

        return $session->club_id
            ? $this->canViewClub($session->club()->firstOrFail(), $user)
            : false;
    }

    private function canEdit(TrainingSession $session, User $user): bool
    {
        if ((int) $session->created_by === (int) $user->id && ! $session->club_id && ! $session->team_id) {
            return true;
        }

        if ($session->team_id) {
            return $this->canEditTeam($session->team()->with('club')->firstOrFail(), $user);
        }

        return $session->club_id
            ? $this->canEditClub($session->club()->firstOrFail(), $user)
            : false;
    }

    private function canDelete(TrainingSession $session, User $user): bool
    {
        if ((int) $session->created_by === (int) $user->id && ! $session->club_id && ! $session->team_id) {
            return true;
        }

        if ($session->team_id) {
            return $this->canDeleteTeam($session->team()->with('club')->firstOrFail(), $user);
        }

        return $session->club_id
            ? $this->canDeleteClub($session->club()->firstOrFail(), $user)
            : false;
    }

    private function canViewClub(Club $club, User $user): bool
    {
        return ClubPermissions::allows($club, $user, ClubPermissions::TRAINING_SESSIONS_VIEW);
    }

    private function canEditClub(Club $club, User $user): bool
    {
        return ClubPermissions::allows($club, $user, ClubPermissions::TRAINING_SESSIONS_EDIT);
    }

    private function canDeleteClub(Club $club, User $user): bool
    {
        return ClubPermissions::allows($club, $user, ClubPermissions::TRAINING_SESSIONS_DELETE);
    }

    private function canViewTeam(Team $team, User $user): bool
    {
        return $this->canViewClub($team->club, $user)
            || ClubPermissions::allowsForTeam($team, $user, ClubPermissions::TRAINING_SESSIONS_VIEW)
            || (! ClubPermissions::explicitlyDenies($team->club, $user, ClubPermissions::TRAINING_SESSIONS_VIEW)
                && $team->users()->whereKey($user->id)->exists());
    }

    private function canEditTeam(Team $team, User $user): bool
    {
        return $this->canEditClub($team->club, $user)
            || ClubPermissions::allowsForTeam($team, $user, ClubPermissions::TRAINING_SESSIONS_EDIT)
            || (! ClubPermissions::explicitlyDenies($team->club, $user, ClubPermissions::TRAINING_SESSIONS_EDIT)
                && $team->users()->whereKey($user->id)->wherePivotIn('role', TeamRoles::TEAM_STAFF_ROLES)->exists());
    }

    private function canDeleteTeam(Team $team, User $user): bool
    {
        return $this->canDeleteClub($team->club, $user)
            || ClubPermissions::allowsForTeam($team, $user, ClubPermissions::TRAINING_SESSIONS_DELETE)
            || (! ClubPermissions::explicitlyDenies($team->club, $user, ClubPermissions::TRAINING_SESSIONS_DELETE)
                && $team->users()->whereKey($user->id)->wherePivotIn('role', TeamRoles::TEAM_STAFF_ROLES)->exists());
    }

    private function viewableClubIds(User $user)
    {
        return Club::query()
            ->where(function ($query) use ($user) {
                $query->where('owner_id', $user->id)
                    ->orWhereHas('users', fn ($members) => $members->where('users.id', $user->id));
            })
            ->get()
            ->filter(fn (Club $club) => $this->canViewClub($club, $user))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();
    }

    private function viewableTeamIds(User $user)
    {
        return Team::query()
            ->with('club')
            ->where(function ($query) use ($user): void {
                $query->whereHas('users', fn ($members) => $members->where('users.id', $user->id))
                    ->orWhereHas('club.users', fn ($members) => $members->where('users.id', $user->id));
            })
            ->get()
            ->filter(fn (Team $team) => $this->canViewTeam($team, $user))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();
    }

    private function payload(TrainingSession $session, User $viewer, bool $withVersions = false): array
    {
        return [
            'id' => $session->id,
            'title' => $session->title,
            'description' => $session->description,
            'goals' => $session->goals ?? [],
            'phases' => $session->phases ?? [],
            'exercises' => $session->exercises ?? [],
            'materials' => $session->materials ?? [],
            'duration_minutes' => $session->duration_minutes,
            'status' => $session->status,
            'revision' => $session->revision,
            'scope' => $session->team_id ? 'team' : ($session->club_id ? 'club' : 'personal'),
            'club' => $session->club ? ['id' => $session->club->id, 'name' => $session->club->name] : null,
            'team' => $session->team ? ['id' => $session->team->id, 'name' => $session->team->name] : null,
            'creator' => $session->creator ? ['id' => $session->creator->id, 'name' => $session->creator->name] : null,
            'can_edit' => $this->canEdit($session, $viewer),
            'can_delete' => $this->canDelete($session, $viewer),
            'versions' => $withVersions && $session->relationLoaded('versions')
                ? $session->versions->map(fn ($version) => [
                    'id' => $version->id,
                    'revision' => $version->revision,
                    'change_note' => $version->change_note,
                    'snapshot' => $version->snapshot,
                    'creator' => $version->creator ? ['id' => $version->creator->id, 'name' => $version->creator->name] : null,
                    'created_at' => $version->created_at?->toIso8601String(),
                ])->values()
                : [],
            'created_at' => $session->created_at?->toIso8601String(),
            'updated_at' => $session->updated_at?->toIso8601String(),
        ];
    }

    private function listValue(array $values): array
    {
        return collect($values)
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function phaseValue(array $values): array
    {
        return collect($values)
            ->map(fn (array $phase) => [
                'title' => trim((string) $phase['title']),
                'goal' => trim((string) ($phase['goal'] ?? '')),
                'duration_minutes' => isset($phase['duration_minutes']) ? (int) $phase['duration_minutes'] : null,
                'notes' => trim((string) ($phase['notes'] ?? '')),
            ])
            ->filter(fn (array $phase) => $phase['title'] !== '')
            ->values()
            ->all();
    }

    private function exerciseValue(array $values): array
    {
        return collect($values)
            ->map(fn (array $exercise) => [
                'training_exercise_id' => isset($exercise['training_exercise_id']) ? (int) $exercise['training_exercise_id'] : null,
                'title' => trim((string) $exercise['title']),
                'phase' => trim((string) ($exercise['phase'] ?? '')),
                'sets' => isset($exercise['sets']) ? (int) $exercise['sets'] : null,
                'reps' => isset($exercise['reps']) ? (int) $exercise['reps'] : null,
                'duration_seconds' => isset($exercise['duration_seconds']) ? (int) $exercise['duration_seconds'] : null,
                'notes' => trim((string) ($exercise['notes'] ?? '')),
            ])
            ->filter(fn (array $exercise) => $exercise['title'] !== '')
            ->values()
            ->all();
    }
}
