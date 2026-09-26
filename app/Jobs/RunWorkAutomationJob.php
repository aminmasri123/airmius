<?php

namespace App\Jobs;

use App\Models\WorkAutomationJob;
use App\Services\WorkAutomationJobService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class RunWorkAutomationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public bool $failOnTimeout = true;

    public function __construct(public int $jobId) {}

    public function handle(WorkAutomationJobService $service): void
    {
        $service->run($this->jobId);
    }

    public function failed(?Throwable $exception): void
    {
        WorkAutomationJob::query()
            ->whereKey($this->jobId)
            ->whereIn('status', [WorkAutomationJob::STATUS_QUEUED, WorkAutomationJob::STATUS_RUNNING])
            ->update([
                'status' => WorkAutomationJob::STATUS_FAILED,
                'failed_at' => now(),
                'error_code' => 'queue_worker_failed',
                'error_message' => $exception ? substr($exception->getMessage(), 0, 255) : 'Queue worker failed before completion.',
            ]);
    }
}
