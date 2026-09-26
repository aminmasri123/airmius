<?php

namespace Tests\Feature;

use App\Support\ClubContinuityReadinessCatalog;
use Tests\TestCase;

class ClubContinuityReadinessCatalogTest extends TestCase
{
    public function test_meta_exposes_budget_sponsor_communication_public_document_and_governance_contracts(): void
    {
        $catalog = $this->getJson('/api/v1/meta')
            ->assertOk()
            ->json('data.catalogs.club_continuity_readiness');

        $this->assertSame(ClubContinuityReadinessCatalog::VERSION, $catalog['version']);
        $this->assertSame('local_contract_ready_external_gates_open', $catalog['decision']);

        $this->assertContains('receipt_reference_required', $catalog['budget_grants']['eligible_costs']);
        $this->assertContains('receipt_reuse_warning', $catalog['budget_grants']['duplicate_use_controls']);
        $this->assertContains('verwendungsnachweis', $catalog['budget_grants']['reporting']);
        $this->assertContains('finance_reconciliation', $catalog['budget_grants']['reporting']);

        $this->assertContains('four_eyes_approval', $catalog['sponsors_donations']['donation_receipts']);
        $this->assertContains('supporter_report', $catalog['sponsors_donations']['supporter_reporting']);
        $this->assertContains('legal_tax_acceptance', $catalog['sponsors_donations']['external_gates']);

        $this->assertContains('quiet_hours', $catalog['communications']['quiet_hours_preferences']);
        $this->assertContains('mandatory_message_exception', $catalog['communications']['quiet_hours_preferences']);
        $this->assertContains('guardian_consent_required', $catalog['communications']['minor_safety']);
        $this->assertContains('privacy', $catalog['communications']['regression_matrix']);

        $this->assertContains('approved_contract_benefits_only', $catalog['public_relations']['sponsor_presentation']);
        $this->assertContains('consent_expiry', $catalog['public_relations']['media_library']);
        $this->assertContains('strict_public_projection', $catalog['public_relations']['public_internal_matrix']);

        $this->assertContains('provider_neutral', $catalog['documents_knowledge']['signature_process']);
        $this->assertContains('role_limited_ai_source', $catalog['documents_knowledge']['internal_handbook']);
        $this->assertContains('time_limited_access', $catalog['documents_knowledge']['handover_package']);
        $this->assertContains('inventory_migration', $catalog['documents_knowledge']['regression_matrix']);

        $this->assertContains('eligibility_cutoff', $catalog['governance_regression']['automated_matrix']);
        $this->assertSame('pending', $catalog['governance_regression']['external_acceptance']);
    }
}
