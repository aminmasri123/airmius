<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubDepartment;
use App\Models\ClubRoleDefinition;
use App\Models\ClubSubscription;
use App\Models\Event;
use App\Models\SubscriptionPlan;
use App\Models\Team;
use App\Models\User;
use App\Support\ClubPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubRoleDefinitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_manage_club_scoped_role_definitions_from_complete_templates(): void
    {
        [$club, $owner] = $this->clubWithOwner();
        Sanctum::actingAs($owner);

        $this->getJson("/api/v1/clubs/{$club->id}/role-definitions")
            ->assertOk()
            ->assertJsonCount(11, 'data.templates')
            ->assertJsonFragment(['key' => 'treasurer', 'name' => 'Kassenwart'])
            ->assertJsonFragment(['key' => 'external_contact', 'name' => 'Externe Ansprechpartner']);

        $created = $this->postJson("/api/v1/clubs/{$club->id}/role-definitions", [
            'key' => 'veranstaltungsleitung',
            'name' => 'Veranstaltungsleitung',
            'permissions' => [ClubPermissions::EVENTS_MANAGE, ClubPermissions::FILES_MANAGE],
        ])->assertCreated()
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.permissions.0', ClubPermissions::EVENTS_MANAGE);

        $id = $created->json('data.id');
        $this->putJson("/api/v1/clubs/{$club->id}/role-definitions/{$id}", [
            'key' => 'veranstaltungsleitung',
            'name' => 'Leitung Veranstaltungen',
            'permissions' => [ClubPermissions::EVENTS_MANAGE],
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('data.name', 'Leitung Veranstaltungen')
            ->assertJsonPath('data.is_active', false);

        $this->deleteJson("/api/v1/clubs/{$club->id}/role-definitions/{$id}")
            ->assertOk()->assertJsonPath('data.deleted', true);
        $this->assertDatabaseMissing('club_role_definitions', ['id' => $id]);
        $this->assertDatabaseHas('activities', ['club_id' => $club->id, 'type' => 'club.role_definition.created']);
        $this->assertDatabaseHas('activities', ['club_id' => $club->id, 'type' => 'club.role_definition.updated']);
        $this->assertDatabaseHas('activities', ['club_id' => $club->id, 'type' => 'club.role_definition.deleted']);
    }

    public function test_keys_are_unique_per_club_and_generated_from_name(): void
    {
        [$first, $firstOwner] = $this->clubWithOwner();
        [$second, $secondOwner] = $this->clubWithOwner();
        $payload = ['name' => 'Kurs Leitung', 'permissions' => []];

        Sanctum::actingAs($firstOwner);
        $this->postJson("/api/v1/clubs/{$first->id}/role-definitions", $payload)
            ->assertCreated()->assertJsonPath('data.key', 'kurs_leitung');
        $this->postJson("/api/v1/clubs/{$first->id}/role-definitions", $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('key');

        Sanctum::actingAs($secondOwner);
        $this->postJson("/api/v1/clubs/{$second->id}/role-definitions", $payload)->assertCreated();
        $this->assertDatabaseCount('club_role_definitions', 2);
    }

    public function test_role_editor_cannot_put_permissions_they_do_not_hold_into_a_role(): void
    {
        [$club, , $editor] = $this->clubWithOwner(true);
        $club->users()->updateExistingPivot($editor->id, [
            'permission_overrides' => [ClubPermissions::MEMBERS_ROLES => true],
        ]);
        Sanctum::actingAs($editor);

        $this->postJson("/api/v1/clubs/{$club->id}/role-definitions", [
            'key' => 'finance_escalation',
            'name' => 'Finance escalation',
            'permissions' => [ClubPermissions::FINANCE_MANAGE],
        ])->assertUnprocessable()->assertJsonValidationErrors('permissions');

        $this->postJson("/api/v1/clubs/{$club->id}/role-definitions", [
            'key' => 'member_reader',
            'name' => 'Member reader',
            'permissions' => [ClubPermissions::MEMBERS_VIEW],
        ])->assertCreated();
    }

    public function test_role_definitions_do_not_cross_club_boundaries(): void
    {
        [$first, $owner] = $this->clubWithOwner();
        [$second] = $this->clubWithOwner();
        $foreign = ClubRoleDefinition::query()->create([
            'club_id' => $second->id,
            'key' => 'foreign',
            'name' => 'Foreign role',
            'permissions' => [],
            'is_active' => true,
        ]);
        Sanctum::actingAs($owner);

        $this->putJson("/api/v1/clubs/{$first->id}/role-definitions/{$foreign->id}", [
            'key' => 'changed', 'name' => 'Changed', 'permissions' => [],
        ])->assertNotFound();
        $this->deleteJson("/api/v1/clubs/{$first->id}/role-definitions/{$foreign->id}")->assertNotFound();
        $this->assertDatabaseHas('club_role_definitions', ['id' => $foreign->id, 'key' => 'foreign']);
    }

    public function test_regular_member_cannot_read_or_manage_role_definitions(): void
    {
        [$club, , $member] = $this->clubWithOwner(true);
        Sanctum::actingAs($member);

        $this->getJson("/api/v1/clubs/{$club->id}/role-definitions")->assertForbidden();
        $this->postJson("/api/v1/clubs/{$club->id}/role-definitions", [])->assertForbidden();
    }

    public function test_multiple_configured_roles_add_permissions_without_changing_legacy_roles(): void
    {
        [$club, $owner, $member] = $this->clubWithOwner(true);
        $finance = $this->role($club, 'finance_reader', [ClubPermissions::FINANCE_VIEW]);
        $events = $this->role($club, 'event_editor', [ClubPermissions::EVENTS_MANAGE]);
        Sanctum::actingAs($owner);

        $this->putJson("/api/v1/clubs/{$club->id}/members/{$member->id}/role-definitions", [
            'role_definition_ids' => [$finance->id, $events->id],
        ])->assertOk()
            ->assertJsonCount(2, 'data.roles')
            ->assertJsonFragment([ClubPermissions::FINANCE_VIEW => true])
            ->assertJsonFragment([ClubPermissions::EVENTS_MANAGE => true]);

        $membership = $club->users()->where('users.id', $member->id)->firstOrFail()->pivot;
        $this->assertSame('member', $membership->role);
        $this->assertSame(['member'], $membership->roles);
        $this->assertTrue(ClubPermissions::allows($club, $member, ClubPermissions::FINANCE_VIEW));
        $this->assertTrue(ClubPermissions::allows($club, $member, ClubPermissions::EVENTS_MANAGE));

        Sanctum::actingAs($member);
        $this->getJson("/api/v1/clubs/{$club->id}")
            ->assertOk()
            ->assertJsonPath('data.can_manage', false)
            ->assertJsonPath('data.can_manage_members', false)
            ->assertJsonPath('data.can_view_finance', true);

        $club->users()->updateExistingPivot($member->id, [
            'permission_overrides' => [ClubPermissions::FINANCE_VIEW => false],
        ]);
        $this->assertFalse(ClubPermissions::allows($club, $member, ClubPermissions::FINANCE_VIEW));
        $this->getJson("/api/v1/clubs/{$club->id}")
            ->assertOk()
            ->assertJsonPath('data.can_view_finance', false);
        $club->users()->updateExistingPivot($member->id, ['permission_overrides' => null]);
        $this->assertDatabaseCount('club_role_assignments', 2);
        $this->assertDatabaseCount('activities', 1);

        Sanctum::actingAs($owner);
        $this->putJson("/api/v1/clubs/{$club->id}/members/{$member->id}/role-definitions", [
            'role_definition_ids' => [$finance->id, $events->id],
        ])->assertOk();
        $this->assertDatabaseCount('activities', 1);
    }

    public function test_role_changes_and_deactivation_take_effect_without_rewriting_membership(): void
    {
        [$club, $owner, $member] = $this->clubWithOwner(true);
        $role = $this->role($club, 'operations', [ClubPermissions::EVENTS_MANAGE]);
        Sanctum::actingAs($owner);
        $this->putJson("/api/v1/clubs/{$club->id}/members/{$member->id}/role-definitions", [
            'role_definition_ids' => [$role->id],
        ])->assertOk();

        $this->assertTrue(ClubPermissions::allows($club, $member, ClubPermissions::EVENTS_MANAGE));
        $role->update(['permissions' => [ClubPermissions::FILES_MANAGE]]);
        $this->assertFalse(ClubPermissions::allows($club, $member, ClubPermissions::EVENTS_MANAGE));
        $this->assertTrue(ClubPermissions::allows($club, $member, ClubPermissions::FILES_MANAGE));
        $role->update(['is_active' => false]);
        $this->assertFalse(ClubPermissions::allows($club, $member, ClubPermissions::FILES_MANAGE));
    }

    public function test_assignment_rejects_foreign_inactive_and_overpowered_roles_but_allows_removal(): void
    {
        [$club, $owner, $editor] = $this->clubWithOwner(true);
        [$foreignClub] = $this->clubWithOwner();
        $powerful = $this->role($club, 'powerful', [ClubPermissions::FINANCE_MANAGE]);
        $inactive = $this->role($club, 'inactive', [], false);
        $foreign = $this->role($foreignClub, 'foreign_assignment', []);
        $club->users()->updateExistingPivot($editor->id, [
            'permission_overrides' => [ClubPermissions::MEMBERS_ROLES => true],
        ]);

        Sanctum::actingAs($owner);
        $this->putJson("/api/v1/clubs/{$club->id}/members/{$editor->id}/role-definitions", [
            'role_definition_ids' => [$powerful->id],
        ])->assertOk();
        $this->deleteJson("/api/v1/clubs/{$club->id}/role-definitions/{$powerful->id}")
            ->assertUnprocessable()->assertJsonValidationErrors('role');

        Sanctum::actingAs($editor);
        $this->putJson("/api/v1/clubs/{$club->id}/members/{$editor->id}/role-definitions", [
            'role_definition_ids' => [$powerful->id, $inactive->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('role_definition_ids');
        $this->putJson("/api/v1/clubs/{$club->id}/members/{$editor->id}/role-definitions", [
            'role_definition_ids' => [$foreign->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('role_definition_ids');
        $this->putJson("/api/v1/clubs/{$club->id}/members/{$editor->id}/role-definitions", [
            'role_definition_ids' => [],
        ])->assertOk()->assertJsonCount(0, 'data.roles');
        $this->assertFalse(ClubPermissions::allows($club, $editor, ClubPermissions::FINANCE_MANAGE));
    }

    public function test_inactive_members_cannot_receive_configured_roles(): void
    {
        [$club, $owner, $member] = $this->clubWithOwner(true);
        $role = $this->role($club, 'reader', [ClubPermissions::FINANCE_VIEW]);
        $club->users()->updateExistingPivot($member->id, ['membership_status' => 'former']);
        Sanctum::actingAs($owner);

        $this->putJson("/api/v1/clubs/{$club->id}/members/{$member->id}/role-definitions", [
            'role_definition_ids' => [$role->id],
        ])->assertUnprocessable();
        $this->assertDatabaseCount('club_role_assignments', 0);
    }

    public function test_department_and_team_roles_are_fail_closed_outside_matching_scope(): void
    {
        [$club, $owner, $member] = $this->clubWithOwner(true);
        $department = ClubDepartment::query()->create([
            'club_id' => $club->id, 'name' => 'Jugend', 'is_public' => false,
        ]);
        $otherDepartment = ClubDepartment::query()->create([
            'club_id' => $club->id, 'name' => 'Senioren', 'is_public' => false,
        ]);
        $team = Team::factory()->create(['club_id' => $club->id]);
        $otherTeam = Team::factory()->create(['club_id' => $club->id]);
        $departmentRole = $this->role($club, 'department_finance', [ClubPermissions::FINANCE_VIEW]);
        $teamRole = $this->role($club, 'team_events', [ClubPermissions::EVENTS_MANAGE]);
        Sanctum::actingAs($owner);

        $this->putJson("/api/v1/clubs/{$club->id}/members/{$member->id}/role-definitions", [
            'assignments' => [
                ['role_definition_id' => $departmentRole->id, 'scope_type' => 'department', 'scope_id' => $department->id],
                ['role_definition_id' => $teamRole->id, 'scope_type' => 'team', 'scope_id' => $team->id],
            ],
        ])->assertOk()
            ->assertJsonCount(2, 'data.assignments')
            ->assertJsonCount(0, 'data.role_definition_ids');

        $this->assertFalse(ClubPermissions::allows($club, $member, ClubPermissions::FINANCE_VIEW));
        $this->assertTrue(ClubPermissions::allowsInScope($club, $member, ClubPermissions::FINANCE_VIEW, 'department', $department->id));
        $this->assertFalse(ClubPermissions::allowsInScope($club, $member, ClubPermissions::FINANCE_VIEW, 'department', $otherDepartment->id));
        $this->assertTrue(ClubPermissions::allowsInScope($club, $member, ClubPermissions::EVENTS_MANAGE, 'team', $team->id));
        $this->assertFalse(ClubPermissions::allowsInScope($club, $member, ClubPermissions::EVENTS_MANAGE, 'team', $otherTeam->id));
    }

    public function test_scoped_assignments_reject_duplicate_and_foreign_scopes(): void
    {
        [$club, $owner, $member] = $this->clubWithOwner(true);
        [$foreignClub] = $this->clubWithOwner();
        $foreignDepartment = ClubDepartment::query()->create([
            'club_id' => $foreignClub->id, 'name' => 'Fremd', 'is_public' => false,
        ]);
        $role = $this->role($club, 'scoped_reader', [ClubPermissions::MEMBERS_VIEW]);
        Sanctum::actingAs($owner);

        $duplicate = ['role_definition_id' => $role->id, 'scope_type' => 'club', 'scope_id' => null];
        $this->putJson("/api/v1/clubs/{$club->id}/members/{$member->id}/role-definitions", [
            'assignments' => [$duplicate, $duplicate],
        ])->assertUnprocessable()->assertJsonValidationErrors('assignments');

        $this->putJson("/api/v1/clubs/{$club->id}/members/{$member->id}/role-definitions", [
            'assignments' => [[
                'role_definition_id' => $role->id,
                'scope_type' => 'department',
                'scope_id' => $foreignDepartment->id,
            ]],
        ])->assertUnprocessable()->assertJsonValidationErrors('assignments');
        $this->assertDatabaseCount('club_role_assignments', 0);
    }

    public function test_team_and_department_event_actions_are_scoped_and_separated(): void
    {
        [$club, $owner, $member] = $this->clubWithOwner(true);
        $department = ClubDepartment::query()->create([
            'club_id' => $club->id, 'name' => 'Jugend', 'is_public' => false,
        ]);
        $team = Team::factory()->create(['club_id' => $club->id, 'club_department_id' => $department->id]);
        $departmentTeam = Team::factory()->create(['club_id' => $club->id, 'club_department_id' => $department->id]);
        $otherTeam = Team::factory()->create(['club_id' => $club->id]);
        $editableEvent = $this->event($owner, $team, 'Teamtermin');
        $departmentEvent = $this->event($owner, $departmentTeam, 'Abteilungstermin');
        $otherEvent = $this->event($owner, $otherTeam, 'Fremder Termin');
        $editor = $this->role($club, 'scoped_event_editor', [ClubPermissions::EVENTS_EDIT]);
        $deleter = $this->role($club, 'scoped_event_deleter', [ClubPermissions::EVENTS_DELETE]);

        Sanctum::actingAs($owner);
        $this->putJson("/api/v1/clubs/{$club->id}/members/{$member->id}/role-definitions", [
            'assignments' => [
                ['role_definition_id' => $editor->id, 'scope_type' => 'team', 'scope_id' => $team->id],
                ['role_definition_id' => $editor->id, 'scope_type' => 'department', 'scope_id' => $department->id],
            ],
        ])->assertOk();

        Sanctum::actingAs($member);
        $this->putJson("/api/v1/events/{$editableEvent->id}", ['title' => 'Teamtermin geändert'])
            ->assertOk()->assertJsonPath('data.title', 'Teamtermin geändert');
        $this->putJson("/api/v1/events/{$departmentEvent->id}", ['title' => 'Abteilungstermin geändert'])
            ->assertOk()->assertJsonPath('data.title', 'Abteilungstermin geändert');
        $this->putJson("/api/v1/events/{$otherEvent->id}", ['title' => 'Nicht erlaubt'])
            ->assertNotFound();
        $this->deleteJson("/api/v1/events/{$editableEvent->id}")->assertForbidden();

        $this->assertDatabaseHas('events', ['id' => $editableEvent->id, 'title' => 'Teamtermin geändert']);
        $this->assertDatabaseHas('events', ['id' => $departmentEvent->id, 'title' => 'Abteilungstermin geändert']);
        $this->assertDatabaseHas('events', ['id' => $otherEvent->id, 'title' => 'Fremder Termin']);

        Sanctum::actingAs($owner);
        $this->putJson("/api/v1/clubs/{$club->id}/members/{$member->id}/role-definitions", [
            'assignments' => [[
                'role_definition_id' => $deleter->id, 'scope_type' => 'team', 'scope_id' => $team->id,
            ]],
        ])->assertOk();

        $this->assertTrue(ClubPermissions::allowsForTeam($team->fresh(), $member, ClubPermissions::EVENTS_DELETE));
        Sanctum::actingAs($member);
        $this->putJson("/api/v1/events/{$editableEvent->id}", ['title' => 'Nicht mehr editierbar'])
            ->assertForbidden();
        $this->deleteJson("/api/v1/events/{$editableEvent->id}")->assertOk();
        $this->assertDatabaseMissing('events', ['id' => $editableEvent->id]);
        $this->deleteJson("/api/v1/events/{$departmentEvent->id}")->assertNotFound();
    }

    public function test_team_content_editor_can_publish_posts_and_stories_only_in_assigned_scope(): void
    {
        Storage::fake('public');
        config(['filesystems.uploads_disk' => 'public']);

        [$club, $owner, $member] = $this->clubWithOwner(true);
        $club->update([
            'members_can_post_to_club' => false,
            'members_can_post_to_teams' => false,
        ]);
        $team = Team::factory()->create(['club_id' => $club->id]);
        $otherTeam = Team::factory()->create(['club_id' => $club->id]);
        $editor = $this->role($club, 'team_content_editor', [ClubPermissions::CONTENT_MANAGE]);

        Sanctum::actingAs($owner);
        $this->putJson("/api/v1/clubs/{$club->id}/members/{$member->id}/role-definitions", [
            'assignments' => [[
                'role_definition_id' => $editor->id,
                'scope_type' => 'team',
                'scope_id' => $team->id,
            ]],
        ])->assertOk();

        Sanctum::actingAs($member);
        $this->postJson('/api/v1/feed', [
            'content' => 'Bereichsbezogener Mannschaftsbeitrag',
            'visibility' => 'team',
            'post_type' => 'normal',
            'team_id' => $team->id,
        ])->assertCreated();
        $this->postJson('/api/v1/feed', [
            'content' => 'Beitrag außerhalb des Bereichs',
            'visibility' => 'team',
            'post_type' => 'normal',
            'team_id' => $otherTeam->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('team_id');

        $this->postJson('/api/v1/stories', [
            'team_id' => $team->id,
            'publisher_type' => 'team',
            'visibility' => 'team',
            'caption' => 'Bereichsbezogene Mannschaftsstory',
            'media' => UploadedFile::fake()->image('team-story.jpg'),
        ])->assertCreated();
        $this->postJson('/api/v1/stories', [
            'team_id' => $otherTeam->id,
            'publisher_type' => 'team',
            'visibility' => 'team',
            'caption' => 'Story außerhalb des Bereichs',
            'media' => UploadedFile::fake()->image('other-team-story.jpg'),
        ])->assertUnprocessable()->assertJsonValidationErrors('team_id');

        $this->actingAs($member)
            ->post(route('auth.posts.store'), [
                'content' => 'Webbeitrag im Bereich',
                'visibility' => 'team',
                'post_type' => 'normal',
                'content_origin' => 'self',
                'team_id' => $team->id,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->post(route('auth.stories.store'), [
            'team_id' => $team->id,
            'publisher_type' => 'team',
            'visibility' => 'team',
            'caption' => 'Webstory im Bereich',
            'media' => UploadedFile::fake()->image('web-team-story.jpg'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('posts', [
            'team_id' => $team->id,
            'content' => 'Bereichsbezogener Mannschaftsbeitrag',
        ]);
        $this->assertDatabaseMissing('posts', [
            'team_id' => $otherTeam->id,
            'content' => 'Beitrag außerhalb des Bereichs',
        ]);
        $this->assertDatabaseHas('stories', [
            'team_id' => $team->id,
            'caption' => 'Bereichsbezogene Mannschaftsstory',
        ]);
        $this->assertDatabaseMissing('stories', [
            'team_id' => $otherTeam->id,
            'caption' => 'Story außerhalb des Bereichs',
        ]);
    }

    public function test_team_metadata_and_deletion_are_scoped_and_separated(): void
    {
        [$club, $owner, $member] = $this->clubWithOwner(true);
        $unlimitedPlan = SubscriptionPlan::query()->whereNull('team_limit')->firstOrFail();
        ClubSubscription::query()->updateOrCreate(
            ['club_id' => $club->id],
            ['subscription_plan_id' => $unlimitedPlan->id, 'status' => 'active'],
        );
        $department = ClubDepartment::query()->create([
            'club_id' => $club->id, 'name' => 'Jugend', 'is_public' => false,
        ]);
        $otherDepartment = ClubDepartment::query()->create([
            'club_id' => $club->id, 'name' => 'Senioren', 'is_public' => false,
        ]);
        $team = Team::factory()->create([
            'club_id' => $club->id, 'club_department_id' => $department->id, 'name' => 'Jugendteam',
        ]);
        $otherTeam = Team::factory()->create([
            'club_id' => $club->id, 'club_department_id' => $otherDepartment->id, 'name' => 'Seniorenteam',
        ]);
        $editor = $this->role($club, 'department_team_editor', [ClubPermissions::TEAMS_EDIT]);
        $deleter = $this->role($club, 'team_deleter', [ClubPermissions::TEAMS_DELETE]);

        Sanctum::actingAs($owner);
        $this->putJson("/api/v1/clubs/{$club->id}/members/{$member->id}/role-definitions", [
            'assignments' => [[
                'role_definition_id' => $editor->id,
                'scope_type' => 'department',
                'scope_id' => $department->id,
            ]],
        ])->assertOk();

        Sanctum::actingAs($member);
        $teamStoreHeaders = [
            'Authorization' => 'Bearer '.$member->createToken('department-team-create')->plainTextToken,
        ];
        $this->getJson("/api/v1/clubs/{$club->id}")
            ->assertOk()
            ->assertJsonPath('data.can_edit_teams', true)
            ->assertJsonPath('data.can_create_teams_globally', false)
            ->assertJsonPath('data.team_creation_departments.0.id', $department->id)
            ->assertJsonPath('data.viewer.can_edit_teams', true)
            ->assertJsonPath('data.viewer.can_create_teams_globally', false)
            ->assertJsonPath('data.viewer.team_creation_departments.0.id', $department->id);
        $this->actingAs($member)
            ->get(route('auth.teams.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('clubs.0.can_edit_teams', true)
                ->where('clubs.0.can_create_teams_globally', false)
                ->where('clubs.0.team_creation_departments.0.id', $department->id)
                ->where('clubs.0.team_creation_departments.0.name', 'Jugend')
                ->etc());
        $this->postJson('/api/v1/teams', [
            'club_id' => $club->id,
            'club_department_id' => $department->id,
            'name' => 'Jugendteam Zwei',
            'sport_type' => 'football',
        ], $teamStoreHeaders)->assertCreated()->assertJsonPath('data.club_department_id', $department->id);
        $this->postJson('/api/v1/teams', [
            'club_id' => $club->id,
            'name' => 'Vereinsweites Team',
        ], $teamStoreHeaders)->assertForbidden();
        $this->postJson('/api/v1/teams', [
            'club_id' => $club->id,
            'club_department_id' => $otherDepartment->id,
            'name' => 'Fremde Neuanlage',
        ], $teamStoreHeaders)->assertForbidden();
        $this->postJson('/api/v1/teams', [
            'club_id' => $club->id,
            'club_department_id' => $department->id,
            'name' => $otherTeam->name,
        ], $teamStoreHeaders)->assertForbidden();
        $this->actingAs($member)
            ->post(route('auth.teams.store'), [
                'club_id' => $club->id,
                'club_department_id' => $department->id,
                'name' => 'Jugendteam Web',
                'sport_type' => 'football',
            ])
            ->assertRedirect();
        $this->assertDatabaseHas('teams', [
            'club_id' => $club->id,
            'club_department_id' => $department->id,
            'name' => 'Jugendteam Web',
        ]);
        $this->assertDatabaseHas('teams', [
            'id' => $otherTeam->id,
            'club_department_id' => $otherDepartment->id,
            'name' => 'Seniorenteam',
        ]);

        Sanctum::actingAs($member);
        $this->putJson("/api/v1/teams/{$team->id}", [
            'name' => 'Jugendteam Neu', 'sport_type' => $team->sport_type,
        ])->assertOk()->assertJsonPath('data.name', 'Jugendteam Neu')
            ->assertJsonPath('data.can_manage', true)
            ->assertJsonPath('data.can_delete', false);
        $this->putJson("/api/v1/teams/{$team->id}", [
            'name' => 'Verschoben', 'sport_type' => $team->sport_type,
            'club_department_id' => $otherDepartment->id,
        ])->assertForbidden();
        $this->putJson("/api/v1/teams/{$otherTeam->id}", [
            'name' => 'Nicht erlaubt', 'sport_type' => $otherTeam->sport_type,
        ])->assertForbidden();
        $this->deleteJson("/api/v1/teams/{$team->id}")->assertForbidden();

        Sanctum::actingAs($owner);
        $this->putJson("/api/v1/clubs/{$club->id}/members/{$member->id}/role-definitions", [
            'assignments' => [[
                'role_definition_id' => $deleter->id,
                'scope_type' => 'team',
                'scope_id' => $team->id,
            ]],
        ])->assertOk();

        Sanctum::actingAs($member);
        $this->putJson("/api/v1/teams/{$team->id}", [
            'name' => 'Nicht editierbar', 'sport_type' => $team->sport_type,
        ])->assertForbidden();
        $this->deleteJson("/api/v1/teams/{$team->id}")->assertOk();
        $this->assertDatabaseMissing('teams', ['id' => $team->id]);
        $this->assertDatabaseHas('teams', ['id' => $otherTeam->id, 'name' => 'Seniorenteam']);
    }

    private function clubWithOwner(bool $withMember = false): array
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $member = null;
        if ($withMember) {
            $member = User::factory()->create();
            $club->users()->attach($member->id, [
                'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
            ]);
        }

        return [$club, $owner, $member];
    }

    private function role(Club $club, string $key, array $permissions, bool $active = true): ClubRoleDefinition
    {
        return ClubRoleDefinition::query()->create([
            'club_id' => $club->id,
            'key' => $key,
            'name' => str_replace('_', ' ', ucfirst($key)),
            'permissions' => $permissions,
            'is_active' => $active,
        ]);
    }

    private function event(User $owner, Team $team, string $title): Event
    {
        return Event::query()->create([
            'club_id' => $team->club_id,
            'team_id' => $team->id,
            'user_id' => $owner->id,
            'title' => $title,
            'type' => 'training',
            'visibility' => 'private',
            'status' => 'scheduled',
            'start_time' => now()->addDay(),
        ]);
    }
}
