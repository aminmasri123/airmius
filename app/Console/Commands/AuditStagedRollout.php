<?php

namespace App\Console\Commands;

use App\Support\StagedRolloutReadinessReport;
use Illuminate\Console\Command;

final class AuditStagedRollout extends Command
{
    protected $signature = 'airmius:audit-staged-rollout
        {--json : Print machine-readable JSON}
        {--strict : Fail while real stage-observation evidence is open}';

    protected $description = 'Audit stateless 0/5/25/100 rollout gates without printing identifiers, buckets, or secrets';

    public function handle(StagedRolloutReadinessReport $reporter): int
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

        return $this->option('strict') && $report['decision'] !== 'go' ? self::FAILURE : self::SUCCESS;
    }
}
