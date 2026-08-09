<?php

namespace App\Console\Commands;

use App\Support\StagingHttpDeliveryReport;
use Illuminate\Console\Command;

final class AuditStagingHttpDelivery extends Command
{
    protected $signature = 'airmius:audit-staging-http
        {--base-url= : Root HTTPS URL of the exact staging release}
        {--origin=https://app.airmius.com : Allowed browser origin for CORS verification}
        {--guest-order-path= : Relative live tokenized guest-order path; never printed}
        {--guest-order-path-file= : Preferred: read the tokenized path from a local protected file; filename and content are never printed}
        {--timeout=10 : Per-request timeout in seconds (2–30)}
        {--json : Print machine-readable, data-minimized JSON}
        {--strict : Fail while the live guest-order check is pending}';

    protected $description = 'Audit production-like HTTP, proxy, cache, CORS, partial guest loading, and tokenized guest-order delivery without printing sensitive values';

    public function handle(StagingHttpDeliveryReport $reporter): int
    {
        $report = $reporter->make(
            (string) $this->option('base-url'),
            $this->guestOrderPath(),
            (string) $this->option('origin'),
            (int) $this->option('timeout'),
        );

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            $this->table(
                ['Check', 'Status', 'Detail'],
                collect($report['checks'])->map(static fn (array $check): array => [
                    $check['id'],
                    strtoupper($check['status']),
                    $check['detail'],
                ])->all(),
            );
        }

        if (! $report['automated_checks_passed']) {
            return self::FAILURE;
        }

        return $this->option('strict') && ! $report['evidence_complete']
            ? self::FAILURE
            : self::SUCCESS;
    }

    private function guestOrderPath(): ?string
    {
        if (filled($this->option('guest-order-path'))) {
            return (string) $this->option('guest-order-path');
        }

        $file = trim((string) $this->option('guest-order-path-file'));
        if ($file === '') {
            return null;
        }
        if (! is_file($file) || ! is_readable($file)) {
            return '/invalid-protected-guest-order-input';
        }

        $value = file_get_contents($file);

        return is_string($value) ? trim($value) : '/invalid-protected-guest-order-input';
    }
}
