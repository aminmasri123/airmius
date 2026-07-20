<?php

namespace Tests\Feature;

use App\Models\MailDelivery;
use App\Support\OperationsMonitor;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
        ]);
    }

    private function statusFor(array $result, string $key): ?string
    {
        return collect($result['checks'])->firstWhere('key', $key)['status'] ?? null;
    }
}
