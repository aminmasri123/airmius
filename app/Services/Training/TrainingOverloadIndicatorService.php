<?php

namespace App\Services\Training;

use App\Models\Activity;
use App\Models\TrainingLog;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class TrainingOverloadIndicatorService
{
    private const MIN_SHARED_LOGS = 3;

    public function __construct(
        private readonly TrainingLogAccessService $access,
        private readonly TrainingResourceService $resources,
    ) {}

    public function canView(User $planner, User $athlete): bool
    {
        if ((int) $planner->id === (int) $athlete->id) {
            return false;
        }

        if (! $this->resources->canManageTrainingPlans($planner)) {
            return false;
        }

        return $planner->hasAnyRole(Roles::FULL_ACCESS)
            || $this->access->manageableAthleteIds($planner)->contains((int) $athlete->id);
    }

    public function indicators(User $planner, User $athlete, int $days = 28): array
    {
        $to = now()->endOfDay();
        $from = now()->subDays($days - 1)->startOfDay();
        $previousFrom = (clone $from)->subDays($days);
        $previousTo = (clone $from)->subSecond();

        $currentLogs = $this->logs($planner, $athlete, $from, $to);
        $previousLogs = $this->logs($planner, $athlete, $previousFrom, $previousTo);
        $currentLoad = $this->loadTotal($currentLogs);
        $previousLoad = $this->loadTotal($previousLogs);
        $spikeRatio = $previousLoad > 0 ? round($currentLoad / $previousLoad, 2) : null;
        $painSignals = $currentLogs->filter(fn (TrainingLog $log) => ($this->metric($log, 'pain') ?? 0) >= 4)->count();
        $highRpeSignals = $currentLogs->filter(fn (TrainingLog $log) => ($this->metric($log, 'rpe') ?? 0) >= 9)->count();
        $lowRecoverySignals = $currentLogs->filter(function (TrainingLog $log) {
            $sleep = $this->metric($log, 'sleep_hours');
            $recovery = $this->metric($log, 'recovery');

            return ($sleep !== null && $sleep < 6) || ($recovery !== null && $recovery <= 2);
        })->count();

        $signals = collect([
            $spikeRatio !== null && $spikeRatio >= 1.5 ? 'load_spike' : null,
            $painSignals >= 2 ? 'repeated_pain' : null,
            $highRpeSignals >= 2 ? 'repeated_high_rpe' : null,
            $lowRecoverySignals >= 2 ? 'low_recovery' : null,
        ])->filter()->values();

        $enoughData = $currentLogs->count() >= self::MIN_SHARED_LOGS;
        $level = match (true) {
            ! $enoughData => 'insufficient_data',
            $signals->count() >= 2 || ($spikeRatio !== null && $spikeRatio >= 2.0) => 'high',
            $signals->isNotEmpty() => 'watch',
            default => 'normal',
        };

        return [
            'athlete_id' => $athlete->id,
            'period' => [
                'days' => $days,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'privacy' => [
                'scope' => 'planner_aggregate',
                'minimum_shared_logs' => self::MIN_SHARED_LOGS,
                'content_included' => false,
            ],
            'summary' => [
                'shared_logs' => $currentLogs->count(),
                'level' => $level,
                'signals' => $enoughData ? $signals->all() : [],
            ],
            'indicators' => [
                'load_score' => $enoughData ? $currentLoad : null,
                'previous_load_score' => $enoughData ? $previousLoad : null,
                'load_spike_ratio' => $enoughData ? $spikeRatio : null,
                'pain_signal_count' => $enoughData ? $painSignals : null,
                'high_rpe_signal_count' => $enoughData ? $highRpeSignals : null,
                'low_recovery_signal_count' => $enoughData ? $lowRecoverySignals : null,
            ],
        ];
    }

    public function auditRead(User $planner, User $athlete, array $payload): void
    {
        $club = $planner->clubs()->first() ?: $athlete->clubs()->first();

        if (! $club) {
            return;
        }

        Activity::query()->create([
            'user_id' => $planner->id,
            'club_id' => $club->id,
            'team_id' => null,
            'type' => 'training.overload_indicators.viewed',
            'subject_type' => User::class,
            'subject_id' => $athlete->id,
            'data' => [
                'period_days' => $payload['period']['days'] ?? null,
                'shared_logs' => $payload['summary']['shared_logs'] ?? null,
                'level' => $payload['summary']['level'] ?? null,
                'signals_count' => count($payload['summary']['signals'] ?? []),
                'content_included' => false,
            ],
        ]);
    }

    private function logs(User $planner, User $athlete, Carbon $from, Carbon $to): Collection
    {
        return $this->access->visibleQuery($planner)
            ->where('user_id', $athlete->id)
            ->where('status', '!=', 'draft')
            ->where('performed_at', '>=', $from)
            ->where('performed_at', '<=', $to)
            ->orderBy('performed_at')
            ->get();
    }

    private function loadTotal(Collection $logs): int
    {
        return (int) round($logs
            ->map(fn (TrainingLog $log) => $this->loadScore($log))
            ->filter(fn ($value) => $value !== null)
            ->sum());
    }

    private function loadScore(TrainingLog $log): ?float
    {
        $rpe = $this->metric($log, 'rpe');
        $duration = (float) ($log->duration_minutes ?? 0);

        return $rpe !== null && $duration > 0 ? round($rpe * $duration, 1) : null;
    }

    private function metric(TrainingLog $log, string $key): ?float
    {
        $metrics = is_array($log->metrics) ? $log->metrics : [];
        $wellness = is_array($metrics['wellness'] ?? null) ? $metrics['wellness'] : [];
        $value = $wellness[$key] ?? $metrics[$key] ?? null;

        return is_numeric($value) ? (float) $value : null;
    }
}
