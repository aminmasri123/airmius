<?php

namespace App\Http\Controllers;

use App\Models\ConnectedSportActivity;
use App\Models\Sport;
use App\Models\Team;
use App\Models\TrainingLog;
use App\Models\TrainingLogFeedback;
use App\Models\TrainingPlan;
use App\Models\TrainingPlanItem;
use App\Models\User;
use App\Services\Ai\AirmiusAiService;
use App\Services\Training\AthleteSportProfileService;
use App\Services\Training\TrainingPlanQualityService;
use App\Services\Training\TrainingResourceService;
use App\Support\Roles;
use App\Support\AppNotification;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class TrainingController extends Controller
{
    public function __construct(private TrainingResourceService $resources) {}

    public function index(Request $request, AirmiusAiService $ai)
    {
        $user = $request->user();
        $teamIds = $user->teams()->pluck('teams.id');
        $manageableAthletes = $this->manageableAthletes($user);
        $manageableAthleteIds = $manageableAthletes->pluck('id');
        $activeDraft = $this->currentDraftLog($user, true);

        $plans = TrainingPlan::query()
            ->with([
                'creator:id,name,first_name,last_name,email',
                'team:id,name',
                'items.logs',
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
            ->map(fn (TrainingPlan $plan) => $this->resources->plan($plan, $user));

        return Inertia::render('Auth/Dashboard/Training/Index', [
            'plans' => $plans,
            'activeDraftLog' => $activeDraft
                ? $this->resources->log($activeDraft->load([
                    'athlete:id,name,first_name,last_name,email',
                    'creator:id,name,first_name,last_name,email',
                    'trainer:id,name,first_name,last_name,email',
                    'team:id,name',
                    'plan:id,title',
                    'planItem:id,title,scheduled_at',
                    'entries',
                ]))
                : null,
            'activities' => $user
                ->connectedSportActivities()
                ->latest('started_at')
                ->limit(20)
                ->get(['id', 'provider', 'activity_type', 'title', 'started_at', 'duration_seconds', 'distance_meters', 'calories', 'metrics', 'image_path'])
                ->map(fn ($activity) => [
                    ...$activity->toArray(),
                    'image_url' => $activity->image_path ? Storage::disk('public')->url($activity->image_path) : null,
                ]),
            'logs' => TrainingLog::query()
                ->with([
                    'athlete:id,name,first_name,last_name,email',
                    'creator:id,name,first_name,last_name,email',
                    'trainer:id,name,first_name,last_name,email',
                    'team:id,name',
                    'plan:id,title',
                    'planItem:id,title,scheduled_at',
                    'entries',
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
                ->map(fn (TrainingLog $log) => $this->resources->log($log)),
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
                ->with('users:id,name,first_name,last_name,email')
                ->orderBy('name')
                ->get(['teams.id', 'teams.name', 'teams.club_id'])
                ->map(fn (Team $team) => [
                    'id' => $team->id,
                    'name' => $team->name,
                    'club_id' => $team->club_id,
                    'users' => $team->users->map(fn (User $member) => $this->resources->user($member)),
                ]),
            'people' => $manageableAthletes->map(fn (User $person) => $this->resources->user($person)),
            'aiCapabilities' => $ai->capabilities($user),
        ]);
    }

    public function createLog(Request $request)
    {
        $user = $request->user();
        $draft = $this->findOrCreateDraftLog($user);

        return Inertia::render('Auth/Dashboard/Training/LogCreate', $this->logFormProps($user, $draft));
    }

    public function showLog(Request $request, TrainingLog $log)
    {
        abort_unless($this->canViewLog($request->user(), $log), 403);

        $log->load([
            'athlete:id,name,first_name,last_name,email',
            'creator:id,name,first_name,last_name,email',
            'trainer:id,name,first_name,last_name,email',
            'team:id,name',
            'plan:id,title',
            'planItem:id,title,sport_type,description,scheduled_at,duration_minutes,distance_meters,calories,intensity,todos,metrics',
            'entries',
            'feedbacks.author:id,name,first_name,last_name,email',
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
            'assignments.user:id,name,first_name,last_name,email',
            'assignments.team:id,name',
        ]);

        $item->load([
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
        $user = $request->user();

        abort_unless($this->canViewLog($user, $log), 403);
        abort_if($log->status === 'draft', 422, 'Feedback ist erst nach dem Speichern der Trainingseinheit möglich.');

        $data = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:3000'],
        ]);

        $feedback = DB::transaction(function () use ($log, $user, $data) {
            return $log->feedbacks()->create([
                'user_id' => $user->id,
                'body' => $data['body'],
                'role' => $this->feedbackRole($user, $log),
            ]);
        });

        $this->notifyTrainingFeedbackRecipients($log->fresh(['feedbacks']), $feedback->load('author'));

        return back()->with('success', 'Feedback wurde gesendet.');
    }

    public function previewAiTrainingPlan(
        Request $request,
        AirmiusAiService $ai,
        AthleteSportProfileService $sportProfiles,
        TrainingPlanQualityService $quality,
    ) {
        $maxPlanItems = $this->aiTrainingPlanMaxItems();
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
                'message' => "Der Plan ist zu groß für eine saubere KI-Vorschau. Maximal {$maxPlanItems} Einheiten sind erlaubt.",
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
                'message' => 'Für einen zuverlässigen KI-Trainingsplan fehlen noch Leistungsdaten für '.$profileReadiness['sport']['name'].'. Möchtest du sie jetzt nachtragen? Fehlend: '.$missing.'. Wenn du die Werte nicht kennst, kannst du bewusst mit vorsichtigen Schätzungen fortfahren.',
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
                    'Der Plan wurde mit vorsichtigen Schätzungen erstellt, weil Leistungsdaten fehlen: '.$missing.'.',
                ])));
                $plan['analysis_tips'] = array_values(array_unique(array_filter([
                    ...($plan['analysis_tips'] ?? []),
                    'Trage die fehlenden Sportprofildaten später nach und generiere den Plan neu, wenn du präzisere Pace-, Umfangs- oder Belastungswerte möchtest.',
                ])));
            }

            return response()->json([
                'message' => $profileReadiness['ready']
                    ? 'KI-Vorschlag erstellt und mit Airmius-Regeln geprüft. Bitte erst danach speichern.'
                    : 'Konservativer KI-Vorschlag mit Schätzungen erstellt und mit Airmius-Regeln geprüft. Bitte genau prüfen, bevor du speicherst.',
                'plan' => $plan,
            ]);
        } catch (\Throwable $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function storeAiTrainingPlan(Request $request)
    {
        $user = $request->user();
        $teamIds = $user->teams()->pluck('teams.id')->all();
        $maxPlanItems = $this->aiTrainingPlanMaxItems();
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
        ]);

        $planPayload = $data['plan'];
        $settings = $planPayload['settings'] ?? [];
        $startsOn = $data['starts_on'] ?? null;
        $weeklySessions = max(1, (int) ($settings['weekly_sessions'] ?? 3));
        $userIds = collect($data['user_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->reject(fn ($id) => $id === (int) $user->id)
            ->unique()
            ->values();

        $plan = DB::transaction(function () use ($user, $data, $planPayload, $settings, $startsOn, $weeklySessions, $userIds) {
            $plan = TrainingPlan::create([
                'created_by' => $user->id,
                'team_id' => $data['team_id'] ?? null,
                'title' => $planPayload['title'],
                'description' => trim(($planPayload['summary'] ?? '')."\n\nWarum so:\n".($planPayload['convincing_explanation'] ?? '')),
                'cadence' => 'weekly',
                'starts_on' => $startsOn,
                'ends_on' => $startsOn && ! empty($settings['weeks'])
                    ? CarbonImmutable::parse($startsOn)->addWeeks((int) $settings['weeks'])->subDay()->toDateString()
                    : null,
                'status' => $data['status'] ?? 'published',
                'share_permission' => $data['share_permission'] ?? 'read',
                'settings' => [
                    'created_from' => 'ai_training_plan',
                    'goal' => $settings['goal'] ?? null,
                    'phase' => $settings['phase'] ?? null,
                    'level' => $settings['level'] ?? null,
                    'weeks' => $settings['weeks'] ?? null,
                    'weekly_sessions' => $settings['weekly_sessions'] ?? null,
                    'ai_generation' => [
                        'provider' => $planPayload['provider'] ?? null,
                        'provider_label' => $planPayload['provider_label'] ?? null,
                        'model' => $planPayload['model'] ?? null,
                        'summary' => $planPayload['summary'] ?? null,
                        'convincing_explanation' => $planPayload['convincing_explanation'] ?? null,
                        'progression_logic' => array_values(array_filter($planPayload['progression_logic'] ?? [])),
                        'analysis_tips' => array_values(array_filter($planPayload['analysis_tips'] ?? [])),
                        'adjustment_tips' => array_values(array_filter($planPayload['adjustment_tips'] ?? [])),
                        'warnings' => array_values(array_filter($planPayload['warnings'] ?? [])),
                        'quality_check' => $planPayload['quality_check'] ?? null,
                        'profile_estimate_mode' => (bool) ($planPayload['profile_estimate_mode'] ?? false),
                        'profile_readiness' => $planPayload['profile_readiness'] ?? null,
                        'generated_at' => now()->toIso8601String(),
                    ],
                ],
            ]);

            collect($planPayload['items'] ?? [])->values()->each(function (array $item, int $index) use ($plan, $startsOn, $weeklySessions) {
                $plan->items()->create([
                    'title' => $item['title'],
                    'sport_type' => $item['sport_type'] ?? null,
                    'description' => $item['description'] ?? null,
                    'scheduled_at' => $this->aiPlanScheduledAt($item, $startsOn, $weeklySessions, $index),
                    'duration_minutes' => $item['duration_minutes'] ?? null,
                    'distance_meters' => isset($item['distance_km']) ? (int) round((float) $item['distance_km'] * 1000) : null,
                    'calories' => $item['calories'] ?? null,
                    'intensity' => $item['intensity'] ?? null,
                    'todos' => array_values(array_filter($item['todos'] ?? [])),
                    'sort_order' => $index + 1,
                    'metrics' => [
                        ...$this->cleanMetrics($item['metrics'] ?? []),
                        ...array_filter([
                            'Woche' => $item['week'] ?? null,
                            'Belastung' => $item['load'] ?? null,
                            'Fokus' => $item['focus'] ?? null,
                            '_training_type' => $item['training_type'] ?? null,
                            'Planlogik' => $item['rationale'] ?? null,
                        ], fn ($value) => $value !== null && $value !== ''),
                    ],
                ]);
            });

            if (! empty($data['team_id'])) {
                $plan->assignments()->create([
                    'team_id' => $data['team_id'],
                    'permission' => $data['share_permission'] ?? 'read',
                ]);
            }

            $userIds->each(fn ($userId) => $plan->assignments()->create([
                'user_id' => $userId,
                'permission' => $data['share_permission'] ?? 'read',
            ]));

            return $plan;
        });

        $plan->load([
            'creator:id,name,first_name,last_name,email',
            'team:id,name',
            'items.logs',
            'assignments.user:id,name,first_name,last_name,email',
            'assignments.team:id,name',
        ]);

        return response()->json([
            'message' => 'KI-Trainingsplan wurde gespeichert. Du kannst jede Einheit jetzt bearbeiten oder dokumentieren.',
            'plan' => $this->resources->plan($plan, $user),
        ], 201);
    }

    public function storeLog(Request $request)
    {
        $user = $request->user();
        $manageableAthleteIds = $this->manageableAthletes($user)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $allowedUserIds = array_values(array_unique(array_merge([(int) $user->id], $manageableAthleteIds)));
        $teamIds = $user->teams()->pluck('teams.id')->map(fn ($id) => (int) $id)->all();

        $data = $request->validate([
            'user_id' => ['nullable', 'integer', Rule::in($allowedUserIds)],
            'draft_log_id' => ['nullable', 'integer', 'exists:training_logs,id'],
            'team_id' => ['nullable', 'integer', Rule::in($teamIds)],
            'training_plan_item_id' => ['nullable', 'integer', 'exists:training_plan_items,id'],
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

        $log = DB::transaction(function () use ($request, $user, $data, $athleteId, $planItem) {
            $draft = ! empty($data['draft_log_id'])
                ? TrainingLog::query()
                    ->whereKey($data['draft_log_id'])
                    ->where('created_by', $user->id)
                    ->where('status', 'draft')
                    ->first()
                : null;

            abort_if(! empty($data['draft_log_id']) && ! $draft, 403);

            $payload = [
                'user_id' => $athleteId,
                'created_by' => $user->id,
                'trainer_id' => $athleteId === (int) $user->id ? null : $user->id,
                'team_id' => $data['team_id'] ?? $planItem?->plan?->team_id,
                'training_plan_id' => $planItem?->training_plan_id,
                'training_plan_item_id' => $planItem?->id,
                'sport_type' => $data['sport_type'] ?? $planItem?->sport_type,
                'title' => $data['title'],
                'status' => $data['status'],
                'performed_at' => $data['performed_at'] ?? now(),
                'duration_minutes' => $data['duration_minutes'] ?? null,
                'distance_meters' => isset($data['distance_km']) ? (int) round((float) $data['distance_km'] * 1000) : null,
                'calories' => $data['calories'] ?? null,
                'intensity' => $data['intensity'] ?? null,
                'notes' => $data['notes'] ?? null,
                'trainer_feedback' => $athleteId === (int) $user->id ? null : ($data['trainer_feedback'] ?? null),
                'metrics' => [
                    'source_kind' => $planItem ? 'planned_training' : 'spontaneous_training',
                    'training_type' => $data['training_type'] ?? null,
                    'privacy_scope' => $data['privacy_scope'] ?? 'trainer',
                    'notify_people' => (bool) ($data['notify_people'] ?? true),
                    'wellness' => array_filter($data['wellness'] ?? [], fn ($value) => $value !== null && $value !== ''),
                    'draft_log_id' => $draft?->id,
                    'completed_from_draft_at' => $draft ? now()->toIso8601String() : null,
                ],
            ];

            if ($draft) {
                $draft->update($payload);
                $draft->entries()->delete();
                $log = $draft;
            } else {
                $log = TrainingLog::create($payload);
            }

            $this->createLogEntries($log, $data['entries'] ?? [], $request);

            return $log;
        });

        if ((bool) ($data['notify_people'] ?? true)) {
            $this->notifyTrainingLogSaved($log->fresh([
                'athlete:id,name,first_name,last_name,email',
                'creator:id,name,first_name,last_name,email',
                'trainer:id,name,first_name,last_name,email',
                'plan:id,title,created_by',
                'planItem:id,title,training_plan_id',
            ]), $user);
        }

        return redirect()->route('auth.training.logs.show', $log)->with('success', $log->trainer_id
            ? 'Trainingseinheit wurde für den Sportler dokumentiert.'
            : 'Trainingseinheit wurde dokumentiert.');
    }

    public function updateDraftLog(Request $request, TrainingLog $log)
    {
        $user = $request->user();

        abort_unless((int) $log->created_by === (int) $user->id && $log->status === 'draft', 403);

        $manageableAthleteIds = $this->manageableAthletes($user)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $allowedUserIds = array_values(array_unique(array_merge([(int) $user->id], $manageableAthleteIds)));
        $teamIds = $user->teams()->pluck('teams.id')->map(fn ($id) => (int) $id)->all();

        $data = $request->validate([
            'user_id' => ['nullable', 'integer', Rule::in($allowedUserIds)],
            'team_id' => ['nullable', 'integer', Rule::in($teamIds)],
            'training_plan_item_id' => ['nullable', 'integer', 'exists:training_plan_items,id'],
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

        DB::transaction(function () use ($request, $log, $user, $data, $athleteId, $planItem) {
            $log->update([
                'user_id' => $athleteId,
                'created_by' => $user->id,
                'trainer_id' => $athleteId === (int) $user->id ? null : $user->id,
                'team_id' => $data['team_id'] ?? $planItem?->plan?->team_id,
                'training_plan_id' => $planItem?->training_plan_id,
                'training_plan_item_id' => $planItem?->id,
                'sport_type' => $data['sport_type'] ?? $planItem?->sport_type,
                'title' => trim((string) ($data['title'] ?? '')) !== '' ? $data['title'] : 'Training-Entwurf',
                'status' => 'draft',
                'performed_at' => $data['performed_at'] ?? $log->performed_at ?? now(),
                'duration_minutes' => $data['duration_minutes'] ?? null,
                'distance_meters' => isset($data['distance_km']) ? (int) round((float) $data['distance_km'] * 1000) : null,
                'calories' => $data['calories'] ?? null,
                'intensity' => $data['intensity'] ?? null,
                'notes' => $data['notes'] ?? null,
                'trainer_feedback' => $athleteId === (int) $user->id ? null : ($data['trainer_feedback'] ?? null),
                'metrics' => [
                    'source_kind' => $planItem ? 'planned_training_draft' : 'spontaneous_training_draft',
                    'training_type' => $data['training_type'] ?? null,
                    'privacy_scope' => $data['privacy_scope'] ?? 'trainer',
                    'notify_people' => (bool) ($data['notify_people'] ?? true),
                    'wellness' => array_filter($data['wellness'] ?? [], fn ($value) => $value !== null && $value !== ''),
                    'intended_status' => $data['status'] ?? 'completed',
                    'autosaved_at' => now()->toIso8601String(),
                ],
            ]);

            $log->entries()->delete();
            $this->createLogEntries($log, $data['entries'] ?? [], $request);
        });

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
            ])),
        ]);
    }

    public function destroyDraftLog(Request $request, TrainingLog $log)
    {
        abort_unless((int) $log->created_by === (int) $request->user()->id && $log->status === 'draft', 403);

        $log->entries()->delete();
        $log->delete();

        return back()->with('success', 'Training-Entwurf wurde verworfen.');
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

        return back()->with('success', $data['status'] === 'published'
            ? 'Trainingsplan wurde freigegeben.'
            : 'Trainingsplan wurde als Entwurf gespeichert.');
    }

    public function storePlanItem(Request $request, TrainingPlan $plan)
    {
        abort_unless($this->resources->canWritePlan($request->user(), $plan), 403);

        $data = $this->validatePlanItemData($request);
        $imagePath = $request->file('image')?->store('training-plans', 'public');

        $item = $plan->items()->create([
            ...$this->planItemPayload($data, $imagePath),
            'sort_order' => $plan->items()->count() + 1,
        ]);

        $this->notifyPlanRecipients($plan->fresh(['assignments.user', 'assignments.team.users', 'creator']), $request->user(), 'Neue Einheit im Trainingsplan', '"'.$item->title.'" wurde zu "'.$plan->title.'" hinzugefügt.', route('auth.training.plans.items.show', [$plan, $item]));

        return back()->with('success', 'Trainingseinheit wurde zum Plan hinzugefügt.');
    }

    public function updatePlanItem(Request $request, TrainingPlan $plan, TrainingPlanItem $item)
    {
        abort_unless($this->resources->canWritePlan($request->user(), $plan), 403);
        abort_unless((int) $item->training_plan_id === (int) $plan->id, 404);

        $data = $this->validatePlanItemData($request);
        $imagePath = $item->image_path;

        if ($request->hasFile('image')) {
            $this->deletePlanItemImageIfUnused($item);
            $imagePath = $request->file('image')->store('training-plans', 'public');
        }

        $item->update($this->planItemPayload($data, $imagePath));

        $this->notifyPlanRecipients($plan->fresh(['assignments.user', 'assignments.team.users', 'creator']), $request->user(), 'Trainingseinheit aktualisiert', '"'.$item->title.'" in "'.$plan->title.'" wurde angepasst.', route('auth.training.plans.items.show', [$plan, $item]));

        return back()->with('success', 'Trainingseinheit wurde aktualisiert.');
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

        $this->notifyPlanRecipients($plan->fresh(['assignments.user', 'assignments.team.users', 'creator']), $request->user(), 'Einheit dupliziert', '"'.$copy->title.'" wurde in "'.$plan->title.'" angelegt.', route('auth.training.plans.items.show', [$plan, $copy]));

        return back()->with('success', 'Trainingseinheit wurde dupliziert.');
    }

    public function destroyPlanItem(Request $request, TrainingPlan $plan, TrainingPlanItem $item)
    {
        abort_unless($this->resources->canWritePlan($request->user(), $plan), 403);
        abort_unless((int) $item->training_plan_id === (int) $plan->id, 404);

        $this->deletePlanItemImageIfUnused($item);
        $item->delete();

        return back()->with('success', 'Trainingseinheit wurde gelöscht.');
    }

    public function updatePlan(Request $request, TrainingPlan $plan)
    {
        abort_unless($this->resources->canWritePlan($request->user(), $plan), 403);

        $teamIds = $request->user()->teams()->pluck('teams.id')->all();
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

        $this->notifyPlanRecipients($plan->fresh(['assignments.user', 'assignments.team.users', 'creator']), $request->user(), 'Trainingsplan aktualisiert', '"'.$plan->title.'" wurde angepasst.', route('auth.training.index'));

        return back()->with('success', 'Trainingsplan wurde aktualisiert.');
    }

    public function publishPlan(Request $request, TrainingPlan $plan)
    {
        abort_unless($this->resources->canWritePlan($request->user(), $plan), 403);

        $plan->update(['status' => 'published']);

        $this->notifyPlanRecipients($plan->fresh(['assignments.user', 'assignments.team.users', 'creator']), $request->user(), 'Trainingsplan freigegeben', '"'.$plan->title.'" ist jetzt für dich sichtbar.', route('auth.training.index'));

        return back()->with('success', 'Trainingsplan wurde freigegeben.');
    }

    public function duplicatePlan(Request $request, TrainingPlan $plan)
    {
        abort_unless($this->resources->canWritePlan($request->user(), $plan), 403);

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

        return back()->with('success', 'Trainingsplan wurde als bearbeitbare Vorlage kopiert.');
    }

    public function markPlanItemMissed(Request $request, TrainingPlan $plan, TrainingPlanItem $item)
    {
        abort_unless((int) $item->training_plan_id === (int) $plan->id, 404);

        $user = $request->user();
        $manageableAthleteIds = $this->manageableAthletes($user)->pluck('id')->map(fn ($id) => (int) $id)->all();
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

        $this->notifyPlanRecipients($plan->fresh(['assignments.user', 'assignments.team.users', 'creator']), $user, 'Trainingseinheit ausgefallen', '"'.$item->title.'" wurde als nicht gemacht markiert.', route('auth.training.logs.show', $missedLog));

        return back()->with('success', 'Einheit wurde als nicht gemacht markiert.');
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

        return back()->with('success', 'Trainingsplan wurde gelöscht.');
    }

    private function logFormProps(User $user, ?TrainingLog $draft = null): array
    {
        $teamIds = $user->teams()->pluck('teams.id');
        $manageableAthletes = $this->manageableAthletes($user);

        $plans = TrainingPlan::query()
            ->with(['team:id,name', 'items.logs', 'assignments.user:id,name,first_name,last_name,email', 'assignments.team:id,name'])
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
            'draftLog' => $draft ? $this->resources->log($draft->loadMissing([
                'athlete:id,name,first_name,last_name,email',
                'creator:id,name,first_name,last_name,email',
                'trainer:id,name,first_name,last_name,email',
                'team:id,name',
                'plan:id,title',
                'planItem:id,title,scheduled_at',
                'entries',
            ])) : null,
        ];
    }

    private function canViewPlan(User $user, TrainingPlan $plan): bool
    {
        if ((int) $plan->created_by === (int) $user->id || $user->hasAnyRole(Roles::FULL_ACCESS)) {
            return true;
        }

        $teamIds = $user->teams()->pluck('teams.id');

        return $plan->assignments()
            ->where(function ($query) use ($user, $teamIds) {
                $query
                    ->where('user_id', $user->id)
                    ->orWhereIn('team_id', $teamIds);
            })
            ->exists();
    }

    private function manageableAthletes(User $user)
    {
        $managedTeamIds = $this->managedTeamIds($user);

        if ($managedTeamIds->isEmpty()) {
            return collect();
        }

        return User::query()
            ->whereKeyNot($user->id)
            ->whereHas('teams', fn ($query) => $query->whereIn('teams.id', $managedTeamIds))
            ->orderBy('name')
            ->get(['id', 'name', 'first_name', 'last_name', 'email']);
    }

    private function canViewLog(User $user, TrainingLog $log): bool
    {
        if ((int) $log->user_id === (int) $user->id || (int) $log->created_by === (int) $user->id) {
            return true;
        }

        if ($user->hasAnyRole(Roles::FULL_ACCESS)) {
            return true;
        }

        $privacyScope = $log->metrics['privacy_scope'] ?? 'trainer';

        if ($privacyScope === 'private') {
            return false;
        }

        if ($privacyScope === 'team' && $log->team_id) {
            return $user->teams()->where('teams.id', $log->team_id)->exists();
        }

        return $this->manageableAthletes($user)
            ->pluck('id')
            ->contains((int) $log->user_id);
    }

    private function feedbackRole(User $user, TrainingLog $log): string
    {
        if ((int) $log->user_id === (int) $user->id) {
            return 'athlete';
        }

        if ((int) $log->trainer_id === (int) $user->id || (int) $log->created_by === (int) $user->id) {
            return 'trainer';
        }

        return $user->hasAnyRole(Roles::FULL_ACCESS) ? 'admin' : 'team_staff';
    }

    private function notifyTrainingLogSaved(TrainingLog $log, User $actor): void
    {
        $actorName = $this->resources->user($actor)['name'];
        $athleteName = $log->athlete ? $this->resources->user($log->athlete)['name'] : 'Sportler';
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

        $recipientIds->each(fn (int $recipientId) => AppNotification::send($recipientId, 'training.log.saved', [
            'title' => 'Training dokumentiert',
            'body' => $actorName.' hat "'.$log->title.'" für '.$athleteName.' gespeichert.',
            'url' => route('auth.training.logs.show', $log),
            'training_log_id' => $log->id,
        ]));
    }

    private function notifyPlanRecipients(TrainingPlan $plan, User $actor, string $title, string $body, string $url): void
    {
        $recipientIds = collect([$plan->created_by])
            ->merge($plan->assignments->pluck('user_id'))
            ->merge($plan->assignments->flatMap(fn ($assignment) => $assignment->team?->users?->pluck('id') ?? collect()))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->reject(fn ($id) => $id === (int) $actor->id);

        $recipientIds->each(fn (int $recipientId) => AppNotification::send($recipientId, 'training.plan.changed', [
            'title' => $title,
            'body' => $body,
            'url' => $url,
            'training_plan_id' => $plan->id,
        ]));
    }

    private function notifyTrainingFeedbackRecipients(TrainingLog $log, TrainingLogFeedback $feedback): void
    {
        $authorName = $feedback->author ? $this->resources->user($feedback->author)['name'] : 'Jemand';
        $recipientIds = collect([
            $log->user_id,
            $log->created_by,
            $log->trainer_id,
        ])
            ->merge($log->feedbacks()->pluck('user_id'))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->reject(fn ($id) => $id === (int) $feedback->user_id);

        $recipientIds->each(fn (int $recipientId) => AppNotification::send($recipientId, 'training.feedback', [
            'title' => 'Neues Training-Feedback',
            'body' => $authorName.' hat bei "'.$log->title.'" geantwortet.',
            'url' => route('auth.training.logs.show', $log),
            'training_log_id' => $log->id,
            'feedback_id' => $feedback->id,
        ]));
    }

    private function findOrCreateDraftLog(User $user): TrainingLog
    {
        $draft = $this->currentDraftLog($user, true);

        if ($draft) {
            $draft->touch();

            return $draft;
        }

        return TrainingLog::create([
            'user_id' => $user->id,
            'created_by' => $user->id,
            'sport_type' => 'gym',
            'title' => 'Training-Entwurf',
            'status' => 'draft',
            'performed_at' => now(),
            'metrics' => [
                'source_kind' => 'training_documentation_draft',
                'training_type' => 'gym',
                'draft_started_at' => now()->toIso8601String(),
            ],
        ]);
    }

    private function currentDraftLog(User $user, bool $freshOnly = false): ?TrainingLog
    {
        return TrainingLog::query()
            ->where('created_by', $user->id)
            ->where('user_id', $user->id)
            ->where('status', 'draft')
            ->when($freshOnly, fn ($query) => $query->where('created_at', '>=', now()->subHours(6)))
            ->latest('updated_at')
            ->latest('id')
            ->first();
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

    private function aiPlanScheduledAt(array $item, ?string $startsOn, int $weeklySessions, int $index): ?CarbonImmutable
    {
        if (! empty($item['scheduled_at'])) {
            return CarbonImmutable::parse($item['scheduled_at']);
        }

        if (! $startsOn) {
            return null;
        }

        $week = max(1, (int) ($item['week'] ?? floor($index / max(1, $weeklySessions)) + 1));
        $sessions = max(1, $weeklySessions);
        $slotInWeek = $index % $sessions;
        $daySpacing = max(1, (int) floor(7 / $sessions));

        return CarbonImmutable::parse($startsOn)
            ->startOfDay()
            ->addWeeks($week - 1)
            ->addDays(min(6, $slotInWeek * $daySpacing))
            ->setTime(18, 0);
    }

    private function aiTrainingPlanMaxItems(): int
    {
        return max(1, min(156, (int) config('airmius_ai.features.training_plan_generation.max_items', 156)));
    }

    private function managedTeamIds(User $user)
    {
        if ($user->hasAnyRole(Roles::FULL_ACCESS)) {
            return Team::query()->pluck('id');
        }

        return $user->teams()
            ->wherePivotIn('role', ['Coach', 'coach', 'Trainer', 'trainer', 'Captain', 'captain', 'Admin', 'admin', 'Manager', 'manager'])
            ->pluck('teams.id');
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

    private function createLogEntries(TrainingLog $log, array $entries, ?Request $request = null): void
    {
        collect($entries)
            ->map(fn ($entry, $inputIndex) => [
                'data' => is_array($entry) ? $entry : [],
                'input_index' => $inputIndex,
            ])
            ->filter(fn ($entry) => trim((string) ($entry['data']['title'] ?? '')) !== '')
            ->values()
            ->each(function (array $entryRow, int $index) use ($log, $request) {
                $entry = $entryRow['data'];
                $inputIndex = $entryRow['input_index'];
                $mediaFile = $request?->file("entries.$inputIndex.media_file");
                $mediaPath = $mediaFile?->store('training-log-media', 'public');

                $log->entries()->create([
                    'title' => trim((string) $entry['title']),
                    'sets' => $entry['sets'] ?? null,
                    'reps' => $entry['reps'] ?? null,
                    'weight_kg' => $entry['weight_kg'] ?? null,
                    'duration_seconds' => isset($entry['duration_minutes']) ? (int) round((float) $entry['duration_minutes'] * 60) : null,
                    'distance_meters' => isset($entry['distance_km']) ? (int) round((float) $entry['distance_km'] * 1000) : null,
                    'intensity' => $entry['intensity'] ?? null,
                    'notes' => $entry['notes'] ?? null,
                    'metrics' => array_filter([
                        'media_url' => $entry['media_url'] ?? null,
                        'media_path' => $mediaPath,
                        'uploaded_media_url' => $mediaPath ? Storage::disk('public')->url($mediaPath) : null,
                        'media_mime' => $mediaFile?->getClientMimeType(),
                        'media_original_name' => $mediaFile?->getClientOriginalName(),
                    ], fn ($value) => $value !== null && $value !== ''),
                    'sort_order' => $index + 1,
                ]);
            });
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
}
