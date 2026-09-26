<?php

namespace Tests\Feature;

use App\Support\ClubPublicNetworkReadinessContract;
use Tests\TestCase;

class ClubPublicNetworkReadinessContractTest extends TestCase
{
    public function test_meta_exposes_public_profiles_discovery_cooperation_sponsor_and_isolation_contract(): void
    {
        $contract = $this->getJson('/api/v1/meta')
            ->assertOk()
            ->json('data.catalogs.club_public_network_readiness');

        $this->assertSame(ClubPublicNetworkReadinessContract::VERSION, $contract['version']);
        $this->assertSame('local_contract_ready_external_security_gates_open', $contract['decision']);

        $this->assertTrue($contract['public_profiles']['optional_publication_only']);
        $this->assertTrue($contract['public_profiles']['separate_public_projection_required']);
        $this->assertTrue($contract['public_profiles']['consent_required_for_people_profiles']);
        $this->assertContains('billing', $contract['public_profiles']['internal_fields_excluded']);
        $this->assertTrue($contract['public_profiles']['withdrawal_unpublishes_projection']);

        $this->assertTrue($contract['trainer_discovery']['uses_published_projection_only']);
        $this->assertTrue($contract['trainer_discovery']['trial_training_requires_opt_in']);
        $this->assertContains('public_self_service_throttle', $contract['trainer_discovery']['spam_protection']);
        $this->assertTrue($contract['trainer_discovery']['no_private_schedule_or_roster_exposure']);

        $this->assertTrue($contract['cross_club_cooperation']['requires_bilateral_approval']);
        $this->assertTrue($contract['cross_club_cooperation']['requires_data_sharing_agreement']);
        $this->assertTrue($contract['cross_club_cooperation']['responsibilities_are_separate_per_club']);
        $this->assertContains('approved_by_partner_club', $contract['cross_club_cooperation']['audit_events']);

        $this->assertTrue($contract['sponsor_contact']['opt_in_initiation_only']);
        $this->assertSame('Sponsor::publiclyVerified', $contract['sponsor_contact']['public_sponsor_source']);
        $this->assertTrue($contract['sponsor_contact']['internal_crm_hidden']);
        $this->assertTrue($contract['sponsor_contact']['member_data_never_disclosed']);

        $this->assertTrue($contract['public_internal_isolation']['public_projection_only']);
        $this->assertContains('private_files_not_in_public_search', $contract['public_internal_isolation']['negative_tests_required']);
        $this->assertContains('MultiClubPlatformIsolationReport', $contract['public_internal_isolation']['local_contract_sources']);
        $this->assertSame('pending', $contract['public_internal_isolation']['external_security_review']);
        $this->assertTrue($contract['public_internal_isolation']['independent_acceptance_required_before_release']);
    }
}
