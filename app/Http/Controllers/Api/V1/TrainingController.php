<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TrainingLogResource;
use App\Http\Resources\Api\V1\TrainingPlanResource;
use App\Models\TrainingLog;
use App\Models\TrainingPlan;
use Illuminate\Http\Request;

class TrainingController extends Controller
{
    public function plans(Request $request)
    {
        $plans = $this->visiblePlans($request)
            ->with(['creator', 'team'])
            ->when($request->boolean('include_items'), fn ($query) => $query->with('items'))
            ->withCount('assignments')
            ->latest()
            ->paginate($this->perPage($request));

        return TrainingPlanResource::collection($plans);
    }

    public function showPlan(Request $request, TrainingPlan $trainingPlan)
    {
        abort_unless($this->visiblePlans($request)->whereKey($trainingPlan->id)->exists(), 404);

        return new TrainingPlanResource(
            $trainingPlan->loadMissing(['creator', 'team', 'items'])->loadCount('assignments')
        );
    }

    public function logs(Request $request)
    {
        $logs = $this->visibleLogs($request)
            ->with(['athlete', 'trainer', 'team', 'plan'])
            ->latest('performed_at')
            ->paginate($this->perPage($request));

        return TrainingLogResource::collection($logs);
    }

    public function showLog(Request $request, TrainingLog $trainingLog)
    {
        abort_unless($this->visibleLogs($request)->whereKey($trainingLog->id)->exists(), 404);

        return new TrainingLogResource(
            $trainingLog->loadMissing(['athlete', 'trainer', 'team', 'plan', 'entries'])
        );
    }

    private function visiblePlans(Request $request)
    {
        $user = $request->user();
        $teamIds = $user->teams()->pluck('teams.id')->all();

        return TrainingPlan::query()->where(function ($query) use ($user, $teamIds) {
            $query
                ->where('created_by', $user->id)
                ->orWhereIn('team_id', $teamIds)
                ->orWhereHas('assignments', function ($assignments) use ($user, $teamIds) {
                    $assignments
                        ->where('user_id', $user->id)
                        ->orWhereIn('team_id', $teamIds);
                });
        });
    }

    private function visibleLogs(Request $request)
    {
        $user = $request->user();
        $teamIds = $user->teams()->pluck('teams.id')->all();

        return TrainingLog::query()->where(function ($query) use ($user, $teamIds) {
            $query
                ->where('user_id', $user->id)
                ->orWhere('created_by', $user->id)
                ->orWhere('trainer_id', $user->id)
                ->orWhereIn('team_id', $teamIds);
        });
    }

    private function perPage(Request $request): int
    {
        return min(max((int) $request->integer('per_page', 20), 1), 50);
    }
}
