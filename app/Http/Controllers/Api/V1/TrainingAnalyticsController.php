<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TrainingLog;
use App\Models\User;
use App\Services\Training\TrainingLogAccessService;
use App\Services\Training\TrainingOverloadIndicatorService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class TrainingAnalyticsController extends Controller
{
    public function index(Request $request, TrainingLogAccessService $access)
    {
        $data = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'days' => ['nullable', 'integer', Rule::in([7, 14, 28, 56, 90])],
        ]);

        $viewer = $request->user();
        $athleteId = (int) ($data['user_id'] ?? $viewer->id);
        $days = (int) ($data['days'] ?? 28);
        $visibleLogs = $access->visibleQuery($viewer)->where('user_id', $athleteId);

        abort_unless(
            $athleteId === (int) $viewer->id
                || (clone $visibleLogs)->exists(),
            403,
        );

        $from = now()->subDays($days - 1)->startOfDay();
        $to = now()->endOfDay();
        $query = $visibleLogs
            ->where('status', '!=', 'draft')
            ->where('performed_at', '>=', $from)
            ->where('performed_at', '<=', $to)
            ->orderBy('performed_at');

        $logs = $query->get();
        $completed = $logs->where('status', 'completed');
        $rpes = $logs->map(fn (TrainingLog $log) => $this->metric($log, 'rpe'))->filter(fn ($value) => $value !== null);
        $pain = $logs->map(fn (TrainingLog $log) => $this->metric($log, 'pain'))->filter(fn ($value) => $value !== null);
        $load = $logs->map(fn (TrainingLog $log) => $this->loadScore($log))->filter(fn ($value) => $value !== null);

        $weeks = $logs->groupBy(fn (TrainingLog $log) => Carbon::parse($log->performed_at)->startOfWeek()->toDateString())
            ->map(function ($weekLogs, string $week) {
                $values = $weekLogs->map(fn (TrainingLog $log) => $this->loadScore($log))->filter(fn ($value) => $value !== null);
                return [
                    'week' => $week,
                    'sessions' => $weekLogs->count(),
                    'duration_minutes' => (int) $weekLogs->sum(fn (TrainingLog $log) => (int) ($log->duration_minutes ?? 0)),
                    'distance_meters' => (int) $weekLogs->sum(fn (TrainingLog $log) => (int) ($log->distance_meters ?? 0)),
                    'load_score' => $values->isNotEmpty() ? (int) round($values->sum()) : 0,
                    'average_rpe' => $this->average($weekLogs->map(fn (TrainingLog $log) => $this->metric($log, 'rpe'))),
                ];
            })->values();

        $alerts = $logs->filter(function (TrainingLog $log) {
            $rpe = $this->metric($log, 'rpe');
            $pain = $this->metric($log, 'pain');
            return ($pain !== null && $pain >= 4) || ($rpe !== null && $rpe >= 9);
        })->sortByDesc('performed_at')->take(5)->map(fn (TrainingLog $log) => [
            'id' => $log->id,
            'title' => $log->title,
            'performed_at' => $log->performed_at?->toIso8601String(),
            'sport_type' => $log->sport_type,
            'rpe' => $this->metric($log, 'rpe'),
            'pain' => $this->metric($log, 'pain'),
        ])->values();

        $recent = $logs->sortByDesc('performed_at')->take(6)->map(fn (TrainingLog $log) => [
            'id' => $log->id,
            'title' => $log->title,
            'performed_at' => $log->performed_at?->toIso8601String(),
            'sport_type' => $log->sport_type,
            'status' => $log->status,
            'duration_minutes' => $log->duration_minutes,
            'distance_meters' => $log->distance_meters,
            'rpe' => $this->metric($log, 'rpe'),
            'pain' => $this->metric($log, 'pain'),
        ])->values();

        return response()->json([
            'data' => [
                'athlete_id' => $athleteId,
                'period' => ['days' => $days, 'from' => $from->toDateString(), 'to' => $to->toDateString()],
                'summary' => [
                    'sessions' => $logs->count(),
                    'completed_sessions' => $completed->count(),
                    'duration_minutes' => (int) $logs->sum(fn (TrainingLog $log) => (int) ($log->duration_minutes ?? 0)),
                    'distance_meters' => (int) $logs->sum(fn (TrainingLog $log) => (int) ($log->distance_meters ?? 0)),
                    'calories' => (int) $logs->sum(fn (TrainingLog $log) => (int) ($log->calories ?? 0)),
                    'average_rpe' => $this->average($rpes),
                    'average_pain' => $this->average($pain),
                    'load_score' => $load->isNotEmpty() ? (int) round($load->sum()) : 0,
                    'alerts' => $alerts->count(),
                ],
                'weeks' => $weeks,
                'alerts' => $alerts,
                'recent' => $recent,
                'privacy' => ['scope' => $athleteId === (int) $viewer->id ? 'self' : 'trainer_shared'],
            ],
        ]);
    }

    public function overloadIndicators(Request $request, TrainingOverloadIndicatorService $overload)
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'days' => ['nullable', 'integer', Rule::in([14, 28, 56])],
        ]);

        $planner = $request->user();
        $athlete = User::query()->findOrFail((int) $data['user_id']);

        abort_unless($overload->canView($planner, $athlete), 403);

        $payload = $overload->indicators($planner, $athlete, (int) ($data['days'] ?? 28));
        $overload->auditRead($planner, $athlete, $payload);

        return response()->json(['data' => $payload]);
    }

    private function metric(TrainingLog $log, string $key): ?float
    {
        $metrics = is_array($log->metrics) ? $log->metrics : [];
        $wellness = is_array($metrics['wellness'] ?? null) ? $metrics['wellness'] : [];
        $value = $wellness[$key] ?? $metrics[$key] ?? null;

        return is_numeric($value) ? (float) $value : null;
    }

    private function loadScore(TrainingLog $log): ?float
    {
        $rpe = $this->metric($log, 'rpe');
        $duration = (float) ($log->duration_minutes ?? 0);

        if ($rpe === null || $duration <= 0) {
            return null;
        }

        return round($rpe * $duration, 1);
    }

    private function average($values): ?float
    {
        $values = collect($values)->filter(fn ($value) => $value !== null);

        return $values->isNotEmpty() ? round((float) $values->avg(), 1) : null;
    }
}
