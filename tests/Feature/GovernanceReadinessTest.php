<?php

namespace Tests\Feature;

use App\Support\GovernanceAssuranceRegistry;
use App\Support\GovernanceReadinessReport;
use App\Support\ReleaseReadinessReport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class GovernanceReadinessTest extends TestCase
{
    /** @var array<int, string> */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        parent::tearDown();
    }

    public function test_repository_contract_is_green_while_separated_external_approvals_stay_open(): void
    {
        config(['airmius_governance.evidence_path' => $this->missingTemporaryPath()]);

        $registry = GovernanceAssuranceRegistry::definitions();
        $report = app(GovernanceReadinessReport::class)->make();

        $this->assertSame('governance-assurance.v1', $registry['contract']);
        $this->assertSame(ReleaseReadinessReport::VERSION, $registry['release_version']);
        $this->assertSame([
            'legal_release_approval',
            'dpia_approval',
            'external_penetration_test',
        ], $registry['gate_ids']);
        $this->assertFalse($registry['role_separation']['self_approval_allowed']);
        $this->assertFalse($registry['role_separation']['waiver_allowed']);
        $this->assertContains('public_guest_discovery_checkout_and_token_pages', $registry['penetration_test_scope']);
        $this->assertCount(12, $registry['legal_requirements']);
        $this->assertCount(10, $registry['dpia_processing_families']);
        $this->assertCount(9, $registry['dpia_required_sections']);
        $this->assertCount(16, $registry['penetration_test_scope']);
        $this->assertCount(8, $registry['penetration_test_acceptance']);

        $this->assertSame('no_go', $report['decision']);
        $this->assertTrue($report['automated_checks_passed']);
        $this->assertFalse($report['external_evidence_complete']);
        $this->assertSame(['pass' => 8, 'pending' => 4, 'fail' => 0], $report['summary']);
        foreach ([
            'repository.contract',
            'repository.artifacts',
            'repository.legal_surfaces',
            'repository.legal_validator',
            'repository.dpia_coverage',
            'repository.penetration_test_coverage',
            'repository.evidence_template',
            'repository.platform_manifest',
        ] as $id) {
            $this->assertSame('pass', $this->checkStatus($report, $id));
        }
        foreach ([
            'local.evidence',
            'external.legal_release_approval',
            'external.dpia_approval',
            'external.external_penetration_test',
        ] as $id) {
            $this->assertSame('pending', $this->checkStatus($report, $id));
        }

        $this->assertSame(Command::SUCCESS, Artisan::call('airmius:audit-governance', ['--json' => true]));
        $this->assertSame(Command::FAILURE, Artisan::call('airmius:audit-governance', ['--json' => true, '--strict' => true]));
    }

    public function test_complete_local_and_authoritative_evidence_can_go_without_leaking_references_or_reviewer_identity(): void
    {
        $localEvidence = $this->writeJson($this->passedEvidence());
        $platformManifest = $this->writeJson($this->platformManifest('passed'));
        config([
            'airmius_governance.evidence_path' => $localEvidence,
            'airmius_governance.platform_gate_path' => $platformManifest,
        ]);

        $report = app(GovernanceReadinessReport::class)->make();
        $encoded = json_encode($report, JSON_THROW_ON_ERROR);

        $this->assertSame('go', $report['decision'], $encoded);
        $this->assertTrue($report['automated_checks_passed']);
        $this->assertTrue($report['external_evidence_complete']);
        $this->assertSame(['pass' => 12, 'pending' => 0, 'fail' => 0], $report['summary']);
        foreach ($report['privacy'] as $value) {
            $this->assertFalse($value);
        }
        foreach ([
            'GOV-LEGAL-provider_identity',
            'GOV-DPIA-health_training_nutrition_and_body_data',
            'GOV-PENTEST-public_guest_discovery_checkout_and_token_pages',
            'Private Legal Reviewer',
            'Private DPO Reviewer',
            'Private Security Reviewer',
            $localEvidence,
            $platformManifest,
        ] as $privateValue) {
            $this->assertStringNotContainsString($privateValue, $encoded);
        }

        $exitCode = Artisan::call('airmius:audit-governance', ['--json' => true, '--strict' => true]);
        $output = Artisan::output();
        $this->assertSame(Command::SUCCESS, $exitCode, $output);
        $this->assertStringNotContainsString('Private Legal Reviewer', $output);
        $this->assertStringNotContainsString('GOV-LEGAL-provider_identity', $output);
    }

    public function test_complete_local_preparation_cannot_promote_pending_authoritative_gates(): void
    {
        config([
            'airmius_governance.evidence_path' => $this->writeJson($this->passedEvidence()),
            'airmius_governance.platform_gate_path' => $this->writeJson($this->platformManifest('pending')),
        ]);

        $report = app(GovernanceReadinessReport::class)->make();

        $this->assertTrue($report['automated_checks_passed']);
        $this->assertFalse($report['external_evidence_complete']);
        $this->assertSame('no_go', $report['decision']);
        $this->assertSame('pass', $this->checkStatus($report, 'local.evidence'));
        $this->assertSame('pending', $this->checkStatus($report, 'external.legal_release_approval'));
        $this->assertSame('pending', $this->checkStatus($report, 'external.dpia_approval'));
        $this->assertSame('pending', $this->checkStatus($report, 'external.external_penetration_test'));
    }

    public function test_raw_report_personal_data_or_security_findings_fail_closed_without_echoing_values(): void
    {
        $evidence = $this->passedEvidence();
        $evidence['raw_report_url'] = 'https://private.example/report?token=secret';
        $evidence['reviewer_name'] = 'Private Person';
        $evidence['findings'] = ['Confidential exploit details'];
        config(['airmius_governance.evidence_path' => $this->writeJson($evidence)]);

        $report = app(GovernanceReadinessReport::class)->make();
        $encoded = json_encode($report, JSON_THROW_ON_ERROR);

        $this->assertFalse($report['automated_checks_passed']);
        $this->assertSame('fail', $this->checkStatus($report, 'local.evidence'));
        foreach (['private.example', 'token=secret', 'Private Person', 'Confidential exploit details'] as $privateValue) {
            $this->assertStringNotContainsString($privateValue, $encoded);
        }
    }

    public function test_legal_dpia_or_pentest_waiver_is_never_treated_as_approval(): void
    {
        config([
            'airmius_governance.evidence_path' => $this->missingTemporaryPath(),
            'airmius_governance.platform_gate_path' => $this->writeJson($this->platformManifest('waived')),
        ]);

        $report = app(GovernanceReadinessReport::class)->make();

        $this->assertTrue($report['automated_checks_passed']);
        $this->assertFalse($report['external_evidence_complete']);
        $this->assertSame('no_go', $report['decision']);
        $this->assertSame(3, $report['summary']['fail']);
        foreach ([
            'external.legal_release_approval',
            'external.dpia_approval',
            'external.external_penetration_test',
        ] as $id) {
            $this->assertSame('fail', $this->checkStatus($report, $id));
        }

        $releaseReport = app(ReleaseReadinessReport::class)->make();
        foreach (GovernanceAssuranceRegistry::GATE_IDS as $id) {
            $this->assertSame('fail', $this->releaseCheckStatus($releaseReport, 'external.'.$id));
        }
    }

    public function test_release_management_or_wrong_role_cannot_self_approve_a_governance_gate(): void
    {
        $manifest = $this->platformManifest('passed');
        $manifest['gates'][0]['reviewed_role'] = 'Release Management';
        config([
            'airmius_governance.evidence_path' => $this->missingTemporaryPath(),
            'airmius_governance.platform_gate_path' => $this->writeJson($manifest),
        ]);

        $report = app(GovernanceReadinessReport::class)->make();
        $encoded = json_encode($report, JSON_THROW_ON_ERROR);

        $this->assertFalse($report['automated_checks_passed']);
        $this->assertFalse($report['external_evidence_complete']);
        $this->assertSame('no_go', $report['decision']);
        $this->assertSame('fail', $this->checkStatus($report, 'repository.platform_manifest'));
        $this->assertSame('pending', $this->checkStatus($report, 'external.legal_release_approval'));
        $this->assertStringNotContainsString('Release Management', $encoded);
    }

    public function test_legal_validator_requires_exact_current_release_and_valid_date_without_printing_values(): void
    {
        $identity = [
            'provider_name' => 'Private Provider GmbH',
            'street' => 'Private Street 1',
            'city' => 'Private City',
            'country' => 'Deutschland',
            'email' => 'private-primary@example.test',
            'support_email' => 'private-support@example.test',
            'privacy_email' => 'private-privacy@example.test',
            'legal_email' => 'private-legal@example.test',
            'phone' => '+49 000 000000',
            'representative' => 'Private Representative',
            'register' => 'HRB 12345',
            'vat_id' => 'DE123456789',
            'supervisory_authority' => 'Private Authority',
            'content_responsible' => 'Private Content Owner',
        ];
        foreach ($identity as $key => $value) {
            config(["legal.{$key}" => $value]);
        }
        config([
            'legal.release.approved_by' => 'Private Counsel',
            'legal.release.approved_at' => now()->toDateString(),
            'legal.release.approved_version' => ReleaseReadinessReport::VERSION,
            'legal.release.expected_version' => ReleaseReadinessReport::VERSION,
        ]);

        $exitCode = Artisan::call('airmius:audit-legal-readiness', ['--json' => true]);
        $output = Artisan::output();
        $this->assertSame(Command::SUCCESS, $exitCode, $output);
        $this->assertStringContainsString('"status": "pass"', $output);
        foreach ([...array_values($identity), 'Private Counsel'] as $privateValue) {
            $this->assertStringNotContainsString($privateValue, $output);
        }

        config([
            'legal.release.approved_at' => now()->addDay()->toDateString(),
            'legal.release.approved_version' => 'private-old-release',
            'legal.release.expected_version' => 'private-other-release',
        ]);
        $exitCode = Artisan::call('airmius:audit-legal-readiness', ['--json' => true]);
        $output = Artisan::output();
        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('LEGAL_EXPECTED_VERSION does not match the current release contract.', $output);
        $this->assertStringContainsString('LEGAL_APPROVED_AT must be a valid non-future YYYY-MM-DD date.', $output);
        $this->assertStringNotContainsString('private-old-release', $output);
        $this->assertStringNotContainsString('private-other-release', $output);
    }

    /** @return array<string, mixed> */
    private function passedEvidence(): array
    {
        return [
            'contract' => GovernanceAssuranceRegistry::CONTRACT,
            'release_version' => ReleaseReadinessReport::VERSION,
            'status' => 'passed',
            'review_reference' => 'GOV-REVIEW-2026',
            'tracks' => $this->passedDimension(GovernanceAssuranceRegistry::GATE_IDS, 'GOV-TRACK'),
            'legal_requirements' => $this->passedDimension(GovernanceAssuranceRegistry::LEGAL_KEYS, 'GOV-LEGAL'),
            'dpia_processing_families' => $this->passedDimension(GovernanceAssuranceRegistry::DPIA_PROCESSING_KEYS, 'GOV-DPIA'),
            'dpia_required_sections' => $this->passedDimension(GovernanceAssuranceRegistry::DPIA_SECTION_KEYS, 'GOV-DPIA-SECTION'),
            'penetration_test_scope' => $this->passedDimension(GovernanceAssuranceRegistry::PENTEST_SCOPE_KEYS, 'GOV-PENTEST'),
            'penetration_test_acceptance' => $this->passedDimension(GovernanceAssuranceRegistry::PENTEST_ACCEPTANCE_KEYS, 'GOV-PENTEST-ACCEPT'),
            'privacy' => [
                'stores_personal_data' => false,
                'stores_security_findings' => false,
                'stores_raw_urls_or_paths' => false,
                'stores_secrets_or_credentials' => false,
                'stores_reviewer_identity' => false,
                'stores_free_text' => false,
            ],
        ];
    }

    /** @param array<int, string> $keys @return array<string, array<string, string>> */
    private function passedDimension(array $keys, string $prefix): array
    {
        return collect($keys)->mapWithKeys(static fn (string $key): array => [
            $key => ['status' => 'passed', 'evidence_reference' => $prefix.'-'.$key],
        ])->all();
    }

    /** @return array<string, mixed> */
    private function platformManifest(string $status): array
    {
        $reviewers = [
            'legal_release_approval' => 'Private Legal Reviewer',
            'dpia_approval' => 'Private DPO Reviewer',
            'external_penetration_test' => 'Private Security Reviewer',
        ];
        $reviewRoles = GovernanceAssuranceRegistry::definitions()['role_separation'];
        $hasReviewMetadata = in_array($status, ['passed', 'waived'], true);

        return [
            'version' => ReleaseReadinessReport::VERSION,
            'governance_audit_command' => 'php artisan airmius:audit-governance --json --strict',
            'gates' => collect(GovernanceAssuranceRegistry::GATE_IDS)->map(static function (string $id) use ($status, $reviewers, $reviewRoles, $hasReviewMetadata): array {
                return [
                    'id' => $id,
                    'title' => $id,
                    'owner' => 'Separated Owner',
                    'status' => $status,
                    'required_evidence' => 'Exact-release independent evidence.',
                    'evidence' => $hasReviewMetadata ? ['GOV-AUTH-'.$id] : [],
                    'reviewed_by' => $hasReviewMetadata ? $reviewers[$id] : null,
                    'reviewed_role' => $hasReviewMetadata ? $reviewRoles[$id] : null,
                    'reviewed_at' => $hasReviewMetadata ? now()->utc()->toIso8601String() : null,
                    'waiver_reason' => $status === 'waived' ? 'This must remain rejected.' : null,
                ];
            })->all(),
        ];
    }

    /** @param array<string, mixed> $data */
    private function writeJson(array $data): string
    {
        $path = tempnam(sys_get_temp_dir(), 'airmius-governance-');
        $this->assertIsString($path);
        file_put_contents($path, json_encode($data, JSON_THROW_ON_ERROR));
        $this->temporaryFiles[] = $path;

        return $path;
    }

    private function missingTemporaryPath(): string
    {
        return sys_get_temp_dir().'/airmius-governance-missing-'.bin2hex(random_bytes(8)).'.json';
    }

    /** @param array<string, mixed> $report */
    private function checkStatus(array $report, string $id): string
    {
        $check = collect($report['checks'])->firstWhere('id', $id);
        $this->assertIsArray($check, "Missing governance check: {$id}");

        return $check['status'];
    }

    /** @param array<string, mixed> $report */
    private function releaseCheckStatus(array $report, string $id): string
    {
        $check = collect($report['checks'])->firstWhere('id', $id);
        $this->assertIsArray($check, "Missing release check: {$id}");

        return $check['status'];
    }
}
