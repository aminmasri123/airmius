<?php

namespace App\Console\Commands;

use App\Support\ClubPilotReadinessReport;
use Illuminate\Console\Command;

class AuditClubPilot extends Command
{
    protected $signature = 'airmius:audit-club-pilot
        {--with-data : Check only aggregate readiness for configured pilot clubs}
        {--json : Print machine-readable JSON}
        {--strict : Fail while runtime or external pilot evidence is open}';

    protected $description = 'Audit the privacy-safe three-to-five-club pilot contract without printing club or user identifiers';

    public function handle(ClubPilotReadinessReport $reporter): int
    {
        $report = $reporter->make((bool) $this->option('with-data'));

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
