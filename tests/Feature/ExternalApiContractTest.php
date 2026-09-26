<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Club;
use App\Models\ClubExternalMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExternalApiContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_external_member_api_requires_authentication_and_token_ability(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);

        $this->getJson("/api/v1/external/clubs/{$club->id}/members")
            ->assertUnauthorized()
            ->assertJsonPath('code', 'unauthenticated')
            ->assertJsonStructure(['message', 'errors', 'code', 'error', 'meta']);

        Sanctum::actingAs($owner, ['external.members:write']);

        $this->getJson("/api/v1/external/clubs/{$club->id}/members")
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden')
            ->assertJsonStructure(['message', 'errors', 'code', 'error', 'meta']);
    }

    public function test_external_member_api_is_scoped_to_authorized_club_tenant(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $otherClub = Club::factory()->create(['owner_id' => $otherOwner->id]);
        $otherMember = ClubExternalMember::query()->create([
            'club_id' => $otherClub->id,
            'created_by' => $otherOwner->id,
            'name' => 'Other Tenant',
            'email' => 'other@example.test',
            'role' => 'member',
            'membership_status' => 'active',
        ]);

        Sanctum::actingAs($owner, ['external.members:read']);

        $this->getJson("/api/v1/external/clubs/{$club->id}/members/{$otherMember->id}")
            ->assertNotFound()
            ->assertJsonPath('code', 'not_found');

        $this->getJson("/api/v1/external/clubs/{$otherClub->id}/members")
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');
    }

    public function test_external_member_api_paginates_and_exposes_versioned_contract_meta(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);

        ClubExternalMember::query()->create([
            'club_id' => $club->id,
            'created_by' => $owner->id,
            'name' => 'Ada Export',
            'email' => 'ada@example.test',
            'role' => 'member',
            'membership_status' => 'active',
        ]);
        ClubExternalMember::query()->create([
            'club_id' => $club->id,
            'created_by' => $owner->id,
            'name' => 'Ben Export',
            'email' => 'ben@example.test',
            'role' => 'trainer',
            'membership_status' => 'pending',
        ]);

        Sanctum::actingAs($owner, ['external.members:read']);

        $this->getJson("/api/v1/external/clubs/{$club->id}/members?per_page=1")
            ->assertOk()
            ->assertHeader('X-Airmius-Api-Version', 'v1')
            ->assertJsonPath('data.0.email', 'ada@example.test')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.tenant.type', 'club')
            ->assertJsonPath('meta.tenant.id', $club->id)
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'from', 'last_page', 'path', 'per_page', 'to', 'total', 'api_version', 'contract_version', 'request_id', 'tenant'],
                'links' => ['first', 'last', 'prev', 'next'],
            ]);
    }

    public function test_external_member_write_api_creates_updates_and_audits_changes(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);

        Sanctum::actingAs($owner, ['external.members:read', 'external.members:write']);

        $create = $this->postJson("/api/v1/external/clubs/{$club->id}/members", [
            'name' => 'Carla Sync',
            'email' => 'CARLA@example.test',
            'country' => 'de',
            'role' => 'member',
            'membership_status' => 'active',
            'member_number' => 'EXT-100',
            'joined_on' => '2026-09-01',
        ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'carla@example.test')
            ->assertJsonPath('data.country', 'DE')
            ->assertJsonPath('meta.tenant.id', $club->id);

        $memberId = $create->json('data.id');

        $this->putJson("/api/v1/external/clubs/{$club->id}/members/{$memberId}", [
            'name' => 'Carla Sync',
            'email' => 'carla@example.test',
            'country' => 'DE',
            'role' => 'trainer',
            'membership_status' => 'active',
            'member_number' => 'EXT-101',
        ])
            ->assertOk()
            ->assertJsonPath('data.role', 'trainer')
            ->assertJsonPath('data.member_number', 'EXT-101');

        $this->assertDatabaseHas('club_external_members', [
            'id' => $memberId,
            'club_id' => $club->id,
            'email' => 'carla@example.test',
            'role' => 'trainer',
            'member_number' => 'EXT-101',
        ]);

        $this->assertDatabaseHas('activities', [
            'club_id' => $club->id,
            'user_id' => $owner->id,
            'type' => 'club.member.external_updated',
            'subject_type' => ClubExternalMember::class,
            'subject_id' => $memberId,
        ]);

        $latest = Activity::query()->latest('id')->firstOrFail();
        $this->assertSame('external_api', $latest->data['source']);
        $this->assertSame('updated', $latest->data['operation']);
        $this->assertContains('role', $latest->data['changed_fields']);
        $this->assertContains('member_number', $latest->data['changed_fields']);
    }
}
