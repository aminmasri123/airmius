<?php

namespace App\Services;

use App\Models\ProviderWebhookEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ProviderWebhookEventService
{
    public function handle(string $provider, string $scope, ?string $eventKey, ?string $eventType, callable $callback): ProviderWebhookEvent
    {
        $eventKey = trim((string) $eventKey);
        if ($eventKey === '') {
            $eventKey = hash('sha256', implode('|', [$provider, $scope, $eventType ?: 'unknown', request()->getContent()]));
        }

        return DB::transaction(function () use ($provider, $scope, $eventKey, $eventType, $callback): ProviderWebhookEvent {
            $event = ProviderWebhookEvent::query()
                ->where('provider', $provider)
                ->where('scope', $scope)
                ->where('event_key', $eventKey)
                ->lockForUpdate()
                ->first();

            if ($event && $event->status === 'processed') {
                return $event;
            }

            $event ??= ProviderWebhookEvent::query()->create([
                'provider' => $provider,
                'scope' => $scope,
                'event_key' => $eventKey,
                'event_type' => $eventType,
                'status' => 'received',
            ]);

            $subject = $callback($event);
            if ($subject instanceof Model) {
                $event->subject()->associate($subject);
            }

            $event->forceFill([
                'event_type' => $eventType ?: $event->event_type,
                'status' => 'processed',
                'processed_at' => now(),
            ])->save();

            return $event;
        });
    }
}
