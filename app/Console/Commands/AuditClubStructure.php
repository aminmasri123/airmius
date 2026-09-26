<?php

namespace App\Console\Commands;

use App\Support\ClubStructureReadinessReport;
use Illuminate\Console\Command;

final class AuditClubStructure extends Command
{
    protected $signature = 'airmius:audit-club-structure
        {--with-data : Inspect aggregate organization and governance data without changing rows or printing identifiers}
        {--evidence= : Reviewed club-structure rollout evidence JSON}
        {--json : Print machine-readable JSON}
        {--strict : Fail until runtime inspection and reviewed browser/device evidence permit rollout}';

    protected $description = 'Audit club organization, governance references and rollout evidence read-only';

    public function handle(ClubStructureReadinessReport $reporter): int
    {
        $report = $reporter->make((bool) $this->option('with-data'), $this->option('evidence') ?: null);

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            $this->table(
                ['Check', 'Status', 'Detail'],
                collect($report['checks'])->map(fn (array $check) => [$check['id'], strtoupper($check['status']), $check['detail']])->all(),
            );
        }

        if (! $report['automated_checks_passed']) {
            return self::FAILURE;
        }

        return $this->option('strict') && $report['decision'] !== 'go' ? self::FAILURE : self::SUCCESS;
    }
}
