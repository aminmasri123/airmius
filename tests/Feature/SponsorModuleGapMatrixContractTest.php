<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Sponsor;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\RevenueTrustService;
use App\Support\ClubPermissions;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SponsorModuleGapMatrixContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_gap_matrix_tracks_the_audited_sponsor_surfaces(): void
    {
        $matrix = File::get(base_path('docs/SPONSOR_MODULE_GAP_MATRIX.md'));

        foreach (['Kontakte', 'Gespraechsverlauf', 'Angebote', 'Vertraege', 'Fristen', 'Zahlungen', 'Rechte'] as $surface) {
            $this->assertStringContainsString('| '.$surface.' |', $matrix);
        }

        $this->assertStringContainsString('SponsorModuleGapMatrixContractTest::test_role_contract_keeps_global_finance_and_club_sponsor_permissions_separate', $matrix);
        $this->assertStringContainsString('SponsorModuleGapMatrixContractTest::test_sponsor_finance_contract_readiness_blocks_untrusted_approval_and_allows_complete_profile', $matrix);
    }

    public function test_management_projection_keeps_contact_and_finance_fields_but_no_conversation_offer_contract_payment_claims(): void
    {
        $manager = User::factory()->create();
        $manager->givePermissionTo($this->permissions(['finance.edit']));
        $sponsor = Sponsor::query()->create([
            'scope' => 'platform',
            'name' => 'Audit Partner',
            'contact_name' => 'Sponsor Kontakt',
            'email' => 'sponsor.audit@example.test',
            'website' => 'https://sponsor.example.test',
            'amount' => 42000,
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addYear()->toDateString(),
            'verification_status' => 'verified',
        ]);

        Sanctum::actingAs($manager);
        $payload = $this->getJson('/api/v1/sponsor-management')
            ->assertOk()
            ->assertJsonPath('data.0.id', $sponsor->id)
            ->assertJsonPath('data.0.contact_name', 'Sponsor Kontakt')
            ->assertJsonPath('data.0.email', 'sponsor.audit@example.test')
            ->assertJsonPath('data.0.amount', '42000.00')
            ->json('data.0');

        foreach (['starts_at', 'ends_at', 'verification_status', 'can_edit', 'can_delete'] as $expectedKey) {
            $this->assertArrayHasKey($expectedKey, $payload);
        }

        foreach ([
            'conversation_id',
            'conversation',
            'last_contacted_at',
            'next_follow_up_at',
            'offer_id',
            'offer_status',
            'contract_id',
            'contract_number',
            'contract_document_id',
            'payment_status',
            'payment_due_at',
            'invoice_id',
            'finance_entry_id',
        ] as $gapKey) {
            $this->assertArrayNotHasKey($gapKey, $payload, "{$gapKey} is not part of the current sponsor management contract.");
        }
    }

    public function test_role_contract_keeps_global_finance_and_club_sponsor_permissions_separate(): void
    {
        $this->seed(RolesPermissionsSeeder::class);
        $financeManager = User::factory()->create();
        $financeManager->givePermissionTo($this->permissions(['finance.edit']));
        $clubOwner = User::factory()->create();
        $clubEditor = User::factory()->create();
        $clubDeleter = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $clubOwner->id]);
        $club->users()->attach([
            $clubEditor->id => [
                'role' => 'member',
                'roles' => ['member'],
                'membership_status' => 'active',
                'permission_overrides' => [ClubPermissions::SPONSORS_EDIT => true],
            ],
            $clubDeleter->id => [
                'role' => 'member',
                'roles' => ['member'],
                'membership_status' => 'active',
                'permission_overrides' => [ClubPermissions::SPONSORS_DELETE => true],
            ],
        ]);
        $this->activateClubSponsors($club);
        $clubSponsor = Sponsor::query()->create([
            'club_id' => $club->id,
            'scope' => 'club',
            'name' => 'Club Audit Partner',
        ]);
        $platformSponsor = Sponsor::query()->create([
            'scope' => 'platform',
            'name' => 'Platform Audit Partner',
        ]);

        Sanctum::actingAs($financeManager);
        $this->getJson('/api/v1/sponsor-management')
            ->assertOk()
            ->assertJsonPath('can.create_global', true);
        $this->putJson("/api/v1/sponsor-management/{$platformSponsor->id}", [
            'scope' => 'outfit_subscription',
            'name' => 'Global Finance Partner',
        ])->assertOk();

        Sanctum::actingAs($clubEditor);
        $this->getJson('/api/v1/sponsor-management')
            ->assertOk()
            ->assertJsonPath('data.0.id', $clubSponsor->id)
            ->assertJsonPath('data.0.can_edit', true)
            ->assertJsonPath('data.0.can_delete', false)
            ->assertJsonPath('can.create_global', false);
        $this->putJson("/api/v1/sponsor-management/{$platformSponsor->id}", [
            'scope' => 'platform',
            'name' => 'Forbidden Platform Move',
        ])->assertForbidden();
        $this->deleteJson("/api/v1/sponsor-management/{$clubSponsor->id}")->assertForbidden();

        Sanctum::actingAs($clubDeleter);
        $this->getJson('/api/v1/sponsor-management')
            ->assertOk()
            ->assertJsonPath('data.0.can_edit', false)
            ->assertJsonPath('data.0.can_delete', true)
            ->assertJsonPath('can.create', false);
        $this->putJson("/api/v1/sponsor-management/{$clubSponsor->id}", [
            'scope' => 'club',
            'club_id' => $club->id,
            'name' => 'Forbidden Edit',
        ])->assertForbidden();
        $this->deleteJson("/api/v1/sponsor-management/{$clubSponsor->id}")->assertOk();
    }

    public function test_sponsor_finance_contract_readiness_blocks_untrusted_approval_and_allows_complete_profile(): void
    {
        $reviewer = User::factory()->create();
        $service = app(RevenueTrustService::class);
        $incomplete = Sponsor::query()->create([
            'scope' => 'platform',
            'name' => 'Incomplete Contract Partner',
            'verification_version' => RevenueTrustService::CONTRACT_VERSION,
        ]);

        try {
            $candidate = clone $incomplete;
            $candidate->forceFill([
                'verification_status' => 'verified',
                'verified_by' => $reviewer->id,
                'verified_at' => now(),
            ]);
            $service->ensureSponsorApprovable($candidate);
            $this->fail('Incomplete sponsor contract readiness should block verification.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('verification_status', $exception->errors());
        }

        $complete = Sponsor::query()->create([
            'scope' => 'platform',
            'name' => 'Complete Contract Partner',
            'legal_name' => 'Complete Contract Partner GmbH',
            'country_code' => 'DE',
            'email' => 'contracts@example.test',
            'website' => 'https://contracts.example.test',
            'accepted_rules' => ['legal_accuracy' => true, 'data_privacy' => true],
            'verification_version' => RevenueTrustService::CONTRACT_VERSION,
        ]);

        $readiness = $service->sponsorReadiness($complete);

        $this->assertTrue($readiness['can_approve']);
        $this->assertSame([], $readiness['blocks']);
        $service->ensureSponsorApprovable($complete);
    }

    public function test_sponsor_contract_approval_requires_second_person_finance_authority(): void
    {
        $this->seed(RolesPermissionsSeeder::class);
        $clubOwner = User::factory()->create();
        $sponsorEditor = User::factory()->create();
        $financeApprover = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $clubOwner->id]);
        $club->users()->attach([
            $sponsorEditor->id => [
                'role' => 'member',
                'roles' => ['member'],
                'membership_status' => 'active',
                'permission_overrides' => [ClubPermissions::SPONSORS_EDIT => true],
            ],
            $financeApprover->id => [
                'role' => 'member',
                'roles' => ['member'],
                'membership_status' => 'active',
                'permission_overrides' => [ClubPermissions::SPONSORS_EDIT => true, ClubPermissions::FINANCE_APPROVE => true],
            ],
        ]);
        $this->activateClubSponsors($club);

        Sanctum::actingAs($sponsorEditor);
        $sponsorId = $this->postJson('/api/v1/sponsor-management', [
            'scope' => 'club',
            'club_id' => $club->id,
            'name' => 'Protected Club Sponsor',
            'package_code' => 'silver',
            'rights_package' => ['court-banner', 'newsletter'],
            'contract_approval_status' => 'pending_review',
        ])
            ->assertCreated()
            ->assertJsonPath('data.contract_approval_status', 'pending_review')
            ->json('data.id');

        $this->putJson("/api/v1/sponsor-management/{$sponsorId}", [
            'scope' => 'club',
            'club_id' => $club->id,
            'name' => 'Protected Club Sponsor',
            'package_code' => 'silver',
            'contract_approval_status' => 'approved',
        ])->assertForbidden();

        $sponsor = Sponsor::query()->findOrFail($sponsorId);
        $sponsor->forceFill(['contract_submitted_by' => $financeApprover->id])->save();

        Sanctum::actingAs($financeApprover);
        $this->putJson("/api/v1/sponsor-management/{$sponsorId}", [
            'scope' => 'club',
            'club_id' => $club->id,
            'name' => 'Protected Club Sponsor',
            'package_code' => 'silver',
            'contract_approval_status' => 'approved',
        ])->assertStatus(422);

        $sponsor->forceFill(['contract_submitted_by' => $sponsorEditor->id])->save();
        $this->putJson("/api/v1/sponsor-management/{$sponsorId}", [
            'scope' => 'club',
            'club_id' => $club->id,
            'name' => 'Protected Club Sponsor',
            'package_code' => 'silver',
            'contract_approval_status' => 'approved',
        ])
            ->assertOk()
            ->assertJsonPath('data.contract_approval_status', 'approved')
            ->assertJsonPath('data.contract_approved_by', $financeApprover->id);
    }

    private function permissions(array $names)
    {
        return collect($names)->map(fn (string $name) => Permission::findOrCreate($name, 'web'))->all();
    }

    private function activateClubSponsors(Club $club): void
    {
        $plan = SubscriptionPlan::query()->firstOrCreate(
            ['slug' => 'club'],
            [
                'target_actor' => 'verein',
                'name' => 'Club',
                'monthly_price_cents' => 2990,
                'yearly_price_cents' => 29900,
                'currency' => 'EUR',
                'features' => [],
                'sort_order' => 1,
                'is_public' => true,
                'is_active' => true,
            ],
        );
        $club->currentSubscription()->updateOrCreate([], [
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
            'billing_interval' => 'monthly',
        ]);
    }
}
