<?php

namespace App\Console\Commands;

use App\Jobs\PublishDomainOutboxEvent;
use App\Models\DomainOutboxEvent;
use Illuminate\Console\Command;

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
                $query->whereNull('processing_at')
                    ->orWhere('processing_at', '<', now()->subMinutes(5));
            })
            ->orderBy('occurred_at')
            ->limit($limit)
            ->pluck('id');

        $ids->each(fn (string $id) => PublishDomainOutboxEvent::dispatch($id));

        $pruned = 0;
        if ($this->option('prune-days') !== null) {
            $days = min(max((int) $this->option('prune-days'), 7), 3650);
            $pruned = DomainOutboxEvent::query()
                ->whereNotNull('published_at')
                ->where('published_at', '<', now()->subDays($days))
                ->delete();
        }

        $this->info("Enqueued {$ids->count()} domain event(s); pruned {$pruned}.");

        return self::SUCCESS;
    }
}
