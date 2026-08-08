<?php

namespace App\Support\Performance;

use Illuminate\Database\Events\QueryExecuted;

class RequestPerformanceTracker
{
    private bool $active = false;

    private int $queryCount = 0;

    private float $queryTimeMs = 0.0;

    public function start(): void
    {
        $this->active = true;
        $this->queryCount = 0;
        $this->queryTimeMs = 0.0;
    }

    public function record(QueryExecuted $query): void
    {
        if (! $this->active) {
            return;
        }

        $this->queryCount++;
        $this->queryTimeMs += max(0.0, (float) $query->time);
    }

    public function recordDuration(float $milliseconds): void
    {
        if (! $this->active) {
            return;
        }

        $this->queryCount++;
        $this->queryTimeMs += max(0.0, $milliseconds);
    }

    /** @return array{query_count: int, query_time_ms: float} */
    public function finish(): array
    {
        $snapshot = [
            'query_count' => $this->queryCount,
            'query_time_ms' => round($this->queryTimeMs, 2),
        ];

        $this->active = false;

        return $snapshot;
    }
}
