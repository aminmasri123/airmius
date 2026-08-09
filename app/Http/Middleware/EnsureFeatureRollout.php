<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\RolloutDecisionService;
use App\Support\Api\V1\ApiErrorResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureFeatureRollout
{
    public function __construct(private readonly RolloutDecisionService $rollouts) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        $decision = $this->rollouts->forRequest($feature, $user, $request);
        if ($decision['enabled']) {
            return $next($request);
        }

        $retryAfter = (int) config('airmius_rollout.retry_after_seconds', 300);
        $message = __('rollout.unavailable');
        if ($request->is('api/*') || $request->expectsJson()) {
            return ApiErrorResponse::serviceUnavailable(
                $request,
                'feature_rollout_unavailable',
                $message,
                ['retryable' => true],
                ['Retry-After' => (string) $retryAfter],
            );
        }

        return redirect((string) config('airmius_rollout.web_fallback_path', '/workspaces'), Response::HTTP_SEE_OTHER)
            ->with('message', $message);
    }
}
