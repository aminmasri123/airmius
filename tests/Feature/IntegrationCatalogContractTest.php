<?php

namespace Tests\Feature;

use App\Support\IntegrationCatalog;
use Tests\TestCase;

class IntegrationCatalogContractTest extends TestCase
{
    public function test_api_meta_exposes_canonical_integration_contracts(): void
    {
        $response = $this->getJson('/api/v1/meta')->assertOk();

        $catalog = $response->json('data.catalogs.integrations');

        $this->assertSame(IntegrationCatalog::VERSION, $catalog['version']);
        $this->assertSame('encrypted_reference_only', $catalog['security_baseline']['credentials']);
        $this->assertSame('least_privilege_per_capability', $catalog['security_baseline']['scopes']);
        $this->assertSame('required_per_provider_policy', $catalog['security_baseline']['credential_rotation']);
        $this->assertSame('required_for_write_events', $catalog['security_baseline']['webhook_signatures']);
        $this->assertSame('provider_and_tenant_bound', $catalog['security_baseline']['rate_limits']);
        $this->assertTrue($catalog['adapter_standard']['encrypted_credentials']);
        $this->assertTrue($catalog['adapter_standard']['rotation_supported']);
        $this->assertTrue($catalog['adapter_standard']['webhook_signature_verification']);
        $this->assertTrue($catalog['sync_observability']['cursor_required']);
        $this->assertContains('quarantined', $catalog['sync_observability']['per_record_statuses']);
        $this->assertTrue($catalog['portable_export']['checksums_required']);
        $this->assertTrue($catalog['portable_export']['relationships_required']);
        $this->assertTrue($catalog['test_matrix']['contract_tests']);
        $this->assertTrue($catalog['test_matrix']['timeouts']);
        $this->assertSame('external_gate', $catalog['test_matrix']['provider_staging_evidence']);

        $contracts = collect($catalog['contracts'])->keyBy('key');

        foreach ([
            'generic_import',
            'portable_export',
            'bank',
            'calendar',
            'mail',
            'association',
            'access_control',
            'ticketing',
            'pos',
            'time_tracking',
        ] as $key) {
            $this->assertTrue($contracts->has($key), "Missing integration contract [{$key}].");
        }

        $this->assertContains('preview', $contracts['generic_import']['capabilities']);
        $this->assertContains('field_mapping', $contracts['generic_import']['capabilities']);
        $this->assertContains('atomic_commit', $contracts['generic_import']['capabilities']);
        $this->assertContains('manifest', $contracts['portable_export']['capabilities']);
        $this->assertContains('checksums', $contracts['portable_export']['capabilities']);
        $this->assertContains('signature_verification', $contracts['mail']['capabilities']);
    }

    public function test_integration_contracts_are_tenant_scoped_and_operationally_retryable(): void
    {
        foreach (IntegrationCatalog::forClient()['contracts'] as $contract) {
            $this->assertTrue($contract['tenant_scoped'], $contract['key'].' must be tenant scoped.');
            $this->assertSame('bounded_exponential_backoff', $contract['retry_policy']);
            $this->assertContains('conflict', $contract['failure_states']);
            $this->assertContains('duplicate', $contract['failure_states']);
            $this->assertContains('quarantined', $contract['failure_states']);
            $this->assertNotContains('api_key', $contract['required_fields']);
            $this->assertNotContains('password', $contract['required_fields']);
        }
    }
}
