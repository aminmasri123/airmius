<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\Team;
use App\Models\TrainingExercise;
use App\Models\TrainingPlan;
use App\Support\ClubPermissions;
use App\Support\Roles;
use App\Support\TeamRoles;
use App\Services\PlanFeatureService;
use App\Services\Training\TrainingResourceService;
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
        $clubIds = $this->activeClubIds($user);
        $teamIds = $user->teams()->pluck('teams.id');

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
            'description' => $data['description'] ?? null,
            'instructions' => $data['instructions'] ?? null,
            'equipment' => $this->listValue($data['equipment'] ?? []),
            'muscle_groups' => $this->listValue($data['muscle_groups'] ?? []),
            'difficulty' => $data['difficulty'],
            'is_active' => true,
        ]);

        return response()->json(['data' => $this->payload($exercise->load(['creator:id,name', 'club:id,name', 'team:id,name']), $request->user())], 201);
    }

    public function update(Request $request, TrainingExercise $trainingExercise)
    {
        abort_unless($this->canManage($trainingExercise, $request->user()), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:160'],
            'sport_type' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:3000'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'equipment' => ['nullable', 'array', 'max:20'],
            'equipment.*' => ['string', 'max:80'],
            'muscle_groups' => ['nullable', 'array', 'max:20'],
            'muscle_groups.*' => ['string', 'max:80'],
            'difficulty' => ['required', Rule::in(['all', 'beginner', 'intermediate', 'advanced'])],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $trainingExercise->update([
            ...$data,
            'equipment' => $this->listValue($data['equipment'] ?? []),
            'muscle_groups' => $this->listValue($data['muscle_groups'] ?? []),
        ]);

        return response()->json(['data' => $this->payload($trainingExercise->refresh()->load(['creator:id,name', 'club:id,name', 'team:id,name']), $request->user())]);
    }

    public function destroy(Request $request, TrainingExercise $trainingExercise)
    {
        abort_unless($this->canManage($trainingExercise, $request->user()), 403);

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
            'description' => ['nullable', 'string', 'max:3000'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'equipment' => ['nullable', 'array', 'max:20'],
            'equipment.*' => ['string', 'max:80'],
            'muscle_groups' => ['nullable', 'array', 'max:20'],
            'muscle_groups.*' => ['string', 'max:80'],
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
            abort_unless($this->canManageClub($club, $user), 403);
            $this->planFeatures->ensureAllows($club, 'exercise_library_custom');
            return [$club, null];
        }

        abort_if(empty($data['team_id']), 422, 'Für Teamübungen ist ein Team erforderlich.');
        $team = Team::query()->with('club')->findOrFail($data['team_id']);
        abort_if(! empty($data['club_id']) && (int) $data['club_id'] !== (int) $team->club_id, 422, 'Verein und Team passen nicht zusammen.');
        abort_unless($this->canManageTeam($team, $user), 403);
        $this->planFeatures->ensureAllows($team->club, 'exercise_library_custom');

        return [$team->club, $team];
    }

    private function visibleTo(TrainingExercise $exercise, $user): bool
    {
        if ((int) $exercise->created_by === (int) $user->id) {
            return true;
        }

        return ($exercise->club_id && in_array((int) $exercise->club_id, $this->activeClubIds($user)->all(), true))
            || ($exercise->team_id && $user->teams()->whereKey($exercise->team_id)->exists());
    }

    private function canManage(TrainingExercise $exercise, $user): bool
    {
        if ((int) $exercise->created_by === (int) $user->id) {
            return true;
        }

        if ($exercise->team_id) {
            return $this->canManageTeam($exercise->team()->with('club')->firstOrFail(), $user);
        }

        return $exercise->club_id
            ? $this->canManageClub($exercise->club()->firstOrFail(), $user)
            : false;
    }

    private function canManageClub(Club $club, $user): bool
    {
        return $club->owner_id === $user->id
            || $user->hasAnyRole(Roles::FULL_ACCESS)
            || ClubPermissions::allows($club, $user, ClubPermissions::EVENTS_MANAGE);
    }

    private function canManageTeam(Team $team, $user): bool
    {
        return $this->canManageClub($team->club, $user)
            || $team->users()->whereKey($user->id)->wherePivotIn('role', TeamRoles::TEAM_STAFF_ROLES)->exists();
    }

    private function activeClubIds($user)
    {
        return Club::query()
            ->where(function ($query) use ($user) {
                $query->where('owner_id', $user->id)
                    ->orWhereHas('users', function ($members) use ($user) {
                        $members->where('users.id', $user->id)
                            ->where(function ($status) {
                                $status->whereNull('club_user.membership_status')
                                    ->orWhere('club_user.membership_status', 'active');
                            });
                    });
            })
            ->pluck('id');
    }

    private function payload(TrainingExercise $exercise, $viewer): array
    {
        return [
            'id' => $exercise->id,
            'name' => $exercise->name,
            'sport_type' => $exercise->sport_type,
            'description' => $exercise->description,
            'instructions' => $exercise->instructions,
            'equipment' => $exercise->equipment ?? [],
            'muscle_groups' => $exercise->muscle_groups ?? [],
            'difficulty' => $exercise->difficulty,
            'scope' => $exercise->team_id ? 'team' : ($exercise->club_id ? 'club' : 'personal'),
            'club' => $exercise->club ? ['id' => $exercise->club->id, 'name' => $exercise->club->name] : null,
            'team' => $exercise->team ? ['id' => $exercise->team->id, 'name' => $exercise->team->name] : null,
            'creator' => $exercise->creator ? ['id' => $exercise->creator->id, 'name' => $exercise->creator->name] : null,
            'can_edit' => $this->canManage($exercise, $viewer),
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
}
