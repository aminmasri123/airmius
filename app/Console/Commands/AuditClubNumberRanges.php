<?php

namespace App\Console\Commands;

use App\Support\ClubNumberRangeReadinessReport;
use Illuminate\Console\Command;

final class AuditClubNumberRanges extends Command
{
    protected $signature = 'airmius:audit-club-number-ranges
        {--with-data : Inspect aggregate runtime numbering data without changing rows or printing identifiers}
        {--evidence= : Reviewed number-range rollout evidence JSON}
        {--json : Print machine-readable JSON}
        {--strict : Fail until every adoption gate is complete}';

    protected $description = 'Audit club number ranges, defaults, legacy duplicates and allocation collisions read-only';

    public function handle(ClubNumberRangeReadinessReport $reporter): int
    {
        $report = $reporter->make(
            (bool) $this->option('with-data'),
            $this->option('evidence') ?: null,
        );

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            $this->table(
                ['Check', 'Status', 'Detail'],
                collect($report['checks'])->map(fn (array $check) => [
                    $check['id'], strtoupper($check['status']), $check['detail'],
                ])->all()
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
