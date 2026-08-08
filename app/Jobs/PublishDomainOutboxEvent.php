<?php

namespace App\Jobs;

use App\Events\DomainEventPublished;
use App\Models\DomainOutboxEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class PublishDomainOutboxEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public readonly string $outboxEventId) {}

    public function backoff(): array
    {
        return [5, 30, 120, 300];
    }

    public function handle(): void
    {
        $claimed = DomainOutboxEvent::query()
            ->whereKey($this->outboxEventId)
            ->whereNull('published_at')
            ->where(function ($query) {
                $query->whereNull('processing_at')
                    ->orWhere('processing_at', '<', now()->subMinutes(5));
            })
            ->update([
                'processing_at' => now(),
                'attempts' => DB::raw('attempts + 1'),
                'updated_at' => now(),
            ]);

        if ($claimed === 0) {
            return;
        }

        $event = DomainOutboxEvent::query()->findOrFail($this->outboxEventId);

        try {
            event(new DomainEventPublished($event->envelope()));

            $event->forceFill([
                'published_at' => now(),
                'processing_at' => null,
                'last_error' => null,
            ])->save();
        } catch (Throwable $exception) {
            $event->forceFill([
                'processing_at' => null,
                'last_error' => mb_substr($exception->getMessage(), 0, 4000),
            ])->save();

            throw $exception;
        }
    }
}
