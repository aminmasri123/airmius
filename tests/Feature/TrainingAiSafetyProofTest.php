<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Team;
use App\Models\TrainingPlan;
use App\Models\User;
use App\Services\Training\TrainingAiPlanService;
use App\Support\TeamRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TrainingAiSafetyProofTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_and_mobile_ai_plan_storage_require_an_accepted_untampered_preview(): void
    {
        $coach = $this->coach();
        $service = app(TrainingAiPlanService::class);
        $plan = $service->attachSafetyProof($coach, $this->safePlan());

        $this->actingAs($coach)
            ->postJson(route('auth.training.ai.plans.store'), $this->storePayload($plan, false))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('accepted_ai_safety');

        $tampered = $plan;
        $tampered['title'] = 'Manipulierter Plan';

        $this->actingAs($coach)
            ->postJson(route('auth.training.ai.plans.store'), $this->storePayload($tampered))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('plan.safety_token');

        $this->actingAs($coach)
            ->postJson(route('auth.training.ai.plans.store'), $this->storePayload($plan))
            ->assertCreated()
            ->assertJsonPath('plan.title', 'Sicherer Laufplan')
            ->assertJsonPath('plan.settings.ai_generation.safety_gate.accepted_by_user', true);

        Sanctum::actingAs($coach);

        $this->postJson('/api/v1/training/ai/plans', $this->storePayload($plan))
            ->assertCreated()
            ->assertJsonPath('plan.title', 'Sicherer Laufplan');

        $this->assertSame(2, TrainingPlan::query()->where('title', 'Sicherer Laufplan')->count());
    }

    public function test_blocked_or_foreign_ai_preview_cannot_be_saved(): void
    {
        $coach = $this->coach();
        $service = app(TrainingAiPlanService::class);
        $blockedPlan = $this->safePlan();
        $blockedPlan['quality_check']['score'] = 20;
        $blocked = $service->attachSafetyProof($coach, $blockedPlan);

        $this->actingAs($coach)
            ->postJson(route('auth.training.ai.plans.store'), $this->storePayload($blocked))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('plan.safety_token');

        $otherCoach = $this->coach();
        $foreign = $service->attachSafetyProof($otherCoach, $this->safePlan());

        $this->actingAs($coach)
            ->postJson(route('auth.training.ai.plans.store'), $this->storePayload($foreign))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('plan.safety_token');
    }

    private function coach(): User
    {
        $coach = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $coach->id]);
        $team = Team::factory()->create(['club_id' => $club->id]);
        $team->users()->attach($coach->id, ['role' => TeamRoles::COACH]);

        return $coach;
    }

    private function safePlan(): array
    {
        return [
            'title' => 'Sicherer Laufplan',
            'summary' => 'Ein kontrollierter Aufbau.',
            'convincing_explanation' => 'Belastung und Erholung wechseln sich ab.',
            'progression_logic' => ['Umfang steigt schrittweise.'],
            'analysis_tips' => ['Belastung beobachten.'],
            'adjustment_tips' => [],
            'warnings' => [],
            'quality_check' => [
                'score' => 88,
                'risk' => 'niedrig',
                'checks' => [[
                    'key' => 'load',
                    'label' => 'Belastung',
                    'status' => 'ok',
                    'message' => 'Plausibel.',
                ]],
            ],
            'settings' => [
                'goal' => '10 km stabil laufen',
                'phase' => 'build',
                'level' => 'intermediate',
                'weeks' => 4,
                'weekly_sessions' => 2,
            ],
            'items' => [[
                'week' => 1,
                'title' => 'Lockerer Lauf',
                'sport_type' => 'laufen',
                'duration_minutes' => 35,
                'distance_km' => 5,
                'intensity' => 'locker',
                'load' => 'low',
                'todos' => [],
                'metrics' => [],
            ]],
        ];
    }

    private function storePayload(array $plan, bool $accepted = true): array
    {
        return [
            'plan' => $plan,
            'starts_on' => now()->addDay()->toDateString(),
            'status' => 'published',
            'share_permission' => 'read',
            'accepted_ai_safety' => $accepted,
        ];
    }
}
