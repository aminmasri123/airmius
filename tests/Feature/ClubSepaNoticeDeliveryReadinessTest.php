<?php

namespace Tests\Feature;

use App\Support\ClubSepaNoticeDeliveryReadinessReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClubSepaNoticeDeliveryReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_repository_audit_is_non_mutating_and_strict_mode_requires_runtime_and_evidence(): void
    {
        $before = $this->counts();

        $report = app(ClubSepaNoticeDeliveryReadinessReport::class)->make();

        $this->assertTrue($report['automated_checks_passed']);
        $this->assertSame('repository-only', $report['mode']);
        $this->assertSame('no-go', $report['decision']);
        $this->assertNull($report['inventory']);
        $this->assertSame($before, $this->counts());
        $this->artisan('airmius:audit-club-sepa-notice-delivery', ['--strict' => true])->assertFailed();
    }

    public function test_runtime_audit_accepts_postmark_and_worker_queue_without_exposing_secrets(): void
    {
        $this->configureHealthyRuntime();
        $secretValues = [
            'server-token-confidential',
            'callback-user-confidential',
            'callback-password-confidential-value',
        ];
        $before = $this->counts();

        $report = app(ClubSepaNoticeDeliveryReadinessReport::class)->make(true);
        $encoded = json_encode($report, JSON_THROW_ON_ERROR);

        $this->assertTrue($report['automated_checks_passed']);
        $this->assertSame('runtime-read-only', $report['mode']);
        $this->assertSame('no-go', $report['decision']);
        $this->assertSame([
            'webhook_ip_allowlist_enabled' => false,
            'notices_awaiting_provider_feedback' => 0,
            'notices_reported_delivered' => 0,
            'notices_reported_bounced' => 0,
            'provider_events' => 0,
        ], $report['inventory']);
        $this->assertSame($before, $this->counts());
        foreach ($secretValues as $secret) {
            $this->assertStringNotContainsString($secret, $encoded);
        }
        $this->assertCount(3, $report['limitations']);
        $this->assertStringContainsString('require reviewed staging evidence', $report['limitations'][1]);
    }

    public function test_runtime_audit_rejects_non_durable_queue_and_incomplete_credentials_without_disclosure(): void
    {
        config([
            'airmius_mail.senders.billing.mailer' => 'postmark',
            'queue.default' => 'sync',
            'services.postmark.key' => 'short-secret',
            'services.postmark.webhook_username' => 'same-value-secret',
            'services.postmark.webhook_password' => 'same-value-secret',
        ]);

        $report = app(ClubSepaNoticeDeliveryReadinessReport::class)->make(true);
        $encoded = json_encode($report, JSON_THROW_ON_ERROR);

        $this->assertFalse($report['automated_checks_passed']);
        $this->assertSame('no-go', $report['decision']);
        $this->assertSame('fail', $this->statusFor($report, 'runtime.postmark_credentials'));
        $this->assertSame('fail', $this->statusFor($report, 'runtime.queue_connection'));
        $this->assertStringNotContainsString('short-secret', $encoded);
        $this->assertStringNotContainsString('same-value-secret', $encoded);
    }

    public function test_strict_audit_accepts_only_complete_versioned_staging_evidence(): void
    {
        $this->configureHealthyRuntime();
        $path = tempnam(sys_get_temp_dir(), 'club-sepa-delivery-evidence-');
        file_put_contents($path, json_encode([
            'contract' => ClubSepaNoticeDeliveryReadinessReport::CONTRACT,
            'status' => 'passed',
            'environment' => 'staging',
            'migration' => ['backup_verified' => true, 'dry_run_passed' => true, 'rollback_rehearsed' => true],
            'runtime' => ['postmark_configured' => true, 'queue_worker_verified' => true],
            'surfaces' => array_fill_keys(ClubSepaNoticeDeliveryReadinessReport::SURFACES, 'passed'),
            'journeys' => array_fill_keys(ClubSepaNoticeDeliveryReadinessReport::JOURNEYS, 'passed'),
            'approvals' => ['finance_owner' => true, 'engineering' => true],
            'evidence_references' => ['SEPA-MAIL-QA-2026-09-25', 'DEVICE-RUN-04'],
        ], JSON_THROW_ON_ERROR));

        try {
            $report = app(ClubSepaNoticeDeliveryReadinessReport::class)->make(true, $path);
            $this->assertSame('go', $report['decision']);
            $this->artisan('airmius:audit-club-sepa-notice-delivery', [
                '--with-runtime' => true,
                '--evidence' => $path,
                '--strict' => true,
            ])->assertSuccessful();

            file_put_contents($path, str_replace('DEVICE-RUN-04', 'https://private.test/report', (string) file_get_contents($path)));
            $this->assertSame('no-go', app(ClubSepaNoticeDeliveryReadinessReport::class)->make(true, $path)['decision']);

            foreach (['550e8400-e29b-41d4-a716-446655440000', str_repeat('a', 64)] as $providerLikeReference) {
                $evidence = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
                $evidence['evidence_references'] = [$providerLikeReference];
                file_put_contents($path, json_encode($evidence, JSON_THROW_ON_ERROR));
                $this->assertSame('no-go', app(ClubSepaNoticeDeliveryReadinessReport::class)->make(true, $path)['decision']);
            }
        } finally {
            @unlink($path);
        }
    }

    public function test_active_sender_override_is_evaluated_as_the_effective_billing_mailer(): void
    {
        $this->configureHealthyRuntime();
        DB::table('mail_sender_settings')->insert([
            'category' => 'billing',
            'mailer' => 'smtp_backup',
            'from_address' => 'billing@example.test',
            'from_name' => 'Billing',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $report = app(ClubSepaNoticeDeliveryReadinessReport::class)->make(true);

        $this->assertFalse($report['automated_checks_passed']);
        $this->assertSame('fail', $this->statusFor($report, 'runtime.billing_mailer'));
        $this->assertStringNotContainsString('billing@example.test', json_encode($report, JSON_THROW_ON_ERROR));
    }

    public function test_legacy_evidence_cannot_hide_unverified_webhook_registration_or_proxy_handling(): void
    {
        $this->configureHealthyRuntime();
        $path = tempnam(sys_get_temp_dir(), 'club-sepa-delivery-v1-');
        file_put_contents($path, json_encode([
            'contract' => 'club-sepa-notice-delivery-rollout.v1',
            'status' => 'passed',
            'environment' => 'staging',
            'migration' => ['backup_verified' => true, 'dry_run_passed' => true, 'rollback_rehearsed' => true],
            'runtime' => ['postmark_configured' => true, 'queue_worker_verified' => true],
            'surfaces' => array_fill_keys(ClubSepaNoticeDeliveryReadinessReport::SURFACES, 'passed'),
            'journeys' => array_fill_keys(array_values(array_diff(
                ClubSepaNoticeDeliveryReadinessReport::JOURNEYS,
                ['provider_webhook_registration', 'trusted_proxy_ip_handling'],
            )), 'passed'),
            'approvals' => ['finance_owner' => true, 'engineering' => true],
            'evidence_references' => ['LEGACY-SEPA-MAIL-QA'],
        ], JSON_THROW_ON_ERROR));

        try {
            $report = app(ClubSepaNoticeDeliveryReadinessReport::class)->make(true, $path);
            $this->assertSame('no-go', $report['decision']);
            $this->assertSame('pending', $this->statusFor($report, 'local.evidence'));
        } finally {
            @unlink($path);
        }
    }

    private function configureHealthyRuntime(): void
    {
        config([
            'airmius_mail.senders.billing.mailer' => 'postmark',
            'queue.default' => 'database',
            'services.postmark.key' => 'server-token-confidential',
            'services.postmark.webhook_username' => 'callback-user-confidential',
            'services.postmark.webhook_password' => 'callback-password-confidential-value',
        ]);
    }

    private function counts(): array
    {
        return collect(['club_sepa_notices', 'club_sepa_notice_provider_events', 'jobs', 'failed_jobs'])
            ->mapWithKeys(fn (string $table) => [$table => DB::table($table)->count()])
            ->all();
    }

    private function statusFor(array $report, string $id): string
    {
        return collect($report['checks'])->firstWhere('id', $id)['status'];
    }
}
