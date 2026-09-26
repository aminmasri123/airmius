<?php

namespace Tests\Feature;

use App\Support\AiGovernanceCatalog;
use App\Support\AiSafetyReadinessReport;
use App\Support\ReleaseSeparationReadinessReport;
use Tests\TestCase;

class AiGovernanceCatalogTest extends TestCase
{
    public function test_meta_exposes_ai_governance_controls_without_secrets_or_self_execution(): void
    {
        $catalog = $this->getJson('/api/v1/meta')
            ->assertOk()
            ->json('data.catalogs.ai_governance');

        $this->assertSame(AiGovernanceCatalog::VERSION, $catalog['version']);
        $this->assertTrue($catalog['controls']['requires_feature_opt_in']);
        $this->assertTrue($catalog['controls']['requires_request_logging']);
        $this->assertContains('iban', $catalog['controls']['redacts_sensitive_fields']);
        $this->assertGreaterThan(0, $catalog['controls']['monthly_cost_limit_cents']);

        foreach ($catalog['providers'] as $provider) {
            $this->assertArrayNotHasKey('api_key', $provider);
            $this->assertFalse($provider['stores_api_key']);
        }

        $features = collect($catalog['features'])->keyBy('key');
        foreach (['receipt_booking_suggestions', 'operations_suggestions', 'training_plan_generation'] as $key) {
            $this->assertTrue($features[$key]['requires_human_confirmation']);
            $this->assertFalse($features[$key]['can_self_execute']);
            $this->assertTrue($features[$key]['explains_confidence']);
        }

        $this->assertSame('manual_confirmation_required', $features['receipt_booking_suggestions']['execution_mode']);
        $this->assertSame('explainable_non_executing_suggestions', $features['operations_suggestions']['execution_mode']);
    }

    public function test_meta_exposes_test_staging_production_separation_without_production_release_claim(): void
    {
        $report = $this->getJson('/api/v1/meta')
            ->assertOk()
            ->json('data.catalogs.release_separation');

        $this->assertSame(ReleaseSeparationReadinessReport::VERSION, $report['version']);
        $this->assertTrue($report['environments']['test']['uses_anonymized_or_factory_data']);
        $this->assertTrue($report['environments']['staging']['requires_separate_credentials']);
        $this->assertTrue($report['environments']['staging']['allows_provider_sandbox_only']);
        $this->assertFalse($report['environments']['production']['automatic_release_allowed']);
        $this->assertFalse($report['evidence_policy']['local_evidence_can_release_production']);
        $this->assertSame('external_pending_gate', $report['release_gates']['production_go_live']);
        $this->assertSame('no_go_until_external_gates_pass', $report['decision']);
    }

    public function test_meta_exposes_ai_safety_readiness_without_privacy_approval_claim(): void
    {
        $report = $this->getJson('/api/v1/meta')
            ->assertOk()
            ->json('data.catalogs.ai_safety_readiness');

        $this->assertSame(AiSafetyReadinessReport::VERSION, $report['version']);
        $this->assertSame('no_go_until_privacy_and_provider_gates_pass', $report['decision']);
        $this->assertFalse($report['evidence_policy']['stores_prompts']);
        $this->assertFalse($report['evidence_policy']['stores_provider_secrets']);
        $this->assertFalse($report['evidence_policy']['local_tests_can_grant_privacy_approval']);
        $this->assertSame('pending', $report['external_gates']['privacy_review']);
        $this->assertSame('pending', $report['external_gates']['production_prompt_red_team']);

        $controls = collect($report['automated_controls'])->keyBy('key');
        foreach (['prompt_injection', 'data_exfiltration', 'cross_tenant_leakage', 'hallucination', 'cost_control', 'provider_outage'] as $key) {
            $this->assertSame('pass', $controls[$key]['status']);
            $this->assertNotEmpty($controls[$key]['evidence']);
        }

        $this->assertContains('foreign_club_context_rejected', $controls['cross_tenant_leakage']['evidence']);
        $this->assertContains('fallback_provider_order_declared', $controls['provider_outage']['evidence']);
    }
}
