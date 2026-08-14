<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\TrainingController as WebTrainingController;
use App\Http\Resources\Api\V1\TrainingLogResource;
use App\Http\Resources\Api\V1\TrainingPlanResource;
use App\Models\TrainingLog;
use App\Models\TrainingPlan;
use App\Models\TrainingPlanItem;
use App\Services\Training\TrainingLogAccessService;
use App\Services\Training\TrainingLogService;
use App\Services\Training\TrainingResourceService;
use App\Services\Training\TrainingRouteLinkService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class TrainingController extends Controller
{
    public function __construct(
        private readonly TrainingResourceService $resources,
        private readonly TrainingLogAccessService $logAccess,
        private readonly TrainingLogService $logs,
        private readonly TrainingRouteLinkService $routeLinks,
    ) {}

    public function plans(Request $request)
    {
        $plans = $this->visiblePlans($request)
            ->with(['creator', 'team'])
            ->when($request->boolean('include_items'), fn ($query) => $query->with('items.sportRoute'))
            ->withCount('assignments')
            ->latest()
            ->paginate($this->perPage($request));

        return TrainingPlanResource::collection($plans)->additional([
            'capabilities' => [
                'can_manage_training_plans' => $this->resources->canManageTrainingPlans($request->user()),
            ],
        ]);
    }

    public function routeOptions(Request $request)
    {
        return response()->json([
            'data' => [
                'routes' => $this->routeLinks->selectableRoutes($request->user()),
                'tracks' => $this->routeLinks->selectableTracks($request->user()),
                'location_payload' => 'summary_only',
            ],
        ]);
    }

    public function templates(Request $request)
    {
        $templates = $this->visiblePlans($request)
            ->where('settings->is_template', true)
            ->with(['creator', 'team', 'items.sportRoute'])
            ->withCount('assignments')
            ->latest('updated_at')
            ->paginate($this->perPage($request));

        return TrainingPlanResource::collection($templates)->additional([
            'capabilities' => [
                'can_manage_training_plans' => $this->resources->canManageTrainingPlans($request->user()),
            ],
        ]);
    }

    public function showPlan(Request $request, TrainingPlan $trainingPlan)
    {
        abort_unless($this->visiblePlans($request)->whereKey($trainingPlan->id)->exists(), 404);

        return new TrainingPlanResource(
            $trainingPlan
                ->loadMissing(['creator', 'team', 'items.sportRoute', 'assignments.user', 'assignments.team'])
                ->loadCount('assignments')
        );
    }

    public function storePlan(Request $request)
    {
        abort_unless($this->resources->canManageTrainingPlans($request->user()), 403, __('server.training.manage_plans_forbidden'));

        $data = $this->validatePlanData($request);
        $data = $this->resources->normalizePlanAudience($request->user(), $data);

        $plan = DB::transaction(function () use ($request, $data) {
            $plan = TrainingPlan::query()->create($this->planPayload($request, $data));
            $this->syncPlanAssignments($plan, $data);

            if (filled($data['item_title'] ?? null)) {
                $plan->items()->create($this->planItemPayload([
                    'title' => $data['item_title'],
                    'sport_type' => $data['item_sport_type'] ?? null,
                    'duration_minutes' => $data['item_duration_minutes'] ?? null,
                    'distance_km' => $data['item_distance_km'] ?? null,
                    'load' => $data['item_load'] ?? null,
                    'focus' => $data['item_focus'] ?? null,
                    'metrics' => $data['item_metrics'] ?? [],
                ]));
            }

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
        $data = $this->resources->normalizePlanAudience($request->user(), $data);

        DB::transaction(function () use ($request, $trainingPlan, $data) {
            $trainingPlan->update($this->planPayload($request, $data, $trainingPlan));
            $trainingPlan->assignments()->delete();
            $this->syncPlanAssignments($trainingPlan, $data);
        });

        return new TrainingPlanResource($this->loadPlanForResource($trainingPlan->refresh()));
    }

    public function destroyPlan(Request $request, TrainingPlan $trainingPlan)
    {
        abort_unless($this->resources->canDeletePlan($request->user(), $trainingPlan), 403);

        $trainingPlan->load('items');

        $trainingPlan->items
            ->pluck('image_path')
            ->filter()
            ->unique()
            ->each(fn ($path) => Storage::disk('public')->delete($path));

        $trainingPlan->delete();

        return response()->json(['message' => __('server.training.plan_deleted')]);
    }

    public function publishPlan(Request $request, TrainingPlan $trainingPlan)
    {
        app(WebTrainingController::class)->publishPlan($request, $trainingPlan);

        return new TrainingPlanResource(
            $this->loadPlanForResource($trainingPlan->refresh())
        );
    }

    public function duplicatePlan(Request $request, TrainingPlan $trainingPlan)
    {
        app(WebTrainingController::class)->duplicatePlan($request, $trainingPlan);

        $copy = TrainingPlan::query()
            ->where('created_by', $request->user()->id)
            ->where('settings->created_from_template_id', $trainingPlan->id)
            ->latest('id')
            ->firstOrFail();

        return (new TrainingPlanResource($this->loadPlanForResource($copy)))
            ->response()
            ->setStatusCode(201);
    }

    public function createTemplate(Request $request, TrainingPlan $trainingPlan)
    {
        abort_unless($this->resources->canWritePlan($request->user(), $trainingPlan), 403);
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:160'],
        ]);

        $template = $this->clonePlan(
            $request,
            $trainingPlan,
            title: $data['title'] ?? $trainingPlan->title.' Vorlage',
            startsOn: null,
            endsOn: null,
            settings: [
                'is_template' => true,
                'template_source_id' => $trainingPlan->id,
            ],
        );

        return (new TrainingPlanResource($this->loadPlanForResource($template)))
            ->response()
            ->setStatusCode(201);
    }

    public function instantiateTemplate(Request $request, TrainingPlan $trainingPlan)
    {
        abort_unless($this->resources->canManageTrainingPlans($request->user()), 403, __('server.training.manage_plans_forbidden'));

        abort_unless(
            $this->visiblePlans($request)
                ->whereKey($trainingPlan->id)
                ->where('settings->is_template', true)
                ->exists(),
            404
        );

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:160'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ]);

        $copy = $this->clonePlan(
            $request,
            $trainingPlan,
            title: $data['title'] ?? $trainingPlan->title.' '.now()->format('Y-m-d'),
            startsOn: $data['starts_on'] ?? null,
            endsOn: $data['ends_on'] ?? null,
            settings: [
                'is_template' => false,
                'is_template_copy' => true,
                'created_from_template_id' => $trainingPlan->id,
            ],
        );

        return (new TrainingPlanResource($this->loadPlanForResource($copy)))
            ->response()
            ->setStatusCode(201);
    }

    public function storePlanItem(Request $request, TrainingPlan $trainingPlan)
    {
        abort_unless($this->resources->canWritePlan($request->user(), $trainingPlan), 403);

        $data = $this->validatePlanItemData($request);
        $data['sport_route_id'] = $this->routeLinks
            ->resolvePlanRoute($request->user(), $data['sport_route_id'] ?? null)?->id;
        $imagePath = $request->file('image')?->store('training-plans', 'public');

        $trainingPlan->items()->create([
            ...$this->planItemPayload($data, $imagePath),
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
        $data['sport_route_id'] = $this->routeLinks
            ->resolvePlanRoute($request->user(), $data['sport_route_id'] ?? null)?->id;
        $imagePath = $trainingPlanItem->image_path;
        if ($request->hasFile('image')) {
            $this->deletePlanItemImageIfUnused($trainingPlanItem);
            $imagePath = $request->file('image')->store('training-plans', 'public');
        }

        $trainingPlanItem->update($this->planItemPayload($data, $imagePath));

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

    public function duplicatePlanItem(
        Request $request,
        TrainingPlan $trainingPlan,
        TrainingPlanItem $trainingPlanItem,
    ) {
        app(WebTrainingController::class)->duplicatePlanItem(
            $request,
            $trainingPlan,
            $trainingPlanItem,
        );

        return (new TrainingPlanResource($this->loadPlanForResource($trainingPlan->refresh())))
            ->response()
            ->setStatusCode(201);
    }

    public function markPlanItemMissed(
        Request $request,
        TrainingPlan $trainingPlan,
        TrainingPlanItem $trainingPlanItem,
    ) {
        app(WebTrainingController::class)->markPlanItemMissed(
            $request,
            $trainingPlan,
            $trainingPlanItem,
        );

        $athleteId = (int) ($request->input('user_id') ?: $request->user()->id);
        $log = TrainingLog::query()
            ->where('user_id', $athleteId)
            ->where('training_plan_item_id', $trainingPlanItem->id)
            ->where('status', 'missed')
            ->latest('id')
            ->firstOrFail();

        return (new TrainingLogResource($this->loadLogForResource($log)))
            ->response()
            ->setStatusCode(201);
    }

    public function logs(Request $request)
    {
        $logs = $this->visibleLogs($request)
            ->with(['athlete', 'trainer', 'team', 'plan', 'entries', ...$this->logRouteRelations($request)])
            ->latest('performed_at')
            ->paginate($this->perPage($request));

        return TrainingLogResource::collection($logs);
    }

    public function showLog(Request $request, TrainingLog $trainingLog)
    {
        abort_unless($this->visibleLogs($request)->whereKey($trainingLog->id)->exists(), 404);

        return new TrainingLogResource(
            $trainingLog->loadMissing(['athlete', 'trainer', 'team', 'plan', 'entries', 'feedbacks.author', ...$this->logRouteRelations($request)])
        );
    }

    public function storeLog(Request $request)
    {
        $data = $this->validateLogData($request);
        $planItem = $this->resolveVisiblePlanItem($request, $data);

        $log = $this->logs->storeFromPayload(
            $request->user(),
            $data,
            (int) $request->user()->id,
            $planItem,
            $request,
            'api_v1_mobile',
        );

        return (new TrainingLogResource($this->loadLogForResource($log)))
            ->response()
            ->setStatusCode(201);
    }

    public function updateLog(Request $request, TrainingLog $trainingLog)
    {
        abort_unless(
            (int) $trainingLog->created_by === (int) $request->user()->id,
            403
        );

        $data = $this->validateLogData($request);
        $planItem = $this->resolveVisiblePlanItem($request, $data);

        $this->logs->replaceFromPayload(
            $trainingLog,
            $request->user(),
            $data,
            (int) $request->user()->id,
            $planItem,
            $request,
            'api_v1_mobile',
        );

        return new TrainingLogResource(
            $this->loadLogForResource($trainingLog->refresh())
        );
    }

    public function destroyLog(Request $request, TrainingLog $trainingLog)
    {
        abort_unless(
            (int) $trainingLog->created_by === (int) $request->user()->id,
            403
        );

        $trainingLog->delete();

        return response()->json([
            'data' => [
                'deleted' => true,
                'message' => __('server.training.log_deleted'),
            ],
        ]);
    }

    private function validatePlanData(Request $request): array
    {
        $teamIds = $this->resources->trainingPlanTeamIds($request->user())->all();

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
            'target_type' => ['nullable', Rule::in(['self', 'private', 'team'])],
            'team_mode' => ['nullable', Rule::in(['all', 'individual'])],
            'team_id' => ['nullable', 'integer', Rule::in($teamIds)],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'item_title' => ['nullable', 'string', 'max:160'],
            'item_sport_type' => ['nullable', 'string', 'max:80'],
            'item_duration_minutes' => ['nullable', 'integer', 'min:0', 'max:14400'],
            'item_distance_km' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'item_load' => ['nullable', Rule::in(['low', 'medium', 'high', 'test'])],
            'item_focus' => ['nullable', 'string', 'max:160'],
            'item_metrics' => ['nullable', 'array'],
            'item_metrics.*' => ['nullable', 'string', 'max:120'],
        ]);
    }

    private function validateLogData(Request $request): array
    {
        $teamIds = $request->user()->teams()->pluck('teams.id')->all();

        return $request->validate([
            'team_id' => ['nullable', 'integer', Rule::in($teamIds)],
            'training_plan_item_id' => ['nullable', 'integer', 'exists:training_plan_items,id'],
            'sport_route_id' => ['nullable', 'integer'],
            'sport_route_track_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:160'],
            'sport_type' => ['nullable', 'string', 'max:80'],
            'status' => ['required', Rule::in(['planned', 'in_progress', 'completed', 'missed'])],
            'performed_at' => ['nullable', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:0', 'max:14400'],
            'distance_km' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'calories' => ['nullable', 'integer', 'min:0', 'max:200000'],
            'intensity' => ['nullable', Rule::in(['locker', 'mittel', 'hart', 'recovery'])],
            'notes' => ['nullable', 'string', 'max:5000'],
            'privacy_scope' => ['nullable', Rule::in(['private', 'trainer', 'team'])],
            'wellness' => ['nullable', 'array'],
            'wellness.rpe' => ['nullable', 'integer', 'min:1', 'max:10'],
            'wellness.energy' => ['nullable', 'integer', 'min:1', 'max:10'],
            'wellness.pain' => ['nullable', 'integer', 'min:0', 'max:10'],
            'wellness.sleep_hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'entries' => ['nullable', 'array', 'max:40'],
            'entries.*.title' => ['required', 'string', 'max:160'],
            'entries.*.sets' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'entries.*.reps' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'entries.*.weight_kg' => ['nullable', 'numeric', 'min:0', 'max:2000'],
            'entries.*.duration_minutes' => ['nullable', 'numeric', 'min:0', 'max:14400'],
            'entries.*.distance_km' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'entries.*.intensity' => ['nullable', 'string', 'max:30'],
            'entries.*.notes' => ['nullable', 'string', 'max:2000'],
            'entries.*.exercise_key' => ['nullable', 'string', 'max:64'],
            'entries.*.set_index' => ['nullable', 'integer', 'min:1', 'max:100'],
            'entries.*.tracking_mode' => ['nullable', Rule::in(['reps', 'time', 'distance', 'rounds'])],
            'entries.*.rest_seconds' => ['nullable', 'integer', 'min:0', 'max:3600'],
            'entries.*.rounds' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'entries.*.completed' => ['nullable', 'boolean'],
        ]);
    }

    private function resolveVisiblePlanItem(Request $request, array $data): ?TrainingPlanItem
    {
        if (empty($data['training_plan_item_id'])) {
            return null;
        }

        $item = TrainingPlanItem::query()
            ->with('plan')
            ->findOrFail($data['training_plan_item_id']);

        abort_unless(
            $item->plan
                && $this->visiblePlans($request)->whereKey($item->training_plan_id)->exists(),
            403
        );

        return $item;
    }

    private function loadLogForResource(TrainingLog $log): TrainingLog
    {
        return $log->load([
            'athlete',
            'trainer',
            'team',
            'plan',
            'entries',
            'feedbacks.author',
            ...$this->logRouteRelations(request()),
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
                'target_type' => $data['target_type'],
                'team_mode' => $data['team_mode'],
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
            'sport_route_id' => ['nullable', 'integer'],
            'todos' => ['nullable', 'array'],
            'todos.*' => ['nullable', 'string', 'max:300'],
            'todos_text' => ['nullable', 'string', 'max:3000'],
            'video_url' => ['nullable', 'url', 'max:2048'],
            'image' => ['nullable', 'image', 'max:5120'],
            'metrics' => ['nullable', 'array'],
            'metrics.*' => ['nullable', 'string', 'max:120'],
        ]);
    }

    private function planItemPayload(array $data, ?string $imagePath = null): array
    {
        return [
            'title' => $data['title'],
            'sport_route_id' => $data['sport_route_id'] ?? null,
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
        return $plan
            ->load(['creator', 'team', 'items.sportRoute', 'assignments.user', 'assignments.team'])
            ->loadCount('assignments');
    }

    private function clonePlan(
        Request $request,
        TrainingPlan $source,
        string $title,
        ?string $startsOn,
        ?string $endsOn,
        array $settings,
    ): TrainingPlan {
        return DB::transaction(function () use (
            $request,
            $source,
            $title,
            $startsOn,
            $endsOn,
            $settings,
        ) {
            $source->load(['items', 'assignments']);

            $copy = $source->replicate();
            $copy->created_by = $request->user()->id;
            $copy->title = trim($title);
            $copy->status = 'draft';
            $copy->starts_on = $startsOn;
            $copy->ends_on = $endsOn;
            $copy->settings = [
                ...($source->settings ?? []),
                ...$settings,
            ];
            $copy->save();

            $source->items->each(function (TrainingPlanItem $item) use ($copy) {
                $itemCopy = $item->replicate();
                $itemCopy->training_plan_id = $copy->id;
                $itemCopy->scheduled_at = null;
                $itemCopy->save();
            });

            $source->assignments->each(fn ($assignment) => $copy->assignments()->create([
                'user_id' => $assignment->user_id,
                'team_id' => $assignment->team_id,
                'permission' => $assignment->permission,
            ]));

            return $copy;
        });
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
        $teamIds = $this->resources->trainingPlanTeamIds($user)->all();

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
        return $this->logAccess->visibleQuery($request->user());
    }

    /** @return array<string, mixed> */
    private function logRouteRelations(Request $request): array
    {
        return [
            'sportRoute' => fn ($query) => $query
                ->visibleTo($request->user())
                ->select($this->routeLinks->routeColumns()),
            'sportRouteTrack' => fn ($query) => $query
                ->select($this->routeLinks->trackColumns()),
        ];
    }

    private function perPage(Request $request): int
    {
        return min(max((int) $request->integer('per_page', 20), 1), 50);
    }
}
