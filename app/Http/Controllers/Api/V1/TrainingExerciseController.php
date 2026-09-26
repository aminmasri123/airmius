<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\Team;
use App\Models\TrainingExercise;
use App\Models\TrainingPlan;
use App\Models\User;
use App\Services\PlanFeatureService;
use App\Services\Training\TrainingResourceService;
use App\Support\ClubPermissions;
use App\Support\TeamRoles;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TrainingExerciseController extends Controller
{
    public function __construct(
        private readonly PlanFeatureService $planFeatures,
        private readonly TrainingResourceService $resources,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $clubIds = $this->viewableClubIds($user);
        $teamIds = $this->viewableTeamIds($user);

        $exercises = TrainingExercise::query()
            ->with(['creator:id,name', 'club:id,name', 'team:id,name'])
            ->where('is_active', true)
            ->where(function ($query) use ($user, $clubIds, $teamIds) {
                $query
                    ->where('created_by', $user->id)
                    ->orWhereIn('club_id', $clubIds)
                    ->orWhereIn('team_id', $teamIds);
            })
            ->when($request->filled('q'), function ($query) use ($request) {
                $needle = trim((string) $request->query('q'));
                $query->where(function ($search) use ($needle) {
                    $search->where('name', 'like', "%{$needle}%")
                        ->orWhere('sport_type', 'like', "%{$needle}%")
                        ->orWhere('description', 'like', "%{$needle}%");
                });
            })
            ->when($request->filled('sport_type'), fn ($query) => $query->where('sport_type', $request->query('sport_type')))
            ->when($request->filled('age_group'), fn ($query) => $query->where('target_age_group', $request->query('age_group')))
            ->when($request->filled('level'), fn ($query) => $query->where(function ($levels) use ($request) {
                $levels->where('target_level', $request->query('level'))->orWhereNull('target_level');
            }))
            ->when($request->filled('focus'), fn ($query) => $query->whereJsonContains('focus_areas', $request->query('focus')))
            ->orderBy('name')
            ->limit(min(100, max(1, (int) $request->query('limit', 50))))
            ->get();

        return response()->json(['data' => $exercises->map(fn (TrainingExercise $exercise) => $this->payload($exercise, $user))->values()]);
    }

    public function show(Request $request, TrainingExercise $trainingExercise)
    {
        abort_unless($this->visibleTo($trainingExercise, $request->user()), 404);

        return response()->json(['data' => $this->payload($trainingExercise->load(['creator:id,name', 'club:id,name', 'team:id,name']), $request->user())]);
    }

    public function store(Request $request)
    {
        $data = $this->validateExercise($request);
        [$club, $team] = $this->resolveScope($request->user(), $data);

        $exercise = TrainingExercise::query()->create([
            'created_by' => $request->user()->id,
            'club_id' => $club?->id,
            'team_id' => $team?->id,
            'name' => $data['name'],
            'sport_type' => $data['sport_type'] ?? null,
            'target_age_group' => $data['target_age_group'] ?? null,
            'target_level' => $data['target_level'] ?? null,
            'description' => $data['description'] ?? null,
            'instructions' => $data['instructions'] ?? null,
            'equipment' => $this->listValue($data['equipment'] ?? []),
            'muscle_groups' => $this->listValue($data['muscle_groups'] ?? []),
            'focus_areas' => $this->listValue($data['focus_areas'] ?? []),
            'protected_media' => $this->protectedMediaValue($data['protected_media'] ?? []),
            'difficulty' => $data['difficulty'],
            'is_active' => true,
        ]);

        return response()->json(['data' => $this->payload($exercise->load(['creator:id,name', 'club:id,name', 'team:id,name']), $request->user())], 201);
    }

    public function update(Request $request, TrainingExercise $trainingExercise)
    {
        abort_unless($this->canEdit($trainingExercise, $request->user()), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:160'],
            'sport_type' => ['nullable', 'string', 'max:80'],
            'target_age_group' => ['nullable', 'string', 'max:40'],
            'target_level' => ['nullable', Rule::in(['all', 'beginner', 'intermediate', 'advanced'])],
            'description' => ['nullable', 'string', 'max:3000'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'equipment' => ['nullable', 'array', 'max:20'],
            'equipment.*' => ['string', 'max:80'],
            'muscle_groups' => ['nullable', 'array', 'max:20'],
            'muscle_groups.*' => ['string', 'max:80'],
            'focus_areas' => ['nullable', 'array', 'max:20'],
            'focus_areas.*' => ['string', 'max:80'],
            'protected_media' => ['nullable', 'array', 'max:10'],
            'protected_media.*.file_id' => ['required_with:protected_media', 'integer', 'exists:files,id'],
            'protected_media.*.kind' => ['required_with:protected_media', Rule::in(['image', 'video', 'document'])],
            'protected_media.*.caption' => ['nullable', 'string', 'max:160'],
            'difficulty' => ['required', Rule::in(['all', 'beginner', 'intermediate', 'advanced'])],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $trainingExercise->update([
            ...$data,
            'equipment' => $this->listValue($data['equipment'] ?? []),
            'muscle_groups' => $this->listValue($data['muscle_groups'] ?? []),
            'focus_areas' => $this->listValue($data['focus_areas'] ?? []),
            'protected_media' => $this->protectedMediaValue($data['protected_media'] ?? []),
        ]);

        return response()->json(['data' => $this->payload($trainingExercise->refresh()->load(['creator:id,name', 'club:id,name', 'team:id,name']), $request->user())]);
    }

    public function destroy(Request $request, TrainingExercise $trainingExercise)
    {
        abort_unless($this->canDelete($trainingExercise, $request->user()), 403);

        $trainingExercise->update(['is_active' => false]);

        return response()->json(['message' => __('platform.training.exercise_removed')]);
    }

    public function addToPlan(Request $request, TrainingExercise $trainingExercise)
    {
        abort_unless($this->visibleTo($trainingExercise, $request->user()), 404);
        $data = $request->validate(['training_plan_id' => ['required', 'integer', 'exists:training_plans,id']]);
        $plan = TrainingPlan::query()->findOrFail($data['training_plan_id']);
        abort_unless($this->resources->canWritePlan($request->user(), $plan), 403);

        $item = $plan->items()->create([
            'source_exercise_id' => $trainingExercise->id,
            'title' => $trainingExercise->name,
            'sport_type' => $trainingExercise->sport_type,
            'description' => trim(implode("\n\n", array_filter([$trainingExercise->description, $trainingExercise->instructions]))),
            'todos' => $trainingExercise->equipment ?? [],
            'metrics' => ['difficulty' => $trainingExercise->difficulty, 'muscle_groups' => $trainingExercise->muscle_groups ?? []],
            'sort_order' => $plan->items()->count() + 1,
        ]);

        return response()->json(['data' => ['id' => $item->id, 'training_plan_id' => $plan->id, 'source_exercise_id' => $trainingExercise->id, 'title' => $item->title]], 201);
    }

    private function validateExercise(Request $request): array
    {
        return $request->validate([
            'scope' => ['required', Rule::in(['personal', 'club', 'team'])],
            'club_id' => ['nullable', 'integer', 'exists:clubs,id'],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'name' => ['required', 'string', 'min:2', 'max:160'],
            'sport_type' => ['nullable', 'string', 'max:80'],
            'target_age_group' => ['nullable', 'string', 'max:40'],
            'target_level' => ['nullable', Rule::in(['all', 'beginner', 'intermediate', 'advanced'])],
            'description' => ['nullable', 'string', 'max:3000'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'equipment' => ['nullable', 'array', 'max:20'],
            'equipment.*' => ['string', 'max:80'],
            'muscle_groups' => ['nullable', 'array', 'max:20'],
            'muscle_groups.*' => ['string', 'max:80'],
            'focus_areas' => ['nullable', 'array', 'max:20'],
            'focus_areas.*' => ['string', 'max:80'],
            'protected_media' => ['nullable', 'array', 'max:10'],
            'protected_media.*.file_id' => ['required_with:protected_media', 'integer', 'exists:files,id'],
            'protected_media.*.kind' => ['required_with:protected_media', Rule::in(['image', 'video', 'document'])],
            'protected_media.*.caption' => ['nullable', 'string', 'max:160'],
            'difficulty' => ['required', Rule::in(['all', 'beginner', 'intermediate', 'advanced'])],
        ]);
    }

    private function resolveScope($user, array $data): array
    {
        $scope = $data['scope'];
        if ($scope === 'personal') {
            abort_if(isset($data['club_id']) || isset($data['team_id']), 422, 'Persönliche Übungen dürfen keinen Vereins- oder Teambezug haben.');

            return [null, null];
        }

        if ($scope === 'club') {
            abort_if(empty($data['club_id']) || ! empty($data['team_id']), 422, 'Für Vereinsübungen ist nur ein Verein erforderlich.');
            $club = Club::query()->findOrFail($data['club_id']);
            abort_unless($this->canEditClub($club, $user), 403);
            $this->planFeatures->ensureAllows($club, 'exercise_library_custom');

            return [$club, null];
        }

        abort_if(empty($data['team_id']), 422, 'Für Teamübungen ist ein Team erforderlich.');
        $team = Team::query()->with('club')->findOrFail($data['team_id']);
        abort_if(! empty($data['club_id']) && (int) $data['club_id'] !== (int) $team->club_id, 422, 'Verein und Team passen nicht zusammen.');
        abort_unless($this->canEditTeam($team, $user), 403);
        $this->planFeatures->ensureAllows($team->club, 'exercise_library_custom');

        return [$team->club, $team];
    }

    private function visibleTo(TrainingExercise $exercise, $user): bool
    {
        if ((int) $exercise->created_by === (int) $user->id && ! $exercise->club_id && ! $exercise->team_id) {
            return true;
        }

        if ($exercise->team_id) {
            return $this->canViewTeam($exercise->team()->with('club')->firstOrFail(), $user);
        }

        return $exercise->club_id
            ? $this->canViewClub($exercise->club()->firstOrFail(), $user)
            : false;
    }

    private function canEdit(TrainingExercise $exercise, User $user): bool
    {
        if ((int) $exercise->created_by === (int) $user->id && ! $exercise->club_id && ! $exercise->team_id) {
            return true;
        }

        if ($exercise->team_id) {
            return $this->canEditTeam($exercise->team()->with('club')->firstOrFail(), $user);
        }

        return $exercise->club_id
            ? $this->canEditClub($exercise->club()->firstOrFail(), $user)
            : false;
    }

    private function canDelete(TrainingExercise $exercise, User $user): bool
    {
        if ((int) $exercise->created_by === (int) $user->id && ! $exercise->club_id && ! $exercise->team_id) {
            return true;
        }

        if ($exercise->team_id) {
            return $this->canDeleteTeam($exercise->team()->with('club')->firstOrFail(), $user);
        }

        return $exercise->club_id
            ? $this->canDeleteClub($exercise->club()->firstOrFail(), $user)
            : false;
    }

    private function canViewClub(Club $club, User $user): bool
    {
        return ClubPermissions::allows($club, $user, ClubPermissions::TRAINING_EXERCISES_VIEW);
    }

    private function canEditClub(Club $club, User $user): bool
    {
        return ClubPermissions::allows($club, $user, ClubPermissions::TRAINING_EXERCISES_EDIT);
    }

    private function canDeleteClub(Club $club, User $user): bool
    {
        return ClubPermissions::allows($club, $user, ClubPermissions::TRAINING_EXERCISES_DELETE);
    }

    private function canViewTeam(Team $team, User $user): bool
    {
        return $this->canViewClub($team->club, $user)
            || ClubPermissions::allowsForTeam($team, $user, ClubPermissions::TRAINING_EXERCISES_VIEW)
            || (! ClubPermissions::explicitlyDenies($team->club, $user, ClubPermissions::TRAINING_EXERCISES_VIEW)
                && $team->users()->whereKey($user->id)->exists());
    }

    private function canEditTeam(Team $team, User $user): bool
    {
        return $this->canEditClub($team->club, $user)
            || ClubPermissions::allowsForTeam($team, $user, ClubPermissions::TRAINING_EXERCISES_EDIT)
            || (! ClubPermissions::explicitlyDenies($team->club, $user, ClubPermissions::TRAINING_EXERCISES_EDIT)
                && $team->users()->whereKey($user->id)->wherePivotIn('role', TeamRoles::TEAM_STAFF_ROLES)->exists());
    }

    private function canDeleteTeam(Team $team, User $user): bool
    {
        return $this->canDeleteClub($team->club, $user)
            || ClubPermissions::allowsForTeam($team, $user, ClubPermissions::TRAINING_EXERCISES_DELETE)
            || (! ClubPermissions::explicitlyDenies($team->club, $user, ClubPermissions::TRAINING_EXERCISES_DELETE)
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

    private function payload(TrainingExercise $exercise, $viewer): array
    {
        return [
            'id' => $exercise->id,
            'name' => $exercise->name,
            'sport_type' => $exercise->sport_type,
            'target_age_group' => $exercise->target_age_group,
            'target_level' => $exercise->target_level,
            'description' => $exercise->description,
            'instructions' => $exercise->instructions,
            'equipment' => $exercise->equipment ?? [],
            'muscle_groups' => $exercise->muscle_groups ?? [],
            'focus_areas' => $exercise->focus_areas ?? [],
            'protected_media' => $this->mediaPayload($exercise),
            'difficulty' => $exercise->difficulty,
            'scope' => $exercise->team_id ? 'team' : ($exercise->club_id ? 'club' : 'personal'),
            'club' => $exercise->club ? ['id' => $exercise->club->id, 'name' => $exercise->club->name] : null,
            'team' => $exercise->team ? ['id' => $exercise->team->id, 'name' => $exercise->team->name] : null,
            'creator' => $exercise->creator ? ['id' => $exercise->creator->id, 'name' => $exercise->creator->name] : null,
            'can_edit' => $this->canEdit($exercise, $viewer),
            'can_delete' => $this->canDelete($exercise, $viewer),
            'created_at' => $exercise->created_at?->toIso8601String(),
            'updated_at' => $exercise->updated_at?->toIso8601String(),
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

    private function protectedMediaValue(array $items): array
    {
        return collect($items)
            ->map(fn (array $item) => [
                'file_id' => (int) $item['file_id'],
                'kind' => $item['kind'],
                'caption' => trim((string) ($item['caption'] ?? '')),
            ])
            ->unique(fn (array $item) => $item['file_id'])
            ->values()
            ->all();
    }

    private function mediaPayload(TrainingExercise $exercise): array
    {
        return collect($exercise->protected_media ?? [])
            ->map(fn (array $item) => [
                'file_id' => (int) $item['file_id'],
                'kind' => $item['kind'],
                'caption' => $item['caption'] ?? '',
                'protected' => true,
            ])
            ->values()
            ->all();
    }
}
