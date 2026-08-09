<?php

namespace Tests\Feature;

use App\Support\ObservabilityAcceptanceRegistry;
use App\Support\ObservabilityReadinessReport;
use App\Support\OperationsMonitor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Mockery\MockInterface;
use Tests\TestCase;

final class ObservabilityReadinessTest extends TestCase
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

    public function test_repository_contract_is_green_but_strict_mode_keeps_external_evidence_open(): void
    {
        config(['airmius_observability.evidence_path' => $this->missingTemporaryPath()]);

        $report = app(ObservabilityReadinessReport::class)->make();

        $this->assertSame('observability-slo-readiness.v1', $report['contract']);
        $this->assertSame('repository', $report['mode']);
        $this->assertSame('no_go', $report['decision']);
        $this->assertTrue($report['automated_checks_passed']);
        $this->assertFalse($report['external_evidence_complete']);
        $this->assertSame('pass', $this->checkStatus($report, 'repository.contract'));
        $this->assertSame('pass', $this->checkStatus($report, 'repository.artifacts'));
        $this->assertSame('pass', $this->checkStatus($report, 'repository.scheduler'));
        $this->assertSame('skipped', $this->checkStatus($report, 'runtime.operations'));
        $this->assertSame('pending', $this->checkStatus($report, 'local.evidence'));
        $this->assertSame('pending', $this->checkStatus($report, 'external.observability'));
        $this->assertSame(Command::SUCCESS, Artisan::call('airmius:audit-observability', ['--json' => true]));
        $this->assertSame(Command::FAILURE, Artisan::call('airmius:audit-observability', ['--json' => true, '--strict' => true]));

        $signals = ObservabilityAcceptanceRegistry::definitions()['signals'];
        $this->assertSame(2500, data_get($signals, 'guest_core_web_vitals.objectives.maximum_lcp_p75_ms'));
        $this->assertSame(200, data_get($signals, 'guest_core_web_vitals.objectives.maximum_inp_p75_ms'));
        $this->assertSame(0.1, data_get($signals, 'guest_core_web_vitals.objectives.maximum_cls_p75'));
        $this->assertSame(
            ['guest.vereine', 'guest.marketplace', 'guest.e-learning'],
            data_get($signals, 'guest_core_web_vitals.coverage'),
        );
    }

    public function test_complete_runtime_local_and_external_evidence_can_pass_without_echoing_references(): void
    {
        $evidence = $this->writeJson($this->passedEvidence());
        $manifest = $this->writeJson([
            'gates' => [[
                'id' => 'external_observability',
                'status' => 'passed',
                'owner' => 'SRE',
                'required_evidence' => 'Version-bound SLO evidence.',
                'evidence' => ['OBS-GATE-2026'],
                'reviewed_by' => 'SRE Lead',
                'reviewed_at' => now()->utc()->toIso8601String(),
            ]],
        ]);
        config([
            'airmius_observability.evidence_path' => $evidence,
            'airmius_observability.platform_gate_path' => $manifest,
            'airmius_monitoring.performance.enabled' => true,
            'airmius_monitoring.backup.enabled' => true,
            'airmius_monitoring.mobile_push.enabled' => true,
            'airmius_monitoring.errors.external_dsn' => 'configured-private-dsn',
            'airmius_backup.disk' => 's3',
            'queue.default' => 'database',
        ]);
        $this->mock(OperationsMonitor::class, function (MockInterface $mock): void {
            $mock->shouldReceive('run')->twice()->andReturn([
                'status' => 'ok',
                'summary' => ['ok' => 20, 'warn' => 0, 'fail' => 0],
                'checks' => [],
            ]);
        });

        $report = app(ObservabilityReadinessReport::class)->make(true);
        $encoded = json_encode($report, JSON_THROW_ON_ERROR);

        $this->assertSame('go', $report['decision'], $encoded);
        $this->assertTrue($report['automated_checks_passed']);
        $this->assertTrue($report['external_evidence_complete']);
        $this->assertSame(['pass' => 6, 'pending' => 0, 'skipped' => 0, 'fail' => 0], $report['summary']);
        $this->assertStringNotContainsString('configured-private-dsn', $encoded);
        $this->assertStringNotContainsString('DASH-availability', $encoded);

        $exitCode = Artisan::call('airmius:audit-observability', [
            '--with-runtime' => true,
            '--json' => true,
            '--strict' => true,
        ]);
        $output = Artisan::output();
        $this->assertSame(Command::SUCCESS, $exitCode, $output);
        foreach (['DASH-availability', 'ALERT-availability', 'VERIFY-availability', 'REVIEW-OBS-2026', $evidence, $manifest] as $privateValue) {
            $this->assertStringNotContainsString($privateValue, $output);
        }
        foreach ($report['privacy'] as $key => $value) {
            if ($key === 'minimum_rum_group_size') {
                $this->assertSame(5, $value);
            } else {
                $this->assertContains($value, [false, true]);
            }
        }
    }

    public function test_evidence_with_identifier_or_raw_url_key_fails_closed_without_echoing_values(): void
    {
        $evidence = $this->passedEvidence();
        $evidence['user_id'] = 987654;
        $evidence['signals']['availability']['dashboard_url'] = 'https://private-monitor.example/?token=secret';
        $evidencePath = $this->writeJson($evidence);
        config(['airmius_observability.evidence_path' => $evidencePath]);

        $report = app(ObservabilityReadinessReport::class)->make();
        $encoded = json_encode($report, JSON_THROW_ON_ERROR);

        $this->assertFalse($report['automated_checks_passed']);
        $this->assertSame('fail', $this->checkStatus($report, 'local.evidence'));
        $this->assertStringNotContainsString('987654', $encoded);
        $this->assertStringNotContainsString('private-monitor', $encoded);
        $this->assertStringNotContainsString('token=secret', $encoded);
    }

    public function test_runtime_mode_requires_every_critical_monitor_before_executing_it(): void
    {
        config([
            'airmius_observability.evidence_path' => $this->missingTemporaryPath(),
            'airmius_monitoring.performance.enabled' => true,
            'airmius_monitoring.backup.enabled' => false,
            'airmius_monitoring.mobile_push.enabled' => false,
            'airmius_monitoring.errors.external_dsn' => null,
            'queue.default' => 'sync',
        ]);
        $this->mock(OperationsMonitor::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('run');
        });

        $report = app(ObservabilityReadinessReport::class)->make(true);

        $this->assertFalse($report['automated_checks_passed']);
        $this->assertSame('fail', $this->checkStatus($report, 'runtime.operations'));
        $this->assertSame(Command::FAILURE, Artisan::call('airmius:audit-observability', ['--with-runtime' => true, '--json' => true]));
    }

    /** @return array<string, mixed> */
    private function passedEvidence(): array
    {
        $signals = [];
        foreach (array_keys(ObservabilityAcceptanceRegistry::definitions()['signals']) as $key) {
            $signals[$key] = [
                'status' => 'passed',
                'dashboard_reference' => 'DASH-'.$key,
                'alert_test_reference' => 'ALERT-'.$key,
                'verification_reference' => 'VERIFY-'.$key,
            ];
        }

        return [
            'contract' => ObservabilityAcceptanceRegistry::CONTRACT,
            'release_version' => '2026-08-09',
            'status' => 'passed',
            'observation_window_hours' => 24,
            'review_reference' => 'REVIEW-OBS-2026',
            'signals' => $signals,
            'privacy' => [
                'stores_personal_data' => false,
                'stores_secrets' => false,
                'stores_raw_urls' => false,
                'stores_request_or_response_data' => false,
                'stores_dashboard_urls' => false,
            ],
        ];
    }

    /** @param array<string, mixed> $data */
    private function writeJson(array $data): string
    {
        $path = tempnam(sys_get_temp_dir(), 'airmius-observability-');
        $this->assertIsString($path);
        file_put_contents($path, json_encode($data, JSON_THROW_ON_ERROR));
        $this->temporaryFiles[] = $path;

        return $path;
    }

    private function missingTemporaryPath(): string
    {
        return sys_get_temp_dir().'/airmius-observability-missing-'.bin2hex(random_bytes(8)).'.json';
    }

    /** @param array<string, mixed> $report */
    private function checkStatus(array $report, string $id): string
    {
        $check = collect($report['checks'])->firstWhere('id', $id);
        $this->assertIsArray($check, "Missing observability check: {$id}");

        return $check['status'];
    }
}
