<?php

namespace Tests\Feature;

use App\Support\ClubOperationsReadinessCatalog;
use Tests\TestCase;

class ClubOperationsReadinessCatalogTest extends TestCase
{
    public function test_meta_exposes_governance_events_teamwear_clubhouse_and_safeguarding_contracts(): void
    {
        $catalog = $this->getJson('/api/v1/meta')
            ->assertOk()
            ->json('data.catalogs.club_operations_readiness');

        $this->assertSame(ClubOperationsReadinessCatalog::VERSION, $catalog['version']);
        $this->assertSame('local_contract_ready_external_gates_open', $catalog['decision']);

        $this->assertContains('final_immutable', $catalog['governance']['minutes_lifecycle']);
        $this->assertContains('creates_work_task', $catalog['governance']['decision_handover']);
        $this->assertContains('eligibility_cutoff', $catalog['governance']['regression_matrix']);
        $this->assertSame('pending', $catalog['governance']['external_acceptance']);

        $this->assertContains('travel', $catalog['events_travel']['audit_types']);
        $this->assertContains('qr_check_in', $catalog['events_travel']['registration']);
        $this->assertContains('guardian_consent_check', $catalog['events_travel']['minor_and_emergency']);
        $this->assertContains('refund_workflow', $catalog['events_travel']['finance']);

        $this->assertContains('partial_delivery', $catalog['teamwear']['payment_and_fulfillment']);
        $this->assertContains('complaint', $catalog['teamwear']['lifecycle']);
        $this->assertContains('variants', $catalog['teamwear']['regression_matrix']);

        $this->assertContains('hospitality_areas', $catalog['clubhouse_commerce']['bookable_resources']);
        $this->assertContains('daily_close', $catalog['clubhouse_commerce']['cash_register_adapter']);
        $this->assertContains('recurring_order_suggestions', $catalog['clubhouse_commerce']['procurement']);
        $this->assertSame('pending', $catalog['clubhouse_commerce']['external_provider_staging']);

        $this->assertContains('public_visibility', $catalog['safeguarding']['concept']);
        $this->assertContains('secure_upload_required', $catalog['safeguarding']['confidential_channel']);
        $this->assertContains('review_due_at', $catalog['safeguarding']['training_and_evidence']);
        $this->assertContains('claim_report', $catalog['safeguarding']['accident_and_insurance']);
        $this->assertContains('independent_case_assignment', $catalog['safeguarding']['assignment_rules']);
        $this->assertSame('pending', $catalog['safeguarding']['external_privacy_and_domain_acceptance']);
    }
}
