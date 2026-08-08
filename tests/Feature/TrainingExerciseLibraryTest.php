<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubSubscription;
use App\Models\Team;
use App\Models\TrainingPlan;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Support\TeamRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TrainingExerciseLibraryTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_manage_personal_exercise_and_add_a_snapshot_to_a_plan(): void
    {
        $coach = User::factory()->create(['name' => 'Mina Coach']);
        $club = Club::factory()->create(['owner_id' => $coach->id]);
        $team = Team::factory()->create(['club_id' => $club->id]);
        $team->users()->attach($coach->id, ['role' => TeamRoles::COACH]);
        $plan = TrainingPlan::query()->create([
            'created_by' => $coach->id,
            'title' => 'Frühjahrsaufbau',
            'cadence' => 'weekly',
            'status' => 'draft',
            'share_permission' => 'write',
        ]);

        Sanctum::actingAs($coach);

        $created = $this->postJson('/api/v1/training/exercises', [
            'scope' => 'personal',
            'name' => 'Einbeinige Kniebeuge',
            'sport_type' => 'strength',
            'description' => 'Stabilität und Kontrolle.',
            'instructions' => 'Langsam absenken und sauber aufrichten.',
            'equipment' => ['Matte'],
            'muscle_groups' => ['Beine', 'Rumpf'],
            'difficulty' => 'intermediate',
        ])
            ->assertCreated()
            ->assertJsonPath('data.scope', 'personal')
            ->assertJsonPath('data.name', 'Einbeinige Kniebeuge')
            ->assertJsonPath('data.can_edit', true);

        $exerciseId = $created->json('data.id');

        $this->getJson('/api/v1/training/exercises?q=Kniebeuge')
            ->assertOk()
            ->assertJsonPath('data.0.id', $exerciseId)
            ->assertJsonPath('data.0.equipment.0', 'Matte');

        $this->postJson("/api/v1/training/exercises/{$exerciseId}/add-to-plan", [
            'training_plan_id' => $plan->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.source_exercise_id', $exerciseId)
            ->assertJsonPath('data.title', 'Einbeinige Kniebeuge');

        $this->assertDatabaseHas('training_plan_items', [
            'training_plan_id' => $plan->id,
            'source_exercise_id' => $exerciseId,
            'title' => 'Einbeinige Kniebeuge',
        ]);

        $this->putJson("/api/v1/training/exercises/{$exerciseId}", [
            'name' => 'Einbeinige Kniebeuge Plus',
            'sport_type' => 'strength',
            'difficulty' => 'advanced',
            'equipment' => ['Matte', 'Kettlebell'],
            'muscle_groups' => ['Beine'],
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Einbeinige Kniebeuge Plus')
            ->assertJsonPath('data.difficulty', 'advanced');

        $this->deleteJson("/api/v1/training/exercises/{$exerciseId}")
            ->assertOk()
            ->assertJsonPath('message', 'Übung wurde aus der Bibliothek entfernt.');

        $this->assertDatabaseHas('training_exercises', [
            'id' => $exerciseId,
            'is_active' => false,
        ]);
        $this->assertDatabaseCount('training_plan_items', 1);
    }

    public function test_personal_exercises_are_not_visible_to_other_users_and_team_staff_can_create_team_exercises(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $club = Club::query()->create(['owner_id' => $owner->id, 'name' => 'Airmius Club']);
        $starter = SubscriptionPlan::query()->where('slug', 'starter')->firstOrFail();
        ClubSubscription::query()->updateOrCreate(
            ['club_id' => $club->id],
            ['subscription_plan_id' => $starter->id, 'status' => 'active'],
        );
        $team = Team::factory()->create(['club_id' => $club->id, 'name' => 'U18']);
        $team->users()->attach($owner->id, ['role' => TeamRoles::COACH]);

        Sanctum::actingAs($owner);

        $personal = $this->postJson('/api/v1/training/exercises', [
            'scope' => 'personal',
            'name' => 'Privater Drill',
            'difficulty' => 'all',
        ])->assertCreated();

        $this->postJson('/api/v1/training/exercises', [
            'scope' => 'team',
            'team_id' => $team->id,
            'name' => 'Team-Drill',
            'difficulty' => 'beginner',
        ])
            ->assertCreated()
            ->assertJsonPath('data.scope', 'team')
            ->assertJsonPath('data.team.id', $team->id);

        Sanctum::actingAs($outsider);
        $this->getJson("/api/v1/training/exercises/{$personal->json('data.id')}")->assertNotFound();
    }
}
