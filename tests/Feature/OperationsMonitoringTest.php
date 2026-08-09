<?php

namespace Tests\Feature;

use App\Models\MailDelivery;
use App\Support\OperationsMonitor;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class OperationsMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_operations_monitor_passes_for_clean_mvp_state(): void
    {
        $this->configureMonitorLog('operations-monitor-clean.log', [
            '['.now()->format('Y-m-d H:i:s').'] testing.INFO: clean',
        ]);

        $result = app(OperationsMonitor::class)->run(24);

        $this->assertSame('ok', $result['status']);
        $this->assertSame(0, $result['summary']['fail']);
        $this->assertSame(0, $result['summary']['warn']);

        $this
            ->artisan('airmius:monitor-operations', ['--hours' => 24])
            ->assertExitCode(Command::SUCCESS);
    }

    public function test_operations_monitor_fails_on_recent_errors_failed_jobs_and_mail_failures(): void
    {
        $this->configureMonitorLog('operations-monitor-failed.log', [
            '['.now()->format('Y-m-d H:i:s').'] testing.ERROR: payment webhook failed',
        ]);

        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'RuntimeException: queue failed',
            'failed_at' => now(),
        ]);

        MailDelivery::query()->create([
            'dedupe_key' => 'operations-monitor-mail-failed',
            'mail_type' => 'invoice.created',
            'recipient_email' => 'member@example.test',
            'status' => 'failed',
            'primary_category' => 'billing',
            'error_message' => 'SMTP unavailable',
        ]);

        $result = app(OperationsMonitor::class)->run(24);

        $this->assertSame('fail', $result['status']);
        $this->assertSame('fail', $this->statusFor($result, 'recent_application_errors'));
        $this->assertSame('fail', $this->statusFor($result, 'recent_failed_jobs'));
        $this->assertSame('fail', $this->statusFor($result, 'failed_deliveries'));

        $this
            ->artisan('airmius:monitor-operations', ['--hours' => 24])
            ->assertExitCode(Command::FAILURE);
    }

    public function test_operations_monitor_verifies_recent_backup_and_firebase_configuration(): void
    {
        $this->configureMonitorLog('operations-monitor-backup-push.log', [
            '['.now()->format('Y-m-d H:i:s').'] testing.INFO: clean',
        ]);
        Storage::fake('local');
        Storage::disk('local')->put('backups/database/current.sql.json', json_encode([
            'created_at' => now()->toJSON(),
            'database' => ['driver' => 'mysql'],
            'backup' => ['sha256' => str_repeat('a', 64)],
        ]));
        $credentials = tempnam(sys_get_temp_dir(), 'airmius-firebase-test-');
        file_put_contents($credentials, json_encode([
            'type' => 'service_account',
            'project_id' => 'airmius-test',
            'private_key' => 'test-private-key',
            'client_email' => 'push@airmius-test.iam.gserviceaccount.com',
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ]));

        try {
            config([
                'airmius_backup.disk' => 'local',
                'airmius_backup.path' => 'backups/database',
                'airmius_monitoring.backup.enabled' => true,
                'airmius_monitoring.mobile_push.enabled' => true,
                'services.mobile_push.fcm.credentials' => $credentials,
                'services.mobile_push.fcm.project_id' => 'airmius-test',
            ]);

            $result = app(OperationsMonitor::class)->run(24);
            $this->assertSame('ok', $this->statusFor($result, 'latest_backup'));
            $this->assertSame('ok', $this->statusFor($result, 'firebase_configuration'));
            $this->assertSame('ok', $this->statusFor($result, 'stale_deliveries'));
        } finally {
            @unlink($credentials);
        }
    }

    public function test_backup_storage_failure_never_exposes_disk_or_exception_details(): void
    {
        $this->configureMonitorLog('operations-monitor-private-backup.log', [
            '['.now()->format('Y-m-d H:i:s').'] testing.INFO: clean',
        ]);
        config([
            'airmius_monitoring.backup.enabled' => true,
            'airmius_backup.disk' => 'private-observability-secret-disk',
        ]);

        $result = app(OperationsMonitor::class)->run(24);
        $check = collect($result['checks'])->firstWhere('key', 'storage_access');
        $encoded = json_encode($check, JSON_THROW_ON_ERROR);

        $this->assertIsArray($check);
        $this->assertSame('fail', $check['status']);
        $this->assertStringNotContainsString('private-observability-secret-disk', $encoded);
        $this->assertStringNotContainsString('InvalidArgumentException', $encoded);
        $this->assertStringContainsString('details are not emitted', $check['detail']);
    }

    private function configureMonitorLog(string $fileName, array $lines): void
    {
        $path = storage_path('logs/'.$fileName);

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        file_put_contents($path, implode(PHP_EOL, $lines).PHP_EOL);

        config([
            'airmius_monitoring.errors.log_file_patterns' => [$path],
            'airmius_monitoring.errors.require_external_monitoring_in_production' => false,
            'airmius_monitoring.webhooks.warn_missing_provider_secrets' => false,
            'airmius_monitoring.backup.enabled' => false,
            'airmius_monitoring.mobile_push.enabled' => false,
        ]);
    }

    private function statusFor(array $result, string $key): ?string
    {
        return collect($result['checks'])->firstWhere('key', $key)['status'] ?? null;
    }
}
