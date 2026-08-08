<?php

namespace App\Http\Middleware;

use App\Models\ApiIdempotencyKey;
use App\Support\Api\V1\ApiErrorResponse;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class EnsureIdempotentApiRequest
{
    private const HEADER = 'Idempotency-Key';

    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethodSafe() || ! $request->headers->has(self::HEADER)) {
            return $next($request);
        }

        $key = trim((string) $request->headers->get(self::HEADER));
        if (! $this->validKey($key)) {
            return ApiErrorResponse::badRequest(
                $request,
                'invalid_idempotency_key',
                'Idempotency-Key must contain 8 to 120 URL-safe characters.',
            );
        }

        $actorKey = $request->user()
            ? 'user:'.$request->user()->getAuthIdentifier()
            : 'guest:'.hash('sha256', (string) $request->ip());
        $scope = $request->method().'|'.($request->route()?->getName() ?: '/'.$request->path());
        $requestHash = hash('sha256', (string) $request->getContent());

        [$record, $state] = $this->acquire($key, $actorKey, $scope, $requestHash);

        if ($state === 'payload_conflict') {
            return ApiErrorResponse::conflict(
                $request,
                'idempotency_payload_conflict',
                'This Idempotency-Key was already used with a different request payload.',
            );
        }

        if ($state === 'processing') {
            return ApiErrorResponse::conflict(
                $request,
                'idempotency_request_processing',
                'A request with this Idempotency-Key is still being processed.',
                ['retry_after_seconds' => 2],
                ['Retry-After' => '2'],
            );
        }

        if ($state === 'replay') {
            return response()
                ->json($record->response_payload ?? [], $record->response_status ?? Response::HTTP_OK, $record->response_headers ?? [])
                ->header('X-Idempotent-Replay', 'true');
        }

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $record->delete();

            throw $exception;
        }

        if ($response instanceof JsonResponse && $response->getStatusCode() < 500) {
            $payload = json_decode((string) $response->getContent(), true);

            $record->forceFill([
                'response_status' => $response->getStatusCode(),
                'response_payload' => is_array($payload) ? $payload : ['data' => $payload],
                'response_headers' => $this->replayableHeaders($response),
                'completed_at' => now(),
                'locked_at' => null,
            ])->save();
        } else {
            $record->delete();
        }

        return $response;
    }

    private function acquire(string $key, string $actorKey, string $scope, string $requestHash): array
    {
        $record = ApiIdempotencyKey::query()
            ->where('key', $key)
            ->where('actor_key', $actorKey)
            ->where('scope', $scope)
            ->first();

        if (! $record) {
            try {
                $record = ApiIdempotencyKey::query()->create([
                    'key' => $key,
                    'actor_key' => $actorKey,
                    'scope' => $scope,
                    'request_hash' => $requestHash,
                    'locked_at' => now(),
                    'expires_at' => now()->addDay(),
                ]);

                return [$record, 'acquired'];
            } catch (QueryException) {
                $record = ApiIdempotencyKey::query()
                    ->where('key', $key)
                    ->where('actor_key', $actorKey)
                    ->where('scope', $scope)
                    ->firstOrFail();
            }
        }

        if ($record->expires_at?->isPast()) {
            $record->forceFill([
                'request_hash' => $requestHash,
                'response_status' => null,
                'response_payload' => null,
                'response_headers' => null,
                'locked_at' => now(),
                'completed_at' => null,
                'expires_at' => now()->addDay(),
            ])->save();

            return [$record, 'acquired'];
        }

        if (! hash_equals($record->request_hash, $requestHash)) {
            return [$record, 'payload_conflict'];
        }

        if ($record->completed_at && $record->response_status) {
            return [$record, 'replay'];
        }

        if ($record->locked_at?->isAfter(now()->subSeconds(30))) {
            return [$record, 'processing'];
        }

        $record->forceFill([
            'locked_at' => now(),
            'expires_at' => now()->addDay(),
        ])->save();

        return [$record, 'acquired'];
    }

    private function validKey(string $key): bool
    {
        return strlen($key) >= 8
            && strlen($key) <= 120
            && preg_match('/^[A-Za-z0-9_.:-]+$/', $key) === 1;
    }

    private function replayableHeaders(JsonResponse $response): array
    {
        return collect(['Content-Language', 'Location'])
            ->filter(fn (string $name) => $response->headers->has($name))
            ->mapWithKeys(fn (string $name) => [$name => $response->headers->get($name)])
            ->all();
    }
}
