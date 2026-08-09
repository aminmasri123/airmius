<?php

namespace App\Console\Commands;

use App\Support\CrossDeviceReadinessReport;
use Illuminate\Console\Command;

final class AuditCrossDevice extends Command
{
    protected $signature = 'airmius:audit-cross-device
        {--json : Print machine-readable, data-minimized JSON}
        {--strict : Fail while mobile, localization, WCAG, or real-device evidence remains open}';

    protected $description = 'Audit version-bound web/mobile, device, locale, RTL, accessibility, and critical-journey evidence';

    public function handle(CrossDeviceReadinessReport $reporter): int
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
