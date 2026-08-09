<?php

namespace Tests\Feature;

use App\Models\AdCampaign;
use App\Models\AdEvent;
use App\Models\MarketplaceProviderProfile;
use App\Models\MarketplaceSellerApplication;
use App\Models\PayoutProfile;
use App\Models\Sponsor;
use App\Models\User;
use App\Services\RevenueTrustService;
use App\Services\SponsorWorkspaceService;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RevenueTrustWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_seller_contract_cannot_be_approved_before_payout_and_identity_readiness(): void
    {
        $this->seed(RolesPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->givePermissionTo('marketplace.manage');
        $seller = User::factory()->create(['email' => 'seller@example.test']);
        MarketplaceProviderProfile::query()->create([
            'user_id' => $seller->id,
            'display_name' => 'Verified Sports',
            'legal_name' => 'Verified Sports GmbH',
            'provider_type' => 'business',
            'support_email' => 'support@example.test',
            'legal_country' => 'DE',
            'legal_city' => 'Berlin',
            'status' => 'active',
        ]);
        $application = MarketplaceSellerApplication::query()->create([
            'user_id' => $seller->id,
            'applicant_type' => 'business',
            'business_name' => 'Verified Sports GmbH',
            'accepted_rules' => [
                'product_truth' => true,
                'rights' => true,
                'shipping_returns' => true,
                'commission' => true,
                'data_privacy' => true,
            ],
            'verification_version' => RevenueTrustService::CONTRACT_VERSION,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($admin);
        $this->patchJson("/api/v1/admin/commerce/seller-applications/{$application->id}", [
            'status' => 'approved',
        ])->assertUnprocessable()->assertJsonValidationErrors('status');

        $profile = PayoutProfile::query()->create([
            'user_id' => $seller->id,
            'account_holder' => 'Verified Sports GmbH',
            'iban' => 'DE89370400440532013000',
            'country_code' => 'DE',
            'tax_status' => 'taxable',
            'beneficial_owner_confirmed' => true,
            'terms_version' => RevenueTrustService::CONTRACT_VERSION,
            'terms_accepted_at' => now(),
            'status' => 'review',
        ]);

        $this->patchJson("/api/v1/admin/commerce/payout-profiles/{$profile->id}", [
            'status' => 'approved',
        ])->assertOk()->assertJsonPath('data.status', 'approved');

        $this->patchJson("/api/v1/admin/commerce/seller-applications/{$application->id}", [
            'status' => 'approved',
        ])->assertOk()->assertJsonPath('data.status', 'approved');

        $application->refresh();
        self::assertSame(RevenueTrustService::CONTRACT_VERSION, $application->verification_snapshot['version']);
        self::assertTrue($application->verification_snapshot['can_approve']);

        $catalog = $this->getJson('/api/v1/admin/commerce/catalog')->assertOk();
        self::assertArrayNotHasKey('marketplace_provider_profile', $catalog->json('data.seller_applications.0.user'));
        self::assertArrayNotHasKey('payout_profile', $catalog->json('data.seller_applications.0.user'));
        self::assertStringNotContainsString('DE89370400440532013000', $catalog->getContent());
    }

    public function test_self_service_sponsor_stays_private_until_verified_and_public_payload_has_no_contract_data(): void
    {
        $this->seed(RolesPermissionsSeeder::class);
        $sponsorUser = User::factory()->create();
        $sponsorUser->assignRole('sponsor');
        Sanctum::actingAs($sponsorUser);

        $this->putJson('/api/v1/sponsor-workspace/profile', [
            'name' => 'Future Sports',
            'legal_name' => 'Future Sports GmbH',
            'country_code' => 'de',
            'registration_number' => 'HRB 2030',
            'vat_id' => 'DE123456789',
            'contact_name' => 'Internal Contact',
            'email' => 'private@example.test',
            'website' => 'https://future-sports.example.test',
            'rule_legal_accuracy' => true,
            'rule_data_privacy' => true,
        ])->assertOk();

        $sponsor = Sponsor::query()->where('owner_user_id', $sponsorUser->id)->firstOrFail();
        self::assertSame('pending_review', $sponsor->verification_status);
        $this->getJson('/api/v1/public/sponsors')->assertOk()->assertJsonCount(0, 'data');

        $admin = User::factory()->create();
        $admin->givePermissionTo('finance.edit');
        Sanctum::actingAs($admin);
        $this->putJson("/api/v1/sponsor-management/{$sponsor->id}", [
            'scope' => 'platform',
            'name' => $sponsor->name,
            'legal_name' => $sponsor->legal_name,
            'country_code' => $sponsor->country_code,
            'registration_number' => $sponsor->registration_number,
            'vat_id' => $sponsor->vat_id,
            'contact_name' => $sponsor->contact_name,
            'email' => $sponsor->email,
            'website' => $sponsor->website,
            'verification_status' => 'verified',
        ])->assertOk()->assertJsonPath('data.verification_status', 'verified');

        $public = $this->getJson('/api/v1/public/sponsors')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Future Sports');
        foreach (['email', 'contact_name', 'amount', 'legal_name', 'vat_id', 'registration_number'] as $privateKey) {
            self::assertArrayNotHasKey($privateKey, $public->json('data.0'));
        }

        $this->get(route('guest.sponsors'))
            ->assertOk()
            ->assertDontSee('Internal Contact')
            ->assertDontSee('private@example.test')
            ->assertDontSee('HRB 2030');
    }

    public function test_sponsor_outcomes_are_bounded_aggregates_without_tracking_identifiers(): void
    {
        $this->seed(RolesPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('sponsor');
        $sponsor = Sponsor::query()->create([
            'owner_user_id' => $user->id,
            'name' => 'Outcome Partner',
            'verification_status' => 'verified',
        ]);
        $campaign = AdCampaign::query()->create([
            'user_id' => $user->id,
            'sponsor_id' => $sponsor->id,
            'name' => 'Outcome Campaign',
            'status' => 'active',
        ]);
        foreach ([
            ['impression', 0, 0],
            ['click', 30, 0],
            ['lead', 200, 0],
            ['sale', 100, 5000],
        ] as [$eventType, $costCents, $valueCents]) {
            AdEvent::query()->create([
                'ad_campaign_id' => $campaign->id,
                'event_type' => $eventType,
                'cost_cents' => $costCents,
                'value_cents' => $valueCents,
                'session_hash' => 'private-session-hash',
                'ip_hash' => 'private-ip-hash',
                'user_agent_hash' => 'private-agent-hash',
                'occurred_at' => now(),
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $payload = app(SponsorWorkspaceService::class)->payload($user);
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        self::assertLessThanOrEqual(16, $queryCount);
        self::assertSame(1, $payload['summary']['leads_28d']);
        self::assertSame(1, $payload['summary']['sales_28d']);
        self::assertSame(5000, $payload['summary']['conversion_value_cents_28d']);
        self::assertSame(165, $payload['summary']['cpa_cents_28d']);
        self::assertSame(15.15, $payload['summary']['roas_28d']);
        self::assertCount(1, $payload['outcome_timeline']);

        $encoded = json_encode($payload, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('private-session-hash', $encoded);
        self::assertStringNotContainsString('private-ip-hash', $encoded);
        self::assertStringNotContainsString('private-agent-hash', $encoded);
        self::assertStringNotContainsString('session_hash', $encoded);
        self::assertStringNotContainsString('ip_hash', $encoded);
        self::assertStringNotContainsString('user_agent_hash', $encoded);
    }
}
