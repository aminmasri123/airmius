<?php

namespace App\Console\Commands;

use App\Models\AdEvent;
use App\Models\Setting;
use Illuminate\Console\Command;

class PruneAdEvents extends Command
{
    protected $signature = 'airmius:prune-ad-events
        {--limit=1000 : Maximum records per run}
        {--dry-run : Show how many records would be removed without deleting them}';

    protected $description = 'Delete old ad tracking events according to the configured retention period.';

    public function handle(): int
    {
        $retentionDays = max(30, (int) Setting::valueFor('ads_event_retention_days', 180));
        $limit = min(max((int) $this->option('limit'), 1), 10_000);
        $ids = AdEvent::query()
            ->where('occurred_at', '<', now()->subDays($retentionDays))
            ->oldest('id')
            ->limit($limit)
            ->pluck('id');

        if ($this->option('dry-run')) {
            $this->info("Would prune {$ids->count()} old ad events older than {$retentionDays} days.");

            return self::SUCCESS;
        }

        $deleted = $ids->isEmpty()
            ? 0
            : AdEvent::query()->whereIn('id', $ids)->delete();

        $this->info("Deleted {$deleted} old ad events older than {$retentionDays} days.");

        return self::SUCCESS;
    }
}
