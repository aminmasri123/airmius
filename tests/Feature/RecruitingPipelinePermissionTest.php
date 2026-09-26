<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubRoleAssignment;
use App\Models\ClubRoleDefinition;
use App\Models\OrganizationJob;
use App\Models\OrganizationJobInterest;
use App\Models\User;
use App\Support\ClubPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RecruitingPipelinePermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_recruiting_view_edit_contact_and_delete_rights_are_independent(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $editor = User::factory()->create();
        $contact = User::factory()->create();
        $deleter = User::factory()->create();
        $candidate = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);

        foreach ([$viewer, $editor, $contact, $deleter] as $member) {
            $club->users()->attach($member->id, [
                'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
            ]);
        }

        $this->assign($club, $owner, $viewer, 'recruiting_viewer', [ClubPermissions::RECRUITING_VIEW]);
        $this->assign($club, $owner, $editor, 'recruiting_editor', [ClubPermissions::RECRUITING_EDIT]);
        $this->assign($club, $owner, $contact, 'recruiting_contact', [ClubPermissions::RECRUITING_CONTACT]);
        $this->assign($club, $owner, $deleter, 'recruiting_deleter', [ClubPermissions::RECRUITING_DELETE]);

        $interest = $this->interest($club, $candidate);
        $endpoint = "/api/v1/recruiting-pipeline/applications/{$interest->id}";

        Sanctum::actingAs($viewer);
        $this->getJson('/api/v1/recruiting-pipeline')
            ->assertOk()
            ->assertJsonPath('data.applications.data.0.id', $interest->id)
            ->assertJsonPath('data.applications.data.0.can_edit', false)
            ->assertJsonPath('data.applications.data.0.can_contact', false)
            ->assertJsonPath('data.applications.data.0.can_delete', false)
            ->assertJsonPath('data.applications.data.0.can_open_chat', false);
        $this->putJson($endpoint, ['status' => 'reviewing'])->assertForbidden();
        $this->postJson($endpoint.'/chat')->assertForbidden();
        $this->deleteJson($endpoint)->assertForbidden();

        Sanctum::actingAs($editor);
        $this->getJson('/api/v1/recruiting-pipeline')
            ->assertOk()
            ->assertJsonPath('data.applications.data.0.can_edit', true)
            ->assertJsonPath('data.applications.data.0.can_contact', false)
            ->assertJsonPath('data.applications.data.0.can_delete', false);
        $this->putJson($endpoint, ['status' => 'reviewing', 'internal_note' => 'Geprüft.'])
            ->assertOk();
        $this->postJson($endpoint.'/chat')->assertForbidden();
        $this->deleteJson($endpoint)->assertForbidden();

        Sanctum::actingAs($contact);
        $this->getJson('/api/v1/recruiting-pipeline')
            ->assertOk()
            ->assertJsonPath('data.applications.data.0.can_edit', false)
            ->assertJsonPath('data.applications.data.0.can_contact', true)
            ->assertJsonPath('data.applications.data.0.can_delete', false)
            ->assertJsonPath('data.applications.data.0.can_open_chat', true);
        $this->postJson($endpoint.'/chat')->assertOk()->assertJsonStructure(['data' => ['conversation_id']]);
        $this->putJson($endpoint, ['status' => 'contacted'])->assertForbidden();
        $this->deleteJson($endpoint)->assertForbidden();

        Sanctum::actingAs($deleter);
        $this->getJson('/api/v1/recruiting-pipeline')
            ->assertOk()
            ->assertJsonPath('data.applications.data.0.can_edit', false)
            ->assertJsonPath('data.applications.data.0.can_contact', false)
            ->assertJsonPath('data.applications.data.0.can_delete', true);
        $this->putJson($endpoint, ['status' => 'interview'])->assertForbidden();
        $this->postJson($endpoint.'/chat')->assertForbidden();
        $this->deleteJson($endpoint)->assertOk();
        $this->assertDatabaseMissing('organization_job_interests', ['id' => $interest->id]);
    }

    public function test_explicit_recruiting_denial_overrides_legacy_manager_defaults(): void
    {
        $owner = User::factory()->create();
        $manager = User::factory()->create();
        $candidate = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($manager->id, [
            'role' => 'manager',
            'roles' => ['manager'],
            'membership_status' => 'active',
            'permission_overrides' => [ClubPermissions::RECRUITING_CONTACT => false],
        ]);
        $interest = $this->interest($club, $candidate);
        $endpoint = "/api/v1/recruiting-pipeline/applications/{$interest->id}";

        Sanctum::actingAs($manager);
        $this->getJson('/api/v1/recruiting-pipeline')
            ->assertOk()
            ->assertJsonPath('data.applications.data.0.can_edit', true)
            ->assertJsonPath('data.applications.data.0.can_contact', false)
            ->assertJsonPath('data.applications.data.0.can_delete', true)
            ->assertJsonPath('data.applications.data.0.can_open_chat', false);
        $this->postJson($endpoint.'/chat')->assertForbidden();
        $this->putJson($endpoint, ['status' => 'reviewing'])->assertOk();
    }

    private function interest(Club $club, User $candidate): OrganizationJobInterest
    {
        $job = OrganizationJob::query()->create([
            'club_id' => $club->id,
            'created_by' => $club->owner_id,
            'title' => 'Jugendtraining',
            'type' => 'volunteer',
            'description' => 'Eine klar beschriebene Aufgabe.',
            'is_published' => true,
            'published_at' => now(),
        ]);

        return OrganizationJobInterest::query()->create([
            'organization_job_id' => $job->id,
            'user_id' => $candidate->id,
            'name' => $candidate->name,
            'email' => $candidate->email,
            'status' => 'new',
            'allow_in_app_contact' => true,
            'consent_at' => now(),
            'retention_expires_at' => now()->addMonths(6),
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
