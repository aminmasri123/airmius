<?php

namespace App\Support;

use App\Models\MailDelivery;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Throwable;

class OperationsMonitor
{
    /**
     * @return array{window_hours:int,status:string,checks:array<int,array<string,mixed>>,summary:array<string,int>}
     */
    public function run(?int $hours = null): array
    {
        $windowHours = max(1, $hours ?: (int) config('airmius_monitoring.window_hours', 24));
        $since = now()->subHours($windowHours);

        $databaseAvailable = $this->databaseAvailable();

        $checks = array_merge(
            $this->errorChecks($since),
            [$this->databaseCheck($databaseAvailable)],
            $this->queueChecks($since, $databaseAvailable),
            $this->jobChecks(),
            $this->webhookChecks(),
            $this->mailChecks($since, $databaseAvailable),
        );

        $failures = collect($checks)->where('status', 'fail')->count();
        $warnings = collect($checks)->where('status', 'warn')->count();

        return [
            'window_hours' => $windowHours,
            'status' => $failures > 0 ? 'fail' : ($warnings > 0 ? 'warn' : 'ok'),
            'checks' => $checks,
            'summary' => [
                'ok' => collect($checks)->where('status', 'ok')->count(),
                'warn' => $warnings,
                'fail' => $failures,
            ],
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function errorChecks(Carbon $since): array
    {
        $recentErrors = $this->recentApplicationErrors($since);
        $maxErrors = (int) config('airmius_monitoring.errors.max_recent_errors', 0);

        $checks = [
            $this->check(
                'errors',
                'recent_application_errors',
                $recentErrors <= $maxErrors ? 'ok' : 'fail',
                $recentErrors,
                "Maximum allowed: {$maxErrors}"
            ),
        ];

        if (app()->environment('production') && (bool) config('airmius_monitoring.errors.require_external_monitoring_in_production', true)) {
            $checks[] = $this->check(
                'errors',
                'external_error_monitoring',
                filled(config('airmius_monitoring.errors.external_dsn')) ? 'ok' : 'warn',
                filled(config('airmius_monitoring.errors.external_dsn')) ? 1 : 0,
                'ERROR_MONITORING_DSN should be configured before production launch.'
            );
        }

        return $checks;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function databaseCheck(bool $available): array
    {
        return $this->check(
            'database',
            'connection',
            $available ? 'ok' : 'fail',
            $available ? 1 : 0,
            $available ? 'Database connection is available.' : 'Database connection is unavailable; dependent checks cannot run.'
        );
    }

    private function databaseAvailable(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function queueChecks(Carbon $since, bool $databaseAvailable): array
    {
        if (! $databaseAvailable) {
            return [
                $this->check(
                    'queue',
                    'database_dependent_checks',
                    'fail',
                    0,
                    'Queue tables and failed jobs cannot be checked without a database connection.'
                ),
            ];
        }

        $checks = [];

        foreach (config('airmius_monitoring.queue.required_tables', []) as $table) {
            $exists = Schema::hasTable($table);

            $checks[] = $this->check(
                'queue',
                'table_'.$table,
                $exists ? 'ok' : 'fail',
                $exists ? 1 : 0,
                $exists ? 'Table exists.' : 'Queue table is missing.'
            );
        }

        $failedJobs = Schema::hasTable('failed_jobs')
            ? DB::table('failed_jobs')->where('failed_at', '>=', $since)->count()
            : 0;
        $maxFailedJobs = (int) config('airmius_monitoring.queue.max_failed_jobs', 0);

        $checks[] = $this->check(
            'queue',
            'recent_failed_jobs',
            $failedJobs <= $maxFailedJobs ? 'ok' : 'fail',
            $failedJobs,
            "Maximum allowed: {$maxFailedJobs}"
        );

        $staleAfterMinutes = max(1, (int) config('airmius_monitoring.queue.stale_after_minutes', 15));
        $staleJobs = Schema::hasTable('jobs')
            ? DB::table('jobs')->where('created_at', '<=', now()->subMinutes($staleAfterMinutes)->timestamp)->count()
            : 0;
        $maxStaleJobs = (int) config('airmius_monitoring.queue.max_stale_jobs', 0);

        $checks[] = $this->check(
            'queue',
            'stale_jobs',
            $staleJobs <= $maxStaleJobs ? 'ok' : 'fail',
            $staleJobs,
            "Older than {$staleAfterMinutes} minutes. Maximum allowed: {$maxStaleJobs}"
        );

        if (app()->environment('production') && (bool) config('airmius_monitoring.queue.warn_sync_queue_in_production', true)) {
            $checks[] = $this->check(
                'queue',
                'queue_connection',
                config('queue.default') === 'sync' ? 'warn' : 'ok',
                config('queue.default'),
                'Production should use a worker-backed queue connection.'
            );
        }

        return $checks;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function jobChecks(): array
    {
        $availableCommands = array_keys(Artisan::all());

        return collect(config('airmius_monitoring.jobs.required_commands', []))
            ->map(fn (string $command) => $this->check(
                'jobs',
                'command_'.$command,
                in_array($command, $availableCommands, true) ? 'ok' : 'fail',
                in_array($command, $availableCommands, true) ? 1 : 0,
                in_array($command, $availableCommands, true) ? 'Command is registered.' : 'Command is missing.'
            ))
            ->values()
            ->all();
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function webhookChecks(): array
    {
        $checks = collect(config('airmius_monitoring.webhooks.required_routes', []))
            ->map(fn (string $route) => $this->check(
                'webhooks',
                'route_'.$route,
                Route::has($route) ? 'ok' : 'fail',
                Route::has($route) ? 1 : 0,
                Route::has($route) ? 'Route is registered.' : 'Webhook route is missing.'
            ))
            ->values()
            ->all();

        if ((bool) config('airmius_monitoring.webhooks.warn_missing_provider_secrets', true)) {
            $checks[] = $this->check(
                'webhooks',
                'stripe_webhook_secret',
                filled(config('services.stripe.webhook_secret')) ? 'ok' : 'warn',
                filled(config('services.stripe.webhook_secret')) ? 1 : 0,
                'STRIPE_WEBHOOK_SECRET should be set before live payments.'
            );

            $paypalWebhookIds = collect([
                config('services.paypal.webhook_id'),
                config('services.paypal.commerce_webhook_id'),
                config('services.paypal.outfit_webhook_id'),
            ])->filter()->count();

            $checks[] = $this->check(
                'webhooks',
                'paypal_webhook_ids',
                $paypalWebhookIds > 0 ? 'ok' : 'warn',
                $paypalWebhookIds,
                'At least one PayPal webhook ID should be set before live PayPal payments.'
            );
        }

        return $checks;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function mailChecks(Carbon $since, bool $databaseAvailable): array
    {
        if (! $databaseAvailable) {
            return [
                $this->check(
                    'mail',
                    'database_dependent_checks',
                    'fail',
                    0,
                    'Mail delivery failures cannot be checked without a database connection.'
                ),
            ];
        }

        $failedDeliveries = Schema::hasTable('mail_deliveries')
            ? MailDelivery::query()
                ->where('status', 'failed')
                ->where('created_at', '>=', $since)
                ->count()
            : 0;
        $maxFailedDeliveries = (int) config('airmius_monitoring.mail.max_failed_deliveries', 0);

        $checks = [
            $this->check(
                'mail',
                'failed_deliveries',
                $failedDeliveries <= $maxFailedDeliveries ? 'ok' : 'fail',
                $failedDeliveries,
                "Maximum allowed: {$maxFailedDeliveries}"
            ),
        ];

        foreach (config('airmius_mail.senders', []) as $category => $sender) {
            $mailer = $sender['mailer'] ?? null;
            $checks[] = $this->check(
                'mail',
                'mailer_'.$category,
                $mailer && config("mail.mailers.{$mailer}") ? 'ok' : 'fail',
                $mailer ?: '',
                $mailer ? 'Mailer configuration exists.' : 'Sender has no mailer.'
            );
        }

        if (app()->environment('production') && (bool) config('airmius_monitoring.mail.fail_log_mailer_in_production', true)) {
            $checks[] = $this->check(
                'mail',
                'default_mailer',
                in_array(config('mail.default'), ['log', 'array'], true) ? 'fail' : 'ok',
                config('mail.default'),
                'Production must not use log or array as default mailer.'
            );
        }

        return $checks;
    }

    private function recentApplicationErrors(Carbon $since): int
    {
        return collect($this->logFiles())
            ->sum(fn (string $file) => $this->countRecentErrorsInFile($file, $since));
    }

    /**
     * @return array<int,string>
     */
    private function logFiles(): array
    {
        return collect(config('airmius_monitoring.errors.log_file_patterns', []))
            ->flatMap(function (string $pattern) {
                $matches = glob($pattern);

                return $matches === false || $matches === [] ? [$pattern] : $matches;
            })
            ->filter(fn (string $file) => is_file($file) && is_readable($file))
            ->unique()
            ->values()
            ->all();
    }

    private function countRecentErrorsInFile(string $file, Carbon $since): int
    {
        $contents = $this->tail($file, max(4096, (int) config('airmius_monitoring.errors.scan_bytes', 262144)));

        preg_match_all('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\].*\.(EMERGENCY|ALERT|CRITICAL|ERROR):/', $contents, $matches, PREG_SET_ORDER);

        return collect($matches)
            ->filter(fn (array $match) => Carbon::createFromFormat('Y-m-d H:i:s', $match[1])->gte($since))
            ->count();
    }

    private function tail(string $file, int $bytes): string
    {
        $handle = fopen($file, 'rb');

        if ($handle === false) {
            return '';
        }

        try {
            $size = filesize($file) ?: 0;
            $offset = max(0, $size - $bytes);

            if ($offset > 0) {
                fseek($handle, $offset);
            }

            return stream_get_contents($handle) ?: '';
        } finally {
            fclose($handle);
        }
    }

    /**
     * @return array{area:string,key:string,status:string,value:mixed,detail:string}
     */
    private function check(string $area, string $key, string $status, mixed $value, string $detail): array
    {
        return [
            'area' => $area,
            'key' => $key,
            'status' => $status,
            'value' => $value,
            'detail' => $detail,
        ];
    }
}
