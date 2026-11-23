<?php

namespace App\Jobs;

use App\Services\WorkAutomationJobService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class RunWorkAutomationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public bool $failOnTimeout = true;

    public function __construct(public int $jobId, public ?string $executionToken = null) {}

    public function handle(WorkAutomationJobService $service): void
    {
        try {
            $service->run($this->jobId, $this->executionToken);
        } catch (ModelNotFoundException) {
            // A manager may remove a queued notification before a worker claims it.
        }
    }

    public function failed(?Throwable $exception): void
    {
        app(WorkAutomationJobService::class)->markFailed($this->jobId, $this->executionToken, 'queue_worker_failed', $exception);
    }
}
