<?php

namespace App\Console\Commands;

use App\Models\AdEvent;
use App\Models\Setting;
use Illuminate\Console\Command;

class PruneAdEvents extends Command
{
    protected $signature = 'airmius:prune-ad-events';

    protected $description = 'Delete old ad tracking events according to the configured retention period.';

    public function handle(): int
    {
        $retentionDays = max(30, (int) Setting::valueFor('ads_event_retention_days', 180));
        $deleted = AdEvent::query()
            ->where('occurred_at', '<', now()->subDays($retentionDays))
            ->delete();

        $this->info("Deleted {$deleted} old ad events older than {$retentionDays} days.");

        return self::SUCCESS;
    }
}
