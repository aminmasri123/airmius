<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ClubYearPeriod;
use App\Models\Event;
use App\Models\SeasonPlan;
use App\Models\Team;
use App\Services\PlanFeatureService;
use App\Services\PlanningWorkspaceService;
use App\Support\ClubAuditLog;
use App\Support\ClubPermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SeasonPlanningController extends Controller
{
    public function index(Request $request, Team $team)
    {
        $this->authorizeRead($request, $team);

        return response()->json(['data' => [
            'plans' => SeasonPlan::query()->where('team_id', $team->id)
                ->when(! $this->canEdit($request, $team), fn ($query) => $query->whereIn('status', ['published', 'completed']))
                ->with(['events' => fn ($events) => $events->visibleTo($request->user())->select('events.id', 'title', 'start_time', 'status')])->latest()->get(),
            'events' => Event::query()->where('team_id', $team->id)->visibleTo($request->user())
                ->orderBy('start_time')->get(['id', 'title', 'start_time', 'sport_year_period_id', 'status']),
            'can_edit' => $this->canEdit($request, $team) && app(PlanFeatureService::class)->allows($team->club, 'season_planning'),
        ]]);
    }

    public function store(Request $request, Team $team)
    {
        return $this->save($request, $team);
    }

    public function update(Request $request, Team $team, SeasonPlan $seasonPlan)
    {
        abort_unless($seasonPlan->team_id === $team->id, 404);

        return $this->save($request, $team, $seasonPlan);
    }

    public function destroy(Request $request, Team $team, SeasonPlan $seasonPlan)
    {
        abort_unless($seasonPlan->team_id === $team->id, 404);
        $this->authorizeEdit($request, $team);
        ClubAuditLog::record($team->club, $request->user(), 'club.season_plan.deleted', $seasonPlan, []);
        $seasonPlan->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    private function save(Request $request, Team $team, ?SeasonPlan $plan = null)
    {
        $this->authorizeEdit($request, $team);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'sport_year_period_id' => ['required', 'integer', Rule::exists('club_year_periods', 'id')->where('club_id', $team->club_id)->where('type', 'sport')],
            'goals' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(['draft', 'published', 'completed'])],
            'revision' => [$plan ? 'required' : 'nullable', 'integer', 'min:1'],
            'phases' => ['required', 'array', 'min:1', 'max:52'],
            'phases.*.name' => ['required', 'string', 'max:120'],
            'phases.*.starts_on' => ['required', 'date_format:Y-m-d'],
            'phases.*.ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:phases.*.starts_on'],
            'phases.*.goal' => ['nullable', 'string', 'max:1000'],
            'event_ids' => ['present', 'array', 'max:500'],
            'event_ids.*' => ['integer', 'distinct', Rule::exists('events', 'id')->where('team_id', $team->id)->where('sport_year_period_id', $request->input('sport_year_period_id'))],
        ]);
        $period = ClubYearPeriod::findOrFail($data['sport_year_period_id']);
        $visibleIds = Event::query()->visibleTo($request->user())->whereIn('id', $data['event_ids'])->pluck('id')->all();
        if (count($visibleIds) !== count($data['event_ids'])) {
            throw ValidationException::withMessages(['event_ids' => 'Select only events visible to you.']);
        }
        $previousEnd = null;
        foreach ($data['phases'] as $index => $phase) {
            if ($phase['starts_on'] < $period->starts_on->toDateString() || $phase['ends_on'] > $period->ends_on->toDateString()
                || ($previousEnd && $phase['starts_on'] <= $previousEnd)) {
                throw ValidationException::withMessages(["phases.$index.starts_on" => 'Phases must be ordered, without overlaps, within the selected sport year.']);
            }
            $previousEnd = $phase['ends_on'];
        }
        $created = ! $plan;
        $saved = DB::transaction(function () use ($request, $team, $plan, $data): SeasonPlan {
            $current = $plan ? SeasonPlan::query()->lockForUpdate()->findOrFail($plan->id) : new SeasonPlan;
            abort_if($plan && $current->revision !== $data['revision'], 409, 'This plan changed. Reload before saving.');
            $current->fill(collect($data)->except(['event_ids', 'revision'])->all());
            $current->fill(['team_id' => $team->id, 'updated_by' => $request->user()->id, 'revision' => $plan ? $current->revision + 1 : 1])->save();
            $current->events()->sync($data['event_ids']);
            ClubAuditLog::record($team->club, $request->user(), 'club.season_plan.saved', $current, ['revision' => $current->revision]);

            return $current->load(['events' => fn ($events) => $events->visibleTo($request->user())->select('events.id', 'title', 'start_time', 'status')]);
        });

        return response()->json(['data' => $saved], $created ? 201 : 200);
    }

    private function canEdit(Request $request, Team $team): bool
    {
        return ClubPermissions::allowsForTeam($team, $request->user(), ClubPermissions::TRAINING_PLANS_EDIT);
    }

    private function authorizeEdit(Request $request, Team $team): void
    {
        $this->authorizeRead($request, $team);
        abort_unless($this->canEdit($request, $team), 403);
        app(PlanFeatureService::class)->ensureAllows($team->club, 'season_planning');
    }

    private function authorizeRead(Request $request, Team $team): void
    {
        app(PlanningWorkspaceService::class)->authorizeClub($team->club, $request->user());
        Gate::authorize('view', $team);
    }
}
