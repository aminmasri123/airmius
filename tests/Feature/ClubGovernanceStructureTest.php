<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Club;
use App\Models\ClubExternalMember;
use App\Models\ClubGovernanceAssignment;
use App\Models\ClubGovernanceBody;
use App\Models\ClubGovernancePosition;
use App\Models\ClubRoleAssignment;
use App\Models\ClubRoleDefinition;
use App\Models\User;
use App\Support\ClubPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubGovernanceStructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_template_composition_keeps_vacancies_and_links_internal_and_external_people(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['name' => 'Mara Mitglied']);
        $club = Club::factory()->create(['owner_id' => $owner->id, 'is_listed' => true]);
        $club->users()->attach($member->id, ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active']);
        $external = ClubExternalMember::query()->create([
            'club_id' => $club->id, 'created_by' => $owner->id, 'name' => 'Erik Extern',
            'email' => 'erik@example.test', 'role' => 'member', 'membership_status' => 'active',
        ]);
        Sanctum::actingAs($owner);

        $response = $this->postJson("/api/v1/clubs/{$club->id}/governance/bodies", [
            'template_key' => 'board',
            'type' => 'board',
            'name' => 'Vorstand',
            'description' => 'Leitung',
            'starts_on' => '2026-01-01',
            'ends_on' => null,
            'is_public' => true,
            'replace_positions' => true,
            'positions' => [
                [
                    'title' => 'Vorsitz', 'responsibilities' => 'Vertretung', 'sort_order' => 0,
                    'is_required' => true, 'allows_multiple' => false, 'is_public' => true,
                    'replace_assignments' => true,
                    'assignments' => [[
                        'user_id' => $member->id, 'club_external_member_id' => null,
                        'starts_on' => '2026-01-01', 'ends_on' => null, 'is_public' => true,
                    ]],
                ],
                [
                    'title' => 'Kassenwart', 'responsibilities' => null, 'sort_order' => 1,
                    'is_required' => true, 'allows_multiple' => false, 'is_public' => false,
                    'replace_assignments' => true,
                    'assignments' => [],
                ],
                [
                    'title' => 'Beisitz', 'responsibilities' => null, 'sort_order' => 2,
                    'is_required' => false, 'allows_multiple' => true, 'is_public' => false,
                    'replace_assignments' => true,
                    'assignments' => [[
                        'user_id' => null, 'club_external_member_id' => $external->id,
                        'starts_on' => null, 'ends_on' => null, 'is_public' => false,
                    ]],
                ],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.template_key', 'board')
            ->assertJsonCount(3, 'data.positions')
            ->assertJsonCount(0, 'data.positions.1.assignments')
            ->assertJsonPath('data.positions.0.assignments.0.person.name', 'Mara Mitglied')
            ->assertJsonPath('data.positions.2.assignments.0.person.name', 'Erik Extern');

        $bodyId = $response->json('data.id');
        $position = ClubGovernancePosition::query()
            ->where('club_governance_body_id', $bodyId)
            ->where('title', 'Vorsitz')
            ->firstOrFail();
        $this->assertDatabaseHas('club_governance_assignments', [
            'club_governance_position_id' => $position->id,
            'user_id' => $member->id,
        ]);

        $this->getJson("/api/v1/clubs/{$club->id}/governance")
            ->assertOk()
            ->assertJsonPath('data.templates.0.key', 'board')
            ->assertJsonCount(8, 'data.templates');

        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/v1/clubs/{$club->id}/governance")
            ->assertOk()
            ->assertJsonCount(1, 'data.bodies.0.positions')
            ->assertJsonPath('data.bodies.0.positions.0.title', 'Vorsitz')
            ->assertJsonMissingPath('data.bodies.0.positions.0.assignments.0.user_id')
            ->assertJsonCount(0, 'data.templates');
    }

    public function test_owner_manages_board_and_internal_or_external_responsibilities(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['name' => 'Alex Vorstand']);
        $club = Club::factory()->create(['owner_id' => $owner->id, 'is_listed' => true]);
        $club->users()->attach($member->id, ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active']);
        $external = ClubExternalMember::query()->create([
            'club_id' => $club->id, 'created_by' => $owner->id, 'name' => 'Robin Extern',
            'email' => 'robin@example.test', 'role' => 'member', 'membership_status' => 'active',
        ]);
        Sanctum::actingAs($owner);

        $body = $this->postJson("/api/v1/clubs/{$club->id}/governance/bodies", [
            'type' => 'board', 'name' => ' Vorstand ', 'description' => 'Leitung',
            'starts_on' => '2026-01-01', 'ends_on' => '2027-12-31', 'is_public' => true,
        ])->assertCreated()->assertJsonPath('data.name', 'Vorstand')->json('data');

        $this->postJson("/api/v1/clubs/{$club->id}/governance/bodies/{$body['id']}/assignments", [
            'user_id' => $member->id, 'position_title' => ' Vorsitz ',
            'responsibilities' => 'Strategie und Vertretung', 'starts_on' => '2026-01-01',
            'ends_on' => '2027-12-31', 'is_public' => true,
        ])->assertCreated()->assertJsonPath('data.person.name', 'Alex Vorstand');

        $this->postJson("/api/v1/clubs/{$club->id}/governance/bodies/{$body['id']}/assignments", [
            'club_external_member_id' => $external->id, 'position_title' => 'Beisitz',
            'responsibilities' => null, 'is_public' => false,
        ])->assertCreated()->assertJsonPath('data.person.name', 'Robin Extern');

        $this->getJson("/api/v1/clubs/{$club->id}/governance")
            ->assertOk()->assertJsonCount(1, 'data.bodies')->assertJsonCount(2, 'data.bodies.0.assignments')
            ->assertJsonCount(3, 'data.member_options')->assertJsonPath('data.can_manage', true)
            ->assertJsonPath('data.can_edit', true)->assertJsonPath('data.can_delete', true);

        $activity = Activity::query()->where('type', 'club.governance.assignment.created')->latest('id')->firstOrFail();
        $this->assertSame(['entity_type' => 'assignment'], $activity->data);
        $this->assertStringNotContainsString('Strategie', json_encode($activity->data));
    }

    public function test_public_and_member_views_respect_body_and_assignment_visibility(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['name' => 'Internes Mitglied']);
        $outsider = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id, 'is_listed' => true]);
        $club->users()->attach($member->id, ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active']);
        $publicBody = ClubGovernanceBody::query()->create(['club_id' => $club->id, 'type' => 'board', 'name' => 'Vorstand', 'is_public' => true]);
        $privateBody = ClubGovernanceBody::query()->create(['club_id' => $club->id, 'type' => 'working_group', 'name' => 'Krisenteam', 'is_public' => false]);
        ClubGovernanceAssignment::query()->create([
            'club_id' => $club->id, 'club_governance_body_id' => $publicBody->id, 'user_id' => $member->id,
            'position_title' => 'Vorsitz', 'responsibilities' => 'Öffentliche Vertretung', 'is_public' => true,
        ]);
        ClubGovernanceAssignment::query()->create([
            'club_id' => $club->id, 'club_governance_body_id' => $publicBody->id, 'user_id' => $member->id,
            'position_title' => 'Interne Koordination', 'responsibilities' => 'Vertraulich', 'is_public' => false,
        ]);
        ClubGovernanceAssignment::query()->create([
            'club_id' => $club->id, 'club_governance_body_id' => $privateBody->id, 'user_id' => $member->id,
            'position_title' => 'Leitung', 'is_public' => false,
        ]);
        $privatePosition = ClubGovernancePosition::query()->create([
            'club_id' => $club->id, 'club_governance_body_id' => $publicBody->id,
            'title' => 'Interne Prüfung', 'is_public' => false, 'is_active' => true,
        ]);
        ClubGovernanceAssignment::query()->create([
            'club_id' => $club->id, 'club_governance_body_id' => $publicBody->id,
            'club_governance_position_id' => $privatePosition->id, 'user_id' => $member->id,
            'position_title' => 'Interne Prüfung', 'is_public' => true,
        ]);

        Sanctum::actingAs($outsider);
        $this->getJson("/api/v1/clubs/{$club->id}/governance")
            ->assertOk()->assertJsonCount(1, 'data.bodies')->assertJsonCount(1, 'data.bodies.0.assignments')
            ->assertJsonPath('data.bodies.0.assignments.0.position_title', 'Vorsitz')
            ->assertJsonMissingPath('data.bodies.0.assignments.0.user_id')
            ->assertJsonCount(0, 'data.member_options');

        Sanctum::actingAs($member);
        $this->getJson("/api/v1/clubs/{$club->id}/governance")
            ->assertOk()->assertJsonCount(2, 'data.bodies')->assertJsonCount(3, 'data.bodies.0.assignments')
            ->assertJsonPath('data.can_manage', false)->assertJsonPath('data.can_edit', false)
            ->assertJsonPath('data.can_delete', false)->assertJsonCount(0, 'data.member_options');

        $club->users()->updateExistingPivot($member->id, ['membership_status' => 'former']);
        $this->getJson("/api/v1/clubs/{$club->id}/governance")
            ->assertOk()->assertJsonCount(1, 'data.bodies')->assertJsonCount(1, 'data.bodies.0.assignments');
    }

    public function test_cross_club_people_and_nested_resources_are_rejected(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $foreignMember = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $foreign = Club::factory()->create(['owner_id' => User::factory()]);
        $club->users()->attach($member->id, ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active']);
        $foreign->users()->attach($foreignMember->id, ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active']);
        $foreignExternal = ClubExternalMember::query()->create([
            'club_id' => $foreign->id, 'created_by' => $foreign->owner_id, 'name' => 'Extern Fremdverein',
            'email' => 'extern-fremdverein@example.test', 'role' => 'member', 'membership_status' => 'active',
        ]);
        $body = ClubGovernanceBody::query()->create(['club_id' => $club->id, 'type' => 'committee', 'name' => 'Sportausschuss', 'is_public' => false]);
        $foreignBody = ClubGovernanceBody::query()->create(['club_id' => $foreign->id, 'type' => 'board', 'name' => 'Fremd', 'is_public' => false]);
        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/clubs/{$club->id}/governance/bodies/{$body->id}/assignments", [
            'user_id' => $foreignMember->id, 'position_title' => 'Leitung', 'is_public' => false,
        ])->assertUnprocessable()->assertJsonValidationErrors('user_id');

        $this->postJson("/api/v1/clubs/{$club->id}/governance/bodies/{$body->id}/assignments", [
            'club_external_member_id' => $foreignExternal->id, 'position_title' => 'Beratung', 'is_public' => false,
        ])->assertUnprocessable()->assertJsonValidationErrors('club_external_member_id');

        $this->postJson("/api/v1/clubs/{$club->id}/governance/bodies", [
            'type' => 'committee', 'name' => 'Manipulierte Besetzung', 'is_public' => false,
            'positions' => [[
                'title' => 'Leitung', 'sort_order' => 0, 'is_required' => true,
                'allows_multiple' => false, 'is_public' => false,
                'assignments' => [[
                    'user_id' => $foreignMember->id, 'club_external_member_id' => null,
                    'starts_on' => null, 'ends_on' => null, 'is_public' => false,
                ]],
            ]],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('positions.0.assignments.0.user_id');
        $this->assertDatabaseMissing('club_governance_bodies', ['name' => 'Manipulierte Besetzung']);

        $this->putJson("/api/v1/clubs/{$club->id}/governance/bodies/{$foreignBody->id}", [
            'type' => 'board', 'name' => 'Manipuliert', 'is_public' => false,
        ])->assertNotFound();

        Sanctum::actingAs($member);
        $this->postJson("/api/v1/clubs/{$club->id}/governance/bodies", [
            'type' => 'committee', 'name' => 'Nicht erlaubt', 'is_public' => false,
        ])->assertForbidden();
    }

    public function test_person_picker_only_returns_active_people_from_the_current_club(): void
    {
        $owner = User::factory()->create(['name' => 'Vereinsleitung']);
        $activeMember = User::factory()->create(['name' => 'Aktives Mitglied']);
        $formerMember = User::factory()->create(['name' => 'Ehemaliges Mitglied']);
        $unrelatedUser = User::factory()->create(['name' => 'Fremder Datenbanknutzer']);
        $foreignMember = User::factory()->create(['name' => 'Mitglied Fremdverein']);
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $foreignClub = Club::factory()->create(['owner_id' => User::factory()]);
        $club->users()->attach($activeMember->id, [
            'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
        ]);
        $club->users()->attach($formerMember->id, [
            'role' => 'member', 'roles' => ['member'], 'membership_status' => 'former',
        ]);
        $foreignClub->users()->attach($foreignMember->id, [
            'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
        ]);
        $clubExternal = ClubExternalMember::query()->create([
            'club_id' => $club->id, 'created_by' => $owner->id, 'name' => 'Extern Verein A',
            'email' => 'extern-a@example.test', 'role' => 'member', 'membership_status' => 'active',
        ]);
        ClubExternalMember::query()->create([
            'club_id' => $club->id, 'created_by' => $owner->id, 'name' => 'Extern Ehemalig',
            'email' => 'extern-former@example.test', 'role' => 'member', 'membership_status' => 'former',
        ]);
        ClubExternalMember::query()->create([
            'club_id' => $foreignClub->id, 'created_by' => $foreignClub->owner_id, 'name' => 'Extern Verein B',
            'email' => 'extern-b@example.test', 'role' => 'member', 'membership_status' => 'active',
        ]);
        Sanctum::actingAs($owner);

        $response = $this->getJson("/api/v1/clubs/{$club->id}/governance")
            ->assertOk()
            ->assertJsonFragment(['kind' => 'user', 'id' => $activeMember->id, 'name' => 'Aktives Mitglied'])
            ->assertJsonFragment(['kind' => 'external', 'id' => $clubExternal->id, 'name' => 'Extern Verein A'])
            ->assertJsonMissing(['name' => $formerMember->name])
            ->assertJsonMissing(['name' => $unrelatedUser->name])
            ->assertJsonMissing(['name' => $foreignMember->name])
            ->assertJsonMissing(['name' => 'Extern Ehemalig'])
            ->assertJsonMissing(['name' => 'Extern Verein B']);

        $personKeys = collect($response->json('data.member_options'))
            ->map(fn (array $person) => $person['kind'].':'.$person['id']);
        $this->assertSame($personKeys->count(), $personKeys->unique()->count());
    }

    public function test_validation_and_delete_protection_preserve_responsibility_history(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($member->id, ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active']);
        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/clubs/{$club->id}/governance/bodies", [
            'type' => 'invalid', 'name' => 'Test', 'starts_on' => '2027-01-01',
            'ends_on' => '2026-01-01', 'is_public' => false,
        ])->assertUnprocessable()->assertJsonValidationErrors(['type', 'ends_on']);

        $body = ClubGovernanceBody::query()->create(['club_id' => $club->id, 'type' => 'working_group', 'name' => 'Projektgruppe', 'is_public' => false]);
        $assignment = ClubGovernanceAssignment::query()->create([
            'club_id' => $club->id, 'club_governance_body_id' => $body->id, 'user_id' => $member->id,
            'position_title' => 'Projektleitung', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'is_public' => false,
        ]);

        $this->deleteJson("/api/v1/clubs/{$club->id}/governance/bodies/{$body->id}")
            ->assertUnprocessable()->assertJsonValidationErrors('governance_body');
        $this->deleteJson("/api/v1/clubs/{$club->id}/governance/bodies/{$body->id}/assignments/{$assignment->id}")->assertOk();
        $this->deleteJson("/api/v1/clubs/{$club->id}/governance/bodies/{$body->id}")->assertOk();
        $this->assertDatabaseMissing('club_governance_bodies', ['id' => $body->id]);
    }

    public function test_governance_edit_and_delete_permissions_are_separated(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $deleter = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        foreach ([$editor, $deleter] as $member) {
            $club->users()->attach($member->id, [
                'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
            ]);
        }
        $this->assign($club, $editor, $this->role($club, 'governance_editor', [ClubPermissions::GOVERNANCE_EDIT]), $owner);
        $this->assign($club, $deleter, $this->role($club, 'governance_deleter', [ClubPermissions::GOVERNANCE_DELETE]), $owner);
        $body = ClubGovernanceBody::query()->create([
            'club_id' => $club->id, 'type' => 'committee', 'name' => 'Sportausschuss', 'is_public' => false,
        ]);

        Sanctum::actingAs($editor);
        $this->getJson("/api/v1/clubs/{$club->id}/governance")
            ->assertOk()->assertJsonPath('data.can_edit', true)->assertJsonPath('data.can_delete', false);
        $this->putJson("/api/v1/clubs/{$club->id}/governance/bodies/{$body->id}", [
            'type' => 'committee', 'name' => 'Sport und Jugend', 'is_public' => false,
        ])->assertOk()->assertJsonPath('data.name', 'Sport und Jugend');
        $this->deleteJson("/api/v1/clubs/{$club->id}/governance/bodies/{$body->id}")->assertForbidden();

        Sanctum::actingAs($deleter);
        $this->getJson("/api/v1/clubs/{$club->id}/governance")
            ->assertOk()->assertJsonPath('data.can_edit', false)->assertJsonPath('data.can_delete', true)
            ->assertJsonCount(0, 'data.member_options');
        $this->postJson("/api/v1/clubs/{$club->id}/governance/bodies", [
            'type' => 'committee', 'name' => 'Nicht erlaubt', 'is_public' => false,
        ])->assertForbidden();
        $this->deleteJson("/api/v1/clubs/{$club->id}/governance/bodies/{$body->id}")->assertOk();
    }

    private function role(Club $club, string $key, array $permissions): ClubRoleDefinition
    {
        return ClubRoleDefinition::query()->create([
            'club_id' => $club->id, 'key' => $key, 'name' => str_replace('_', ' ', $key),
            'permissions' => $permissions, 'is_active' => true,
        ]);
    }

    private function assign(Club $club, User $user, ClubRoleDefinition $role, User $owner): void
    {
        ClubRoleAssignment::query()->create([
            'club_id' => $club->id, 'club_role_definition_id' => $role->id,
            'user_id' => $user->id, 'scope_type' => 'club', 'scope_key' => 'club',
            'assigned_by' => $owner->id,
        ]);
    }
}
