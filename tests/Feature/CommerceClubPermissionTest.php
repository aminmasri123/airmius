<?php

namespace Tests\Feature;

use App\Models\AdCampaign;
use App\Models\Club;
use App\Models\ClubRoleAssignment;
use App\Models\ClubRoleDefinition;
use App\Models\MarketplaceSellerApplication;
use App\Models\Sponsor;
use App\Models\SubscriptionAddon;
use App\Models\User;
use App\Models\WebsiteRequest;
use App\Support\ClubPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommerceClubPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_commerce_club_capabilities_and_actions_are_independent(): void
    {
        $owner = User::factory()->create();
        $addonBuyer = User::factory()->create();
        $productEditor = User::factory()->create();
        $advertiser = User::factory()->create();
        $websiteRequester = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);

        foreach ([$addonBuyer, $productEditor, $advertiser, $websiteRequester] as $member) {
            $club->users()->attach($member->id, [
                'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
            ]);
        }
        $this->assign($club, $owner, $addonBuyer, 'addon_buyer', [ClubPermissions::COMMERCE_ADDONS_PURCHASE]);
        $this->assign($club, $owner, $productEditor, 'shop_editor', [ClubPermissions::COMMERCE_PRODUCTS_EDIT]);
        $this->assign($club, $owner, $advertiser, 'advertiser', [ClubPermissions::ADVERTISING_EDIT]);
        $this->assign($club, $owner, $websiteRequester, 'website_requester', [ClubPermissions::WEBSITE_REQUEST_CREATE]);
        MarketplaceSellerApplication::query()->create([
            'user_id' => $productEditor->id,
            'applicant_type' => 'club',
            'accepted_rules' => ['product_truth', 'rights', 'commission'],
            'status' => 'approved',
            'reviewed_at' => now(),
        ]);

        Sanctum::actingAs($productEditor);
        $this->getJson('/api/v1/commerce/seller')
            ->assertOk()
            ->assertJsonPath('data.clubs.0.id', $club->id)
            ->assertJsonPath('data.clubs.0.can_purchase_addons', false)
            ->assertJsonPath('data.clubs.0.can_manage_shop_products', true)
            ->assertJsonPath('data.clubs.0.can_manage_advertising', false)
            ->assertJsonPath('data.clubs.0.can_request_website', false);
        $this->postJson('/api/v1/commerce/seller/products', $this->productPayload($club->id))
            ->assertCreated()
            ->assertJsonPath('data.club_id', $club->id);
        $this->postJson('/api/v1/commerce/seller/campaigns', $this->campaignPayload($club->id))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('club_id');
        $this->postJson('/api/v1/commerce/seller/website-requests', $this->websitePayload($club->id))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('club_id');

        Sanctum::actingAs($advertiser);
        $this->postJson('/api/v1/commerce/seller/campaigns', $this->campaignPayload($club->id))
            ->assertCreated()
            ->assertJsonPath('data.club_id', $club->id);
        $this->postJson('/api/v1/commerce/seller/website-requests', $this->websitePayload($club->id))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('club_id');

        Sanctum::actingAs($websiteRequester);
        $this->postJson('/api/v1/commerce/seller/website-requests', $this->websitePayload($club->id))
            ->assertCreated()
            ->assertJsonPath('data.club_id', $club->id);
        $this->postJson('/api/v1/commerce/seller/campaigns', $this->campaignPayload($club->id))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('club_id');

        Sanctum::actingAs($addonBuyer);
        $this->getJson('/api/v1/commerce/seller')
            ->assertOk()
            ->assertJsonPath('data.clubs.0.can_purchase_addons', true)
            ->assertJsonPath('data.clubs.0.can_manage_shop_products', false)
            ->assertJsonPath('data.clubs.0.can_manage_advertising', false)
            ->assertJsonPath('data.clubs.0.can_request_website', false);
    }

    public function test_addon_checkout_uses_its_own_club_permission(): void
    {
        $owner = User::factory()->create();
        $buyer = User::factory()->create();
        $shopEditor = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        foreach ([$buyer, $shopEditor] as $member) {
            $club->users()->attach($member->id, [
                'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
            ]);
        }
        $this->assign($club, $owner, $buyer, 'addon_buyer', [ClubPermissions::COMMERCE_ADDONS_PURCHASE]);
        $this->assign($club, $owner, $shopEditor, 'shop_editor', [ClubPermissions::COMMERCE_PRODUCTS_EDIT]);
        $addon = SubscriptionAddon::query()->create([
            'slug' => 'club-storage',
            'name' => 'Club Speicher',
            'monthly_price_cents' => 500,
            'yearly_price_cents' => 5000,
            'target_actor' => 'verein',
            'is_active' => true,
        ]);
        $payload = [
            'provider' => 'bank_transfer',
            'billing_interval' => 'monthly',
            'club_id' => $club->id,
            'accepted_terms' => true,
        ];

        Sanctum::actingAs($shopEditor);
        $this->withHeader('Idempotency-Key', 'commerce-addon-shop-editor')
            ->postJson(route('auth.commerce.addons.checkout', $addon), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('club_id');

        Sanctum::actingAs($buyer);
        $this->withHeader('Idempotency-Key', 'commerce-addon-buyer')
            ->postJson(route('auth.commerce.addons.checkout', $addon), $payload)
            ->assertUnprocessable()
            ->assertJsonMissingValidationErrors('club_id');
        $this->assertDatabaseHas('commerce_orders', [
            'user_id' => $buyer->id,
            'club_id' => $club->id,
            'type' => 'addon',
        ]);
    }

    public function test_sponsor_workspace_uses_data_specific_club_rights_and_explicit_denials(): void
    {
        $owner = User::factory()->create();
        $sponsorEditor = User::factory()->create();
        $advertiser = User::factory()->create();
        $websiteRequester = User::factory()->create();
        $deniedManager = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        foreach ([$sponsorEditor, $advertiser, $websiteRequester] as $member) {
            $club->users()->attach($member->id, [
                'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
            ]);
        }
        $club->users()->attach($deniedManager->id, [
            'role' => 'manager',
            'membership_status' => 'active',
            'permission_overrides' => [
                ClubPermissions::SPONSORS_EDIT => false,
                ClubPermissions::SPONSORS_DELETE => false,
                ClubPermissions::ADVERTISING_EDIT => false,
                ClubPermissions::WEBSITE_REQUEST_CREATE => false,
            ],
        ]);
        $this->assign($club, $owner, $sponsorEditor, 'sponsor_workspace_editor', [ClubPermissions::SPONSORS_EDIT]);
        $this->assign($club, $owner, $advertiser, 'sponsor_workspace_advertiser', [ClubPermissions::ADVERTISING_EDIT]);
        $this->assign($club, $owner, $websiteRequester, 'sponsor_workspace_web', [ClubPermissions::WEBSITE_REQUEST_CREATE]);
        $sponsor = Sponsor::query()->create([
            'club_id' => $club->id,
            'scope' => 'club',
            'name' => 'Club Partner',
        ]);
        AdCampaign::query()->create([
            'user_id' => $owner->id,
            'club_id' => $club->id,
            'sponsor_id' => $sponsor->id,
            'name' => 'Club Campaign',
            'status' => 'active',
        ]);
        WebsiteRequest::query()->create([
            'user_id' => $owner->id,
            'club_id' => $club->id,
            'status' => 'new',
            'package' => 'website_plus',
        ]);

        Sanctum::actingAs($sponsorEditor);
        $this->getJson(route('api.v1.sponsor-workspace.index'))
            ->assertOk()
            ->assertJsonPath('data.summary.partners', 1)
            ->assertJsonPath('data.summary.campaigns', 1)
            ->assertJsonPath('data.summary.agency_briefs', 0);

        Sanctum::actingAs($advertiser);
        $this->getJson(route('api.v1.sponsor-workspace.index'))
            ->assertOk()
            ->assertJsonPath('data.summary.partners', 0)
            ->assertJsonPath('data.summary.campaigns', 1)
            ->assertJsonPath('data.summary.agency_briefs', 0);

        Sanctum::actingAs($websiteRequester);
        $this->getJson(route('api.v1.sponsor-workspace.index'))
            ->assertOk()
            ->assertJsonPath('data.summary.partners', 0)
            ->assertJsonPath('data.summary.campaigns', 0)
            ->assertJsonPath('data.summary.agency_briefs', 1);

        Sanctum::actingAs($deniedManager);
        $this->getJson(route('api.v1.sponsor-workspace.index'))->assertForbidden();
    }

    private function productPayload(int $clubId): array
    {
        return [
            'club_id' => $clubId,
            'title' => 'Vereinsball',
            'description' => 'Ein geprüftes Vereinsangebot.',
            'category' => 'equipment',
            'offer_type' => 'physical_product',
            'product_type' => 'single',
            'price_cents' => 1999,
            'stock_quantity' => 10,
        ];
    }

    private function campaignPayload(int $clubId): array
    {
        return [
            'club_id' => $clubId,
            'name' => 'Sommeraktion',
            'objective' => 'traffic',
            'placement' => 'feed',
            'creative_format' => 'feed_square',
            'budget_cents' => 2500,
            'start_payment' => false,
        ];
    }

    private function websitePayload(int $clubId): array
    {
        return [
            'club_id' => $clubId,
            'domain' => 'verein.example',
            'goals' => 'Mitglieder informieren.',
            'accepted_privacy' => true,
        ];
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
