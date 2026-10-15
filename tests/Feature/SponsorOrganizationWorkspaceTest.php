<?php

namespace Tests\Feature;

use App\Models\AdCampaign;
use App\Models\Sponsor;
use App\Models\SponsorMembership;
use App\Models\User;
use App\Models\UserRoleApplication;
use App\Services\SponsorOrganizationService;
use App\Services\SponsorWorkspaceService;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SponsorOrganizationWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_application_creates_company_workspace_and_owner_access(): void
    {
        $this->seed(RolesPermissionsSeeder::class);
        $owner = User::factory()->create(['email' => 'owner@nike.test']);
        $application = UserRoleApplication::query()->create([
            'user_id' => $owner->id,
            'type' => UserRoleApplication::TYPE_SPONSOR,
            'status' => UserRoleApplication::STATUS_APPROVED,
            'application_data' => [
                'company_name' => 'Nike',
                'legal_name' => 'Nike Test GmbH',
                'country_code' => 'DE',
                'contact_name' => 'Test Owner',
                'contact_email' => 'owner@nike.test',
                'legal_accuracy' => true,
                'data_privacy' => true,
            ],
            'requested_at' => now(),
            'reviewed_at' => now(),
        ]);

        $sponsor = app(SponsorOrganizationService::class)->createForApprovedApplication($application);

        $this->assertSame('Nike', $sponsor->name);
        $this->assertDatabaseHas('sponsor_memberships', [
            'sponsor_id' => $sponsor->id,
            'user_id' => $owner->id,
            'role' => SponsorMembership::ROLE_OWNER,
            'status' => SponsorMembership::STATUS_ACTIVE,
        ]);
    }

    public function test_workspace_payload_repairs_an_older_approved_application(): void
    {
        $this->seed(RolesPermissionsSeeder::class);
        $owner = User::factory()->create(['email' => 'legacy@nike.test']);
        $owner->assignRole('sponsor');
        UserRoleApplication::query()->create([
            'user_id' => $owner->id,
            'type' => UserRoleApplication::TYPE_SPONSOR,
            'status' => UserRoleApplication::STATUS_APPROVED,
            'application_data' => [
                'company_name' => 'Legacy Nike',
                'country_code' => 'DE',
                'contact_name' => $owner->name,
                'contact_email' => $owner->email,
                'legal_accuracy' => true,
                'data_privacy' => true,
            ],
            'requested_at' => now()->subDay(),
            'reviewed_at' => now()->subDay(),
        ]);

        $payload = app(SponsorWorkspaceService::class)->payload($owner);

        $this->assertSame('Legacy Nike', $payload['current_organization']['name']);
        $this->assertSame(SponsorMembership::ROLE_OWNER, $payload['current_organization']['role']);
        $this->assertTrue($payload['capabilities']['manage_team']);
        $this->assertCount(1, $payload['team']);
        $this->assertDatabaseHas('sponsor_activity_logs', [
            'sponsor_id' => $payload['current_organization']['id'],
            'action' => 'organization.created',
        ]);
    }

    public function test_approved_application_claims_matching_legacy_company_instead_of_duplicating_it(): void
    {
        $this->seed(RolesPermissionsSeeder::class);
        $owner = User::factory()->create(['email' => 'contact@legacy.test']);
        $legacy = Sponsor::query()->create([
            'name' => 'Legacy Company',
            'email' => $owner->email,
            'scope' => 'platform',
        ]);
        $application = UserRoleApplication::query()->create([
            'user_id' => $owner->id,
            'type' => UserRoleApplication::TYPE_SPONSOR,
            'status' => UserRoleApplication::STATUS_APPROVED,
            'application_data' => [
                'company_name' => 'Legacy Company',
                'country_code' => 'DE',
                'contact_name' => $owner->name,
                'contact_email' => $owner->email,
                'legal_accuracy' => true,
                'data_privacy' => true,
            ],
            'requested_at' => now(),
            'reviewed_at' => now(),
        ]);

        $organization = app(SponsorOrganizationService::class)->createForApprovedApplication($application);

        $this->assertSame($legacy->id, $organization->id);
        $this->assertSame($owner->id, $organization->owner_user_id);
        $this->assertSame(1, Sponsor::query()->where('scope', 'platform')->count());
    }

    public function test_member_role_is_scoped_to_its_company(): void
    {
        $this->seed(RolesPermissionsSeeder::class);
        $owner = User::factory()->create();
        $employee = User::factory()->create();
        $nike = Sponsor::query()->create(['name' => 'Nike', 'scope' => 'platform', 'owner_user_id' => $owner->id]);
        $adidas = Sponsor::query()->create(['name' => 'Adidas', 'scope' => 'platform']);
        SponsorMembership::query()->create([
            'sponsor_id' => $nike->id,
            'user_id' => $employee->id,
            'email' => $employee->email,
            'role' => SponsorMembership::ROLE_CAMPAIGN_MANAGER,
            'status' => SponsorMembership::STATUS_ACTIVE,
        ]);

        $organizations = app(SponsorOrganizationService::class)->organizations($employee);

        $this->assertSame([$nike->id], $organizations->pluck('id')->all());
        $this->assertTrue(app(SponsorOrganizationService::class)->can($employee, $nike, 'campaigns'));
        $this->assertFalse(app(SponsorOrganizationService::class)->can($employee, $nike, 'finance'));
        $this->assertNull(app(SponsorOrganizationService::class)->resolve($employee, $adidas->id));
    }

    public function test_foreign_workspace_id_never_changes_payload_scope(): void
    {
        $this->seed(RolesPermissionsSeeder::class);
        $owner = User::factory()->create();
        $foreignOwner = User::factory()->create();
        $own = Sponsor::query()->create(['name' => 'Own company', 'scope' => 'platform', 'owner_user_id' => $owner->id]);
        $foreign = Sponsor::query()->create(['name' => 'Foreign company', 'scope' => 'platform', 'owner_user_id' => $foreignOwner->id]);

        $payload = app(SponsorWorkspaceService::class)->payload($owner, $foreign->id);

        $this->assertSame($own->id, $payload['current_organization']['id']);
        $this->assertSame([$own->id], $payload['organizations']->pluck('id')->all());
        $this->assertSame($own->id, $payload['own_profile']['id']);
    }

    public function test_campaign_manager_creates_campaign_inside_assigned_company(): void
    {
        $this->seed(RolesPermissionsSeeder::class);
        $owner = User::factory()->create();
        $manager = User::factory()->create();
        $sponsor = Sponsor::query()->create(['name' => 'Nike', 'scope' => 'platform', 'owner_user_id' => $owner->id]);
        SponsorMembership::query()->create([
            'sponsor_id' => $sponsor->id,
            'user_id' => $manager->id,
            'email' => $manager->email,
            'role' => SponsorMembership::ROLE_CAMPAIGN_MANAGER,
            'status' => SponsorMembership::STATUS_ACTIVE,
        ]);

        $this->actingAs($manager)->postJson('/api/v1/commerce/seller/campaigns', [
            'sponsor_id' => $sponsor->id,
            'name' => 'Herbstkampagne',
            'objective' => 'awareness',
            'budget_cents' => 1000,
        ])->assertCreated();

        $this->assertDatabaseHas('ad_campaigns', [
            'sponsor_id' => $sponsor->id,
            'user_id' => $manager->id,
            'name' => 'Herbstkampagne',
        ]);
    }

    public function test_viewer_can_read_but_cannot_change_company_campaign(): void
    {
        $this->seed(RolesPermissionsSeeder::class);
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $sponsor = Sponsor::query()->create(['name' => 'Nike', 'scope' => 'platform', 'owner_user_id' => $owner->id]);
        SponsorMembership::query()->create([
            'sponsor_id' => $sponsor->id,
            'user_id' => $viewer->id,
            'email' => $viewer->email,
            'role' => SponsorMembership::ROLE_VIEWER,
            'status' => SponsorMembership::STATUS_ACTIVE,
        ]);
        $campaign = AdCampaign::query()->create([
            'sponsor_id' => $sponsor->id,
            'user_id' => $owner->id,
            'name' => 'Geschützte Kampagne',
            'budget_cents' => 1000,
            'status' => 'draft',
        ]);

        $this->actingAs($viewer)
            ->getJson('/api/v1/commerce/seller?sponsor_id='.$sponsor->id)
            ->assertOk()
            ->assertJsonPath('data.campaigns.0.id', $campaign->id)
            ->assertJsonPath('data.current_sponsor.can_manage_campaigns', false);

        $this->actingAs($viewer)
            ->patchJson('/api/v1/commerce/seller/campaigns/'.$campaign->id.'/status', ['status' => 'pending_review'])
            ->assertForbidden();
    }

    public function test_confirmed_owner_transfer_keeps_previous_owner_as_administrator(): void
    {
        $this->seed(RolesPermissionsSeeder::class);
        $owner = User::factory()->create();
        $successor = User::factory()->create();
        $sponsor = Sponsor::query()->create(['name' => 'Nike', 'scope' => 'platform', 'owner_user_id' => $owner->id]);
        SponsorMembership::query()->create([
            'sponsor_id' => $sponsor->id,
            'user_id' => $owner->id,
            'email' => $owner->email,
            'role' => SponsorMembership::ROLE_OWNER,
            'status' => SponsorMembership::STATUS_ACTIVE,
        ]);
        $membership = SponsorMembership::query()->create([
            'sponsor_id' => $sponsor->id,
            'user_id' => $successor->id,
            'email' => $successor->email,
            'role' => SponsorMembership::ROLE_ADMINISTRATOR,
            'status' => SponsorMembership::STATUS_ACTIVE,
        ]);

        app(SponsorOrganizationService::class)->transferOwnership($owner, $membership, true);

        $this->assertSame($successor->id, $sponsor->fresh()->owner_user_id);
        $this->assertDatabaseHas('sponsor_memberships', [
            'sponsor_id' => $sponsor->id,
            'user_id' => $successor->id,
            'role' => SponsorMembership::ROLE_OWNER,
        ]);
        $this->assertDatabaseHas('sponsor_memberships', [
            'sponsor_id' => $sponsor->id,
            'user_id' => $owner->id,
            'role' => SponsorMembership::ROLE_ADMINISTRATOR,
        ]);
        $this->assertDatabaseHas('sponsor_activity_logs', [
            'sponsor_id' => $sponsor->id,
            'action' => 'organization.owner_transferred',
        ]);
    }
}
