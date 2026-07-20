<?php

namespace App\Console\Commands;

use App\Support\OperationsMonitor;
use Illuminate\Console\Command;

class MonitorOperations extends Command
{
    protected $signature = 'airmius:monitor-operations
        {--hours= : Monitoring window in hours.}
        {--json : Print machine-readable JSON.}';

    protected $description = 'Monitor application errors, queue health, scheduled jobs, webhooks, and mail delivery.';

    public function handle(OperationsMonitor $monitor): int
    {
        $hours = $this->option('hours');
        $result = $monitor->run($hours ? (int) $hours : null);

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->table(
                ['Area', 'Status', 'Check', 'Value', 'Detail'],
                collect($result['checks'])
                    ->map(fn (array $check) => [
                        $check['area'],
                        strtoupper($check['status']),
                        $check['key'],
                        is_scalar($check['value']) ? (string) $check['value'] : json_encode($check['value']),
                        $check['detail'],
                    ])
                    ->all()
            );
        }

        if ($result['summary']['fail'] > 0) {
            $this->error(sprintf(
                'Operations monitor found %d failing checks and %d warnings.',
                $result['summary']['fail'],
                $result['summary']['warn']
            ));

            return self::FAILURE;
        }

        if ($result['summary']['warn'] > 0) {
            $this->warn(sprintf('Operations monitor is healthy with %d warnings.', $result['summary']['warn']));

            return self::SUCCESS;
        }

        $this->info('Operations monitor is healthy.');

        return self::SUCCESS;
    }
}
