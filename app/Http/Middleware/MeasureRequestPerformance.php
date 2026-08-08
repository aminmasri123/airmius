<?php

namespace App\Http\Middleware;

use App\Support\Performance\RequestPerformanceTracker;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class MeasureRequestPerformance
{
    public function __construct(private RequestPerformanceTracker $tracker) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('airmius_monitoring.performance.enabled', false)) {
            return $next($request);
        }

        $startedAt = hrtime(true);
        $memoryAtStart = memory_get_usage(true);
        $response = null;
        $this->tracker->start();

        try {
            $response = $next($request);

            return $response;
        } finally {
            $this->record(
                $request,
                $response,
                (hrtime(true) - $startedAt) / 1_000_000,
                max(0, memory_get_usage(true) - $memoryAtStart),
            );
        }
    }

    private function record(Request $request, ?Response $response, float $durationMs, int $memoryDeltaBytes): void
    {
        $database = $this->tracker->finish();
        $status = $response?->getStatusCode() ?? Response::HTTP_INTERNAL_SERVER_ERROR;
        $breaches = [];

        if ($durationMs >= $this->positiveThreshold('slow_request_ms', 1000)) {
            $breaches[] = 'request_duration';
        }

        if ($database['query_count'] > $this->positiveThreshold('max_query_count', 100)) {
            $breaches[] = 'query_count';
        }

        if ($database['query_time_ms'] > $this->positiveThreshold('max_query_time_ms', 500)) {
            $breaches[] = 'query_time';
        }

        if ($status >= 500) {
            $breaches[] = 'server_error';
        }

        if ($breaches === [] && ! $this->isSampled()) {
            return;
        }

        $context = [
            'route' => $request->route()?->getName() ?: 'unmatched',
            'method' => $request->getMethod(),
            'status' => $status,
            'duration_ms' => round($durationMs, 2),
            'db_query_count' => $database['query_count'],
            'db_time_ms' => $database['query_time_ms'],
            'memory_delta_kb' => round($memoryDeltaBytes / 1024, 2),
            'breaches' => $breaches,
        ];

        $requestId = $request->attributes->get('api_request_id');
        if (is_string($requestId) && $requestId !== '') {
            $context['request_id'] = $requestId;
        }

        if ($breaches !== []) {
            Log::warning('http.performance', $context);

            return;
        }

        Log::info('http.performance', $context);
    }

    private function positiveThreshold(string $key, int $default): int
    {
        return max(1, (int) config("airmius_monitoring.performance.{$key}", $default));
    }

    private function isSampled(): bool
    {
        $rate = min(1.0, max(0.0, (float) config('airmius_monitoring.performance.sample_rate', 0.01)));

        return $rate > 0.0 && mt_rand() / mt_getrandmax() < $rate;
    }
}
