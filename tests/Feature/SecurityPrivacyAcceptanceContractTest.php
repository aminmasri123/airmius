<?php

namespace Tests\Feature;

use App\Support\CriticalJourneyRegistry;
use App\Support\Privacy\DataClassification;
use App\Support\Privacy\ProcessingPurpose;
use App\Support\SecurityPrivacyAcceptanceRegistry;
use App\Support\SecurityPrivacyReadinessReport;
use Illuminate\Console\Command;
use Tests\TestCase;

class SecurityPrivacyAcceptanceContractTest extends TestCase
{
    public function test_registry_covers_data_classes_purposes_guest_surfaces_and_incident_owners(): void
    {
        $registry = SecurityPrivacyAcceptanceRegistry::definitions();

        $this->assertSame('security-privacy-acceptance.v1', $registry['contract']);
        $this->assertSame(
            array_column(DataClassification::cases(), 'value'),
            $registry['classifications'],
        );
        $this->assertSame(
            array_column(ProcessingPurpose::cases(), 'value'),
            $registry['purposes'],
        );
        $this->assertGreaterThanOrEqual(5, count($registry['guest_controls']));
        $this->assertGreaterThanOrEqual(6, count($registry['incident_drill']['roles']));
        $this->assertGreaterThanOrEqual(6, count($registry['incident_drill']['stages']));
        $this->assertTrue($registry['notices_and_consents']['versioned_notices_required']);
        $this->assertTrue($registry['notices_and_consents']['purpose_bound_consent_required']);
        $this->assertContains('notice_version', $registry['notices_and_consents']['evidence_fields']);
        $this->assertTrue($registry['retention_governance']['legal_hold_blocks_deletion']);
        $this->assertTrue($registry['retention_governance']['four_eyes_release_required_for_irreversible_delete']);
        $this->assertSame('airmius.processor-management.v1', $registry['processors']['contract']);
        $this->assertContains('review_due_at', $registry['processors']['required_fields']);
        $this->assertTrue($registry['security_controls']['secret_values_never_printed']);
        $this->assertTrue($registry['security_controls']['audit_integrity_hash_chain_required']);
        $this->assertTrue($registry['incident_drill']['classification_required']);
        $this->assertTrue($registry['incident_drill']['notification_decision_required']);
        $this->assertFalse($registry['incident_drill']['stores_personal_data']);
        $this->assertFalse($registry['incident_drill']['stores_secrets']);
        $this->assertSame(['dpia_approval', 'external_penetration_test'], $registry['external_gates']);
        $this->assertCount(5, CriticalJourneyRegistry::definitions());
    }

    public function test_every_control_artifact_exists_and_retention_job_is_safe_to_rehearse(): void
    {
        $registry = SecurityPrivacyAcceptanceRegistry::definitions();

        foreach (['technical_controls', 'guest_controls'] as $group) {
            foreach ($registry[$group] as $control) {
                foreach (array_merge($control['sources'], $control['tests']) as $path) {
                    $this->assertFileExists(base_path($path));
                }
            }
        }

        foreach ($registry['retention'] as $policy) {
            $source = file_get_contents(base_path($policy['command_source']));
            $this->assertIsString($source);
            $this->assertStringContainsString('{--dry-run', $source);
            $this->assertTrue($policy['bounded']);
            $this->assertTrue($policy['dry_run']);
        }

        $this->assertFileExists(base_path($registry['processors']['source']));
        foreach ($registry['security_controls']['control_sources'] as $path) {
            $this->assertFileExists(base_path($path));
        }
    }

    public function test_repository_drill_passes_without_approving_external_evidence(): void
    {
        $report = SecurityPrivacyReadinessReport::make();

        $this->assertTrue($report['automated_checks_passed']);
        $this->assertFalse($report['external_evidence_complete']);
        $this->assertSame('no_go', $report['decision']);
        $this->assertSame(['dpia_approval', 'external_penetration_test'], $report['external_gates']['pending']);
        $this->assertSame(0, $report['summary']['fail']);
        $this->assertFalse($report['privacy']['stores_personal_data']);
        $this->assertFalse($report['privacy']['stores_secrets']);
    }

    public function test_audit_command_supports_ci_json_and_strict_external_gate_mode(): void
    {
        $this->artisan('airmius:audit-security-privacy', ['--json' => true])
            ->expectsOutputToContain('"automated_checks_passed": true')
            ->assertExitCode(Command::SUCCESS);

        $this->artisan('airmius:audit-security-privacy', ['--strict' => true])
            ->assertExitCode(Command::FAILURE);
    }
}
