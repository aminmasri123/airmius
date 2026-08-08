<?php

namespace App\Console\Commands;

use App\Support\ReleaseReadinessReport;
use Illuminate\Console\Command;

class ReleasePreflight extends Command
{
    protected $signature = 'airmius:release-preflight
        {--json : Print machine-readable JSON.}
        {--strict : Fail when evidence is pending or an environment gate is skipped.}
        {--with-operations : Include live database, queue, webhook, mail, push, and backup checks.}';

    protected $description = 'Run the resource-efficient AIRMIUS release Go/No-Go preflight without exposing secrets or personal data.';

    public function handle(ReleaseReadinessReport $reporter): int
    {
        $report = $reporter->make((bool) $this->option('with-operations'));

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            $this->components->info('AIRMIUS release preflight');
            $this->table(
                ['Area', 'Status', 'Owner', 'Gate', 'Detail'],
                collect($report['checks'])->map(fn (array $check): array => [
                    $check['area'],
                    strtoupper($check['status']),
                    $check['owner'],
                    $check['title'],
                    $check['detail'],
                ])->all(),
            );
            $this->line(sprintf(
                'Decision: %s | pass %d | warn %d | pending %d | skipped %d | fail %d',
                strtoupper($report['decision']),
                $report['summary']['pass'],
                $report['summary']['warn'],
                $report['summary']['pending'],
                $report['summary']['skipped'],
                $report['summary']['fail'],
            ));
        }

        if ($report['summary']['fail'] > 0) {
            return self::FAILURE;
        }

        if ($this->option('strict') && ($report['summary']['pending'] > 0 || $report['summary']['skipped'] > 0)) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
