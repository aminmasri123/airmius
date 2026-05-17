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
use App\Support\Roles;
use App\Support\AppNotification;
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
            ->map(fn (TrainingPlan $plan) => $this->serializePlan($plan, $user));

        return Inertia::render('Auth/Dashboard/Training/Index', [
            'plans' => $plans,
            'activeDraftLog' => $activeDraft
                ? $this->serializeLog($activeDraft->load([
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
                ->map(fn (TrainingLog $log) => $this->serializeLog($log)),
            'manageableAthletes' => $manageableAthletes->map(fn (User $athlete) => $this->serializeUser($athlete)),
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
                    'users' => $team->users->map(fn (User $member) => $this->serializeUser($member)),
                ]),
            'people' => $manageableAthletes->map(fn (User $person) => $this->serializeUser($person)),
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
            'log' => $this->serializeLog($log),
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
            'plan' => $this->serializePlan($plan, $request->user()),
            'item' => $this->serializePlanItemDetail($item),
        ]);
    }

    public function storeLogFeedback(Request $request, TrainingLog $log)
    {
        $user = $request->user();

        abort_unless($this->canViewLog($user, $log), 403);
        abort_if($log->status === 'draft', 422, 'Feedback ist erst nach dem Speichern der Trainingseinheit moeglich.');

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
            ? 'Trainingseinheit wurde fuer den Sportler dokumentiert.'
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
            'log' => $this->serializeLog($log->load([
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
        abort_unless($this->canWritePlan($request->user(), $plan), 403);

        $data = $this->validatePlanItemData($request);
        $imagePath = $request->file('image')?->store('training-plans', 'public');

        $item = $plan->items()->create([
            ...$this->planItemPayload($data, $imagePath),
            'sort_order' => $plan->items()->count() + 1,
        ]);

        $this->notifyPlanRecipients($plan->fresh(['assignments.user', 'assignments.team.users', 'creator']), $request->user(), 'Neue Einheit im Trainingsplan', '"'.$item->title.'" wurde zu "'.$plan->title.'" hinzugefuegt.', route('auth.training.plans.items.show', [$plan, $item]));

        return back()->with('success', 'Trainingseinheit wurde zum Plan hinzugefuegt.');
    }

    public function updatePlanItem(Request $request, TrainingPlan $plan, TrainingPlanItem $item)
    {
        abort_unless($this->canWritePlan($request->user(), $plan), 403);
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
        abort_unless($this->canWritePlan($request->user(), $plan), 403);
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
        abort_unless($this->canWritePlan($request->user(), $plan), 403);
        abort_unless((int) $item->training_plan_id === (int) $plan->id, 404);

        $this->deletePlanItemImageIfUnused($item);
        $item->delete();

        return back()->with('success', 'Trainingseinheit wurde geloescht.');
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
        abort_unless($this->canWritePlan($request->user(), $plan), 403);

        $plan->update(['status' => 'published']);

        $this->notifyPlanRecipients($plan->fresh(['assignments.user', 'assignments.team.users', 'creator']), $request->user(), 'Trainingsplan freigegeben', '"'.$plan->title.'" ist jetzt fuer dich sichtbar.', route('auth.training.index'));

        return back()->with('success', 'Trainingsplan wurde freigegeben.');
    }

    public function duplicatePlan(Request $request, TrainingPlan $plan)
    {
        abort_unless($this->canWritePlan($request->user(), $plan), 403);

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

        return back()->with('success', 'Trainingsplan wurde geloescht.');
    }

    private function serializePlan(TrainingPlan $plan, User $viewer): array
    {
        $items = $plan->items;
        $itemCount = max(1, $items->count());
        $completedCount = $items->filter(fn ($item) => $item->relationLoaded('logs') && $item->logs->contains(fn ($log) => $log->status === 'completed'))->count();
        $missedCount = $items->filter(fn ($item) => $item->relationLoaded('logs') && $item->logs->contains(fn ($log) => $log->status === 'missed'))->count();

        return [
            'id' => $plan->id,
            'title' => $plan->title,
            'description' => $plan->description,
            'cadence' => $plan->cadence,
            'starts_on' => $plan->starts_on?->toDateString(),
            'ends_on' => $plan->ends_on?->toDateString(),
            'status' => $plan->status,
            'share_permission' => $plan->share_permission,
            'settings' => $plan->settings ?? [],
            'creator' => $plan->creator ? $this->serializeUser($plan->creator) : null,
            'team' => $plan->team ? ['id' => $plan->team->id, 'name' => $plan->team->name] : null,
            'can_write' => $this->canWritePlan($viewer, $plan),
            'progress' => [
                'completed' => $completedCount,
                'missed' => $missedCount,
                'open' => max(0, $items->count() - $completedCount - $missedCount),
                'percent' => $items->count() ? (int) round(($completedCount / $itemCount) * 100) : 0,
            ],
            'items' => $items->map(fn ($item) => [
                ...$item->toArray(),
                'image_url' => $item->image_path ? Storage::disk('public')->url($item->image_path) : null,
                'log_statuses' => $item->relationLoaded('logs')
                    ? $item->logs->map(fn (TrainingLog $log) => [
                        'id' => $log->id,
                        'status' => $log->status,
                        'user_id' => $log->user_id,
                        'reason' => $log->metrics['missed_reason'] ?? null,
                    ])
                    : [],
            ]),
            'assignments' => $plan->assignments->map(fn ($assignment) => [
                'id' => $assignment->id,
                'permission' => $assignment->permission,
                'user' => $assignment->user ? $this->serializeUser($assignment->user) : null,
                'team' => $assignment->team ? ['id' => $assignment->team->id, 'name' => $assignment->team->name] : null,
            ]),
        ];
    }

    private function serializeLog(TrainingLog $log): array
    {
        return [
            'id' => $log->id,
            'title' => $log->title,
            'status' => $log->status,
            'sport_type' => $log->sport_type,
            'performed_at' => $log->performed_at?->toIso8601String(),
            'created_at' => $log->created_at?->toIso8601String(),
            'updated_at' => $log->updated_at?->toIso8601String(),
            'duration_minutes' => $log->duration_minutes,
            'distance_meters' => $log->distance_meters,
            'calories' => $log->calories,
            'intensity' => $log->intensity,
            'notes' => $log->notes,
            'trainer_feedback' => $log->trainer_feedback,
            'metrics' => $log->metrics ?? [],
            'athlete' => $log->athlete ? $this->serializeUser($log->athlete) : null,
            'creator' => $log->creator ? $this->serializeUser($log->creator) : null,
            'trainer' => $log->trainer ? $this->serializeUser($log->trainer) : null,
            'team' => $log->team ? ['id' => $log->team->id, 'name' => $log->team->name] : null,
            'plan' => $log->plan ? ['id' => $log->plan->id, 'title' => $log->plan->title] : null,
            'plan_item' => $log->planItem ? [
                'id' => $log->planItem->id,
                'title' => $log->planItem->title,
                'sport_type' => $log->planItem->sport_type,
                'description' => $log->planItem->description,
                'scheduled_at' => $log->planItem->scheduled_at?->toIso8601String(),
                'duration_minutes' => $log->planItem->duration_minutes,
                'distance_meters' => $log->planItem->distance_meters,
                'calories' => $log->planItem->calories,
                'intensity' => $log->planItem->intensity,
                'todos' => $log->planItem->todos ?? [],
                'metrics' => $log->planItem->metrics ?? [],
            ] : null,
            'plan_comparison' => $this->trainingPlanComparison($log),
            'entries' => $log->entries->map(fn ($entry) => [
                'id' => $entry->id,
                'title' => $entry->title,
                'sets' => $entry->sets,
                'reps' => $entry->reps,
                'weight_kg' => $entry->weight_kg,
                'duration_seconds' => $entry->duration_seconds,
                'distance_meters' => $entry->distance_meters,
                'intensity' => $entry->intensity,
                'notes' => $entry->notes,
                'metrics' => $entry->metrics ?? [],
            ]),
            'feedbacks' => $log->relationLoaded('feedbacks')
                ? $log->feedbacks->map(fn (TrainingLogFeedback $feedback) => [
                    'id' => $feedback->id,
                    'body' => $feedback->body,
                    'role' => $feedback->role,
                    'created_at' => $feedback->created_at?->toIso8601String(),
                    'author' => $feedback->author ? $this->serializeUser($feedback->author) : null,
                ])
                : [],
        ];
    }

    private function serializePlanItemDetail(TrainingPlanItem $item): array
    {
        return [
            ...$item->toArray(),
            'image_url' => $item->image_path ? Storage::disk('public')->url($item->image_path) : null,
            'logs' => $item->relationLoaded('logs')
                ? $item->logs->map(fn (TrainingLog $log) => $this->serializeLog($log))
                : [],
            'stats' => [
                'completed' => $item->relationLoaded('logs') ? $item->logs->where('status', 'completed')->count() : 0,
                'missed' => $item->relationLoaded('logs') ? $item->logs->where('status', 'missed')->count() : 0,
                'in_progress' => $item->relationLoaded('logs') ? $item->logs->where('status', 'in_progress')->count() : 0,
            ],
        ];
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
            ->map(fn (TrainingPlan $plan) => $this->serializePlan($plan, $user));

        return [
            'plans' => $plans,
            'manageableAthletes' => $manageableAthletes->map(fn (User $athlete) => $this->serializeUser($athlete)),
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
            'draftLog' => $draft ? $this->serializeLog($draft->loadMissing([
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

    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: $user->name,
            'email' => $user->email,
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
        $actorName = $this->serializeUser($actor)['name'];
        $athleteName = $log->athlete ? $this->serializeUser($log->athlete)['name'] : 'Sportler';
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
            'body' => $actorName.' hat "'.$log->title.'" fuer '.$athleteName.' gespeichert.',
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

    private function trainingPlanComparison(TrainingLog $log): ?array
    {
        if (! $log->planItem) {
            return null;
        }

        $planned = [
            'title' => $log->planItem->title,
            'scheduled_at' => $log->planItem->scheduled_at?->toIso8601String(),
            'duration_minutes' => $log->planItem->duration_minutes,
            'distance_meters' => $log->planItem->distance_meters,
            'calories' => $log->planItem->calories,
            'intensity' => $log->planItem->intensity,
            'todos_count' => count($log->planItem->todos ?? []),
            'metrics_count' => count($log->planItem->metrics ?? []),
        ];

        $actual = [
            'title' => $log->title,
            'performed_at' => $log->performed_at?->toIso8601String(),
            'duration_minutes' => $log->duration_minutes,
            'distance_meters' => $log->distance_meters,
            'calories' => $log->calories,
            'intensity' => $log->intensity,
            'entries_count' => $log->relationLoaded('entries') ? $log->entries->count() : null,
        ];

        return [
            'planned' => $planned,
            'actual' => $actual,
            'delta' => [
                'duration_minutes' => $actual['duration_minutes'] !== null && $planned['duration_minutes'] !== null
                    ? (int) $actual['duration_minutes'] - (int) $planned['duration_minutes']
                    : null,
                'distance_meters' => $actual['distance_meters'] !== null && $planned['distance_meters'] !== null
                    ? (int) $actual['distance_meters'] - (int) $planned['distance_meters']
                    : null,
                'calories' => $actual['calories'] !== null && $planned['calories'] !== null
                    ? (int) $actual['calories'] - (int) $planned['calories']
                    : null,
            ],
        ];
    }

    private function notifyTrainingFeedbackRecipients(TrainingLog $log, TrainingLogFeedback $feedback): void
    {
        $authorName = $feedback->author ? $this->serializeUser($feedback->author)['name'] : 'Jemand';
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
