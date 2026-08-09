<?php

namespace App\Console\Commands;

use App\Support\DatabaseQueryPlanReport;
use Illuminate\Console\Command;

final class AuditDatabaseQueryPlans extends Command
{
    protected $signature = 'airmius:audit-query-plans
        {--connection= : Configured staging database connection; its name is never emitted}
        {--analyze : Execute bounded EXPLAIN ANALYZE plans; permitted only in staging/testing}
        {--online-ddl-reference= : Non-sensitive version-bound DBA artifact identifier; its value is never emitted}
        {--json : Print machine-readable, data-minimized JSON}
        {--strict : Fail while MySQL/MariaDB analysis, optimizer review, or DDL evidence is pending}';

    protected $description = 'Audit bounded critical query plans and exact index contracts without printing SQL, bindings, plan bodies, connection details, or personal data';

    public function handle(DatabaseQueryPlanReport $reporter): int
    {
        $report = $reporter->make(
            filled($this->option('connection')) ? (string) $this->option('connection') : null,
            (bool) $this->option('analyze'),
            filled($this->option('online-ddl-reference')) ? (string) $this->option('online-ddl-reference') : null,
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
}
