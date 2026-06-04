<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Team;
use App\Models\TrainingLog;
use App\Models\TrainingPlan;
use App\Models\TrainingPlanItem;
use App\Models\User;
use App\Support\TeamRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TrainerCockpitWeeklyControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_trainer_cockpit_exposes_weekly_readiness_risk_and_actions(): void
    {
        $trainer = User::factory()->create();
        $athlete = User::factory()->create(['name' => 'Mira Runner']);
        $owner = User::factory()->create();
        $club = Club::query()->create([
            'owner_id' => $owner->id,
            'name' => 'Airmius Club',
        ]);
        $team = Team::factory()->create([
            'club_id' => $club->id,
            'name' => 'U18 Performance',
            'sport_type' => 'running',
        ]);

        $team->users()->attach($trainer->id, ['role' => TeamRoles::COACH]);
        $team->users()->attach($athlete->id, ['role' => TeamRoles::PLAYER]);

        $plan = TrainingPlan::query()->create([
            'created_by' => $trainer->id,
            'team_id' => $team->id,
            'title' => 'Race week',
            'status' => 'active',
        ]);
        TrainingPlanItem::query()->create([
            'training_plan_id' => $plan->id,
            'title' => 'Easy recovery',
            'scheduled_at' => now()->subDay(),
        ]);

        TrainingLog::query()->create([
            'user_id' => $athlete->id,
            'created_by' => $athlete->id,
            'trainer_id' => $trainer->id,
            'team_id' => $team->id,
            'training_plan_id' => $plan->id,
            'title' => 'Hard intervals',
            'status' => 'completed',
            'performed_at' => now()->subDay(),
            'duration_minutes' => 55,
            'distance_meters' => 9000,
            'intensity' => 'high',
            'metrics' => [
                'wellness' => [
                    'rpe' => 9,
                    'energy' => 4,
                    'pain' => 3,
                ],
            ],
        ]);
        TrainingLog::query()->create([
            'user_id' => $athlete->id,
            'created_by' => $athlete->id,
            'trainer_id' => $trainer->id,
            'team_id' => $team->id,
            'training_plan_id' => $plan->id,
            'title' => 'Previous base run',
            'status' => 'completed',
            'performed_at' => now()->subDays(9),
            'duration_minutes' => 40,
            'distance_meters' => 6000,
            'intensity' => 'low',
            'trainer_feedback' => 'Good rhythm.',
        ]);

        $this->actingAs($trainer)
            ->get(route('auth.trainer-cockpit.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Dashboard/TrainerCockpit/Index')
                ->where('summary.risk_athletes', 1)
                ->where('summary.overdue_items', 1)
                ->where('coachWeekly.risk_level', 'watch')
                ->where('coachWeekly.current_week.session_count', 1)
                ->where('coachWeekly.current_week.average_rpe', 9)
                ->where('coachWeekly.risk_athletes.0.name', 'Mira Runner')
                ->where('coachWeekly.team_cards.0.risk_athletes', 1)
                ->where('coachWeekly.actions.0.key', 'checkRiskAthletes')
            );
    }
}
