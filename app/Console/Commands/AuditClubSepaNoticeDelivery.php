<?php

namespace App\Console\Commands;

use App\Support\ClubSepaNoticeDeliveryReadinessReport;
use Illuminate\Console\Command;

final class AuditClubSepaNoticeDelivery extends Command
{
    protected $signature = 'airmius:audit-club-sepa-notice-delivery
        {--with-runtime : Inspect effective mail/queue configuration and aggregate delivery state read-only}
        {--evidence= : Reviewed staging delivery evidence JSON}
        {--json : Print machine-readable data-minimized JSON}
        {--strict : Fail until runtime inspection and reviewed staging evidence permit rollout}';

    protected $description = 'Audit Postmark, queue and SEPA notice delivery rollout readiness without sending mail or printing sensitive data';

    public function handle(ClubSepaNoticeDeliveryReadinessReport $reporter): int
    {
        $report = $reporter->make((bool) $this->option('with-runtime'), $this->option('evidence') ?: null);

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
