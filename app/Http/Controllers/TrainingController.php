<?php

namespace App\Http\Controllers;

use App\Models\ConnectedSportActivity;
use App\Models\Event;
use App\Models\Sport;
use App\Models\Team;
use App\Models\TrainingLog;
use App\Models\TrainingPlan;
use App\Models\TrainingPlanItem;
use App\Models\User;
use App\Services\Ai\AirmiusAiService;
use App\Services\Training\AthleteSportProfileService;
use App\Services\Training\TrainingAiPlanService;
use App\Services\Training\TrainingFeedbackService;
use App\Services\Training\TrainingLogAccessService;
use App\Services\Training\TrainingLogService;
use App\Services\Training\TrainingPlanQualityService;
use App\Services\Training\TrainingResourceService;
use App\Services\Training\TrainingRouteLinkService;
use App\Support\AppNotification;
use App\Support\Roles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class TrainingController extends Controller
{
    public function __construct(
        private TrainingResourceService $resources,
        private TrainingLogAccessService $logAccess,
        private TrainingLogService $logs,
        private TrainingFeedbackService $feedback,
        private TrainingRouteLinkService $routeLinks,
        private TrainingAiPlanService $aiPlans,
    ) {}

    public function index(Request $request, AirmiusAiService $ai)
    {
        $user = $request->user();
        $teamIds = $this->resources->trainingPlanTeamIds($user);
        $manageableAthletes = null;
        $resolveManageableAthletes = function () use ($user, &$manageableAthletes) {
            return $manageableAthletes ??= $this->logAccess->manageableAthletes($user);
        };

        return Inertia::render('Auth/Dashboard/Training/Index', [
            'plans' => fn () => TrainingPlan::query()
                ->with([
                    'creator:id,name,first_name,last_name,email',
                    'team:id,name',
                    'items.logs',
                    'items.sportRoute',
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
                ->map(fn (TrainingPlan $plan) => $this->resources->plan($plan, $user)),
            'canManageTrainingPlans' => $this->resources->canManageTrainingPlans($user),
            'activeDraftLog' => function () use ($user) {
                $activeDraft = $this->logs->currentDraft($user, true);

                return $activeDraft ? $this->resources->log($activeDraft->load([
                    'athlete:id,name,first_name,last_name,email',
                    'creator:id,name,first_name,last_name,email',
                    'trainer:id,name,first_name,last_name,email',
                    'team:id,name',
                    'plan:id,title',
                    'planItem:id,title,scheduled_at',
                    'entries',
                    ...$this->logRouteRelations($user),
                ])) : null;
            },
            'activities' => fn () => $user
                ->connectedSportActivities()
                ->latest('started_at')
                ->limit(20)
                ->get(['id', 'provider', 'activity_type', 'title', 'started_at', 'duration_seconds', 'distance_meters', 'calories', 'metrics', 'image_path'])
                ->map(fn ($activity) => [
                    ...$activity->toArray(),
                    'image_url' => $activity->image_path ? Storage::disk('public')->url($activity->image_path) : null,
                ]),
            'logs' => function () use ($user, $resolveManageableAthletes) {
                $manageableAthleteIds = $resolveManageableAthletes()->pluck('id');

                return TrainingLog::query()
                    ->with([
                        'athlete:id,name,first_name,last_name,email',
                        'creator:id,name,first_name,last_name,email',
                        'trainer:id,name,first_name,last_name,email',
                        'team:id,name',
                        'plan:id,title',
                        'planItem:id,title,scheduled_at',
                        'entries',
                        ...$this->logRouteRelations($user),
                    ])
                    ->where(function ($query) use ($user, $manageableAthleteIds) {
                        $query->where('user_id', $user->id);

                        if ($manageableAthleteIds->isNotEmpty()) {
                            $query->orWhereIn('user_id', $manageableAthleteIds);
                        }
                    })
                    ->latest('performed_at')
                    ->latest('id')
                    ->limit(60)
                    ->get()
                    ->map(fn (TrainingLog $log) => $this->resources->log($log));
            },
            'manageableAthletes' => fn () => $resolveManageableAthletes()->map(fn (User $athlete) => $this->resources->user($athlete)),
            'sportCatalog' => fn () => Sport::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'category'])
                ->map(fn (Sport $sport) => [
                    'id' => $sport->id,
                    'name' => $sport->name,
                    'slug' => $sport->slug,
                    'category' => $sport->category,
                ]),
            'teams' => fn () => Team::query()
                ->whereIn('teams.id', $teamIds)
                ->with('users:id,name,first_name,last_name,email')
                ->orderBy('name')
                ->get(['teams.id', 'teams.name', 'teams.club_id'])
                ->map(fn (Team $team) => [
                    'id' => $team->id,
                    'name' => $team->name,
                    'club_id' => $team->club_id,
                    'users' => $team->users->map(fn (User $member) => [
                        ...$this->resources->user($member),
                        'team_role' => $member->pivot?->role,
                    ]),
                ]),
            // Legacy edit form data; new plan creation uses privatePeople and
            // derives team members only from the selected team.
            'people' => fn () => $resolveManageableAthletes()->map(fn (User $person) => $this->resources->user($person)),
            'privatePeople' => fn () => $user->friendships()
                ->with('friend:id,name,first_name,last_name,email')
                ->get()
                ->map(fn ($friendship) => $friendship->friend ? $this->resources->user($friendship->friend) : null)
                ->filter()
                ->values(),
            'aiCapabilities' => fn () => $ai->capabilities($user),
            'sportRoutes' => fn () => $this->routeLinks->selectableRoutes($user),
            'sportRouteTracks' => fn () => $this->routeLinks->selectableTracks($user),
        ]);
    }

    public function createLog(Request $request)
    {
        $user = $request->user();
        $draft = $this->logs->findOrCreateDraft($user);
        $props = $this->logFormProps($user, $draft);
        $prefillPlanItemId = $request->integer('plan_item_id');
        $prefillEventId = $request->integer('event_id');

        if ($prefillPlanItemId > 0) {
            $isVisible = collect($props['plans'])->contains(
                fn (array $plan) => collect($plan['items'] ?? [])->contains(
                    fn (array $item) => (int) ($item['id'] ?? 0) === $prefillPlanItemId
                )
            );

            abort_unless($isVisible, 404);
        }

        $prefillEvent = null;

        if ($prefillEventId > 0) {
            $event = Event::query()
                ->visibleTo($user)
                ->where('type', 'training')
                ->whereKey($prefillEventId)
                ->firstOrFail();

            $prefillEvent = [
                'id' => $event->id,
                'title' => $event->title,
                'start_time' => $event->start_time?->toIso8601String(),
                'sport_route_id' => $event->sport_route_id,
            ];
        }

        return Inertia::render('Auth/Dashboard/Training/LogCreate', [
            ...$props,
            'prefillPlanItemId' => $prefillPlanItemId > 0 ? $prefillPlanItemId : null,
            'prefillEvent' => $prefillEvent,
        ]);
    }

    public function showLog(Request $request, TrainingLog $log)
    {
        abort_unless($this->logAccess->canView($request->user(), $log), 403);

        $log->load([
            'athlete:id,name,first_name,last_name,email',
            'creator:id,name,first_name,last_name,email',
            'trainer:id,name,first_name,last_name,email',
            'team:id,name',
            'plan:id,title',
            'planItem:id,title,sport_type,description,scheduled_at,duration_minutes,distance_meters,calories,intensity,todos,metrics',
            'planItem.sportRoute',
            'entries',
            'feedbacks.author:id,name,first_name,last_name,email',
            ...$this->logRouteRelations($request->user()),
        ]);

        return Inertia::render('Auth/Dashboard/Training/LogShow', [
            'log' => $this->resources->log($log),
        ]);
    }

    public function showPlanItem(Request $request, TrainingPlan $plan, TrainingPlanItem $item)
    {
        abort_unless((int) $item->training_plan_id === (int) $plan->id, 404);
        abort_unless($this->canViewPlan($request->user(), $plan), 403);

        $plan->load([
            'creator:id,name,first_name,last_name,email',
            'team:id,name',
            'items.logs',
            'items.sportRoute',
            'assignments.user:id,name,first_name,last_name,email',
            'assignments.team:id,name',
        ]);

        $item->load([
            'sportRoute',
            'logs.athlete:id,name,first_name,last_name,email',
            'logs.creator:id,name,first_name,last_name,email',
            'logs.trainer:id,name,first_name,last_name,email',
            'logs.entries',
        ]);

        return Inertia::render('Auth/Dashboard/Training/PlanItemShow', [
            'plan' => $this->resources->plan($plan, $request->user()),
            'item' => $this->resources->planItemDetail($item),
        ]);
    }

    public function storeLogFeedback(Request $request, TrainingLog $log)
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:3000'],
        ]);

        $feedback = $this->feedback->create($request->user(), $log, $data['body']);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('server.training.feedback_sent'),
                'data' => $feedback,
            ], 201);
        }

        return back()->with('success', __('server.training.feedback_sent'));
    }

    public function previewAiTrainingPlan(
        Request $request,
        AirmiusAiService $ai,
        AthleteSportProfileService $sportProfiles,
        TrainingPlanQualityService $quality,
    ) {
        abort_unless($this->resources->canManageTrainingPlans($request->user()), 403, __('server.training.manage_plans_forbidden'));

        $maxPlanItems = $this->aiPlans->maxItems();
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:160'],
            'goal' => ['required', 'string', 'min:3', 'max:500'],
            'sport_type' => ['required', 'string', 'max:80'],
            'training_type' => ['nullable', 'string', 'max:80'],
            'level' => ['required', Rule::in(['beginner', 'intermediate', 'advanced', 'elite'])],
            'phase' => ['required', Rule::in(['base', 'build', 'peak', 'recovery', 'rehab'])],
            'weeks' => ['required', 'integer', 'min:1', 'max:26'],
            'sessions_per_week' => ['required', 'integer', 'min:1', 'max:6'],
            'duration_minutes' => ['nullable', 'integer', 'min:10', 'max:240'],
            'starts_on' => ['nullable', 'date'],
            'equipment' => ['nullable', 'string', 'max:800'],
            'constraints' => ['nullable', 'string', 'max:1200'],
            'preferences' => ['nullable', 'string', 'max:1200'],
            'revision_instruction' => ['nullable', 'string', 'max:1200'],
            'current_plan' => ['nullable', 'array'],
            'allow_profile_estimate' => ['sometimes', 'boolean'],
        ]);

        if (((int) $data['weeks'] * (int) $data['sessions_per_week']) > $maxPlanItems) {
            return response()->json([
                'message' => __('server.training.ai.plan_too_large', ['max' => $maxPlanItems]),
            ], 422);
        }

        $profileReadiness = $sportProfiles->readiness($request->user(), $data['sport_type']);

        $profileFieldsToResolve = collect([
            ...($profileReadiness['missing'] ?? []),
            ...($profileReadiness['unknown'] ?? []),
        ]);
        $missing = $profileFieldsToResolve
            ->pluck('label')
            ->filter()
            ->take(6)
            ->implode(', ');
        $allowProfileEstimate = (bool) ($data['allow_profile_estimate'] ?? false);

        if (! $profileReadiness['ready'] && ! $allowProfileEstimate) {
            return response()->json([
                'message' => __('server.training.ai.profile_missing', [
                    'sport' => $profileReadiness['sport']['name'],
                    'missing' => $missing,
                ]),
                'missing_profile_fields' => $profileFieldsToResolve->values()->all(),
                'profile_completion_url' => route('auth.settings', ['tab' => 'sport-profile']),
                'profile_estimate_allowed' => true,
            ], 422);
        }

        $data['athlete_profile'] = $profileReadiness['profile'];
        $data['profile_readiness'] = [
            'ready' => (bool) $profileReadiness['ready'],
            'score' => (int) ($profileReadiness['score'] ?? 0),
            'missing' => $profileReadiness['missing'] ?? [],
            'unknown' => $profileReadiness['unknown'] ?? [],
            'sport' => $profileReadiness['sport'] ?? null,
            'group' => $profileReadiness['group'] ?? 'generic',
        ];
        $data['profile_estimate_mode'] = ! $profileReadiness['ready'];

        try {
            $plan = $ai->generateTrainingPlan($request->user(), $data);
            $plan['profile_estimate_mode'] = ! $profileReadiness['ready'];
            $plan['profile_readiness'] = $data['profile_readiness'];
            $plan['quality_check'] = $quality->evaluate($plan, $profileReadiness, $data);

            if (! $profileReadiness['ready']) {
                $plan['warnings'] = array_values(array_unique(array_filter([
                    ...($plan['warnings'] ?? []),
                    __('server.training.ai.estimate_warning', ['missing' => $missing]),
                ])));
                $plan['analysis_tips'] = array_values(array_unique(array_filter([
                    ...($plan['analysis_tips'] ?? []),
                    __('server.training.ai.estimate_tip'),
                ])));
            }

            $plan = $this->aiPlans->attachSafetyProof($request->user(), $plan);

            return response()->json([
                'message' => $profileReadiness['ready']
                    ? __('server.training.ai.preview_ready')
                    : __('server.training.ai.preview_estimated'),
                'plan' => $plan,
            ]);
        } catch (\Throwable $exception) {
            Log::warning('AI training plan generation failed.', [
                'user_id' => $request->user()->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => __('server.training.ai.unavailable'),
                'code' => 'training_ai_unavailable',
            ], 422);
        }
    }

    public function storeAiTrainingPlan(Request $request)
    {
        $user = $request->user();
        abort_unless($this->resources->canManageTrainingPlans($user), 403, __('server.training.manage_plans_forbidden'));

        $teamIds = $this->resources->trainingPlanTeamIds($user)->all();
        $maxPlanItems = $this->aiPlans->maxItems();
        $data = $request->validate([
            'plan' => ['required', 'array'],
            'plan.title' => ['required', 'string', 'max:160'],
            'plan.summary' => ['nullable', 'string', 'max:1000'],
            'plan.convincing_explanation' => ['nullable', 'string', 'max:2000'],
            'plan.progression_logic' => ['nullable', 'array'],
            'plan.progression_logic.*' => ['nullable', 'string', 'max:300'],
            'plan.analysis_tips' => ['nullable', 'array'],
            'plan.analysis_tips.*' => ['nullable', 'string', 'max:300'],
            'plan.adjustment_tips' => ['nullable', 'array'],
            'plan.adjustment_tips.*' => ['nullable', 'string', 'max:300'],
            'plan.warnings' => ['nullable', 'array'],
            'plan.warnings.*' => ['nullable', 'string', 'max:300'],
            'plan.quality_check' => ['nullable', 'array'],
            'plan.safety_gate' => ['required', 'array'],
            'plan.safety_token' => ['required', 'string', 'max:5000'],
            'plan.profile_estimate_mode' => ['nullable', 'boolean'],
            'plan.profile_readiness' => ['nullable', 'array'],
            'plan.provider' => ['nullable', 'string', 'max:80'],
            'plan.provider_label' => ['nullable', 'string', 'max:120'],
            'plan.model' => ['nullable', 'string', 'max:160'],
            'plan.settings' => ['nullable', 'array'],
            'plan.settings.goal' => ['nullable', 'string', 'max:200'],
            'plan.settings.phase' => ['nullable', Rule::in(['base', 'build', 'peak', 'recovery', 'rehab'])],
            'plan.settings.level' => ['nullable', Rule::in(['beginner', 'intermediate', 'advanced', 'elite'])],
            'plan.settings.weeks' => ['nullable', 'integer', 'min:1', 'max:104'],
            'plan.settings.weekly_sessions' => ['nullable', 'integer', 'min:1', 'max:21'],
            'plan.items' => ['required', 'array', 'min:1', 'max:'.$maxPlanItems],
            'plan.items.*.week' => ['nullable', 'integer', 'min:1', 'max:104'],
            'plan.items.*.day' => ['nullable', 'string', 'max:30'],
            'plan.items.*.title' => ['required', 'string', 'max:160'],
            'plan.items.*.sport_type' => ['nullable', 'string', 'max:80'],
            'plan.items.*.training_type' => ['nullable', 'string', 'max:80'],
            'plan.items.*.focus' => ['nullable', 'string', 'max:160'],
            'plan.items.*.description' => ['nullable', 'string', 'max:3000'],
            'plan.items.*.duration_minutes' => ['nullable', 'integer', 'min:0', 'max:14400'],
            'plan.items.*.distance_km' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'plan.items.*.calories' => ['nullable', 'integer', 'min:0', 'max:200000'],
            'plan.items.*.intensity' => ['nullable', Rule::in(['locker', 'mittel', 'hart', 'recovery'])],
            'plan.items.*.load' => ['nullable', Rule::in(['low', 'medium', 'high', 'test'])],
            'plan.items.*.todos' => ['nullable', 'array'],
            'plan.items.*.todos.*' => ['nullable', 'string', 'max:300'],
            'plan.items.*.metrics' => ['nullable', 'array'],
            'plan.items.*.metrics.*' => ['nullable', 'string', 'max:160'],
            'plan.items.*.rationale' => ['nullable', 'string', 'max:800'],
            'starts_on' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in(['draft', 'published'])],
            'share_permission' => ['nullable', Rule::in(['read', 'write'])],
            'team_id' => ['nullable', 'integer', Rule::in($teamIds)],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'accepted_ai_safety' => ['required', 'boolean'],
        ]);

        $data['safety_gate'] = $this->aiPlans->validateSafetyProof($user, $data);
        $plan = $this->aiPlans->storeFromPayload($user, $data);

        $plan->load([
            'creator:id,name,first_name,last_name,email',
            'team:id,name',
            'items.logs',
            'assignments.user:id,name,first_name,last_name,email',
            'assignments.team:id,name',
        ]);

        return response()->json([
            'message' => __('server.training.ai.saved'),
            'plan' => $this->resources->plan($plan, $user),
        ], 201);
    }

    public function storeLog(Request $request)
    {
        $user = $request->user();
        $manageableAthleteIds = $this->logAccess->manageableAthleteIds($user)->all();
        $allowedUserIds = array_values(array_unique(array_merge([(int) $user->id], $manageableAthleteIds)));
        $teamIds = $user->teams()->pluck('teams.id')->map(fn ($id) => (int) $id)->all();

        $data = $request->validate([
            'user_id' => ['nullable', 'integer', Rule::in($allowedUserIds)],
            'draft_log_id' => ['nullable', 'integer', 'exists:training_logs,id'],
            'team_id' => ['nullable', 'integer', Rule::in($teamIds)],
            'training_plan_item_id' => ['nullable', 'integer', 'exists:training_plan_items,id'],
            'sport_route_id' => ['nullable', 'integer'],
            'sport_route_track_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:160'],
            'sport_type' => ['nullable', 'string', 'max:80'],
            'status' => ['required', Rule::in(['planned', 'in_progress', 'completed', 'missed'])],
            'privacy_scope' => ['nullable', Rule::in(['private', 'trainer', 'team', 'selected'])],
            'performed_at' => ['nullable', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:0', 'max:14400'],
            'distance_km' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'calories' => ['nullable', 'integer', 'min:0', 'max:200000'],
            'intensity' => ['nullable', Rule::in(['locker', 'mittel', 'hart', 'recovery'])],
            'training_type' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'trainer_feedback' => ['nullable', 'string', 'max:5000'],
            'notify_people' => ['nullable', 'boolean'],
            'wellness' => ['nullable', 'array'],
            'wellness.rpe' => ['nullable', 'integer', 'min:1', 'max:10'],
            'wellness.energy' => ['nullable', 'integer', 'min:1', 'max:10'],
            'wellness.pain' => ['nullable', 'integer', 'min:0', 'max:10'],
            'wellness.sleep_hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'entries' => ['nullable', 'array', 'max:40'],
            'entries.*.title' => ['nullable', 'string', 'max:160'],
            'entries.*.sets' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'entries.*.reps' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'entries.*.weight_kg' => ['nullable', 'numeric', 'min:0', 'max:2000'],
            'entries.*.duration_minutes' => ['nullable', 'numeric', 'min:0', 'max:14400'],
            'entries.*.distance_km' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'entries.*.intensity' => ['nullable', 'string', 'max:30'],
            'entries.*.notes' => ['nullable', 'string', 'max:2000'],
            'entries.*.media_url' => ['nullable', 'url', 'max:2048'],
            'entries.*.media_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,mp4,mov,webm', 'max:51200'],
        ]);

        $athleteId = (int) ($data['user_id'] ?? $user->id);
        $planItem = null;

        if (! empty($data['training_plan_item_id'])) {
            $planItem = TrainingPlanItem::query()
                ->with('plan.assignments')
                ->findOrFail($data['training_plan_item_id']);

            abort_unless($this->canLogPlanItemForAthlete($user, $athleteId, $planItem, $manageableAthleteIds), 403);
        }

        $log = $this->logs->storeFromPayload($user, $data, $athleteId, $planItem, $request);

        if ((bool) ($data['notify_people'] ?? true)) {
            $this->notifyTrainingLogSaved($log->fresh([
                'athlete:id,name,first_name,last_name,email',
                'creator:id,name,first_name,last_name,email',
                'trainer:id,name,first_name,last_name,email',
                'plan:id,title,created_by',
                'planItem:id,title,training_plan_id',
            ]), $user);
        }

        return redirect()->route('auth.training.logs.show', $log)->with(
            'success',
            __($log->trainer_id ? 'server.training.log_created_for_athlete' : 'server.training.log_created')
        );
    }

    public function updateDraftLog(Request $request, TrainingLog $log)
    {
        $user = $request->user();

        abort_unless((int) $log->created_by === (int) $user->id && $log->status === 'draft', 403);

        $manageableAthleteIds = $this->logAccess->manageableAthleteIds($user)->all();
        $allowedUserIds = array_values(array_unique(array_merge([(int) $user->id], $manageableAthleteIds)));
        $teamIds = $user->teams()->pluck('teams.id')->map(fn ($id) => (int) $id)->all();

        $data = $request->validate([
            'user_id' => ['nullable', 'integer', Rule::in($allowedUserIds)],
            'team_id' => ['nullable', 'integer', Rule::in($teamIds)],
            'training_plan_item_id' => ['nullable', 'integer', 'exists:training_plan_items,id'],
            'sport_route_id' => ['nullable', 'integer'],
            'sport_route_track_id' => ['nullable', 'integer'],
            'title' => ['nullable', 'string', 'max:160'],
            'sport_type' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', Rule::in(['planned', 'in_progress', 'completed', 'missed'])],
            'privacy_scope' => ['nullable', Rule::in(['private', 'trainer', 'team', 'selected'])],
            'performed_at' => ['nullable', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:0', 'max:14400'],
            'distance_km' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'calories' => ['nullable', 'integer', 'min:0', 'max:200000'],
            'intensity' => ['nullable', Rule::in(['locker', 'mittel', 'hart', 'recovery'])],
            'training_type' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'trainer_feedback' => ['nullable', 'string', 'max:5000'],
            'notify_people' => ['nullable', 'boolean'],
            'wellness' => ['nullable', 'array'],
            'wellness.rpe' => ['nullable', 'integer', 'min:1', 'max:10'],
            'wellness.energy' => ['nullable', 'integer', 'min:1', 'max:10'],
            'wellness.pain' => ['nullable', 'integer', 'min:0', 'max:10'],
            'wellness.sleep_hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'entries' => ['nullable', 'array', 'max:40'],
            'entries.*.title' => ['nullable', 'string', 'max:160'],
            'entries.*.sets' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'entries.*.reps' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'entries.*.weight_kg' => ['nullable', 'numeric', 'min:0', 'max:2000'],
            'entries.*.duration_minutes' => ['nullable', 'numeric', 'min:0', 'max:14400'],
            'entries.*.distance_km' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'entries.*.intensity' => ['nullable', 'string', 'max:30'],
            'entries.*.notes' => ['nullable', 'string', 'max:2000'],
            'entries.*.media_url' => ['nullable', 'url', 'max:2048'],
            'entries.*.media_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,mp4,mov,webm', 'max:51200'],
        ]);

        $athleteId = (int) ($data['user_id'] ?? $user->id);
        $planItem = null;

        if (! empty($data['training_plan_item_id'])) {
            $planItem = TrainingPlanItem::query()
                ->with('plan.assignments')
                ->findOrFail($data['training_plan_item_id']);

            abort_unless($this->canLogPlanItemForAthlete($user, $athleteId, $planItem, $manageableAthleteIds), 403);
        }

        $this->logs->updateDraftFromPayload($log, $user, $data, $athleteId, $planItem, $request);

        return response()->json([
            'saved_at' => now()->toIso8601String(),
            'log' => $this->resources->log($log->load([
                'athlete:id,name,first_name,last_name,email',
                'creator:id,name,first_name,last_name,email',
                'trainer:id,name,first_name,last_name,email',
                'team:id,name',
                'plan:id,title',
                'planItem:id,title,scheduled_at',
                'entries',
                ...$this->logRouteRelations($user),
            ])),
        ]);
    }

    public function destroyDraftLog(Request $request, TrainingLog $log)
    {
        abort_unless((int) $log->created_by === (int) $request->user()->id && $log->status === 'draft', 403);

        $log->entries()->delete();
        $log->delete();

        return back()->with('success', __('server.training.draft_deleted'));
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

        return back()->with('success', __('server.training.log_entered'));
    }

    public function storePlan(Request $request)
    {
        abort_unless($this->resources->canManageTrainingPlans($request->user()), 403, __('server.training.manage_plans_forbidden'));

        $teamIds = $this->resources->trainingPlanTeamIds($request->user())->all();
        $data = $request->validate([
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
            'item_title' => ['required', 'string', 'max:160'],
            'item_sport_type' => ['nullable', 'string', 'max:80'],
            'item_description' => ['nullable', 'string', 'max:3000'],
            'item_scheduled_at' => ['nullable', 'date'],
            'item_week' => ['nullable', 'integer', 'min:1', 'max:104'],
            'item_duration_minutes' => ['nullable', 'integer', 'min:0', 'max:14400'],
            'item_distance_km' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'item_calories' => ['nullable', 'integer', 'min:0', 'max:200000'],
            'item_intensity' => ['nullable', Rule::in(['locker', 'mittel', 'hart', 'recovery'])],
            'item_load' => ['nullable', Rule::in(['low', 'medium', 'high', 'test'])],
            'item_focus' => ['nullable', 'string', 'max:160'],
            'item_todos' => ['nullable', 'string', 'max:3000'],
            'item_image' => ['nullable', 'image', 'max:5120'],
            'item_video_url' => ['nullable', 'url', 'max:2048'],
            'item_metrics' => ['nullable', 'array'],
            'item_metrics.*' => ['nullable', 'string', 'max:120'],
        ]);

        $data = $this->resources->normalizePlanAudience($request->user(), $data);

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
                'metrics' => [
                    ...$this->cleanMetrics($data['item_metrics'] ?? []),
                    ...array_filter([
                        'Woche' => $data['item_week'] ?? null,
                        'Belastung' => $data['item_load'] ?? null,
                        'Fokus' => $data['item_focus'] ?? null,
                    ], fn ($value) => $value !== null && $value !== ''),
                ],
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

        return back()->with(
            'success',
            __($data['status'] === 'published' ? 'server.training.plan_created_and_published' : 'server.training.plan_created')
        );
    }

    public function storePlanItem(Request $request, TrainingPlan $plan)
    {
        abort_unless($this->resources->canWritePlan($request->user(), $plan), 403);

        $data = $this->validatePlanItemData($request);
        $data['sport_route_id'] = $this->routeLinks
            ->resolvePlanRoute($request->user(), $data['sport_route_id'] ?? null)?->id;
        $imagePath = $request->file('image')?->store('training-plans', 'public');

        $item = $plan->items()->create([
            ...$this->planItemPayload($data, $imagePath),
            'sort_order' => $plan->items()->count() + 1,
        ]);

        $this->notifyPlanRecipients(
            $plan->fresh(['assignments.user', 'assignments.team.users', 'creator']),
            $request->user(),
            'server.training.notifications.item_added_title',
            'server.training.notifications.item_added_body',
            ['item' => $item->title, 'plan' => $plan->title],
            route('auth.training.plans.items.show', [$plan, $item]),
        );

        return back()->with('success', __('server.training.item_added'));
    }

    public function updatePlanItem(Request $request, TrainingPlan $plan, TrainingPlanItem $item)
    {
        abort_unless($this->resources->canWritePlan($request->user(), $plan), 403);
        abort_unless((int) $item->training_plan_id === (int) $plan->id, 404);

        $data = $this->validatePlanItemData($request);
        $data['sport_route_id'] = $this->routeLinks
            ->resolvePlanRoute($request->user(), $data['sport_route_id'] ?? null)?->id;
        $imagePath = $item->image_path;

        if ($request->hasFile('image')) {
            $this->deletePlanItemImageIfUnused($item);
            $imagePath = $request->file('image')->store('training-plans', 'public');
        }

        $item->update($this->planItemPayload($data, $imagePath));

        $this->notifyPlanRecipients(
            $plan->fresh(['assignments.user', 'assignments.team.users', 'creator']),
            $request->user(),
            'server.training.notifications.item_updated_title',
            'server.training.notifications.item_updated_body',
            ['item' => $item->title, 'plan' => $plan->title],
            route('auth.training.plans.items.show', [$plan, $item]),
        );

        return back()->with('success', __('server.training.item_updated'));
    }

    public function duplicatePlanItem(Request $request, TrainingPlan $plan, TrainingPlanItem $item)
    {
        abort_unless($this->resources->canWritePlan($request->user(), $plan), 403);
        abort_unless((int) $item->training_plan_id === (int) $plan->id, 404);

        $copy = $item->replicate();
        $copy->title = Str::limit($item->title.' Kopie', 160, '');
        $copy->sort_order = $plan->items()->count() + 1;
        $copy->scheduled_at = null;
        $copy->save();

        $this->notifyPlanRecipients(
            $plan->fresh(['assignments.user', 'assignments.team.users', 'creator']),
            $request->user(),
            'server.training.notifications.item_duplicated_title',
            'server.training.notifications.item_duplicated_body',
            ['item' => $copy->title, 'plan' => $plan->title],
            route('auth.training.plans.items.show', [$plan, $copy]),
        );

        return back()->with('success', __('server.training.item_duplicated'));
    }

    public function destroyPlanItem(Request $request, TrainingPlan $plan, TrainingPlanItem $item)
    {
        abort_unless($this->resources->canWritePlan($request->user(), $plan), 403);
        abort_unless((int) $item->training_plan_id === (int) $plan->id, 404);

        $this->deletePlanItemImageIfUnused($item);
        $item->delete();

        return back()->with('success', __('server.training.item_deleted'));
    }

    public function updatePlan(Request $request, TrainingPlan $plan)
    {
        abort_unless($this->resources->canWritePlan($request->user(), $plan), 403);

        $teamIds = $this->resources->trainingPlanTeamIds($request->user())->all();
        $data = $request->validate([
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
        ]);

        $data = $this->resources->normalizePlanAudience($request->user(), $data);

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
                'settings' => [
                    ...($plan->settings ?? []),
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

        $this->notifyPlanRecipients(
            $plan->fresh(['assignments.user', 'assignments.team.users', 'creator']),
            $request->user(),
            'server.training.notifications.plan_updated_title',
            'server.training.notifications.plan_updated_body',
            ['plan' => $plan->title],
            route('auth.training.index'),
        );

        return back()->with('success', __('server.training.plan_updated'));
    }

    public function publishPlan(Request $request, TrainingPlan $plan)
    {
        abort_unless($this->resources->canWritePlan($request->user(), $plan), 403);

        $plan->update(['status' => 'published']);

        $this->notifyPlanRecipients(
            $plan->fresh(['assignments.user', 'assignments.team.users', 'creator']),
            $request->user(),
            'server.training.notifications.plan_published_title',
            'server.training.notifications.plan_published_body',
            ['plan' => $plan->title],
            route('auth.training.index'),
        );

        return back()->with('success', __('server.training.plan_published'));
    }

    public function duplicatePlan(Request $request, TrainingPlan $plan)
    {
        $canUseTemplate = (bool) data_get($plan->settings, 'is_template', false)
            && $this->resources->canManageTrainingPlans($request->user());

        abort_unless($this->resources->canWritePlan($request->user(), $plan) || $canUseTemplate, 403);

        DB::transaction(function () use ($request, $plan) {
            $plan->load(['items', 'assignments']);

            $copy = $plan->replicate();
            $copy->created_by = $request->user()->id;
            $copy->title = Str::limit($plan->title.' Vorlage', 160, '');
            $copy->status = 'draft';
            $copy->settings = [
                ...($plan->settings ?? []),
                'created_from_template_id' => $plan->id,
                'is_template_copy' => true,
            ];
            $copy->save();

            $plan->items->each(function (TrainingPlanItem $item) use ($copy) {
                $itemCopy = $item->replicate();
                $itemCopy->training_plan_id = $copy->id;
                $itemCopy->scheduled_at = null;
                $itemCopy->save();
            });

            $plan->assignments->each(fn ($assignment) => $copy->assignments()->create([
                'user_id' => $assignment->user_id,
                'team_id' => $assignment->team_id,
                'permission' => $assignment->permission,
            ]));
        });

        return back()->with('success', __('server.training.plan_copied'));
    }

    public function markPlanItemMissed(Request $request, TrainingPlan $plan, TrainingPlanItem $item)
    {
        abort_unless((int) $item->training_plan_id === (int) $plan->id, 404);

        $user = $request->user();
        $manageableAthleteIds = $this->logAccess->manageableAthleteIds($user)->all();
        $allowedUserIds = array_values(array_unique(array_merge([(int) $user->id], $manageableAthleteIds)));

        $data = $request->validate([
            'user_id' => ['nullable', 'integer', Rule::in($allowedUserIds)],
            'reason' => ['required', Rule::in(['krank', 'verletzt', 'keine_zeit', 'verschoben', 'bewusst_ausgelassen', 'anderes'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $athleteId = (int) ($data['user_id'] ?? $user->id);
        abort_unless($this->canLogPlanItemForAthlete($user, $athleteId, $item->loadMissing('plan.assignments'), $manageableAthleteIds), 403);

        $missedLog = TrainingLog::query()->updateOrCreate(
            [
                'user_id' => $athleteId,
                'training_plan_item_id' => $item->id,
                'status' => 'missed',
            ],
            [
                'created_by' => $user->id,
                'trainer_id' => $athleteId === (int) $user->id ? null : $user->id,
                'team_id' => $plan->team_id,
                'training_plan_id' => $plan->id,
                'sport_type' => $item->sport_type,
                'title' => $item->title,
                'performed_at' => now(),
                'notes' => $data['notes'] ?? null,
                'metrics' => [
                    'source_kind' => 'missed_planned_training',
                    'missed_reason' => $data['reason'],
                    'missed_at' => now()->toIso8601String(),
                ],
            ],
        );

        $this->notifyPlanRecipients(
            $plan->fresh(['assignments.user', 'assignments.team.users', 'creator']),
            $user,
            'server.training.notifications.item_missed_title',
            'server.training.notifications.item_missed_body',
            ['item' => $item->title],
            route('auth.training.logs.show', $missedLog),
        );

        return back()->with('success', __('server.training.item_missed'));
    }

    public function destroyPlan(Request $request, TrainingPlan $plan)
    {
        abort_unless($this->resources->canDeletePlan($request->user(), $plan), 403);

        $plan->load('items');

        $plan->items
            ->pluck('image_path')
            ->filter()
            ->each(fn ($path) => Storage::disk('public')->delete($path));

        $plan->delete();

        return back()->with('success', __('server.training.plan_deleted'));
    }

    private function logFormProps(User $user, ?TrainingLog $draft = null): array
    {
        $teamIds = $this->resources->trainingPlanTeamIds($user);
        $manageableAthletes = $this->logAccess->manageableAthletes($user);

        $plans = TrainingPlan::query()
            ->with(['team:id,name', 'items.logs', 'items.sportRoute', 'assignments.user:id,name,first_name,last_name,email', 'assignments.team:id,name'])
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
            ->limit(50)
            ->get()
            ->map(fn (TrainingPlan $plan) => $this->resources->plan($plan, $user));

        return [
            'plans' => $plans,
            'manageableAthletes' => $manageableAthletes->map(fn (User $athlete) => $this->resources->user($athlete)),
            'sportCatalog' => Sport::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'category'])
                ->map(fn (Sport $sport) => [
                    'id' => $sport->id,
                    'name' => $sport->name,
                    'slug' => $sport->slug,
                    'category' => $sport->category,
                ]),
            'teams' => $user
                ->teams()
                ->orderBy('name')
                ->get(['teams.id', 'teams.name', 'teams.club_id'])
                ->map(fn (Team $team) => [
                    'id' => $team->id,
                    'name' => $team->name,
                    'club_id' => $team->club_id,
                ]),
            'recentExercises' => $this->recentExerciseSuggestions($user, $manageableAthletes),
            'recentSports' => $this->recentSportSuggestions($user, $manageableAthletes),
            'sportRoutes' => $this->routeLinks->selectableRoutes($user),
            'sportRouteTracks' => $this->routeLinks->selectableTracks($user),
            'draftLog' => $draft ? $this->resources->log($draft->loadMissing([
                'athlete:id,name,first_name,last_name,email',
                'creator:id,name,first_name,last_name,email',
                'trainer:id,name,first_name,last_name,email',
                'team:id,name',
                'plan:id,title',
                'planItem:id,title,scheduled_at',
                'entries',
                ...$this->logRouteRelations($user),
            ])) : null,
        ];
    }

    private function canViewPlan(User $user, TrainingPlan $plan): bool
    {
        if ((int) $plan->created_by === (int) $user->id || $user->hasAnyRole(Roles::FULL_ACCESS)) {
            return true;
        }

        $teamIds = $this->resources->trainingPlanTeamIds($user);

        return $plan->assignments()
            ->where(function ($query) use ($user, $teamIds) {
                $query
                    ->where('user_id', $user->id)
                    ->orWhereIn('team_id', $teamIds);
            })
            ->exists();
    }

    private function notifyTrainingLogSaved(TrainingLog $log, User $actor): void
    {
        $actorName = $this->resources->user($actor)['name'];
        $athleteName = $log->athlete
            ? $this->resources->user($log->athlete)['name']
            : AppNotification::translatedReplacement(
                'server.training.notifications.fallback_athlete',
                'Sportler',
            );
        $privacyScope = $log->metrics['privacy_scope'] ?? 'trainer';

        if ($privacyScope === 'private') {
            $recipientIds = (int) $log->user_id !== (int) $actor->id
                ? collect([(int) $log->user_id])
                : collect();
        } else {
            $recipientIds = collect([
                (int) $log->user_id !== (int) $actor->id ? $log->user_id : null,
                (int) $log->trainer_id !== (int) $actor->id ? $log->trainer_id : null,
                $log->plan?->created_by && (int) $log->plan->created_by !== (int) $actor->id ? $log->plan->created_by : null,
                (int) $log->created_by !== (int) $actor->id ? $log->created_by : null,
            ]);
        }

        $recipientIds = $recipientIds->filter()
            ->map(fn ($id) => (int) $id)
            ->unique();

        User::query()
            ->select(['id', 'language'])
            ->whereKey($recipientIds->all())
            ->get()
            ->each(fn (User $recipient) => AppNotification::sendLocalized(
                $recipient,
                'training.log.saved',
                'server.training.notifications.log_saved_title',
                'server.training.notifications.log_saved_body',
                ['actor' => $actorName, 'log' => $log->title, 'athlete' => $athleteName],
                [
                    'url' => route('auth.training.logs.show', $log),
                    'training_log_id' => $log->id,
                ],
            ));
    }

    private function notifyPlanRecipients(
        TrainingPlan $plan,
        User $actor,
        string $titleKey,
        string $bodyKey,
        array $replace,
        string $url,
    ): void {
        $recipients = collect([$plan->creator])
            ->merge($plan->assignments->pluck('user'))
            ->merge($plan->assignments->flatMap(fn ($assignment) => $assignment->team?->users ?? collect()))
            ->filter()
            ->unique('id')
            ->reject(fn (User $recipient) => (int) $recipient->id === (int) $actor->id);

        $recipients->each(fn (User $recipient) => AppNotification::sendLocalized(
            $recipient,
            'training.plan.changed',
            $titleKey,
            $bodyKey,
            $replace,
            [
                'url' => $url,
                'training_plan_id' => $plan->id,
            ],
        ));
    }

    private function recentExerciseSuggestions(User $user, $manageableAthletes): array
    {
        $visibleAthleteIds = collect([(int) $user->id])
            ->merge($manageableAthletes->pluck('id')->map(fn ($id) => (int) $id))
            ->unique()
            ->values();

        $suggestions = [];

        TrainingLog::query()
            ->with('entries')
            ->whereIn('user_id', $visibleAthleteIds)
            ->where('sport_type', 'gym')
            ->where('status', '!=', 'draft')
            ->latest('performed_at')
            ->latest('id')
            ->limit(120)
            ->get()
            ->each(function (TrainingLog $log) use (&$suggestions) {
                $athleteKey = (string) $log->user_id;

                foreach ($log->entries->groupBy(fn ($entry) => $this->exerciseBaseTitle($entry->title)) as $title => $entries) {
                    if (! $title) {
                        continue;
                    }

                    $session = [
                        'title' => $title,
                        'performed_at' => $log->performed_at?->toIso8601String(),
                        'sets' => $entries->values()->map(fn ($entry) => [
                            'reps' => $entry->reps,
                            'weight_kg' => $entry->weight_kg,
                            'duration_minutes' => $entry->duration_seconds ? round($entry->duration_seconds / 60, 1) : null,
                            'intensity' => $entry->intensity,
                            'notes' => $this->cleanSetNotes($entry->notes),
                        ])->all(),
                    ];

                    if (! isset($suggestions[$athleteKey][$title])) {
                        $suggestions[$athleteKey][$title] = [
                            ...$session,
                            'history' => [],
                        ];
                    }

                    if (count($suggestions[$athleteKey][$title]['history']) < 5) {
                        $suggestions[$athleteKey][$title]['history'][] = $session;
                    }
                }
            });

        return collect($suggestions)
            ->map(fn ($items) => collect($items)->values()->take(20)->all())
            ->all();
    }

    private function recentSportSuggestions(User $user, $manageableAthletes): array
    {
        $visibleAthleteIds = collect([(int) $user->id])
            ->merge($manageableAthletes->pluck('id')->map(fn ($id) => (int) $id))
            ->unique()
            ->values();

        $suggestions = [];

        TrainingLog::query()
            ->whereIn('user_id', $visibleAthleteIds)
            ->whereNotNull('sport_type')
            ->where('status', '!=', 'draft')
            ->latest('performed_at')
            ->latest('id')
            ->limit(150)
            ->get(['id', 'user_id', 'sport_type', 'status', 'performed_at'])
            ->each(function (TrainingLog $log) use (&$suggestions) {
                $athleteKey = (string) $log->user_id;
                $sportKey = trim((string) $log->sport_type);

                if ($sportKey === '') {
                    return;
                }

                $suggestions[$athleteKey] ??= [];

                if (count($suggestions[$athleteKey]) >= 5 || in_array($sportKey, $suggestions[$athleteKey], true)) {
                    return;
                }

                $suggestions[$athleteKey][] = $sportKey;
            });

        return $suggestions;
    }

    private function exerciseBaseTitle(?string $title): string
    {
        return trim((string) preg_replace('/\s+-\s+Satz\s+\d+$/i', '', (string) $title));
    }

    private function cleanSetNotes(?string $notes): ?string
    {
        $cleaned = trim(str_replace(['Erledigt | ', 'Erledigt'], '', (string) $notes));

        return $cleaned !== '' ? $cleaned : null;
    }

    private function canLogPlanItemForAthlete(User $user, int $athleteId, TrainingPlanItem $item, array $manageableAthleteIds): bool
    {
        if ($athleteId === (int) $user->id) {
            $teamIds = $user->teams()->pluck('teams.id');

            return $item->plan
                && (
                    (int) $item->plan->created_by === (int) $user->id
                    || $item->plan->assignments()
                        ->where(function ($query) use ($user, $teamIds) {
                            $query
                                ->where('user_id', $user->id)
                                ->orWhereIn('team_id', $teamIds);
                        })
                        ->exists()
                );
        }

        return in_array($athleteId, $manageableAthleteIds, true);
    }

    private function cleanMetrics(array $metrics): array
    {
        return collect($metrics)
            ->map(fn ($value) => is_string($value) ? trim($value) : $value)
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();
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
            'distance_km' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'calories' => ['nullable', 'integer', 'min:0', 'max:200000'],
            'intensity' => ['nullable', Rule::in(['locker', 'mittel', 'hart', 'recovery'])],
            'load' => ['nullable', Rule::in(['low', 'medium', 'high', 'test'])],
            'focus' => ['nullable', 'string', 'max:160'],
            'sport_route_id' => ['nullable', 'integer'],
            'todos' => ['nullable', 'string', 'max:3000'],
            'image' => ['nullable', 'image', 'max:5120'],
            'video_url' => ['nullable', 'url', 'max:2048'],
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
            'distance_meters' => isset($data['distance_km']) ? (int) round((float) $data['distance_km'] * 1000) : null,
            'calories' => $data['calories'] ?? null,
            'intensity' => $data['intensity'] ?? null,
            'image_path' => $imagePath,
            'video_url' => $data['video_url'] ?? null,
            'todos' => collect(preg_split('/\r\n|\r|\n/', $data['todos'] ?? ''))
                ->map(fn ($todo) => trim($todo))
                ->filter()
                ->values()
                ->all(),
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

    /** @return array<string, mixed> */
    private function logRouteRelations(User $viewer): array
    {
        return [
            'sportRoute' => fn ($query) => $query
                ->visibleTo($viewer)
                ->select($this->routeLinks->routeColumns()),
            'sportRouteTrack' => fn ($query) => $query
                ->select($this->routeLinks->trackColumns()),
        ];
    }
}
