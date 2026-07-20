<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TrainingLogResource;
use App\Http\Resources\Api\V1\TrainingPlanResource;
use App\Models\TrainingLog;
use App\Models\TrainingPlan;
use App\Models\TrainingPlanItem;
use App\Services\Training\TrainingResourceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class TrainingController extends Controller
{
    public function __construct(private readonly TrainingResourceService $resources) {}

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

    public function storePlan(Request $request)
    {
        $data = $this->validatePlanData($request);

        $plan = DB::transaction(function () use ($request, $data) {
            $plan = TrainingPlan::query()->create($this->planPayload($request, $data));
            $this->syncPlanAssignments($plan, $data);

            return $plan;
        });

        return (new TrainingPlanResource($this->loadPlanForResource($plan)))
            ->response()
            ->setStatusCode(201);
    }

    public function updatePlan(Request $request, TrainingPlan $trainingPlan)
    {
        abort_unless($this->resources->canWritePlan($request->user(), $trainingPlan), 403);

        $data = $this->validatePlanData($request);

        DB::transaction(function () use ($request, $trainingPlan, $data) {
            $trainingPlan->update($this->planPayload($request, $data, $trainingPlan));
            $trainingPlan->assignments()->delete();
            $this->syncPlanAssignments($trainingPlan, $data);
        });

        return new TrainingPlanResource($this->loadPlanForResource($trainingPlan->refresh()));
    }

    public function destroyPlan(Request $request, TrainingPlan $trainingPlan)
    {
        abort_unless((int) $trainingPlan->created_by === (int) $request->user()->id, 403);

        $trainingPlan->load('items');

        $trainingPlan->items
            ->pluck('image_path')
            ->filter()
            ->unique()
            ->each(fn ($path) => Storage::disk('public')->delete($path));

        $trainingPlan->delete();

        return response()->json(['message' => 'Trainingsplan wurde gelöscht.']);
    }

    public function storePlanItem(Request $request, TrainingPlan $trainingPlan)
    {
        abort_unless($this->resources->canWritePlan($request->user(), $trainingPlan), 403);

        $data = $this->validatePlanItemData($request);

        $trainingPlan->items()->create([
            ...$this->planItemPayload($data),
            'sort_order' => $trainingPlan->items()->count() + 1,
        ]);

        return (new TrainingPlanResource($this->loadPlanForResource($trainingPlan->refresh())))
            ->response()
            ->setStatusCode(201);
    }

    public function updatePlanItem(Request $request, TrainingPlan $trainingPlan, TrainingPlanItem $trainingPlanItem)
    {
        abort_unless((int) $trainingPlanItem->training_plan_id === (int) $trainingPlan->id, 404);
        abort_unless($this->resources->canWritePlan($request->user(), $trainingPlan), 403);

        $data = $this->validatePlanItemData($request);

        $trainingPlanItem->update($this->planItemPayload($data, $trainingPlanItem->image_path));

        return new TrainingPlanResource($this->loadPlanForResource($trainingPlan->refresh()));
    }

    public function destroyPlanItem(Request $request, TrainingPlan $trainingPlan, TrainingPlanItem $trainingPlanItem)
    {
        abort_unless((int) $trainingPlanItem->training_plan_id === (int) $trainingPlan->id, 404);
        abort_unless($this->resources->canWritePlan($request->user(), $trainingPlan), 403);

        $this->deletePlanItemImageIfUnused($trainingPlanItem);
        $trainingPlanItem->delete();

        return new TrainingPlanResource($this->loadPlanForResource($trainingPlan->refresh()));
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

    private function validatePlanData(Request $request): array
    {
        $teamIds = $request->user()->teams()->pluck('teams.id')->all();

        return $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:3000'],
            'cadence' => ['required', Rule::in(['single', 'daily', 'weekly', 'monthly'])],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'goal' => ['nullable', 'string', 'max:200'],
            'phase' => ['nullable', Rule::in(['base', 'build', 'peak', 'recovery', 'rehab'])],
            'level' => ['nullable', Rule::in(['beginner', 'intermediate', 'advanced', 'elite'])],
            'weeks' => ['nullable', 'integer', 'min:1', 'max:104'],
            'weekly_sessions' => ['nullable', 'integer', 'min:1', 'max:21'],
            'macrocycle' => ['nullable', 'string', 'max:160'],
            'mesocycle' => ['nullable', 'string', 'max:160'],
            'deload_week' => ['nullable', 'integer', 'min:1', 'max:104'],
            'competition_date' => ['nullable', 'date'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'share_permission' => ['required', Rule::in(['read', 'write'])],
            'team_id' => ['nullable', 'integer', Rule::in($teamIds)],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);
    }

    private function planPayload(Request $request, array $data, ?TrainingPlan $plan = null): array
    {
        return [
            'created_by' => $plan?->created_by ?? $request->user()->id,
            'team_id' => $data['team_id'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'cadence' => $data['cadence'],
            'starts_on' => $data['starts_on'] ?? null,
            'ends_on' => $data['ends_on'] ?? null,
            'status' => $data['status'],
            'share_permission' => $data['share_permission'],
            'settings' => [
                ...($plan?->settings ?? []),
                'created_from' => $plan?->settings['created_from'] ?? 'api_v1_training',
                'goal' => $data['goal'] ?? null,
                'phase' => $data['phase'] ?? null,
                'level' => $data['level'] ?? null,
                'weeks' => $data['weeks'] ?? null,
                'weekly_sessions' => $data['weekly_sessions'] ?? null,
                'macrocycle' => $data['macrocycle'] ?? null,
                'mesocycle' => $data['mesocycle'] ?? null,
                'deload_week' => $data['deload_week'] ?? null,
                'competition_date' => $data['competition_date'] ?? null,
            ],
        ];
    }

    private function syncPlanAssignments(TrainingPlan $plan, array $data): void
    {
        if (! empty($data['team_id'])) {
            $plan->assignments()->create([
                'team_id' => $data['team_id'],
                'permission' => $data['share_permission'],
            ]);
        }

        collect($data['user_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->reject(fn ($id) => $id === (int) $plan->created_by)
            ->unique()
            ->each(fn ($userId) => $plan->assignments()->create([
                'user_id' => $userId,
                'permission' => $data['share_permission'],
            ]));
    }

    private function validatePlanItemData(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'sport_type' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:3000'],
            'scheduled_at' => ['nullable', 'date'],
            'week' => ['nullable', 'integer', 'min:1', 'max:104'],
            'duration_minutes' => ['nullable', 'integer', 'min:0', 'max:14400'],
            'distance_meters' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'distance_km' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'calories' => ['nullable', 'integer', 'min:0', 'max:200000'],
            'intensity' => ['nullable', Rule::in(['locker', 'mittel', 'hart', 'recovery'])],
            'load' => ['nullable', Rule::in(['low', 'medium', 'high', 'test'])],
            'focus' => ['nullable', 'string', 'max:160'],
            'todos' => ['nullable', 'array'],
            'todos.*' => ['nullable', 'string', 'max:300'],
            'todos_text' => ['nullable', 'string', 'max:3000'],
            'video_url' => ['nullable', 'url', 'max:2048'],
            'metrics' => ['nullable', 'array'],
            'metrics.*' => ['nullable', 'string', 'max:120'],
        ]);
    }

    private function planItemPayload(array $data, ?string $imagePath = null): array
    {
        return [
            'title' => $data['title'],
            'sport_type' => $data['sport_type'] ?? null,
            'description' => $data['description'] ?? null,
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'duration_minutes' => $data['duration_minutes'] ?? null,
            'distance_meters' => $data['distance_meters'] ?? (isset($data['distance_km']) ? (int) round((float) $data['distance_km'] * 1000) : null),
            'calories' => $data['calories'] ?? null,
            'intensity' => $data['intensity'] ?? null,
            'image_path' => $imagePath,
            'video_url' => $data['video_url'] ?? null,
            'todos' => $this->normalizeTodos($data),
            'metrics' => [
                ...$this->cleanMetrics($data['metrics'] ?? []),
                ...array_filter([
                    'Woche' => $data['week'] ?? null,
                    'Belastung' => $data['load'] ?? null,
                    'Fokus' => $data['focus'] ?? null,
                ], fn ($value) => $value !== null && $value !== ''),
            ],
        ];
    }

    private function normalizeTodos(array $data): array
    {
        if (isset($data['todos']) && is_array($data['todos'])) {
            return collect($data['todos'])
                ->map(fn ($todo) => trim((string) $todo))
                ->filter()
                ->values()
                ->all();
        }

        return collect(preg_split('/\r\n|\r|\n/', $data['todos_text'] ?? ''))
            ->map(fn ($todo) => trim($todo))
            ->filter()
            ->values()
            ->all();
    }

    private function cleanMetrics(array $metrics): array
    {
        return collect($metrics)
            ->map(fn ($value) => is_string($value) ? trim($value) : $value)
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();
    }

    private function loadPlanForResource(TrainingPlan $plan): TrainingPlan
    {
        return $plan->load(['creator', 'team', 'items'])->loadCount('assignments');
    }

    private function deletePlanItemImageIfUnused(TrainingPlanItem $item): void
    {
        if (! $item->image_path) {
            return;
        }

        $uses = TrainingPlanItem::query()
            ->where('image_path', $item->image_path)
            ->whereKeyNot($item->id)
            ->exists();

        if (! $uses) {
            Storage::disk('public')->delete($item->image_path);
        }
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
