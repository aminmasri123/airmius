<?php

namespace App\Console\Commands;

use App\Support\SecurityPrivacyReadinessReport;
use Illuminate\Console\Command;

class AuditSecurityPrivacy extends Command
{
    protected $signature = 'airmius:audit-security-privacy
        {--json : Print machine-readable JSON}
        {--strict : Fail while external DPIA or penetration-test evidence is open}';

    protected $description = 'Run the privacy-safe repository incident drill and security/privacy acceptance checks';

    public function handle(): int
    {
        $report = SecurityPrivacyReadinessReport::make();

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->table(
                ['Check', 'Status', 'Detail'],
                collect($report['checks'])->map(static fn (array $check): array => [
                    $check['id'],
                    strtoupper($check['status']),
                    $check['detail'],
                ])->all(),
            );

            if (! $report['external_evidence_complete']) {
                $this->warn('External DPIA and penetration-test evidence remains open; no external gate was auto-approved.');
            }
        }

        if (! $report['automated_checks_passed']) {
            return self::FAILURE;
        }

        return $this->option('strict') && $report['decision'] !== 'go'
            ? self::FAILURE
            : self::SUCCESS;
    }
}
