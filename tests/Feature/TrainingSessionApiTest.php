<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubRoleAssignment;
use App\Models\ClubRoleDefinition;
use App\Models\Team;
use App\Models\TrainingExercise;
use App\Models\TrainingSession;
use App\Models\User;
use App\Support\ClubPermissions;
use App\Support\TeamRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TrainingSessionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_coach_can_create_and_version_team_training_session_with_goals_phases_exercises_and_materials(): void
    {
        $coach = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $coach->id]);
        $team = Team::factory()->create(['club_id' => $club->id]);
        $team->users()->attach($coach->id, ['role' => TeamRoles::COACH]);
        $exercise = TrainingExercise::query()->create([
            'created_by' => $coach->id,
            'club_id' => $club->id,
            'team_id' => $team->id,
            'name' => 'Pressing-Auslöser',
            'difficulty' => 'intermediate',
            'is_active' => true,
        ]);

        Sanctum::actingAs($coach);

        $response = $this->postJson('/api/v1/training/sessions', [
            'scope' => 'team',
            'team_id' => $team->id,
            'title' => 'Pressing Training',
            'description' => 'Gemeinsame Mannschaftseinheit.',
            'goals' => ['Ballgewinn nach außen lenken', 'Kommunikation verbessern'],
            'phases' => [[
                'title' => 'Aktivierung',
                'goal' => 'Körper und Kopf vorbereiten',
                'duration_minutes' => 12,
                'notes' => 'Mit Ball starten.',
            ]],
            'exercises' => [[
                'training_exercise_id' => $exercise->id,
                'title' => 'Pressing-Auslöser',
                'phase' => 'Hauptteil',
                'sets' => 4,
                'duration_seconds' => 360,
                'notes' => 'Nach jedem Durchgang kurz coachen.',
            ]],
            'materials' => ['Hütchen', 'Bälle', 'Leibchen'],
            'duration_minutes' => 75,
            'status' => 'draft',
            'change_note' => 'Erster Aufbau',
        ])
            ->assertCreated()
            ->assertJsonPath('data.scope', 'team')
            ->assertJsonPath('data.revision', 1)
            ->assertJsonPath('data.goals.0', 'Ballgewinn nach außen lenken')
            ->assertJsonPath('data.phases.0.title', 'Aktivierung')
            ->assertJsonPath('data.exercises.0.training_exercise_id', $exercise->id)
            ->assertJsonPath('data.materials.2', 'Leibchen')
            ->assertJsonPath('data.versions.0.revision', 1)
            ->assertJsonPath('data.versions.0.change_note', 'Erster Aufbau');

        $sessionId = $response->json('data.id');

        $this->putJson("/api/v1/training/sessions/{$sessionId}", [
            'title' => 'Pressing Training final',
            'description' => 'Aktualisierte Mannschaftseinheit.',
            'goals' => ['Ballgewinn erzwingen'],
            'phases' => [[
                'title' => 'Hauptteil',
                'duration_minutes' => 45,
            ]],
            'exercises' => [[
                'training_exercise_id' => $exercise->id,
                'title' => 'Pressing-Auslöser',
                'phase' => 'Hauptteil',
                'sets' => 5,
            ]],
            'materials' => ['Hütchen', 'Leibchen'],
            'duration_minutes' => 80,
            'status' => 'ready',
            'change_note' => 'Belastung erhöht',
        ])
            ->assertOk()
            ->assertJsonPath('data.revision', 2)
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.versions.0.revision', 2)
            ->assertJsonPath('data.versions.1.revision', 1);

        $this->assertDatabaseHas('training_sessions', [
            'id' => $sessionId,
            'team_id' => $team->id,
            'revision' => 2,
            'status' => 'ready',
        ]);
        $this->assertDatabaseHas('training_session_versions', [
            'training_session_id' => $sessionId,
            'revision' => 1,
            'change_note' => 'Erster Aufbau',
        ]);
        $this->assertDatabaseHas('training_session_versions', [
            'training_session_id' => $sessionId,
            'revision' => 2,
            'change_note' => 'Belastung erhöht',
        ]);
    }

    public function test_training_session_access_is_limited_by_club_roles_and_exercise_scope(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $otherOwner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $team = Team::factory()->create(['club_id' => $club->id]);
        $otherClub = Club::factory()->create(['owner_id' => $otherOwner->id]);
        $otherTeam = Team::factory()->create(['club_id' => $otherClub->id]);
        $club->users()->attach($viewer->id, [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'active',
        ]);
        $this->assign($club, $owner, $viewer, 'session_viewer', [ClubPermissions::TRAINING_SESSIONS_VIEW]);

        $session = TrainingSession::query()->create([
            'created_by' => $owner->id,
            'club_id' => $club->id,
            'team_id' => $team->id,
            'title' => 'Interne Einheit',
            'goals' => ['Zusammenarbeit'],
            'phases' => [],
            'exercises' => [],
            'materials' => [],
            'status' => 'draft',
            'revision' => 1,
            'is_active' => true,
        ]);
        $otherExercise = TrainingExercise::query()->create([
            'created_by' => $otherOwner->id,
            'club_id' => $otherClub->id,
            'team_id' => $otherTeam->id,
            'name' => 'Fremde Übung',
            'difficulty' => 'all',
            'is_active' => true,
        ]);

        Sanctum::actingAs($viewer);

        $this->getJson('/api/v1/training/sessions')
            ->assertOk()
            ->assertJsonPath('data.0.id', $session->id)
            ->assertJsonPath('data.0.can_edit', false);

        $this->putJson("/api/v1/training/sessions/{$session->id}", $this->sessionPayload('Nicht erlaubt'))
            ->assertForbidden();

        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/training/sessions', [
            ...$this->sessionPayload('Grenztest'),
            'scope' => 'team',
            'team_id' => $team->id,
            'exercises' => [[
                'training_exercise_id' => $otherExercise->id,
                'title' => 'Fremde Übung',
            ]],
        ])->assertUnprocessable();

        Sanctum::actingAs($otherOwner);
        $this->getJson("/api/v1/training/sessions/{$session->id}")->assertNotFound();
    }

    private function assign(Club $club, User $assigner, User $user, string $key, array $permissions): void
    {
        $role = ClubRoleDefinition::query()->create([
            'club_id' => $club->id,
            'key' => $key,
            'name' => $key,
            'permissions' => $permissions,
            'is_active' => true,
        ]);

        ClubRoleAssignment::query()->create([
            'club_id' => $club->id,
            'club_role_definition_id' => $role->id,
            'user_id' => $user->id,
            'scope_type' => 'club',
            'scope_id' => $club->id,
            'scope_key' => 'club:'.$club->id,
            'assigned_by' => $assigner->id,
        ]);
    }

    private function sessionPayload(string $title): array
    {
        return [
            'title' => $title,
            'description' => 'Beschreibung',
            'goals' => ['Ziel'],
            'phases' => [[
                'title' => 'Warmup',
                'duration_minutes' => 10,
            ]],
            'exercises' => [[
                'title' => 'Lauf-ABC',
            ]],
            'materials' => ['Hütchen'],
            'duration_minutes' => 60,
            'status' => 'draft',
        ];
    }
}
