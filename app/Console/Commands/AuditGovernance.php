<?php

namespace App\Console\Commands;

use App\Support\GovernanceReadinessReport;
use Illuminate\Console\Command;

final class AuditGovernance extends Command
{
    protected $signature = 'airmius:audit-governance
        {--json : Print machine-readable, data-minimized JSON}
        {--strict : Fail while Legal, DPIA, or independent penetration-test approval remains open}';

    protected $description = 'Audit version-bound Legal, DPIA, and independent penetration-test release assurance';

    public function handle(GovernanceReadinessReport $reporter): int
    {
        $report = $reporter->make();

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
