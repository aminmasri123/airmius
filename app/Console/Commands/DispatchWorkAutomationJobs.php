<?php

namespace App\Console\Commands;

use App\Models\WorkAutomationJob;
use App\Services\WorkAutomationJobService;
use Illuminate\Console\Command;

class DispatchWorkAutomationJobs extends Command
{
    protected $signature = 'airmius:dispatch-work-automation-jobs {--limit=200}';

    protected $description = 'Dispatch due work automations and recover undispatched queued jobs';

    public function handle(WorkAutomationJobService $service): int
    {
        $ids = WorkAutomationJob::query()->whereNull('dispatched_at')
            ->where(fn ($query) => $query->where('status', WorkAutomationJob::STATUS_QUEUED)
                ->orWhere(fn ($query) => $query->where('status', WorkAutomationJob::STATUS_SCHEDULED)->where('scheduled_at', '<=', now())))
            ->orderBy('id')->limit(max(1, min(1000, (int) $this->option('limit'))))->pluck('id');
        $failed = 0;
        foreach ($ids as $id) {
            if (! $service->dispatchPending((int) $id)) {
                $failed++;
                $this->error("Queue submission failed for automation {$id}.");
            }
        }
        if ($failed > 0) {
            return self::FAILURE;
        }
        $this->info($ids->count().' due automation jobs checked.');

        return self::SUCCESS;
    }
}
