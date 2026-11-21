<?php

namespace App\Console\Commands;

use App\Models\Activity;
use App\Support\ClubAuditLog;
use Illuminate\Console\Command;

class PruneClubAuditLogs extends Command
{
    protected $signature = 'airmius:prune-club-audit-logs
        {--limit=5000 : Maximum records per run}
        {--dry-run : Count expired records without deleting them}';

    protected $description = 'Delete club audit entries after the published seven-day retention period.';

    public function handle(): int
    {
        $limit = min(max((int) $this->option('limit'), 1), 50_000);
        if ($this->option('dry-run')) {
            $count = Activity::query()
                ->whereNotNull('club_id')
                ->where('created_at', '<', now()->subDays(ClubAuditLog::RETENTION_DAYS))
                ->count();
            $this->info("Would prune {$count} club audit entries older than ".ClubAuditLog::RETENTION_DAYS.' days.');
            return self::SUCCESS;
        }

        $deleted = 0;
        do {
            $ids = Activity::query()
                ->whereNotNull('club_id')
                ->where('created_at', '<', now()->subDays(ClubAuditLog::RETENTION_DAYS))
                ->oldest('id')
                ->limit($limit)
                ->pluck('id');
            $batchDeleted = $ids->isEmpty() ? 0 : Activity::query()->whereIn('id', $ids)->delete();
            $deleted += $batchDeleted;
        } while ($batchDeleted === $limit);

        $this->info("Deleted {$deleted} club audit entries older than ".ClubAuditLog::RETENTION_DAYS.' days.');

        return self::SUCCESS;
    }
}
