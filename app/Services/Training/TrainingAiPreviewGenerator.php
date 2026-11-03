<?php

namespace App\Services\Training;

use App\Models\User;
use App\Services\Ai\AirmiusAiService;

class TrainingAiPreviewGenerator
{
    public function generate(User $user, array $data, array $profileReadiness): array
    {
        $ai = app(AirmiusAiService::class);
        $quality = app(TrainingPlanQualityService::class);
        $proofs = app(TrainingAiPlanService::class);
        $missing = collect([...($profileReadiness['missing'] ?? []), ...($profileReadiness['unknown'] ?? [])])->pluck('label')->filter()->take(6)->implode(', ');
        $plan = $ai->generateTrainingPlan($user, $data);
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

        $plan = $proofs->attachSafetyProof($user, $plan);

        return $plan;
    }
}
