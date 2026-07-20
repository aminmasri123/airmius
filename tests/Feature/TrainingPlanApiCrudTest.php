<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Team;
use App\Models\TrainingPlan;
use App\Models\User;
use App\Support\TeamRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TrainingPlanApiCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_user_can_create_update_and_delete_training_plan(): void
    {
        [$coach, $athlete, $team] = $this->trainingFixture();

        Sanctum::actingAs($coach);

        $response = $this->postJson('/api/v1/training/plans', [
            'title' => '10k Aufbau',
            'description' => 'Progressiver Laufplan',
            'cadence' => 'weekly',
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->addWeeks(4)->toDateString(),
            'goal' => '10 km stabil laufen',
            'phase' => 'build',
            'level' => 'intermediate',
            'weeks' => 4,
            'weekly_sessions' => 3,
            'status' => 'draft',
            'share_permission' => 'write',
            'team_id' => $team->id,
            'user_ids' => [$athlete->id],
        ])
            ->assertCreated()
            ->assertJsonPath('data.title', '10k Aufbau')
            ->assertJsonPath('data.team_id', $team->id)
            ->assertJsonPath('data.can_write', true)
            ->assertJsonPath('data.can_delete', true)
            ->assertJsonPath('data.assignments_count', 2)
            ->assertJsonPath('data.settings.goal', '10 km stabil laufen');

        $planId = $response->json('data.id');

        $this->assertDatabaseHas('training_plans', [
            'id' => $planId,
            'created_by' => $coach->id,
            'team_id' => $team->id,
            'status' => 'draft',
        ]);
        $this->assertDatabaseHas('training_plan_assignments', [
            'training_plan_id' => $planId,
            'team_id' => $team->id,
            'permission' => 'write',
        ]);
        $this->assertDatabaseHas('training_plan_assignments', [
            'training_plan_id' => $planId,
            'user_id' => $athlete->id,
            'permission' => 'write',
        ]);

        $this->putJson("/api/v1/training/plans/{$planId}", [
            'title' => '10k Aufbau final',
            'description' => 'Angepasster Laufplan',
            'cadence' => 'weekly',
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->addWeeks(6)->toDateString(),
            'goal' => '10 km Wettkampf',
            'phase' => 'peak',
            'level' => 'advanced',
            'weeks' => 6,
            'weekly_sessions' => 4,
            'status' => 'published',
            'share_permission' => 'read',
            'team_id' => $team->id,
            'user_ids' => [],
        ])
            ->assertOk()
            ->assertJsonPath('data.title', '10k Aufbau final')
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.share_permission', 'read')
            ->assertJsonPath('data.settings.phase', 'peak')
            ->assertJsonPath('data.assignments_count', 1);

        $this->deleteJson("/api/v1/training/plans/{$planId}")
            ->assertOk()
            ->assertJsonPath('message', 'Trainingsplan wurde gelöscht.');

        $this->assertDatabaseMissing('training_plans', ['id' => $planId]);
    }

    public function test_api_plan_items_can_be_created_updated_and_deleted(): void
    {
        [$coach, , $team] = $this->trainingFixture();
        $plan = $this->createPlan($coach, $team);

        Sanctum::actingAs($coach);

        $response = $this->postJson("/api/v1/training/plans/{$plan->id}/items", [
            'title' => 'Tempo Run',
            'sport_type' => 'laufen',
            'description' => 'Kontrolliertes Tempo.',
            'scheduled_at' => now()->addDay()->format('Y-m-d\TH:i:sP'),
            'duration_minutes' => 45,
            'distance_km' => 5.5,
            'calories' => 420,
            'intensity' => 'mittel',
            'load' => 'medium',
            'focus' => 'Schwelle',
            'todos' => ['Einlaufen', 'Hauptteil', 'Auslaufen'],
            'metrics' => ['pace' => '5:00'],
        ])
            ->assertCreated()
            ->assertJsonPath('data.items.0.title', 'Tempo Run')
            ->assertJsonPath('data.items.0.distance_meters', 5500)
            ->assertJsonPath('data.items.0.metrics.Fokus', 'Schwelle')
            ->assertJsonPath('data.items.0.todos.0', 'Einlaufen');

        $itemId = $response->json('data.items.0.id');

        $this->putJson("/api/v1/training/plans/{$plan->id}/items/{$itemId}", [
            'title' => 'Tempo Run angepasst',
            'sport_type' => 'laufen',
            'description' => 'Mehr Umfang.',
            'duration_minutes' => 50,
            'distance_meters' => 6000,
            'calories' => 450,
            'intensity' => 'hart',
            'load' => 'high',
            'focus' => 'Tempohaerte',
            'todos_text' => "Einlaufen\nIntervalle\nCooldown",
            'metrics' => ['pace' => '4:45'],
        ])
            ->assertOk()
            ->assertJsonPath('data.items.0.title', 'Tempo Run angepasst')
            ->assertJsonPath('data.items.0.distance_meters', 6000)
            ->assertJsonPath('data.items.0.metrics.Belastung', 'high')
            ->assertJsonPath('data.items.0.todos.1', 'Intervalle');

        $this->deleteJson("/api/v1/training/plans/{$plan->id}/items/{$itemId}")
            ->assertOk()
            ->assertJsonPath('data.items', []);

        $this->assertDatabaseMissing('training_plan_items', ['id' => $itemId]);
    }

    public function test_read_only_assigned_user_cannot_modify_training_plan_or_items(): void
    {
        [$coach, $athlete, $team] = $this->trainingFixture();
        $plan = $this->createPlan($coach, $team, 'read');
        $item = $plan->items()->create([
            'title' => 'Long Run',
            'sport_type' => 'laufen',
        ]);

        $plan->assignments()->create([
            'user_id' => $athlete->id,
            'permission' => 'read',
        ]);

        Sanctum::actingAs($athlete);

        $this->putJson("/api/v1/training/plans/{$plan->id}", [
            'title' => 'Nicht erlaubt',
            'cadence' => 'weekly',
            'status' => 'published',
            'share_permission' => 'read',
        ])->assertForbidden();

        $this->putJson("/api/v1/training/plans/{$plan->id}/items/{$item->id}", [
            'title' => 'Nicht erlaubt',
        ])->assertForbidden();

        $this->deleteJson("/api/v1/training/plans/{$plan->id}")
            ->assertForbidden();
    }

    private function trainingFixture(): array
    {
        $coach = User::factory()->create();
        $athlete = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $coach->id]);
        $team = Team::factory()->create([
            'club_id' => $club->id,
            'sport_type' => 'laufen',
        ]);

        $team->users()->attach($coach->id, ['role' => TeamRoles::COACH]);
        $team->users()->attach($athlete->id, ['role' => TeamRoles::PLAYER]);

        return [$coach, $athlete, $team];
    }

    private function createPlan(User $coach, Team $team, string $permission = 'write'): TrainingPlan
    {
        $plan = TrainingPlan::query()->create([
            'created_by' => $coach->id,
            'team_id' => $team->id,
            'title' => 'Grundlagenplan',
            'cadence' => 'weekly',
            'status' => 'published',
            'share_permission' => $permission,
            'settings' => ['goal' => 'Grundlage'],
        ]);

        $plan->assignments()->create([
            'team_id' => $team->id,
            'permission' => $permission,
        ]);

        return $plan;
    }
}
