<?php

namespace App\Services\Training;

use App\Models\TrainingPlan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class TrainingAiPlanService
{
    public const SAFETY_GATE_VERSION = '2026-06-03';

    public function maxItems(): int
    {
        return max(1, min(156, (int) config('airmius_ai.features.training_plan_generation.max_items', 156)));
    }

    public function safetyGate(array $plan, string $requestedStatus = 'published'): array
    {
        $quality = $plan['quality_check'] ?? [];
        $score = (int) ($quality['score'] ?? 0);
        $risk = (string) ($quality['risk'] ?? 'unbekannt');
        $checks = collect($quality['checks'] ?? [])->filter(fn ($check) => is_array($check))->values();
        $dangerChecks = $checks
            ->filter(fn (array $check) => ($check['status'] ?? null) === 'danger')
            ->map(fn (array $check) => [
                'key' => $check['key'] ?? 'unknown',
                'label' => $check['label'] ?? 'Check',
                'message' => $check['message'] ?? null,
            ])
            ->values()
            ->all();
        $hasQualityCheck = isset($plan['quality_check'])
            && array_key_exists('score', $quality)
            && array_key_exists('risk', $quality)
            && $checks->isNotEmpty();
        $blocks = [];

        if (! $hasQualityCheck) {
            $blocks[] = 'missing_quality_check';
        }

        if ($score < 50) {
            $blocks[] = 'score_too_low';
        }

        if ($requestedStatus === 'published' && ($risk === 'hoch' || $dangerChecks !== [])) {
            $blocks[] = 'high_risk_requires_draft';
        }

        return [
            'version' => self::SAFETY_GATE_VERSION,
            'status' => $blocks === [] ? 'pass' : 'blocked',
            'can_save' => $blocks === [],
            'can_publish' => $hasQualityCheck && $score >= 50 && $risk !== 'hoch' && $dangerChecks === [],
            'requires_user_confirmation' => true,
            'blocks' => $blocks,
            'quality_score' => $score,
            'risk' => $risk,
            'danger_checks' => $dangerChecks,
            'checked_at' => now()->toIso8601String(),
        ];
    }

    public function storeFromPayload(User $user, array $data): TrainingPlan
    {
        $planPayload = $data['plan'];
        $settings = $planPayload['settings'] ?? [];
        $startsOn = $data['starts_on'] ?? null;
        $weeklySessions = max(1, (int) ($settings['weekly_sessions'] ?? 3));
        $userIds = collect($data['user_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->reject(fn ($id) => $id === (int) $user->id)
            ->unique()
            ->values();

        return DB::transaction(function () use ($user, $data, $planPayload, $settings, $startsOn, $weeklySessions, $userIds) {
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
                        'safety_gate' => array_merge($data['safety_gate'] ?? [], [
                            'accepted_by_user' => (bool) ($data['accepted_ai_safety'] ?? false),
                            'accepted_at' => now()->toIso8601String(),
                        ]),
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
                    'scheduled_at' => $this->scheduledAt($item, $startsOn, $weeklySessions, $index),
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
    }

    private function scheduledAt(array $item, ?string $startsOn, int $weeklySessions, int $index): ?CarbonImmutable
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

    private function cleanMetrics(array $metrics): array
    {
        return collect($metrics)
            ->map(fn ($value) => is_string($value) ? trim($value) : $value)
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();
    }
}
