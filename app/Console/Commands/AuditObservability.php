<?php

namespace App\Console\Commands;

use App\Support\ObservabilityReadinessReport;
use Illuminate\Console\Command;

final class AuditObservability extends Command
{
    protected $signature = 'airmius:audit-observability
        {--with-runtime : Run the bounded operations monitor in staging}
        {--json : Print machine-readable, data-minimized JSON}
        {--strict : Fail while runtime or external dashboard/alert evidence is open}';

    protected $description = 'Audit versioned SLOs, guest performance, runtime health, and privacy-safe external monitoring evidence';

    public function handle(ObservabilityReadinessReport $reporter): int
    {
        $report = $reporter->make((bool) $this->option('with-runtime'));

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

        return $this->option('strict') && $report['decision'] !== 'go'
            ? self::FAILURE
            : self::SUCCESS;
    }
}
