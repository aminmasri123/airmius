<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Team;
use App\Models\TrainingLog;
use App\Models\User;
use App\Support\TeamRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TrainingAnalyticsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_athlete_receives_summary_weekly_load_and_safety_alerts(): void
    {
        $athlete = User::factory()->create();
        TrainingLog::query()->create([
            'user_id' => $athlete->id,
            'created_by' => $athlete->id,
            'title' => 'Grundlage',
            'sport_type' => 'running',
            'status' => 'completed',
            'performed_at' => now()->subDays(2),
            'duration_minutes' => 60,
            'distance_meters' => 10000,
            'metrics' => ['wellness' => ['rpe' => 6, 'pain' => 1]],
        ]);
        TrainingLog::query()->create([
            'user_id' => $athlete->id,
            'created_by' => $athlete->id,
            'title' => 'Intervalle',
            'sport_type' => 'running',
            'status' => 'completed',
            'performed_at' => now()->subDay(),
            'duration_minutes' => 30,
            'distance_meters' => 5000,
            'metrics' => ['wellness' => ['rpe' => 9, 'pain' => 5]],
        ]);

        Sanctum::actingAs($athlete);

        $this->getJson('/api/v1/training/analytics?days=28')
            ->assertOk()
            ->assertJsonPath('data.athlete_id', $athlete->id)
            ->assertJsonPath('data.summary.sessions', 2)
            ->assertJsonPath('data.summary.completed_sessions', 2)
            ->assertJsonPath('data.summary.duration_minutes', 90)
            ->assertJsonPath('data.summary.distance_meters', 15000)
            ->assertJsonPath('data.summary.average_rpe', 7.5)
            ->assertJsonPath('data.summary.alerts', 1)
            ->assertJsonPath('data.alerts.0.title', 'Intervalle')
            ->assertJsonPath('data.privacy.scope', 'self');
    }

    public function test_trainer_scope_excludes_private_logs_and_foreign_users(): void
    {
        $coach = User::factory()->create();
        $athlete = User::factory()->create();
        $outsider = User::factory()->create();
        $club = Club::query()->create(['owner_id' => $coach->id, 'name' => 'Airmius Club']);
        $team = Team::factory()->create(['club_id' => $club->id]);
        $team->users()->attach($coach->id, ['role' => TeamRoles::COACH]);
        $team->users()->attach($athlete->id, ['role' => TeamRoles::PLAYER]);

        TrainingLog::query()->create([
            'user_id' => $athlete->id,
            'created_by' => $athlete->id,
            'trainer_id' => $coach->id,
            'team_id' => $team->id,
            'title' => 'Geteilte Einheit',
            'status' => 'completed',
            'performed_at' => now()->subDay(),
            'duration_minutes' => 45,
            'metrics' => ['wellness' => ['rpe' => 7], 'privacy_scope' => 'trainer'],
        ]);
        TrainingLog::query()->create([
            'user_id' => $athlete->id,
            'created_by' => $athlete->id,
            'trainer_id' => $coach->id,
            'team_id' => $team->id,
            'title' => 'Private Einheit',
            'status' => 'completed',
            'performed_at' => now()->subDay(),
            'duration_minutes' => 90,
            'metrics' => ['wellness' => ['rpe' => 10], 'privacy_scope' => 'private'],
        ]);

        Sanctum::actingAs($coach);

        $this->getJson("/api/v1/training/analytics?user_id={$athlete->id}")
            ->assertOk()
            ->assertJsonPath('data.summary.sessions', 1)
            ->assertJsonPath('data.recent.0.title', 'Geteilte Einheit')
            ->assertJsonPath('data.privacy.scope', 'trainer_shared');

        Sanctum::actingAs($outsider);
        $this->getJson("/api/v1/training/analytics?user_id={$athlete->id}")->assertForbidden();
    }
}
