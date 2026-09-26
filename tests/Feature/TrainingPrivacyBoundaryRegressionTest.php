<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Team;
use App\Models\User;
use App\Support\TeamRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TrainingPrivacyBoundaryRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_training_log_visibility_respects_private_trainer_and_team_scopes(): void
    {
        $coach = User::factory()->create(['name' => 'Coach']);
        $athlete = User::factory()->create(['name' => 'Athlete']);
        $teammate = User::factory()->create(['name' => 'Teammate']);
        $outsider = User::factory()->create(['name' => 'Outsider']);
        $club = Club::factory()->create(['owner_id' => $coach->id]);
        $team = Team::factory()->create(['club_id' => $club->id]);

        $team->users()->attach($coach->id, ['role' => TeamRoles::COACH]);
        $team->users()->attach($athlete->id, ['role' => TeamRoles::PLAYER]);
        $team->users()->attach($teammate->id, ['role' => TeamRoles::PLAYER]);

        $privateLog = $athlete->trainingLogs()->create([
            'created_by' => $athlete->id,
            'team_id' => $team->id,
            'title' => 'Private pain note',
            'status' => 'completed',
            'performed_at' => now()->subDays(2),
            'notes' => 'Knee pain after intervals',
            'metrics' => [
                'privacy_scope' => 'private',
                'wellness' => ['pain' => 8, 'sleep_hours' => 4],
            ],
        ]);

        $trainerLog = $athlete->trainingLogs()->create([
            'created_by' => $athlete->id,
            'trainer_id' => $coach->id,
            'team_id' => $team->id,
            'title' => 'Trainer-only readiness',
            'status' => 'completed',
            'performed_at' => now()->subDay(),
            'notes' => 'Discuss load privately with coach',
            'metrics' => [
                'privacy_scope' => 'trainer',
                'wellness' => ['rpe' => 9],
            ],
        ]);

        $teamLog = $athlete->trainingLogs()->create([
            'created_by' => $athlete->id,
            'team_id' => $team->id,
            'title' => 'Team relay session',
            'status' => 'completed',
            'performed_at' => now(),
            'duration_minutes' => 55,
            'metrics' => ['privacy_scope' => 'team'],
        ]);

        Sanctum::actingAs($coach);
        $coachPayload = $this->getJson('/api/v1/training/logs')
            ->assertOk()
            ->assertJsonFragment(['id' => $trainerLog->id, 'title' => 'Trainer-only readiness'])
            ->assertJsonFragment(['id' => $teamLog->id, 'title' => 'Team relay session'])
            ->assertJsonMissing(['notes' => 'Knee pain after intervals'])
            ->json('data');
        $this->assertNotContains($privateLog->id, collect($coachPayload)->pluck('id')->all());
        $this->getJson("/api/v1/training/logs/{$privateLog->id}")->assertNotFound();

        Sanctum::actingAs($teammate);
        $teammatePayload = $this->getJson('/api/v1/training/logs')
            ->assertOk()
            ->assertJsonFragment(['id' => $teamLog->id, 'title' => 'Team relay session'])
            ->assertJsonMissing(['notes' => 'Discuss load privately with coach'])
            ->assertJsonMissing(['notes' => 'Knee pain after intervals'])
            ->json('data');
        $visibleIds = collect($teammatePayload)->pluck('id')->all();
        $this->assertNotContains($trainerLog->id, $visibleIds);
        $this->assertNotContains($privateLog->id, $visibleIds);
        $this->getJson("/api/v1/training/logs/{$trainerLog->id}")->assertNotFound();
        $this->getJson("/api/v1/training/logs/{$privateLog->id}")->assertNotFound();

        Sanctum::actingAs($outsider);
        $this->getJson('/api/v1/training/logs')
            ->assertOk()
            ->assertJsonMissing(['id' => $teamLog->id])
            ->assertJsonMissing(['id' => $trainerLog->id])
            ->assertJsonMissing(['id' => $privateLog->id]);
        $this->getJson("/api/v1/training/logs/{$teamLog->id}")->assertNotFound();

        Sanctum::actingAs($athlete);
        $this->getJson("/api/v1/training/logs/{$privateLog->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $privateLog->id)
            ->assertJsonPath('data.metrics.privacy_scope', 'private')
            ->assertJsonPath('data.metrics.wellness.pain', 8);
    }
}
