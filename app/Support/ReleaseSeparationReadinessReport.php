<?php

namespace App\Support;

final class ReleaseSeparationReadinessReport
{
    public const VERSION = '2026-09-26.release-separation.v1';

    public static function make(): array
    {
        return [
            'version' => self::VERSION,
            'environments' => [
                'test' => [
                    'purpose' => 'automated_test_runs',
                    'uses_anonymized_or_factory_data' => true,
                    'allows_real_users' => false,
                    'allows_productive_provider_actions' => false,
                ],
                'staging' => [
                    'purpose' => 'release_identical_acceptance',
                    'uses_anonymized_or_seeded_test_data' => true,
                    'allows_real_users' => false,
                    'requires_separate_credentials' => true,
                    'allows_provider_sandbox_only' => true,
                ],
                'production' => [
                    'purpose' => 'live_operation_after_external_release_gates',
                    'uses_anonymized_test_data' => false,
                    'requires_release_gate_manifest' => true,
                    'automatic_release_allowed' => false,
                ],
            ],
            'release_gates' => [
                'technical_tests' => 'automated_repository_checks',
                'staging_acceptance' => 'release_identical_staging_evidence',
                'human_accessibility_acceptance' => 'external_pending_gate',
                'legal_dpia_pentest' => 'external_pending_gate',
                'production_go_live' => 'external_pending_gate',
            ],
            'evidence_policy' => [
                'stores_credentials' => false,
                'stores_personal_data' => false,
                'stores_full_urls_or_raw_payloads' => false,
                'local_evidence_can_release_production' => false,
            ],
            'decision' => 'no_go_until_external_gates_pass',
        ];
    }
}
