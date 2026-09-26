<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubDepartment;
use App\Models\ClubPermissionDelegation;
use App\Models\ClubRoleAssignment;
use App\Models\ClubRoleDefinition;
use App\Models\ClubSubscription;
use App\Models\File;
use App\Models\SubscriptionPlan;
use App\Models\Team;
use App\Models\TrainingExercise;
use App\Models\TrainingPlan;
use App\Models\User;
use App\Support\ClubPermissions;
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

    public function test_club_exercise_view_edit_and_delete_rights_are_independent(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $editor = User::factory()->create();
        $deleter = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $starter = SubscriptionPlan::query()->where('slug', 'starter')->firstOrFail();
        ClubSubscription::query()->updateOrCreate(
            ['club_id' => $club->id],
            ['subscription_plan_id' => $starter->id, 'status' => 'active'],
        );
        foreach ([$viewer, $editor, $deleter] as $member) {
            $club->users()->attach($member->id, [
                'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
            ]);
        }
        $this->assign($club, $owner, $viewer, 'exercise_viewer', [ClubPermissions::TRAINING_EXERCISES_VIEW]);
        $this->assign($club, $owner, $editor, 'exercise_editor', [ClubPermissions::TRAINING_EXERCISES_EDIT]);
        $this->assign($club, $owner, $deleter, 'exercise_deleter', [ClubPermissions::TRAINING_EXERCISES_DELETE]);

        $exercise = TrainingExercise::query()->create([
            'created_by' => $owner->id,
            'club_id' => $club->id,
            'name' => 'Passstaffel',
            'difficulty' => 'beginner',
            'is_active' => true,
        ]);
        $endpoint = "/api/v1/training/exercises/{$exercise->id}";

        Sanctum::actingAs($viewer);
        $this->getJson('/api/v1/training/exercises')
            ->assertOk()
            ->assertJsonPath('data.0.id', $exercise->id)
            ->assertJsonPath('data.0.can_edit', false)
            ->assertJsonPath('data.0.can_delete', false);
        $this->putJson($endpoint, $this->exercisePayload('Nicht erlaubt'))->assertForbidden();
        $this->deleteJson($endpoint)->assertForbidden();

        Sanctum::actingAs($editor);
        $this->getJson('/api/v1/clubs?mine=1')
            ->assertOk()
            ->assertJsonPath('data.0.can_create_training_exercises', true)
            ->assertJsonPath('data.0.can_delete_training_exercises', false);
        $this->putJson($endpoint, $this->exercisePayload('Passstaffel Plus'))
            ->assertOk()
            ->assertJsonPath('data.can_edit', true)
            ->assertJsonPath('data.can_delete', false);
        $this->deleteJson($endpoint)->assertForbidden();

        Sanctum::actingAs($deleter);
        $this->getJson('/api/v1/training/exercises')
            ->assertOk()
            ->assertJsonPath('data.0.can_edit', false)
            ->assertJsonPath('data.0.can_delete', true);
        $this->putJson($endpoint, $this->exercisePayload('Nicht erlaubt'))->assertForbidden();
        $this->deleteJson($endpoint)->assertOk();
        $this->assertDatabaseHas('training_exercises', ['id' => $exercise->id, 'is_active' => false]);
    }

    public function test_scoped_exercise_editor_cannot_change_another_team_and_explicit_manager_denial_wins(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $manager = User::factory()->create();
        $deniedCoach = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $starter = SubscriptionPlan::query()->where('slug', 'starter')->firstOrFail();
        ClubSubscription::query()->updateOrCreate(
            ['club_id' => $club->id],
            ['subscription_plan_id' => $starter->id, 'status' => 'active'],
        );
        $firstTeam = Team::factory()->create(['club_id' => $club->id]);
        $secondTeam = Team::factory()->create(['club_id' => $club->id]);
        $club->users()->attach($editor->id, [
            'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
        ]);
        $club->users()->attach($manager->id, [
            'role' => 'manager', 'roles' => ['manager'], 'membership_status' => 'active',
            'permission_overrides' => [ClubPermissions::TRAINING_EXERCISES_DELETE => false],
        ]);
        $club->users()->attach($deniedCoach->id, [
            'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
            'permission_overrides' => [
                ClubPermissions::TRAINING_EXERCISES_VIEW => false,
                ClubPermissions::TRAINING_EXERCISES_EDIT => false,
                ClubPermissions::TRAINING_EXERCISES_DELETE => false,
            ],
        ]);
        $firstTeam->users()->attach($deniedCoach->id, ['role' => TeamRoles::COACH]);
        $role = ClubRoleDefinition::query()->create([
            'club_id' => $club->id,
            'key' => 'team_exercise_editor',
            'name' => 'Teamübungen bearbeiten',
            'permissions' => [ClubPermissions::TRAINING_EXERCISES_EDIT],
            'is_active' => true,
        ]);
        ClubRoleAssignment::query()->create([
            'club_id' => $club->id,
            'club_role_definition_id' => $role->id,
            'user_id' => $editor->id,
            'scope_type' => 'team',
            'scope_id' => $firstTeam->id,
            'scope_key' => 'team:'.$firstTeam->id,
            'assigned_by' => $owner->id,
        ]);
        $first = $this->teamExercise($owner, $firstTeam, 'Erste Übung');
        $second = $this->teamExercise($owner, $secondTeam, 'Zweite Übung');

        Sanctum::actingAs($editor);
        $this->putJson("/api/v1/training/exercises/{$first->id}", $this->exercisePayload('Erste geändert'))
            ->assertOk();
        $this->putJson("/api/v1/training/exercises/{$second->id}", $this->exercisePayload('Zweite geändert'))
            ->assertForbidden();
        $this->deleteJson("/api/v1/training/exercises/{$first->id}")->assertForbidden();

        Sanctum::actingAs($manager);
        $this->putJson("/api/v1/training/exercises/{$second->id}", $this->exercisePayload('Manager geändert'))
            ->assertOk();
        $this->deleteJson("/api/v1/training/exercises/{$second->id}")->assertForbidden();

        Sanctum::actingAs($deniedCoach);
        $this->getJson('/api/v1/training/exercises')
            ->assertOk()
            ->assertJsonMissing(['id' => $first->id]);
        $this->getJson("/api/v1/teams/{$firstTeam->id}")
            ->assertOk()
            ->assertJsonPath('data.can_create_training_exercises', false);
        $this->getJson("/api/v1/training/exercises/{$first->id}")->assertNotFound();
        $this->postJson('/api/v1/training/exercises', [
            'scope' => 'team',
            'team_id' => $firstTeam->id,
            ...$this->exercisePayload('Gesperrte neue Übung'),
        ])->assertForbidden();
        $this->putJson("/api/v1/training/exercises/{$first->id}", $this->exercisePayload('Gesperrte Änderung'))
            ->assertForbidden();
        $this->deleteJson("/api/v1/training/exercises/{$first->id}")->assertForbidden();
    }

    public function test_department_roles_and_delegations_only_manage_exercises_in_their_department(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $delegate = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $starter = SubscriptionPlan::query()->where('slug', 'starter')->firstOrFail();
        ClubSubscription::query()->updateOrCreate(
            ['club_id' => $club->id],
            ['subscription_plan_id' => $starter->id, 'status' => 'active'],
        );
        foreach ([$editor, $delegate] as $member) {
            $club->users()->attach($member->id, [
                'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
            ]);
        }

        $firstDepartment = ClubDepartment::query()->create([
            'club_id' => $club->id,
            'name' => 'Leistungssport',
        ]);
        $secondDepartment = ClubDepartment::query()->create([
            'club_id' => $club->id,
            'name' => 'Breitensport',
        ]);
        $firstTeam = Team::factory()->create([
            'club_id' => $club->id,
            'club_department_id' => $firstDepartment->id,
        ]);
        $secondTeam = Team::factory()->create([
            'club_id' => $club->id,
            'club_department_id' => $secondDepartment->id,
        ]);
        $role = ClubRoleDefinition::query()->create([
            'club_id' => $club->id,
            'key' => 'department_exercise_editor',
            'name' => 'Abteilungsübungen bearbeiten',
            'permissions' => [ClubPermissions::TRAINING_EXERCISES_EDIT],
            'is_active' => true,
        ]);
        ClubRoleAssignment::query()->create([
            'club_id' => $club->id,
            'club_role_definition_id' => $role->id,
            'user_id' => $editor->id,
            'scope_type' => 'department',
            'scope_id' => $firstDepartment->id,
            'scope_key' => 'department:'.$firstDepartment->id,
            'assigned_by' => $owner->id,
        ]);
        ClubPermissionDelegation::query()->create([
            'club_id' => $club->id,
            'grantor_user_id' => $owner->id,
            'grantee_user_id' => $delegate->id,
            'permissions' => [ClubPermissions::TRAINING_EXERCISES_DELETE],
            'scope_type' => 'department',
            'scope_id' => $firstDepartment->id,
            'scope_key' => 'department:'.$firstDepartment->id,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
        ]);
        $first = $this->teamExercise($owner, $firstTeam, 'Übung Leistungssport');
        $second = $this->teamExercise($owner, $secondTeam, 'Übung Breitensport');

        Sanctum::actingAs($editor);
        $this->postJson('/api/v1/training/exercises', [
            'scope' => 'team',
            'team_id' => $firstTeam->id,
            ...$this->exercisePayload('Neue Abteilungsübung'),
        ])->assertCreated();
        $this->postJson('/api/v1/training/exercises', [
            'scope' => 'team',
            'team_id' => $secondTeam->id,
            ...$this->exercisePayload('Fremde Abteilungsübung'),
        ])->assertForbidden();
        $this->putJson("/api/v1/training/exercises/{$first->id}", $this->exercisePayload('Eigene Abteilung geändert'))
            ->assertOk();
        $this->putJson("/api/v1/training/exercises/{$second->id}", $this->exercisePayload('Fremde Abteilung geändert'))
            ->assertForbidden();

        Sanctum::actingAs($delegate);
        $this->getJson('/api/v1/training/exercises')
            ->assertOk()
            ->assertJsonFragment(['id' => $first->id, 'can_delete' => true])
            ->assertJsonFragment(['id' => $second->id, 'can_delete' => false]);
        $this->deleteJson("/api/v1/training/exercises/{$second->id}")->assertForbidden();
        $this->deleteJson("/api/v1/training/exercises/{$first->id}")->assertOk();
    }

    public function test_exercise_filters_and_protected_media_contract_are_scoped_to_visible_exercises(): void
    {
        $coach = User::factory()->create();
        $otherCoach = User::factory()->create();
        $file = File::query()->create([
            'user_id' => $coach->id,
            'display_name' => 'technik-video.mp4',
            'path' => 'training/private/technik-video.mp4',
            'type' => 'video/mp4',
            'size' => 4096,
        ]);

        Sanctum::actingAs($coach);
        $created = $this->postJson('/api/v1/training/exercises', [
            'scope' => 'personal',
            'name' => 'U16 Sprinttechnik',
            'sport_type' => 'athletics',
            'target_age_group' => 'u16',
            'target_level' => 'intermediate',
            'focus_areas' => ['sprint', 'koordination', 'sprint'],
            'protected_media' => [
                ['file_id' => $file->id, 'kind' => 'video', 'caption' => 'Startphase'],
            ],
            'difficulty' => 'intermediate',
        ])->assertCreated()
            ->assertJsonPath('data.target_age_group', 'u16')
            ->assertJsonPath('data.target_level', 'intermediate')
            ->assertJsonPath('data.focus_areas', ['sprint', 'koordination'])
            ->assertJsonPath('data.protected_media.0.file_id', $file->id)
            ->assertJsonPath('data.protected_media.0.protected', true)
            ->assertJsonMissingPath('data.protected_media.0.url')
            ->assertJsonMissingPath('data.protected_media.0.path');

        $this->getJson('/api/v1/training/exercises?sport_type=athletics&age_group=u16&level=intermediate&focus=sprint')
            ->assertOk()
            ->assertJsonPath('data.0.id', $created->json('data.id'))
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/v1/training/exercises?sport_type=football&age_group=u16&level=intermediate&focus=sprint')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        Sanctum::actingAs($otherCoach);
        $this->getJson('/api/v1/training/exercises?sport_type=athletics&age_group=u16&level=intermediate&focus=sprint')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    private function exercisePayload(string $name): array
    {
        return ['name' => $name, 'difficulty' => 'intermediate'];
    }

    private function teamExercise(User $owner, Team $team, string $name): TrainingExercise
    {
        return TrainingExercise::query()->create([
            'created_by' => $owner->id,
            'club_id' => $team->club_id,
            'team_id' => $team->id,
            'name' => $name,
            'difficulty' => 'beginner',
            'is_active' => true,
        ]);
    }

    private function assign(Club $club, User $owner, User $user, string $key, array $permissions): void
    {
        $role = ClubRoleDefinition::query()->create([
            'club_id' => $club->id,
            'key' => $key,
            'name' => str_replace('_', ' ', ucfirst($key)),
            'permissions' => $permissions,
            'is_active' => true,
        ]);
        ClubRoleAssignment::query()->create([
            'club_id' => $club->id,
            'club_role_definition_id' => $role->id,
            'user_id' => $user->id,
            'scope_type' => 'club',
            'scope_key' => 'club',
            'assigned_by' => $owner->id,
        ]);
    }
}
