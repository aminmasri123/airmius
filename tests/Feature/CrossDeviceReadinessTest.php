<?php

namespace Tests\Feature;

use App\Support\CrossDeviceAcceptanceRegistry;
use App\Support\CrossDeviceReadinessReport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class CrossDeviceReadinessTest extends TestCase
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

    public function test_repository_contract_is_green_while_physical_and_human_evidence_stays_open(): void
    {
        config(['airmius_cross_device.evidence_path' => $this->missingTemporaryPath()]);

        $report = app(CrossDeviceReadinessReport::class)->make();

        $this->assertSame('cross-device-experience.v1', $report['contract']);
        $this->assertSame('2026-08-09', $report['release_version']);
        $this->assertSame('1.0.71+157', $report['mobile_version']);
        $this->assertSame('no_go', $report['decision']);
        $this->assertTrue($report['automated_checks_passed']);
        $this->assertFalse($report['external_evidence_complete']);
        $this->assertSame(['pass' => 5, 'pending' => 5, 'fail' => 0], $report['summary']);
        foreach (['repository.contract', 'repository.artifacts', 'repository.real_device_validator', 'repository.evidence_template', 'repository.mobile_manifest'] as $id) {
            $this->assertSame('pass', $this->checkStatus($report, $id));
        }
        foreach (['local.evidence', 'mobile.authoritative_evidence', 'external.mobile_real_devices', 'external.native_localization', 'external.wcag_human_acceptance'] as $id) {
            $this->assertSame('pending', $this->checkStatus($report, $id));
        }
        $this->assertSame(Command::SUCCESS, Artisan::call('airmius:audit-cross-device', ['--json' => true]));
        $this->assertSame(Command::FAILURE, Artisan::call('airmius:audit-cross-device', ['--json' => true, '--strict' => true]));
    }

    public function test_complete_local_mobile_and_platform_evidence_can_pass_without_echoing_references(): void
    {
        $evidence = $this->writeJson($this->passedEvidence());
        $mobileManifest = $this->writeJson($this->mobileManifest('passed'));
        $platformManifest = $this->writeJson($this->platformManifest('passed'));
        config([
            'airmius_cross_device.evidence_path' => $evidence,
            'airmius_cross_device.mobile_manifest_path' => $mobileManifest,
            'airmius_cross_device.platform_gate_path' => $platformManifest,
        ]);

        $report = app(CrossDeviceReadinessReport::class)->make();
        $encoded = json_encode($report, JSON_THROW_ON_ERROR);

        $this->assertSame('go', $report['decision'], $encoded);
        $this->assertTrue($report['automated_checks_passed']);
        $this->assertTrue($report['external_evidence_complete']);
        $this->assertSame(['pass' => 10, 'pending' => 0, 'fail' => 0], $report['summary']);
        foreach ($report['privacy'] as $value) {
            $this->assertFalse($value);
        }
        foreach (['PLATFORM-android', 'LOCALE-ar_rtl', 'JOURNEY-recruiting_handoff', 'A11Y-voiceover', 'REVIEW-CROSS-DEVICE-2026'] as $reference) {
            $this->assertStringNotContainsString($reference, $encoded);
        }

        $exitCode = Artisan::call('airmius:audit-cross-device', ['--json' => true, '--strict' => true]);
        $output = Artisan::output();
        $this->assertSame(Command::SUCCESS, $exitCode, $output);
        foreach (['PLATFORM-android', 'REVIEW-CROSS-DEVICE-2026', $evidence, $mobileManifest, $platformManifest] as $privateValue) {
            $this->assertStringNotContainsString($privateValue, $output);
        }
    }

    public function test_pending_authoritative_mobile_gate_cannot_be_promoted_by_complete_local_evidence(): void
    {
        config([
            'airmius_cross_device.evidence_path' => $this->writeJson($this->passedEvidence()),
            'airmius_cross_device.mobile_manifest_path' => $this->writeJson($this->mobileManifest('pending_execution')),
            'airmius_cross_device.platform_gate_path' => $this->writeJson($this->platformManifest('passed')),
        ]);

        $report = app(CrossDeviceReadinessReport::class)->make();

        $this->assertTrue($report['automated_checks_passed']);
        $this->assertFalse($report['external_evidence_complete']);
        $this->assertSame('no_go', $report['decision']);
        $this->assertSame('pass', $this->checkStatus($report, 'local.evidence'));
        $this->assertSame('pending', $this->checkStatus($report, 'mobile.authoritative_evidence'));
    }

    public function test_identifier_free_text_or_raw_url_evidence_fails_closed_without_echoing_values(): void
    {
        $evidence = $this->passedEvidence();
        $evidence['tester'] = 'Private Person';
        $evidence['device_id'] = 'private-device-987654';
        $evidence['screenshot_url'] = 'https://private.example/?token=secret';
        config(['airmius_cross_device.evidence_path' => $this->writeJson($evidence)]);

        $report = app(CrossDeviceReadinessReport::class)->make();
        $encoded = json_encode($report, JSON_THROW_ON_ERROR);

        $this->assertFalse($report['automated_checks_passed']);
        $this->assertSame('fail', $this->checkStatus($report, 'local.evidence'));
        foreach (['Private Person', 'private-device-987654', 'private.example', 'token=secret'] as $privateValue) {
            $this->assertStringNotContainsString($privateValue, $encoded);
        }
    }

    public function test_shell_validator_rejects_legacy_short_evidence_and_accepts_exact_19_item_contract(): void
    {
        $android = $this->writeText("Result: PASS\n- [x] Login\n- [x] Push\n- [x] Upload\n- [x] Deep link\n- [x] Private review\n");
        $ios = $this->writeText("Result: PASS\n- [x] Login\n- [x] Push\n- [x] Upload\n- [x] Deep link\n- [x] Private review\n");
        $legacy = $this->deviceValidator($android, $ios);

        $this->assertFalse($legacy->isSuccessful());
        $this->assertStringContainsString('missing version-bound metadata', $legacy->getOutput());

        file_put_contents($android, $this->completeDeviceMarkdown('android', 'ANDROID-DEVICE-EVIDENCE'));
        file_put_contents($ios, $this->completeDeviceMarkdown('ios', 'IOS-DEVICE-EVIDENCE'));
        $complete = $this->deviceValidator($android, $ios);

        $this->assertTrue($complete->isSuccessful(), $complete->getOutput().$complete->getErrorOutput());
        $this->assertStringContainsString('Real device smoke evidence check passed.', $complete->getOutput());
    }

    public function test_passed_evidence_for_an_older_app_build_cannot_approve_the_current_build(): void
    {
        foreach (['1.0.42+127', '1.0.65+150'] as $oldVersion) {
            $evidence = $this->passedEvidence();
            $evidence['mobile_version'] = $oldVersion;
            config([
                'airmius_cross_device.evidence_path' => $this->writeJson($evidence),
                'airmius_cross_device.mobile_manifest_path' => $this->writeJson($this->mobileManifest('passed')),
                'airmius_cross_device.platform_gate_path' => $this->writeJson($this->platformManifest('passed')),
            ]);
            $report = app(CrossDeviceReadinessReport::class)->make();
            $this->assertSame('fail', $this->checkStatus($report, 'local.evidence'));
            $this->assertSame('no_go', $report['decision']);
            $this->assertFalse($report['automated_checks_passed']);
            $this->assertSame(Command::FAILURE, Artisan::call('airmius:audit-cross-device', ['--json' => true, '--strict' => true]));
        }
    }

    public function test_device_validator_rejects_complete_checklists_for_old_builds(): void
    {
        $ios = $this->writeText($this->completeDeviceMarkdown('ios', 'IOS-TEST-EVIDENCE'));
        foreach (['1.0.42+127', '1.0.65+150'] as $oldVersion) {
            $android = $this->writeText(str_replace(CrossDeviceAcceptanceRegistry::MOBILE_VERSION, $oldVersion, $this->completeDeviceMarkdown('android', 'ANDROID-TEST-EVIDENCE')));
            $process = $this->deviceValidator($android, $ios);
            $this->assertFalse($process->isSuccessful());
            $this->assertStringContainsString('missing version-bound metadata', $process->getOutput());
        }
    }

    public function test_old_authoritative_mobile_manifest_cannot_approve_current_local_evidence(): void
    {
        $manifest = $this->mobileManifest('passed');
        $manifest['version'] = '1.0.65+150';
        config([
            'airmius_cross_device.evidence_path' => $this->writeJson($this->passedEvidence()),
            'airmius_cross_device.mobile_manifest_path' => $this->writeJson($manifest),
            'airmius_cross_device.platform_gate_path' => $this->writeJson($this->platformManifest('passed')),
        ]);
        $report = app(CrossDeviceReadinessReport::class)->make();
        $this->assertSame('fail', $this->checkStatus($report, 'repository.mobile_manifest'));
        $this->assertSame('fail', $this->checkStatus($report, 'mobile.authoritative_evidence'));
        $this->assertSame('no_go', $report['decision']);
        $this->assertFalse($report['automated_checks_passed']);
        $this->assertFalse($report['external_evidence_complete']);
    }

    public function test_current_template_and_authoritative_manifest_do_not_claim_new_acceptance(): void
    {
        $template = json_decode((string) file_get_contents(base_path('resources/release/cross_device_evidence.template.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(CrossDeviceAcceptanceRegistry::MOBILE_VERSION, $template['mobile_version']);
        $this->assertSame('pending', $template['status']);
        $this->assertSame('', $template['review_reference']);
        foreach (['platforms', 'locales', 'journeys', 'assistive_technology'] as $dimension) {
            foreach ($template[$dimension] as $entry) {
                $this->assertSame('pending', $entry['status']);
                $this->assertSame('', $entry['evidence_reference']);
            }
        }
        config(['airmius_cross_device.evidence_path' => $this->missingTemporaryPath()]);
        $report = app(CrossDeviceReadinessReport::class)->make();
        $this->assertTrue($report['automated_checks_passed']);
        $this->assertSame('no_go', $report['decision']);
        $this->assertSame('pending', $this->checkStatus($report, 'mobile.authoritative_evidence'));
    }

    /** @return array<string, mixed> */
    private function passedEvidence(): array
    {
        return [
            'contract' => CrossDeviceAcceptanceRegistry::CONTRACT,
            'release_version' => '2026-08-09',
            'mobile_version' => CrossDeviceAcceptanceRegistry::MOBILE_VERSION,
            'status' => 'passed',
            'review_reference' => 'REVIEW-CROSS-DEVICE-2026',
            'platforms' => $this->passedDimension(CrossDeviceAcceptanceRegistry::PLATFORM_KEYS, 'PLATFORM'),
            'locales' => $this->passedDimension(CrossDeviceAcceptanceRegistry::LOCALE_KEYS, 'LOCALE'),
            'journeys' => $this->passedDimension(CrossDeviceAcceptanceRegistry::JOURNEY_KEYS, 'JOURNEY'),
            'assistive_technology' => $this->passedDimension(CrossDeviceAcceptanceRegistry::ASSISTIVE_KEYS, 'A11Y'),
            'privacy' => [
                'stores_personal_data' => false,
                'stores_device_identifiers' => false,
                'stores_credentials' => false,
                'stores_raw_urls_or_paths' => false,
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
    private function mobileManifest(string $status): array
    {
        return [
            'product' => 'Airmius Mobile',
            'version' => CrossDeviceAcceptanceRegistry::MOBILE_VERSION,
            'cross_device_audit_command' => 'php artisan airmius:audit-cross-device --json --strict',
            'gates' => collect(CrossDeviceAcceptanceRegistry::MOBILE_GATE_IDS)
                ->map(static fn (string $id): array => [
                    'id' => $id,
                    'owner' => 'Release QA',
                    'status' => $status,
                    'required_evidence' => 'Version-bound cross-device evidence.',
                    'evidence' => ['MOBILE-'.$id],
                ])->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function platformManifest(string $status): array
    {
        return [
            'gates' => collect(['mobile_real_devices', 'native_localization_qa', 'wcag_human_acceptance'])
                ->map(static fn (string $id): array => [
                    'id' => $id,
                    'owner' => 'Owner',
                    'status' => $status,
                    'required_evidence' => 'Reviewed evidence.',
                    'evidence' => ['PLATFORM-'.$id],
                ])->all(),
        ];
    }

    private function completeDeviceMarkdown(string $platform, string $reference): string
    {
        $keys = [
            'CDX-01-release-build', 'CDX-02-login-secure-session', 'CDX-03-push-delivery-target',
            'CDX-04-event-file-access', 'CDX-05-deep-links', 'CDX-06-route-training-event',
            'CDX-07-event-training-log', 'CDX-08-recruiting-profile-consent',
            'CDX-09-recruiting-chat-handoff', 'CDX-10-refund-duplicate-submit',
            'CDX-11-payout-reconciliation', 'CDX-12-gps-ownership', 'CDX-13-locale-de',
            'CDX-14-locale-en', 'CDX-15-locale-fr', 'CDX-16-locale-ar-rtl',
            'CDX-17-assistive-technology', 'CDX-18-text-scale-200', 'CDX-19-privacy-review',
        ];

        return implode("\n", [
            'Contract: cross-device-experience.v1',
            'Release: 2026-08-09',
            'Mobile build: 1.0.71+157',
            'Platform: '.$platform,
            'Evidence reference: '.$reference,
            'Result: PASS',
            ...array_map(static fn (string $key): string => '- [x] '.$key, $keys),
            '',
        ]);
    }

    private function deviceValidator(string $android, string $ios): Process
    {
        $process = new Process([
            'bash',
            base_path('mobile/airmius_mobile/scripts/assert_real_device_smoke_evidence.sh'),
            '--android',
            $android,
            '--ios',
            $ios,
        ], base_path('mobile/airmius_mobile'));
        $process->run();

        return $process;
    }

    /** @param array<string, mixed> $data */
    private function writeJson(array $data): string
    {
        return $this->writeText(json_encode($data, JSON_THROW_ON_ERROR));
    }

    private function writeText(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'airmius-cross-device-');
        $this->assertIsString($path);
        file_put_contents($path, $contents);
        $this->temporaryFiles[] = $path;

        return $path;
    }

    private function missingTemporaryPath(): string
    {
        return sys_get_temp_dir().'/airmius-cross-device-missing-'.bin2hex(random_bytes(8)).'.json';
    }

    /** @param array<string, mixed> $report */
    private function checkStatus(array $report, string $id): string
    {
        $check = collect($report['checks'])->firstWhere('id', $id);
        $this->assertIsArray($check, "Missing cross-device check: {$id}");

        return $check['status'];
    }
}
