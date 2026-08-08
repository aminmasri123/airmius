<?php

namespace App\Console\Commands;

use App\Models\ApiIdempotencyKey;
use App\Models\DomainOutboxEvent;
use Illuminate\Console\Command;

class PrunePlatformDeliveryData extends Command
{
    protected $signature = 'airmius:prune-platform-delivery
        {--outbox-days=30 : Retention for successfully published envelopes}
        {--failed-outbox-days=90 : Retention for permanently failed envelopes}';

    protected $description = 'Prune expired idempotency responses and old domain outbox envelopes';

    public function handle(): int
    {
        $outboxDays = min(max((int) $this->option('outbox-days'), 7), 365);
        $failedOutboxDays = min(max((int) $this->option('failed-outbox-days'), 30), 730);

        $idempotencyKeys = ApiIdempotencyKey::query()
            ->where('expires_at', '<', now())
            ->delete();
        $publishedEvents = DomainOutboxEvent::query()
            ->whereNotNull('published_at')
            ->where('published_at', '<', now()->subDays($outboxDays))
            ->delete();
        $failedEvents = DomainOutboxEvent::query()
            ->whereNull('published_at')
            ->where('attempts', '>=', 5)
            ->where('occurred_at', '<', now()->subDays($failedOutboxDays))
            ->delete();

        $this->info(
            "Pruned {$idempotencyKeys} idempotency key(s), {$publishedEvents} published event(s), and {$failedEvents} failed event(s).",
        );

        return self::SUCCESS;
    }
}
