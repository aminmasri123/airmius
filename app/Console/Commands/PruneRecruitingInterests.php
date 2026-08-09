<?php

namespace App\Console\Commands;

use App\Models\OrganizationJobInterest;
use Illuminate\Console\Command;

class PruneRecruitingInterests extends Command
{
    protected $signature = 'airmius:prune-recruiting-interests
        {--limit=1000 : Maximum records per run}
        {--dry-run : Show how many records would be removed without deleting them}';

    protected $description = 'Delete recruiting enquiries whose documented retention period has expired';

    public function handle(): int
    {
        $limit = min(max((int) $this->option('limit'), 1), 10_000);
        $ids = OrganizationJobInterest::query()
            ->whereNotNull('retention_expires_at')
            ->where('retention_expires_at', '<=', now())
            ->oldest('id')
            ->limit($limit)
            ->pluck('id');

        if ($this->option('dry-run')) {
            $this->info("Would prune {$ids->count()} expired recruiting interests.");

            return self::SUCCESS;
        }

        $deleted = $ids->isEmpty()
            ? 0
            : OrganizationJobInterest::query()->whereIn('id', $ids)->delete();

        $this->info("Pruned {$deleted} expired recruiting interests.");

        return self::SUCCESS;
    }
}
