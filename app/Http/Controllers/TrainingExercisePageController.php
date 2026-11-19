<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\V1\TrainingExerciseController;
use App\Models\TrainingExercise;
use App\Models\TrainingPlan;
use App\Services\Training\TrainingResourceService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Web and native clients deliberately use the same library actions and permissions. */
class TrainingExercisePageController extends Controller
{
    public function __construct(private TrainingExerciseController $api, private TrainingResourceService $resources) {}

    public function index(Request $request)
    {
        $request->merge(['limit' => 100]);
        $teamIds = $this->resources->trainingPlanTeamIds($request->user());
        $plans = TrainingPlan::query()->releasedTo($request->user())->where(function ($query) use ($request, $teamIds) {
            $query->where('created_by', $request->user()->id)->orWhereHas('assignments', fn ($assigned) => $assigned->where('user_id', $request->user()->id)->orWhereIn('team_id', $teamIds));
        })->with('assignments')->latest()->limit(100)->get()->filter(fn ($plan) => $this->resources->canWritePlan($request->user(), $plan))->map(fn ($plan) => ['id' => $plan->id, 'title' => $plan->title])->values();

        $library = $this->api->index($request)->getData(true);

        return Inertia::render('Auth/Dashboard/Training/ExerciseLibrary', [
            'exercises' => $library['data'], 'sports' => $library['sports'], 'plans' => $plans,
            'filters' => $request->only(['sport_type', 'category']),
        ]);
    }

    public function create(Request $request)
    {
        return Inertia::render('Auth/Dashboard/Training/ExerciseEdit', ['exercise' => null, ...$this->api->scopeOptions($request)]);
    }

    public function edit(Request $request, TrainingExercise $trainingExercise)
    {
        $exercise = $this->api->show($request, $trainingExercise)->getData(true)['data'];
        abort_unless($exercise['can_edit'], 403);

        return Inertia::render('Auth/Dashboard/Training/ExerciseEdit', ['exercise' => $exercise, ...$this->api->scopeOptions($request)]);
    }

    public function store(Request $request)
    {
        $this->api->store($request);

        return redirect()->route('auth.training.exercises.index')->with('success', __('server.training.item_added'));
    }

    public function update(Request $request, TrainingExercise $trainingExercise)
    {
        $this->api->update($request, $trainingExercise);

        return redirect()->route('auth.training.exercises.index')->with('success', __('server.training.item_updated'));
    }

    public function addToPlan(Request $request, TrainingExercise $trainingExercise)
    {
        $item = $this->api->addToPlan($request, $trainingExercise)->getData(true)['data'];

        return redirect()->route('auth.training.plans.items.show', [$item['training_plan_id'], $item['id']]);
    }
}
