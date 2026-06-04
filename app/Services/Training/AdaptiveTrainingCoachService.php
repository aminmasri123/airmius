<?php

namespace App\Services\Training;

use App\Models\TrainingLog;
use App\Models\TrainingPlan;
use App\Models\TrainingPlanItem;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class AdaptiveTrainingCoachService
{
    public function forUser(User $user, ?TrainingPlan $plan = null): array
    {
        $now = now();
        $currentStart = $now->copy()->startOfDay()->subDays(6);
        $previousStart = $now->copy()->startOfDay()->subDays(13);
        $previousEnd = $now->copy()->startOfDay()->subDays(7)->endOfDay();

        $currentLogs = $this->logsForWindow($user, $currentStart, $now);
        $previousLogs = $this->logsForWindow($user, $previousStart, $previousEnd);
        $currentStats = $this->windowStats($currentLogs);
        $previousStats = $this->windowStats($previousLogs);
        $recovery = $this->recoveryReadiness($currentStats);
        $injury = $this->injuryRisk($currentStats, $previousStats, $recovery);
        $upcomingItems = $this->upcomingItems($user, $plan, $now);
        $adaptations = $this->adaptations($upcomingItems, $recovery, $injury, $currentStats, $previousStats);

        return [
            'generated_at' => $now->toIso8601String(),
            'status' => 'ready',
            'mode' => 'preview_requires_confirmation',
            'plan' => $plan ? [
                'id' => $plan->id,
                'title' => $plan->title,
                'status' => $plan->status,
                'starts_on' => $plan->starts_on?->toDateString(),
                'ends_on' => $plan->ends_on?->toDateString(),
            ] : null,
            'current_window' => $currentStats,
            'previous_window' => $previousStats,
            'load_trend' => [
                'load_percent' => $this->trendPercent($currentStats['training_load'], $previousStats['training_load']),
                'distance_percent' => $this->trendPercent($currentStats['distance_meters'], $previousStats['distance_meters']),
                'duration_percent' => $this->trendPercent($currentStats['duration_minutes'], $previousStats['duration_minutes']),
            ],
            'recovery' => $recovery,
            'injury_risk' => $injury,
            'adaptations' => $adaptations,
            'coach_explanations' => $this->coachExplanations($recovery, $injury, $adaptations),
            'contract' => [
                'version' => '2026-06-03.runna_coach.v1',
                'safe_by_default' => true,
                'writes_plan_automatically' => false,
                'confirmation_required_for' => ['reduce_load', 'replace_with_recovery', 'reschedule_session', 'cancel_high_risk_session'],
                'wellness_inputs' => ['rpe', 'energy', 'pain', 'sleep_hours'],
            ],
        ];
    }

    private function logsForWindow(User $user, CarbonInterface $start, CarbonInterface $end): Collection
    {
        return TrainingLog::query()
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->whereNotNull('performed_at')
            ->whereBetween('performed_at', [$start, $end])
            ->orderBy('performed_at')
            ->get();
    }

    private function windowStats(Collection $logs): array
    {
        $wellness = $logs
            ->map(fn (TrainingLog $log) => $log->metrics['wellness'] ?? [])
            ->filter(fn ($item) => is_array($item));
        $sessionLoad = $logs->sum(fn (TrainingLog $log) => $this->sessionLoad($log));
        $highLoadCount = $logs->filter(fn (TrainingLog $log) => $this->intensityScore($log) >= 8)->count();

        return [
            'session_count' => $logs->count(),
            'distance_meters' => (int) $logs->sum('distance_meters'),
            'duration_minutes' => (int) $logs->sum('duration_minutes'),
            'training_load' => (int) round($sessionLoad),
            'high_load_sessions' => $highLoadCount,
            'average_rpe' => $this->averageWellness($wellness, 'rpe'),
            'average_energy' => $this->averageWellness($wellness, 'energy'),
            'average_pain' => $this->averageWellness($wellness, 'pain'),
            'average_sleep_hours' => $this->averageWellness($wellness, 'sleep_hours'),
            'latest_wellness' => $wellness->last() ?: null,
        ];
    }

    private function recoveryReadiness(array $current): array
    {
        $score = 82;
        $signals = [];
        $pain = (float) ($current['average_pain'] ?? 0);
        $rpe = (float) ($current['average_rpe'] ?? 0);
        $energy = (float) ($current['average_energy'] ?? 0);
        $sleep = (float) ($current['average_sleep_hours'] ?? 0);

        if ($pain >= 4) {
            $score -= 28;
            $signals[] = 'pain_elevated';
        } elseif ($pain >= 2) {
            $score -= 12;
            $signals[] = 'pain_watch';
        }

        if ($rpe >= 8) {
            $score -= 18;
            $signals[] = 'rpe_high';
        }

        if ($energy > 0 && $energy <= 4) {
            $score -= 14;
            $signals[] = 'low_energy';
        }

        if ($sleep > 0 && $sleep < 6) {
            $score -= 12;
            $signals[] = 'low_sleep';
        }

        if ((int) $current['high_load_sessions'] >= 3) {
            $score -= 10;
            $signals[] = 'too_many_hard_sessions';
        }

        $score = max(5, min(100, $score));

        return [
            'score' => $score,
            'status' => $score >= 75 ? 'ready' : ($score >= 50 ? 'watch' : 'recover'),
            'signals' => $signals,
            'recommended_load_factor' => $score >= 75 ? 1.0 : ($score >= 50 ? 0.75 : 0.45),
        ];
    }

    private function injuryRisk(array $current, array $previous, array $recovery): array
    {
        $loadTrend = $this->trendPercent($current['training_load'], $previous['training_load']);
        $flags = [];
        $score = 18;

        if ($loadTrend >= 35 && $current['training_load'] > 0) {
            $score += 28;
            $flags[] = 'load_spike';
        }

        if ((float) ($current['average_pain'] ?? 0) >= 3) {
            $score += 24;
            $flags[] = 'pain_reported';
        }

        if ((int) $current['high_load_sessions'] >= 3) {
            $score += 18;
            $flags[] = 'hard_session_density';
        }

        if ($recovery['status'] === 'recover') {
            $score += 20;
            $flags[] = 'recovery_low';
        }

        $score = min(100, $score);

        return [
            'score' => $score,
            'level' => $score >= 70 ? 'high' : ($score >= 40 ? 'watch' : 'low'),
            'flags' => $flags,
            'requires_plan_adjustment' => $score >= 40,
            'medical_disclaimer_key' => 'training.adaptive.medical_disclaimer',
        ];
    }

    private function upcomingItems(User $user, ?TrainingPlan $plan, CarbonInterface $now): Collection
    {
        $query = TrainingPlanItem::query()
            ->where('scheduled_at', '>=', $now->copy()->startOfDay())
            ->where('scheduled_at', '<=', $now->copy()->addDays(14)->endOfDay())
            ->with('plan')
            ->orderBy('scheduled_at')
            ->limit(10);

        if ($plan) {
            return $query->where('training_plan_id', $plan->id)->get();
        }

        $teamIds = $user->teams()->pluck('teams.id')->all();

        return $query
            ->whereHas('plan', function ($plans) use ($user, $teamIds) {
                $plans
                    ->where('created_by', $user->id)
                    ->orWhereIn('team_id', $teamIds)
                    ->orWhereHas('assignments', function ($assignments) use ($user, $teamIds) {
                        $assignments
                            ->where('user_id', $user->id)
                            ->orWhereIn('team_id', $teamIds);
                    });
            })
            ->get();
    }

    private function adaptations(Collection $items, array $recovery, array $injury, array $current, array $previous): array
    {
        return $items
            ->map(function (TrainingPlanItem $item) use ($recovery, $injury, $current, $previous) {
                $action = 'keep';
                $factor = 1.0;
                $reasonKeys = [];

                if ($injury['level'] === 'high') {
                    $action = $this->isHardItem($item) ? 'replace_with_recovery' : 'reduce_load';
                    $factor = 0.45;
                    $reasonKeys[] = 'injury_risk_high';
                } elseif ($recovery['status'] === 'recover') {
                    $action = $this->isHardItem($item) ? 'replace_with_recovery' : 'reduce_load';
                    $factor = 0.5;
                    $reasonKeys[] = 'recovery_low';
                } elseif ($recovery['status'] === 'watch' || $this->trendPercent($current['training_load'], $previous['training_load']) >= 30) {
                    $action = $this->isHardItem($item) ? 'reduce_load' : 'keep_easy';
                    $factor = $this->isHardItem($item) ? 0.75 : 1.0;
                    $reasonKeys[] = 'load_watch';
                }

                return [
                    'training_plan_item_id' => $item->id,
                    'title' => $item->title,
                    'scheduled_at' => $item->scheduled_at?->toIso8601String(),
                    'original' => [
                        'duration_minutes' => $item->duration_minutes,
                        'distance_meters' => $item->distance_meters,
                        'intensity' => $item->intensity,
                    ],
                    'suggested' => [
                        'action' => $action,
                        'duration_minutes' => $item->duration_minutes ? max(10, (int) round($item->duration_minutes * $factor)) : null,
                        'distance_meters' => $item->distance_meters ? max(1000, (int) round($item->distance_meters * $factor)) : null,
                        'intensity' => in_array($action, ['reduce_load', 'replace_with_recovery'], true) ? 'low' : $item->intensity,
                        'replacement_type' => $action === 'replace_with_recovery' ? 'recovery_run_or_mobility' : null,
                    ],
                    'reason_keys' => $reasonKeys,
                    'requires_confirmation' => $action !== 'keep' && $action !== 'keep_easy',
                ];
            })
            ->values()
            ->all();
    }

    private function coachExplanations(array $recovery, array $injury, array $adaptations): array
    {
        $explanations = [[
            'key' => 'recovery_status',
            'severity' => $recovery['status'],
            'label_key' => 'training.adaptive.explanations.recovery_status',
            'data' => ['score' => $recovery['score'], 'signals' => $recovery['signals']],
        ]];

        if ($injury['level'] !== 'low') {
            $explanations[] = [
                'key' => 'injury_risk',
                'severity' => $injury['level'],
                'label_key' => 'training.adaptive.explanations.injury_risk',
                'data' => ['score' => $injury['score'], 'flags' => $injury['flags']],
            ];
        }

        $changed = collect($adaptations)->filter(fn (array $item) => $item['requires_confirmation'])->count();
        $explanations[] = [
            'key' => 'plan_adjustments',
            'severity' => $changed > 0 ? 'watch' : 'ready',
            'label_key' => 'training.adaptive.explanations.plan_adjustments',
            'data' => ['changed_items' => $changed],
        ];

        return $explanations;
    }

    private function sessionLoad(TrainingLog $log): float
    {
        return (float) ($log->duration_minutes ?? 0) * $this->intensityScore($log);
    }

    private function intensityScore(TrainingLog $log): float
    {
        $rpe = (float) data_get($log->metrics, 'wellness.rpe', 0);

        if ($rpe > 0) {
            return $rpe;
        }

        return match ($log->intensity) {
            'high', 'hart', 'intensiv' => 8,
            'medium', 'mittel' => 5,
            default => 3,
        };
    }

    private function isHardItem(TrainingPlanItem $item): bool
    {
        $load = strtolower((string) data_get($item->metrics, 'Belastung', ''));
        $type = strtolower((string) data_get($item->metrics, '_training_type', ''));
        $intensity = strtolower((string) $item->intensity);

        return in_array($intensity, ['high', 'hart', 'intensiv'], true)
            || in_array($load, ['high', 'hoch'], true)
            || str_contains($type, 'interval')
            || str_contains($type, 'tempo');
    }

    private function averageWellness(Collection $wellness, string $key): ?float
    {
        $values = $wellness
            ->map(fn (array $item) => $item[$key] ?? null)
            ->filter(fn ($value) => is_numeric($value));

        return $values->isEmpty() ? null : round((float) $values->avg(), 1);
    }

    private function trendPercent(int|float $current, int|float $previous): int
    {
        if ((float) $previous <= 0.0) {
            return (float) $current > 0.0 ? 100 : 0;
        }

        return (int) round((($current - $previous) / max($previous, 1)) * 100);
    }
}
