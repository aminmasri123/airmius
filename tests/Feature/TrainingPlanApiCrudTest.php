<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Team;
use App\Models\TrainingPlan;
use App\Models\User;
use App\Support\TeamRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
            'macrocycle' => 'Herbstaufbau',
            'mesocycle' => 'Grundlagenausdauer',
            'deload_week' => 4,
            'competition_date' => now()->addWeeks(8)->toDateString(),
            'status' => 'draft',
            'share_permission' => 'write',
            'team_id' => $team->id,
            'user_ids' => [$athlete->id],
            'item_title' => 'Grundlagenlauf',
            'item_sport_type' => 'laufen',
            'item_duration_minutes' => 45,
            'item_distance_km' => 7.5,
            'item_load' => 'medium',
            'item_focus' => 'Zone 2',
            'item_metrics' => [
                'Abschnitt' => 'Ausdauer',
                'Trainingsziel' => 'Ausdauer',
                'Niveau' => 'intermediate',
                'Equipment' => 'Laufschuhe',
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.title', '10k Aufbau')
            ->assertJsonPath('data.team_id', $team->id)
            ->assertJsonPath('data.can_write', true)
            ->assertJsonPath('data.can_delete', true)
            ->assertJsonPath('data.assignments_count', 2)
            ->assertJsonPath('data.settings.goal', '10 km stabil laufen')
            ->assertJsonPath('data.settings.macrocycle', 'Herbstaufbau')
            ->assertJsonPath('data.settings.deload_week', 4)
            ->assertJsonPath('data.items.0.title', 'Grundlagenlauf')
            ->assertJsonPath('data.items.0.sport_type', 'laufen')
            ->assertJsonPath('data.items.0.metrics.Abschnitt', 'Ausdauer')
            ->assertJsonPath('data.items.0.metrics.Trainingsziel', 'Ausdauer')
            ->assertJsonPath('data.items.0.metrics.Niveau', 'intermediate')
            ->assertJsonPath('data.items.0.metrics.Equipment', 'Laufschuhe');

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
            'macrocycle' => 'Wettkampfvorbereitung',
            'mesocycle' => 'Tempo',
            'deload_week' => 5,
            'competition_date' => now()->addWeeks(6)->toDateString(),
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
            ->assertJsonPath('data.settings.mesocycle', 'Tempo')
            ->assertJsonPath('data.settings.deload_week', 5)
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

    public function test_api_plan_item_image_can_be_uploaded_and_replaced(): void
    {
        Storage::fake('public');
        [$coach, , $team] = $this->trainingFixture();
        $plan = $this->createPlan($coach, $team);

        Sanctum::actingAs($coach);

        $created = $this->post("/api/v1/training/plans/{$plan->id}/items", [
            'title' => 'Technikeinheit',
            'sport_type' => 'laufen',
            'todos_text' => "Mobilisieren\nLauf-ABC",
            'week' => 2,
            'calories' => 280,
            'load' => 'medium',
            'focus' => 'Lauftechnik',
            'metrics' => ['Kadenz' => '170 spm'],
            'image' => UploadedFile::fake()->image('technik.jpg', 900, 600),
        ])
            ->assertCreated()
            ->assertJsonPath('data.items.0.title', 'Technikeinheit')
            ->assertJsonPath('data.items.0.todos.1', 'Lauf-ABC')
            ->assertJsonPath('data.items.0.metrics.Woche', 2)
            ->assertJsonPath('data.items.0.metrics.Kadenz', '170 spm');

        $itemId = $created->json('data.items.0.id');
        $firstPath = $plan->items()->findOrFail($itemId)->image_path;
        Storage::disk('public')->assertExists($firstPath);
        $this->assertNotEmpty($created->json('data.items.0.image_url'));

        $updated = $this->post("/api/v1/training/plans/{$plan->id}/items/{$itemId}", [
            '_method' => 'PUT',
            'title' => 'Technikeinheit aktualisiert',
            'sport_type' => 'laufen',
            'image' => UploadedFile::fake()->image('technik-neu.png', 800, 800),
        ])
            ->assertOk()
            ->assertJsonPath('data.items.0.title', 'Technikeinheit aktualisiert');

        $secondPath = $plan->items()->findOrFail($itemId)->image_path;
        $this->assertNotSame($firstPath, $secondPath);
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($secondPath);
        $this->assertNotEmpty($updated->json('data.items.0.image_url'));
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

    public function test_mobile_api_supports_publish_duplicate_and_missed_training_workflow(): void
    {
        [$coach, $athlete, $team] = $this->trainingFixture();
        $plan = $this->createPlan($coach, $team);
        $plan->update(['status' => 'draft']);
        $item = $plan->items()->create([
            'title' => 'Tempolauf',
            'sport_type' => 'laufen',
            'duration_minutes' => 45,
            'sort_order' => 1,
        ]);

        Sanctum::actingAs($coach);

        $this->postJson("/api/v1/training/plans/{$plan->id}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', 'published');

        $copy = $this->postJson("/api/v1/training/plans/{$plan->id}/duplicate")
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.items.0.title', 'Tempolauf');
        $this->assertNotSame($plan->id, $copy->json('data.id'));

        $this->postJson("/api/v1/training/plans/{$plan->id}/items/{$item->id}/duplicate")
            ->assertCreated()
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.1.title', 'Tempolauf Kopie');

        Sanctum::actingAs($athlete);
        $this->postJson("/api/v1/training/plans/{$plan->id}/items/{$item->id}/missed", [
            'reason' => 'krank',
            'notes' => 'Heute ist Erholung sicherer.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'missed')
            ->assertJsonPath('data.user_id', $athlete->id)
            ->assertJsonPath('data.metrics.missed_reason', 'krank');
    }

    public function test_mobile_api_can_save_and_instantiate_scoped_training_templates(): void
    {
        [$coach, $athlete, $team] = $this->trainingFixture();
        $plan = $this->createPlan($coach, $team);
        $plan->items()->create([
            'title' => 'Grundlageneinheit',
            'scheduled_at' => now()->addDay(),
            'sort_order' => 1,
        ]);

        Sanctum::actingAs($coach);

        $template = $this->postJson("/api/v1/training/plans/{$plan->id}/template", [
            'title' => 'Grundlagen Vorlage',
        ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Grundlagen Vorlage')
            ->assertJsonPath('data.is_template', true)
            ->assertJsonPath('data.items.0.title', 'Grundlageneinheit');

        $templateId = $template->json('data.id');

        $this->getJson('/api/v1/training/templates')
            ->assertOk()
            ->assertJsonPath('data.0.id', $templateId)
            ->assertJsonPath('data.0.is_template', true);

        Sanctum::actingAs($athlete);

        $this->postJson("/api/v1/training/templates/{$templateId}/instantiate", [
            'title' => 'Neue Grundlagenwoche',
            'starts_on' => now()->addWeek()->toDateString(),
            'ends_on' => now()->addWeeks(2)->toDateString(),
        ])->assertForbidden();

        Sanctum::actingAs($coach);

        $copy = $this->postJson("/api/v1/training/templates/{$templateId}/instantiate", [
            'title' => 'Neue Grundlagenwoche',
            'starts_on' => now()->addWeek()->toDateString(),
            'ends_on' => now()->addWeeks(2)->toDateString(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Neue Grundlagenwoche')
            ->assertJsonPath('data.is_template', false)
            ->assertJsonPath('data.items.0.scheduled_at', null);

        $this->assertNotSame($templateId, $copy->json('data.id'));
        $this->assertDatabaseHas('training_plans', [
            'id' => $copy->json('data.id'),
            'created_by' => $coach->id,
            'status' => 'draft',
        ]);
    }

    public function test_only_coaches_club_owners_and_club_presidents_can_create_or_modify_plans(): void
    {
        $owner = User::factory()->create();
        $coach = User::factory()->create();
        $president = User::factory()->create();
        $captain = User::factory()->create();
        $player = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $team = Team::factory()->create(['club_id' => $club->id]);

        $team->users()->attach([
            $coach->id => ['role' => TeamRoles::COACH],
            $president->id => ['role' => TeamRoles::CLUB_PRESIDENT],
            $captain->id => ['role' => TeamRoles::CAPTAIN],
            $player->id => ['role' => TeamRoles::PLAYER],
        ]);

        $payload = [
            'title' => 'Sommerplan',
            'cadence' => 'weekly',
            'status' => 'draft',
            'share_permission' => 'write',
            'team_id' => $team->id,
            'item_title' => 'Grundlageneinheit',
        ];

        foreach ([$coach, $owner, $president] as $manager) {
            Sanctum::actingAs($manager);

            $this->postJson('/api/v1/training/plans', $payload)
                ->assertCreated()
                ->assertJsonPath('data.can_write', true);
        }

        foreach ([$captain, $player] as $restrictedUser) {
            Sanctum::actingAs($restrictedUser);

            $this->getJson('/api/v1/training/plans')
                ->assertOk()
                ->assertJsonPath('capabilities.can_manage_training_plans', false);

            $this->postJson('/api/v1/training/plans', $payload)->assertForbidden();
        }

        $plan = TrainingPlan::query()->where('created_by', $coach->id)->firstOrFail();

        foreach ([$captain, $player] as $restrictedUser) {
            Sanctum::actingAs($restrictedUser);

            $this->putJson("/api/v1/training/plans/{$plan->id}", [])
                ->assertForbidden();

            $this->postJson("/api/v1/training/plans/{$plan->id}/items", [])
                ->assertForbidden();
        }
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
