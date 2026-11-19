<?php

namespace App\Services\Training;

use App\Models\Activity;
use App\Models\Team;
use App\Models\TrainingLog;
use App\Models\TrainingPlan;
use App\Models\TrainingPlanItem;
use App\Models\User;
use App\Support\Roles;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TrainingPlanEvaluationService
{
    public function __construct(private readonly TrainingLogAccessService $access) {}

    public function evaluate(User $coach, array $filters): array
    {
        $teamIds = $this->access->managedTeamIds($coach);
        $query = $this->plans($coach, $teamIds);
        $plans = (clone $query)->orderByDesc('updated_at')->orderBy('id')->get(['id', 'title']);
        $planId = $filters['plan_id'] ?? $plans->first()?->id;
        if (! $planId) {
            return ['plans' => [], 'plan_id' => null, 'weeks' => [], 'rows' => [], 'summary' => [], 'pagination' => ['page' => 1, 'last_page' => 1, 'total' => 0]];
        }

        // Query-scoped lookup: a guessed plan ID must not reveal even its title.
        $plan = $query->whereKey($planId)->with(['items', 'assignments.user:id,name', 'assignments.team', 'assignments.trainingGroup'])->firstOrFail();
        $recipients = $this->recipients($coach, $plan, $teamIds);
        $weeks = $plan->items->map(fn ($item) => $this->week($item))->unique()->sort()->values();
        $items = $plan->items->filter(fn ($item) => empty($filters['week']) || $this->week($item) === $filters['week'])->values();
        $logs = TrainingLog::query()
            ->where('training_plan_id', $plan->id)
            ->whereIn('training_plan_item_id', $items->pluck('id'))
            ->whereIn('user_id', $recipients->keys())
            ->whereIn('status', ['completed', 'partial', 'missed'])
            ->where(fn ($q) => $q->whereNull('metrics->privacy_scope')->orWhere('metrics->privacy_scope', '!=', 'private'))
            ->when(! $coach->hasAnyRole(Roles::FULL_ACCESS), fn ($q) => $q
                ->where(fn ($team) => $team->whereNull('team_id')->orWhereIn('team_id', $teamIds)))
            ->with(['entries', 'feedbacks'])
            ->orderBy('performed_at')->orderBy('id')->get()
            ->groupBy(fn ($log) => $log->user_id.':'.$log->training_plan_item_id);

        $rows = $recipients->map(function (array $recipient) use ($items, $logs) {
            $sessions = $items->map(function (TrainingPlanItem $item) use ($recipient, $logs) {
                $reports = $logs->get($recipient['id'].':'.$item->id, collect())
                    ->map(fn (TrainingLog $log) => $this->report($item, $log))->values();

                return [
                    'id' => $item->id, 'title' => $item->title,
                    'scheduled_at' => $item->scheduled_at?->toIso8601String(),
                    'week' => $this->week($item),
                    'planned' => $this->compare($item, null),
                    'reports' => $reports,
                    'missing' => $reports->isEmpty(),
                    'overdue' => $reports->isEmpty() && $item->scheduled_at?->isPast(),
                ];
            });
            $reports = $sessions->flatMap(fn ($session) => $session['reports']);

            return [...$recipient, 'sessions' => $sessions,
                'missing' => $sessions->where('missing', true)->count(),
                'reported' => $sessions->where('missing', false)->count(),
                'feedback_open' => $reports->where('feedback_open', true)->count(),
                'pain' => $reports->contains(fn ($report) => $report['pain'] > 0),
            ];
        })->sortBy(fn ($row) => mb_strtolower($row['name']))->values();

        $summary = [
            'athletes' => $rows->count(), 'sessions' => $rows->count() * $items->count(),
            'reported' => $rows->sum('reported'), 'missing' => $rows->sum('missing'),
            'feedback_open' => $rows->sum('feedback_open'), 'pain' => $rows->where('pain', true)->count(),
        ];
        $rows = $rows->filter(fn ($row) => (empty($filters['search']) || str_contains(mb_strtolower($row['name'].' '.implode(' ', $row['teams'])), mb_strtolower($filters['search'])))
            && (empty($filters['open_only']) || $row['feedback_open'] > 0))->values();
        $lastPage = max(1, (int) ceil($rows->count() / 20));
        $page = min($lastPage, (int) ($filters['page'] ?? 1));
        $pageRows = $rows->slice(($page - 1) * 20, 20)->values();
        $feedbackLogIds = $pageRows->flatMap(fn ($row) => $row['sessions'])
            ->flatMap(fn ($session) => $session['reports'])
            ->filter(fn ($report) => $report['feedback']->isNotEmpty())
            ->pluck('id')->unique()->values()->all();
        if ($feedbackLogIds) {
            // One audit event per page; never copy sensitive feedback into the audit trail.
            Activity::query()->create([
                'user_id' => $coach->id, 'type' => 'training.feedback_case_file.viewed',
                'subject_type' => TrainingPlan::class, 'subject_id' => $plan->id,
                'data' => ['surface' => 'plan_evaluation', 'training_log_ids' => $feedbackLogIds,
                    'classification' => 'protected_training_record', 'contains_sensitive_content' => false],
            ]);
        }

        return ['plans' => $plans, 'plan_id' => $plan->id, 'weeks' => $weeks,
            'summary' => $summary, 'rows' => $pageRows,
            'pagination' => ['page' => $page, 'last_page' => $lastPage, 'total' => $rows->count()],
        ];
    }

    private function plans(User $coach, Collection $teamIds): Builder
    {
        return TrainingPlan::query()->releasedTo($coach)->when(! $coach->hasAnyRole(Roles::FULL_ACCESS), fn (Builder $q) => $q->where(function (Builder $q) use ($coach, $teamIds) {
            $q->whereIn('team_id', $teamIds)
                ->orWhereHas('assignments', fn ($a) => $a->whereIn('team_id', $teamIds))
                ->orWhereHas('trainingGroup.teams', fn ($t) => $t->whereIn('teams.id', $teamIds))
                ->orWhereHas('assignments.trainingGroup.teams', fn ($t) => $t->whereIn('teams.id', $teamIds))
                ->orWhere(fn ($own) => $own->where('created_by', $coach->id)->whereNull('team_id')->whereHas('assignments', fn ($a) => $a->whereNotNull('user_id')));
        }));
    }

    private function recipients(User $coach, TrainingPlan $plan, Collection $managedTeamIds): Collection
    {
        $allAccess = $coach->hasAnyRole(Roles::FULL_ACCESS);
        $groupIds = $plan->assignments->pluck('club_training_group_id')->push($plan->club_training_group_id)->filter();
        $targetTeamIds = $plan->assignments->pluck('team_id')->push($plan->team_id)->filter();
        $teams = Team::query()->where(fn ($q) => $q->whereIn('id', $targetTeamIds)->orWhereIn('club_training_group_id', $groupIds))
            ->when(! $allAccess, fn ($q) => $q->whereIn('id', $managedTeamIds))
            ->with(['club:id,name', 'users:id,name'])->get();
        $individual = data_get($plan->settings, 'team_mode') === 'individual';
        $directIds = $plan->assignments->pluck('user_id')->filter()->map(fn ($id) => (int) $id);
        $people = collect();
        foreach ($teams as $team) {
            foreach ($team->users as $athlete) {
                if ($individual && ! $directIds->contains((int) $athlete->id)) {
                    continue;
                }
                // Team recipients are athletes, not administrative/parent-only seats.
                if (! $directIds->contains((int) $athlete->id)
                    && ! in_array(strtolower((string) $athlete->pivot?->role), ['player', 'athlete', 'sportler', 'member', 'captain'], true)) {
                    continue;
                }
                $row = $people->get($athlete->id, ['id' => $athlete->id, 'name' => $athlete->name, 'teams' => []]);
                $row['teams'][] = trim(($team->club?->name ? $team->club->name.' · ' : '').$team->name);
                $people->put($athlete->id, $row);
            }
        }
        // Private clients need no club/team. Team-specific assignments still require current team authority.
        if ($allAccess || ((int) $plan->created_by === (int) $coach->id && ! $plan->team_id)) {
            foreach ($plan->assignments as $assignment) {
                if ($assignment->user && ! $people->has($assignment->user_id)) {
                    $people->put($assignment->user_id, ['id' => $assignment->user_id, 'name' => $assignment->user->name, 'teams' => []]);
                }
            }
        }

        return $people;
    }

    private function week(TrainingPlanItem $item): string
    {
        return $item->scheduled_at ? CarbonImmutable::parse($item->scheduled_at)->startOfWeek()->toDateString() : 'unscheduled';
    }

    private function report(TrainingPlanItem $item, TrainingLog $log): array
    {
        $last = $log->feedbacks->sortBy([['created_at', 'asc'], ['id', 'asc']])->last();
        $open = $last ? $last->role === 'athlete' : blank($log->trainer_feedback);

        return [
            'id' => $log->id, 'status' => $log->status, 'performed_at' => $log->performed_at?->toIso8601String(),
            'notes' => $log->notes, 'pain' => data_get($log->metrics, 'wellness.pain'),
            'duration_minutes' => $log->duration_minutes, 'distance_meters' => $log->distance_meters,
            'rpe' => data_get($log->metrics, 'wellness.rpe'), 'feedback_open' => $open,
            'snapshot' => is_array(data_get($log->metrics, 'plan_snapshot')),
            'exercises' => $this->compare($item, $log),
            'feedback' => $log->feedbacks->map(fn ($f) => [
                'id' => $f->id, 'body' => $f->body, 'role' => $f->role,
                'author' => $f->author?->name, 'created_at' => $f->created_at?->toIso8601String(),
            ])->values(),
            'trainer_feedback' => $log->trainer_feedback,
        ];
    }

    /** Match stable exercise keys; never silently assign unmatched legacy entries to a different exercise. */
    private function compare(TrainingPlanItem $item, ?TrainingLog $log): array
    {
        $snapshot = data_get($log?->metrics, 'plan_snapshot');
        $metrics = is_array($snapshot) ? ($snapshot['metrics'] ?? []) : ($item->metrics ?? []);
        $planned = collect($metrics['planned_exercises'] ?? []);
        if ($planned->isEmpty()) {
            $planned = collect([[
                'title' => $snapshot['title'] ?? $item->title, 'exercise_key' => null,
                'notes' => $item->description,
                'sets' => [['duration_minutes' => $snapshot['duration_minutes'] ?? $item->duration_minutes,
                    'distance_km' => isset($snapshot['distance_meters']) ? $snapshot['distance_meters'] / 1000 : ($item->distance_meters ? $item->distance_meters / 1000 : null)]],
                'unstructured' => true,
            ]]);
        }
        $entries = $log?->entries ?? collect();
        $used = [];
        $result = [];
        foreach ($planned as $exercise) {
            $key = $exercise['exercise_key'] ?? null;
            $matched = $entries->filter(function ($entry) use ($key, $exercise, &$used) {
                if (in_array($entry->id, $used, true)) {
                    return false;
                }
                $entryKey = data_get($entry->metrics, 'substituted_for') ?: data_get($entry->metrics, 'exercise_key');
                $title = preg_replace('/\s*[-–]\s*(Satz|Set)\s*\d+$/iu', '', $entry->title);

                return $key && $entryKey ? $key === $entryKey
                    : mb_strtolower(trim($exercise['title'])) === mb_strtolower(trim($title));
            });
            $used = [...$used, ...$matched->pluck('id')->all()];
            $actual = $matched->map(fn ($entry) => $this->actual($entry))->values()->all();
            $result[] = [
                'key' => $key, 'title' => $exercise['title'], 'notes' => $exercise['notes'] ?? null,
                'unstructured' => $exercise['unstructured'] ?? false,
                'planned' => $exercise['sets'] ?? [], 'actual' => $actual,
            ];
        }
        foreach ($entries->reject(fn ($e) => in_array($e->id, $used, true))->groupBy(fn ($e) => data_get($e->metrics, 'exercise_key') ?: $e->title) as $key => $extra) {
            $result[] = ['key' => (string) $key, 'title' => $extra->first()->title, 'notes' => null, 'unstructured' => false,
                'planned' => [], 'actual' => $extra->map(fn ($entry) => $this->actual($entry))->values()->all()];
        }

        return $result;
    }

    private function actual($entry): array
    {
        return [
            'title' => $entry->title, 'set_index' => data_get($entry->metrics, 'set_index'), 'sets' => $entry->sets,
            'reps' => $entry->reps, 'weight_kg' => $entry->weight_kg,
            'duration_minutes' => $entry->duration_seconds !== null ? $entry->duration_seconds / 60 : null,
            'distance_km' => $entry->distance_meters !== null ? $entry->distance_meters / 1000 : null,
            'rounds' => data_get($entry->metrics, 'rounds'), 'rest_seconds' => data_get($entry->metrics, 'rest_seconds'),
            'completed' => data_get($entry->metrics, 'completed'), 'skip_reason' => data_get($entry->metrics, 'skip_reason'),
            'substituted_for' => data_get($entry->metrics, 'substituted_for'), 'notes' => $entry->notes,
        ];
    }

    public function authorizeFeedback(User $coach, TrainingLog $log): void
    {
        $teamIds = $this->access->managedTeamIds($coach);
        $plan = $this->plans($coach, $teamIds)->whereKey($log->training_plan_id)->with(['assignments.user:id,name'])->firstOrFail();
        abort_unless($this->recipients($coach, $plan, $teamIds)->has($log->user_id), 404);
        abort_unless(in_array($log->status, ['completed', 'partial', 'missed'], true), 404);
        abort_unless($plan->items()->whereKey($log->training_plan_item_id)->exists(), 404);
        abort_if(data_get($log->metrics, 'privacy_scope') === 'private', 404);
        abort_if($log->team_id && ! $teamIds->contains((int) $log->team_id), 404);
    }
}
