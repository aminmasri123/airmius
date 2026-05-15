<?php

namespace App\Http\Controllers;

use App\Models\ConnectedSportActivity;
use App\Models\Team;
use App\Models\TrainingPlan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class TrainingController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $teamIds = $user->teams()->pluck('teams.id');

        $plans = TrainingPlan::query()
            ->with([
                'creator:id,name,first_name,last_name,email',
                'team:id,name',
                'items',
                'assignments.user:id,name,first_name,last_name,email',
                'assignments.team:id,name',
            ])
            ->where(function ($query) use ($user, $teamIds) {
                $query
                    ->where('created_by', $user->id)
                    ->orWhereHas('assignments', function ($assignmentQuery) use ($user, $teamIds) {
                        $assignmentQuery
                            ->where('user_id', $user->id)
                            ->orWhereIn('team_id', $teamIds);
                    });
            })
            ->latest('id')
            ->limit(30)
            ->get()
            ->map(fn (TrainingPlan $plan) => $this->serializePlan($plan, $user));

        return Inertia::render('Auth/Dashboard/Training/Index', [
            'plans' => $plans,
            'activities' => $user
                ->connectedSportActivities()
                ->latest('started_at')
                ->limit(20)
                ->get(['id', 'provider', 'activity_type', 'title', 'started_at', 'duration_seconds', 'distance_meters', 'calories', 'metrics', 'image_path'])
                ->map(fn ($activity) => [
                    ...$activity->toArray(),
                    'image_url' => $activity->image_path ? Storage::disk('public')->url($activity->image_path) : null,
                ]),
            'teams' => $user
                ->teams()
                ->with('users:id,name,first_name,last_name,email')
                ->orderBy('name')
                ->get(['teams.id', 'teams.name', 'teams.club_id'])
                ->map(fn (Team $team) => [
                    'id' => $team->id,
                    'name' => $team->name,
                    'club_id' => $team->club_id,
                    'users' => $team->users->map(fn (User $member) => $this->serializeUser($member)),
                ]),
            'people' => User::query()
                ->whereKeyNot($user->id)
                ->orderBy('name')
                ->limit(100)
                ->get(['id', 'name', 'first_name', 'last_name', 'email'])
                ->map(fn (User $person) => $this->serializeUser($person)),
        ]);
    }

    public function storeActivity(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'activity_type' => ['nullable', 'string', 'max:80'],
            'started_at' => ['required', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:0', 'max:14400'],
            'distance_km' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'calories' => ['nullable', 'integer', 'min:0', 'max:200000'],
            'image' => ['nullable', 'image', 'max:5120'],
        ]);

        $imagePath = $request->file('image')?->store('sport-activities', 'public');

        ConnectedSportActivity::create([
            'user_id' => $request->user()->id,
            'provider' => 'manual',
            'provider_activity_id' => 'manual-'.Str::uuid(),
            'activity_type' => $data['activity_type'] ?: 'Training',
            'title' => $data['title'],
            'started_at' => $data['started_at'],
            'duration_seconds' => isset($data['duration_minutes']) ? (int) $data['duration_minutes'] * 60 : null,
            'distance_meters' => isset($data['distance_km']) ? (int) round((float) $data['distance_km'] * 1000) : null,
            'calories' => $data['calories'] ?? null,
            'image_path' => $imagePath,
            'metrics' => [
                'source_kind' => 'manual_entry',
                'created_from' => 'training_module',
                'created_manually_at' => now()->toIso8601String(),
            ],
        ]);

        return back()->with('success', 'Trainingseinheit wurde eingetragen.');
    }

    public function storePlan(Request $request)
    {
        $teamIds = $request->user()->teams()->pluck('teams.id')->all();
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:3000'],
            'cadence' => ['required', Rule::in(['single', 'daily', 'weekly', 'monthly'])],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'share_permission' => ['required', Rule::in(['read', 'write'])],
            'team_id' => ['nullable', 'integer', Rule::in($teamIds)],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'item_title' => ['required', 'string', 'max:160'],
            'item_sport_type' => ['nullable', 'string', 'max:80'],
            'item_description' => ['nullable', 'string', 'max:3000'],
            'item_scheduled_at' => ['nullable', 'date'],
            'item_duration_minutes' => ['nullable', 'integer', 'min:0', 'max:14400'],
            'item_distance_km' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'item_calories' => ['nullable', 'integer', 'min:0', 'max:200000'],
            'item_intensity' => ['nullable', Rule::in(['locker', 'mittel', 'hart', 'recovery'])],
            'item_todos' => ['nullable', 'string', 'max:3000'],
            'item_image' => ['nullable', 'image', 'max:5120'],
            'item_video_url' => ['nullable', 'url', 'max:2048'],
            'item_metrics' => ['nullable', 'array'],
            'item_metrics.*' => ['nullable', 'string', 'max:120'],
        ]);

        $imagePath = $request->file('item_image')?->store('training-plans', 'public');
        $userIds = collect($data['user_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->reject(fn ($id) => $id === (int) $request->user()->id)
            ->unique()
            ->values();

        DB::transaction(function () use ($request, $data, $imagePath, $userIds) {
            $plan = TrainingPlan::create([
                'created_by' => $request->user()->id,
                'team_id' => $data['team_id'] ?? null,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'cadence' => $data['cadence'],
                'starts_on' => $data['starts_on'] ?? null,
                'ends_on' => $data['ends_on'] ?? null,
                'status' => $data['status'],
                'share_permission' => $data['share_permission'],
                'settings' => [
                    'created_from' => 'training_module',
                ],
            ]);

            $plan->items()->create([
                'title' => $data['item_title'],
                'sport_type' => $data['item_sport_type'] ?? null,
                'description' => $data['item_description'] ?? null,
                'scheduled_at' => $data['item_scheduled_at'] ?? null,
                'duration_minutes' => $data['item_duration_minutes'] ?? null,
                'distance_meters' => isset($data['item_distance_km']) ? (int) round((float) $data['item_distance_km'] * 1000) : null,
                'calories' => $data['item_calories'] ?? null,
                'intensity' => $data['item_intensity'] ?? null,
                'image_path' => $imagePath,
                'video_url' => $data['item_video_url'] ?? null,
                'todos' => collect(preg_split('/\r\n|\r|\n/', $data['item_todos'] ?? ''))
                    ->map(fn ($todo) => trim($todo))
                    ->filter()
                    ->values()
                    ->all(),
                'metrics' => $this->cleanMetrics($data['item_metrics'] ?? []),
            ]);

            if (! empty($data['team_id'])) {
                $plan->assignments()->create([
                    'team_id' => $data['team_id'],
                    'permission' => $data['share_permission'],
                ]);
            }

            $userIds->each(fn ($userId) => $plan->assignments()->create([
                'user_id' => $userId,
                'permission' => $data['share_permission'],
            ]));
        });

        return back()->with('success', $data['status'] === 'published'
            ? 'Trainingsplan wurde freigegeben.'
            : 'Trainingsplan wurde als Entwurf gespeichert.');
    }

    public function storePlanItem(Request $request, TrainingPlan $plan)
    {
        abort_unless($this->canWritePlan($request->user(), $plan), 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'sport_type' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:3000'],
            'scheduled_at' => ['nullable', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:0', 'max:14400'],
            'intensity' => ['nullable', Rule::in(['locker', 'mittel', 'hart', 'recovery'])],
            'todos' => ['nullable', 'string', 'max:3000'],
            'image' => ['nullable', 'image', 'max:5120'],
            'video_url' => ['nullable', 'url', 'max:2048'],
            'metrics' => ['nullable', 'array'],
            'metrics.*' => ['nullable', 'string', 'max:120'],
        ]);

        $imagePath = $request->file('image')?->store('training-plans', 'public');

        $plan->items()->create([
            'title' => $data['title'],
            'sport_type' => $data['sport_type'] ?? null,
            'description' => $data['description'] ?? null,
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'duration_minutes' => $data['duration_minutes'] ?? null,
            'intensity' => $data['intensity'] ?? null,
            'image_path' => $imagePath,
            'video_url' => $data['video_url'] ?? null,
            'todos' => collect(preg_split('/\r\n|\r|\n/', $data['todos'] ?? ''))
                ->map(fn ($todo) => trim($todo))
                ->filter()
                ->values()
                ->all(),
            'metrics' => $this->cleanMetrics($data['metrics'] ?? []),
            'sort_order' => $plan->items()->count() + 1,
        ]);

        return back()->with('success', 'Trainingseinheit wurde zum Plan hinzugefuegt.');
    }

    public function updatePlan(Request $request, TrainingPlan $plan)
    {
        abort_unless($this->canWritePlan($request->user(), $plan), 403);

        $teamIds = $request->user()->teams()->pluck('teams.id')->all();
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:3000'],
            'cadence' => ['required', Rule::in(['single', 'daily', 'weekly', 'monthly'])],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'share_permission' => ['required', Rule::in(['read', 'write'])],
            'team_id' => ['nullable', 'integer', Rule::in($teamIds)],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        DB::transaction(function () use ($request, $plan, $data) {
            $plan->update([
                'team_id' => $data['team_id'] ?? null,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'cadence' => $data['cadence'],
                'starts_on' => $data['starts_on'] ?? null,
                'ends_on' => $data['ends_on'] ?? null,
                'status' => $data['status'],
                'share_permission' => $data['share_permission'],
            ]);

            $plan->assignments()->delete();

            if (! empty($data['team_id'])) {
                $plan->assignments()->create([
                    'team_id' => $data['team_id'],
                    'permission' => $data['share_permission'],
                ]);
            }

            collect($data['user_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->reject(fn ($id) => $id === (int) $request->user()->id)
                ->unique()
                ->each(fn ($userId) => $plan->assignments()->create([
                    'user_id' => $userId,
                    'permission' => $data['share_permission'],
                ]));
        });

        return back()->with('success', 'Trainingsplan wurde aktualisiert.');
    }

    public function publishPlan(Request $request, TrainingPlan $plan)
    {
        abort_unless($this->canWritePlan($request->user(), $plan), 403);

        $plan->update(['status' => 'published']);

        return back()->with('success', 'Trainingsplan wurde freigegeben.');
    }

    public function destroyPlan(Request $request, TrainingPlan $plan)
    {
        abort_unless((int) $plan->created_by === (int) $request->user()->id, 403);

        $plan->load('items');

        $plan->items
            ->pluck('image_path')
            ->filter()
            ->each(fn ($path) => Storage::disk('public')->delete($path));

        $plan->delete();

        return back()->with('success', 'Trainingsplan wurde geloescht.');
    }

    private function serializePlan(TrainingPlan $plan, User $viewer): array
    {
        return [
            'id' => $plan->id,
            'title' => $plan->title,
            'description' => $plan->description,
            'cadence' => $plan->cadence,
            'starts_on' => $plan->starts_on?->toDateString(),
            'ends_on' => $plan->ends_on?->toDateString(),
            'status' => $plan->status,
            'share_permission' => $plan->share_permission,
            'creator' => $plan->creator ? $this->serializeUser($plan->creator) : null,
            'team' => $plan->team ? ['id' => $plan->team->id, 'name' => $plan->team->name] : null,
            'can_write' => $this->canWritePlan($viewer, $plan),
            'items' => $plan->items->map(fn ($item) => [
                ...$item->toArray(),
                'image_url' => $item->image_path ? Storage::disk('public')->url($item->image_path) : null,
            ]),
            'assignments' => $plan->assignments->map(fn ($assignment) => [
                'id' => $assignment->id,
                'permission' => $assignment->permission,
                'user' => $assignment->user ? $this->serializeUser($assignment->user) : null,
                'team' => $assignment->team ? ['id' => $assignment->team->id, 'name' => $assignment->team->name] : null,
            ]),
        ];
    }

    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: $user->name,
            'email' => $user->email,
        ];
    }

    private function canWritePlan(User $user, TrainingPlan $plan): bool
    {
        if ((int) $plan->created_by === (int) $user->id) {
            return true;
        }

        $teamIds = $user->teams()->pluck('teams.id');

        return $plan->assignments()
            ->where('permission', 'write')
            ->where(function ($query) use ($user, $teamIds) {
                $query
                    ->where('user_id', $user->id)
                    ->orWhereIn('team_id', $teamIds);
            })
            ->exists();
    }

    private function cleanMetrics(array $metrics): array
    {
        return collect($metrics)
            ->map(fn ($value) => is_string($value) ? trim($value) : $value)
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();
    }
}
