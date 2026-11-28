<?php

namespace App\Console\Commands;

use App\Jobs\PublishDomainOutboxEvent;
use App\Models\DomainOutboxEvent;
use Illuminate\Console\Command;
use Throwable;

class DispatchDomainOutboxEvents extends Command
{
    protected $signature = 'airmius:dispatch-domain-outbox {--limit=200 : Maximum pending events to enqueue} {--prune-days= : Delete published events older than this many days}';

    protected $description = 'Dispatch pending AIRMIUS domain events and optionally prune old published envelopes';

    public function handle(): int
    {
        $limit = min(max((int) $this->option('limit'), 1), 2000);
        $ids = DomainOutboxEvent::query()
            ->whereNull('published_at')
            ->where('available_at', '<=', now())
            ->where(function ($query) {
                $query->whereNull('queued_at')
                    ->orWhere('queued_at', '<', now()->subMinutes(5));
            })
            ->whereNull('processing_at')
            ->orderBy('occurred_at')
            ->limit($limit)
            ->pluck('id');

        $enqueued = 0;
        foreach ($ids as $id) {
            $claimed = DomainOutboxEvent::query()
                ->whereKey($id)
                ->whereNull('published_at')
                ->whereNull('processing_at')
                ->where(function ($query) {
                    $query->whereNull('queued_at')
                        ->orWhere('queued_at', '<', now()->subMinutes(5));
                })
                ->update(['queued_at' => now(), 'updated_at' => now()]);

            if ($claimed === 1) {
                try {
                    PublishDomainOutboxEvent::dispatch($id);
                    $enqueued++;
                } catch (Throwable $exception) {
                    DomainOutboxEvent::query()->whereKey($id)->update([
                        'queued_at' => null,
                        'updated_at' => now(),
                    ]);

                    throw $exception;
                }
            }
        }

        $pruned = 0;
        if ($this->option('prune-days') !== null) {
            $days = min(max((int) $this->option('prune-days'), 7), 3650);
            $pruned = DomainOutboxEvent::query()
                ->whereNotNull('published_at')
                ->where('published_at', '<', now()->subDays($days))
                ->delete();
        }

        $this->info("Enqueued {$enqueued} domain event(s); pruned {$pruned}.");

        return self::SUCCESS;
    }
}
