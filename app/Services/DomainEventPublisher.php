<?php

namespace App\Services;

use App\Jobs\PublishDomainOutboxEvent;
use App\Models\DomainOutboxEvent;
use App\Support\Api\V1\ApiContract;
use App\Support\Authorization\AuthorizationContext;
use App\Support\Privacy\ProcessingPurpose;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class DomainEventPublisher
{
    public function record(
        string $eventName,
        Model|string $aggregate,
        string|int|null $aggregateId = null,
        array $payload = [],
        array $audience = [],
        int $aggregateVersion = 1,
        array $metadata = [],
    ): DomainOutboxEvent {
        $aggregateType = $aggregate instanceof Model ? $aggregate->getMorphClass() : $aggregate;
        $aggregateId ??= $aggregate instanceof Model ? $aggregate->getKey() : null;

        if ($aggregateId === null || $aggregateId === '') {
            throw new \InvalidArgumentException('A domain event requires an aggregate id.');
        }

        $event = DomainOutboxEvent::query()->create([
            'id' => (string) Str::uuid(),
            'event_name' => $eventName,
            'aggregate_type' => $aggregateType,
            'aggregate_id' => (string) $aggregateId,
            'aggregate_version' => max(1, $aggregateVersion),
            'payload' => $payload,
            'metadata' => array_filter([
                ...$this->requestMetadata(),
                ...$metadata,
            ], fn ($value) => $value !== null && $value !== ''),
            'audience' => $audience,
            'occurred_at' => now(),
            'available_at' => now(),
        ]);

        DB::afterCommit(function () use ($event): void {
            try {
                PublishDomainOutboxEvent::dispatch($event->id);
            } catch (Throwable $exception) {
                report($exception);
            }
        });

        return $event;
    }

    private function requestMetadata(): array
    {
        if (! app()->bound('request')) {
            return [];
        }

        $request = request();
        $purpose = $request->attributes->get(AuthorizationContext::REQUEST_PURPOSE_ATTRIBUTE);

        return [
            'request_id' => ApiContract::requestId($request),
            'actor_id' => $request->user()?->getAuthIdentifier(),
            'purpose' => $purpose instanceof ProcessingPurpose ? $purpose->value : null,
        ];
    }
}
