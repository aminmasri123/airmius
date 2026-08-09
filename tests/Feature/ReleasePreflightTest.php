<?php

namespace Tests\Feature;

use App\Support\ReleaseReadinessReport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ReleasePreflightTest extends TestCase
{
    public function test_quick_preflight_passes_automated_repository_checks_and_reports_manual_work(): void
    {
        $exitCode = Artisan::call('airmius:release-preflight', ['--json' => true]);
        $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertSame('no_go', $report['decision']);
        $this->assertTrue($report['automated_checks_passed']);
        $this->assertFalse($report['release_evidence_complete']);
        $this->assertSame(0, $report['summary']['fail']);
        $this->assertGreaterThan(0, $report['summary']['pending']);
        $this->assertSame('skipped', $this->check($report, 'deployment.production_configuration')['status']);
        $this->assertSame('pass', $this->check($report, 'repository.localization')['status']);
        $this->assertSame('pass', $this->check($report, 'repository.web_build')['status']);
        $this->assertSame('pass', $this->check($report, 'repository.security_privacy')['status']);
        $this->assertSame('pass', $this->check($report, 'repository.club_pilot')['status']);
        $this->assertSame('pass', $this->check($report, 'repository.staged_rollout')['status']);
    }

    public function test_strict_preflight_blocks_when_external_evidence_is_pending(): void
    {
        $this->artisan('airmius:release-preflight', ['--strict' => true, '--json' => true])
            ->assertExitCode(Command::FAILURE);
    }

    public function test_valid_production_configuration_passes_without_printing_values_or_secrets(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        config([
            'app.name' => 'Airmius',
            'app.url' => 'https://app.airmius.test',
            'app.key' => 'base64:release-secret-that-must-not-be-printed',
            'app.debug' => false,
            'app.locale' => 'de',
            'app.fallback_locale' => 'de',
            'queue.default' => 'database',
            'cache.default' => 'database',
            'mail.default' => 'smtp',
            'mail.mailers.smtp.password' => 'smtp-secret-that-must-not-be-printed',
            'session.secure' => true,
            'session.http_only' => true,
            'airmius_monitoring.performance.enabled' => true,
        ]);

        $report = $this->app->make(ReleaseReadinessReport::class)->make();
        $encoded = json_encode($report, JSON_THROW_ON_ERROR);

        $this->assertSame('pass', $this->check($report, 'deployment.production_configuration')['status']);
        $this->assertStringNotContainsString('release-secret-that-must-not-be-printed', $encoded);
        $this->assertStringNotContainsString('smtp-secret-that-must-not-be-printed', $encoded);
        $this->assertFalse($report['privacy']['stores_secrets']);
        $this->assertFalse($report['privacy']['stores_personal_data']);
    }

    public function test_unsafe_production_configuration_fails_with_setting_names_only(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        config([
            'app.name' => 'Laravel',
            'app.url' => 'http://private-host.test',
            'app.key' => 'do-not-print-this-key',
            'app.debug' => true,
            'queue.default' => 'sync',
            'cache.default' => 'array',
            'mail.default' => 'log',
            'session.secure' => false,
            'airmius_monitoring.performance.enabled' => false,
        ]);

        $exitCode = Artisan::call('airmius:release-preflight', ['--json' => true]);
        $output = Artisan::output();
        $report = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
        $check = $this->check($report, 'deployment.production_configuration');

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertSame('fail', $check['status']);
        $this->assertStringContainsString('APP_URL_HTTPS', $check['detail']);
        $this->assertStringContainsString('SESSION_COOKIE_SECURITY', $check['detail']);
        $this->assertStringNotContainsString('private-host.test', $output);
        $this->assertStringNotContainsString('do-not-print-this-key', $output);
    }

    public function test_platform_manifest_has_unique_owned_evidence_gates(): void
    {
        $manifest = json_decode(
            file_get_contents(base_path('resources/release/platform_release_gates.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $ids = collect($manifest['gates'])->pluck('id');

        $this->assertCount(12, $manifest['gates']);
        $this->assertCount($ids->count(), $ids->unique());
        $this->assertContains('wcag_human_acceptance', $ids);
        $this->assertContains('mysql_query_plans', $ids);
        $this->assertContains('native_localization_qa', $ids);
        $this->assertContains('dpia_approval', $ids);
        $this->assertContains('external_penetration_test', $ids);
        $this->assertContains('club_pilot', $ids);
        $this->assertContains('staged_rollout', $ids);
        $this->assertContains('external_observability', $ids);

        foreach ($manifest['gates'] as $gate) {
            $this->assertNotEmpty($gate['owner']);
            $this->assertNotEmpty($gate['required_evidence']);
            $this->assertIsArray($gate['evidence']);
        }
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    private function check(array $report, string $id): array
    {
        $check = collect($report['checks'])->firstWhere('id', $id);

        $this->assertIsArray($check, "Missing release preflight check: {$id}");

        return $check;
    }
}
