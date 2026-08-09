<?php

namespace App\Console\Commands;

use App\Models\WebsiteRequest;
use Illuminate\Console\Command;

class PruneWebsiteRequests extends Command
{
    protected $signature = 'airmius:prune-website-requests
        {--limit=1000 : Maximum records per run}
        {--dry-run : Show how many records would be removed without deleting them}';

    protected $description = 'Delete agency enquiries whose documented retention period has expired';

    public function handle(): int
    {
        $limit = min(max((int) $this->option('limit'), 1), 10_000);
        $ids = WebsiteRequest::query()
            ->whereNotNull('retention_expires_at')
            ->where('retention_expires_at', '<=', now())
            ->whereIn('status', ['new', 'contacted', 'quoted', 'done', 'cancelled'])
            ->oldest('id')
            ->limit($limit)
            ->pluck('id');

        if ($this->option('dry-run')) {
            $this->info("Would prune {$ids->count()} expired agency requests.");

            return self::SUCCESS;
        }

        $deleted = $ids->isEmpty()
            ? 0
            : WebsiteRequest::query()->whereIn('id', $ids)->delete();

        $this->info("Pruned {$deleted} expired agency requests.");

        return self::SUCCESS;
    }
}
