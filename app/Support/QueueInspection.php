<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class QueueInspection
{
    public function summary(): array
    {
        if (! Schema::hasTable('jobs')) {
            return ['pending_jobs' => 0, 'job_types' => [], 'recent_jobs' => []];
        }

        return [
            'pending_jobs' => DB::table('jobs')->count(),
            'job_types' => $this->jobTypes(),
            'recent_jobs' => DB::table('jobs')->latest('id')->limit(25)
                ->get(['id', 'queue', 'payload', 'attempts', 'available_at', 'created_at'])
                ->map(fn ($job) => [
                    'id' => $job->id,
                    'queue' => $job->queue,
                    'type' => $this->jobType($job->payload),
                    'attempts' => (int) $job->attempts,
                    'available_at' => $this->timestamp($job->available_at),
                    'created_at' => $this->timestamp($job->created_at),
                ])->all(),
        ];
    }

    private function jobTypes(): array
    {
        try {
            $driver = DB::connection()->getDriverName();
            $expression = $driver === 'sqlite'
                ? "coalesce(json_extract(payload, '$.displayName'), json_extract(payload, '$.job'), 'Unbekannter Job')"
                : "coalesce(json_unquote(json_extract(payload, '$.displayName')), json_unquote(json_extract(payload, '$.job')), 'Unbekannter Job')";

            return DB::table('jobs')
                ->select('queue')
                ->selectRaw("{$expression} as job_type")
                ->selectRaw('count(*) as total, min(created_at) as oldest_at, max(created_at) as newest_at, max(attempts) as max_attempts')
                ->groupBy('queue')
                ->groupByRaw($expression)
                ->orderByDesc('total')
                ->limit(50)
                ->get()
                ->map(fn ($row) => [
                    'queue' => $row->queue,
                    'type' => $this->shortType((string) $row->job_type),
                    'total' => (int) $row->total,
                    'max_attempts' => (int) $row->max_attempts,
                    'oldest_at' => $this->timestamp($row->oldest_at),
                    'newest_at' => $this->timestamp($row->newest_at),
                ])->all();
        } catch (Throwable) {
            return [];
        }
    }

    private function jobType(string $payload): string
    {
        $decoded = json_decode($payload, true);

        return $this->shortType((string) ($decoded['displayName'] ?? $decoded['job'] ?? 'Unbekannter Job'));
    }

    private function shortType(string $type): string
    {
        return str_contains($type, '\\') ? class_basename($type) : $type;
    }

    private function timestamp(mixed $timestamp): ?string
    {
        return is_numeric($timestamp) ? Carbon::createFromTimestamp((int) $timestamp)->toIso8601String() : null;
    }
}
