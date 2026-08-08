<?php

namespace Tests\Unit;

use App\Http\Middleware\MeasureRequestPerformance;
use App\Support\Performance\RequestPerformanceTracker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class RequestPerformanceTelemetryTest extends TestCase
{
    public function test_tracker_resets_and_ignores_queries_outside_a_request(): void
    {
        $tracker = new RequestPerformanceTracker;

        $tracker->recordDuration(99);
        $tracker->start();
        $tracker->recordDuration(12.25);
        $tracker->recordDuration(7.75);

        $this->assertSame([
            'query_count' => 2,
            'query_time_ms' => 20.0,
        ], $tracker->finish());

        $tracker->recordDuration(50);
        $this->assertSame([
            'query_count' => 2,
            'query_time_ms' => 20.0,
        ], $tracker->finish());

        $tracker->start();
        $this->assertSame([
            'query_count' => 0,
            'query_time_ms' => 0.0,
        ], $tracker->finish());
    }

    public function test_slow_request_log_contains_aggregates_but_no_sensitive_request_or_sql_data(): void
    {
        config()->set('airmius_monitoring.performance', [
            'enabled' => true,
            'slow_request_ms' => 60_000,
            'max_query_count' => 1,
            'max_query_time_ms' => 60_000,
            'sample_rate' => 0,
        ]);
        Log::spy();

        $request = Request::create('/api/v1/users/42?token=secret-value', 'GET');
        $request->attributes->set('api_request_id', 'performance-contract-request');
        $tracker = new RequestPerformanceTracker;
        $middleware = new MeasureRequestPerformance($tracker);

        $response = $middleware->handle($request, function () use ($tracker) {
            $tracker->recordDuration(10.5);
            $tracker->recordDuration(11.5);

            return response('ok');
        });

        $this->assertSame(200, $response->getStatusCode());
        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                $serialized = json_encode($context);

                return $message === 'http.performance'
                    && $context['route'] === 'unmatched'
                    && $context['db_query_count'] === 2
                    && $context['db_time_ms'] === 22.0
                    && $context['breaches'] === ['query_count']
                    && $context['request_id'] === 'performance-contract-request'
                    && ! array_key_exists('url', $context)
                    && ! array_key_exists('path', $context)
                    && ! array_key_exists('user_id', $context)
                    && ! array_key_exists('query', $context)
                    && ! array_key_exists('bindings', $context)
                    && is_string($serialized)
                    && ! str_contains($serialized, 'secret-value');
            });
    }

    public function test_runtime_wiring_keeps_performance_measurement_global_and_query_only(): void
    {
        $bootstrap = file_get_contents(base_path('bootstrap/app.php'));
        $provider = file_get_contents(app_path('Providers/AppServiceProvider.php'));

        $this->assertIsString($bootstrap);
        $this->assertIsString($provider);
        $this->assertStringContainsString('prepend(MeasureRequestPerformance::class)', $bootstrap);
        $this->assertStringContainsString('EventFacade::listen(QueryExecuted::class', $provider);
        $this->assertStringContainsString('RequestPerformanceTracker::class)->record($query)', $provider);
        $this->assertStringNotContainsString('toRawSql()', $provider);
    }
}
