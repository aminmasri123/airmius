<?php

namespace App\Console\Commands;

use App\Models\ApiIdempotencyKey;
use App\Models\DomainOutboxEvent;
use Illuminate\Console\Command;

class PrunePlatformDeliveryData extends Command
{
    protected $signature = 'airmius:prune-platform-delivery
        {--outbox-days=30 : Retention for successfully published envelopes}
        {--failed-outbox-days=90 : Retention for permanently failed envelopes}
        {--limit=1000 : Maximum records per data class and run}
        {--dry-run : Show how many records would be removed without deleting them}';

    protected $description = 'Prune expired idempotency responses and old domain outbox envelopes';

    public function handle(): int
    {
        $outboxDays = min(max((int) $this->option('outbox-days'), 7), 365);
        $failedOutboxDays = min(max((int) $this->option('failed-outbox-days'), 30), 730);
        $limit = min(max((int) $this->option('limit'), 1), 10_000);

        $idempotencyKeyIds = ApiIdempotencyKey::query()
            ->where('expires_at', '<', now())
            ->oldest('id')
            ->limit($limit)
            ->pluck('id');
        $publishedEventIds = DomainOutboxEvent::query()
            ->whereNotNull('published_at')
            ->where('published_at', '<', now()->subDays($outboxDays))
            ->oldest('occurred_at')
            ->limit($limit)
            ->pluck('id');
        $failedEventIds = DomainOutboxEvent::query()
            ->whereNull('published_at')
            ->where('attempts', '>=', 5)
            ->where('occurred_at', '<', now()->subDays($failedOutboxDays))
            ->oldest('occurred_at')
            ->limit($limit)
            ->pluck('id');

        if ($this->option('dry-run')) {
            $this->info(sprintf(
                'Would prune %d idempotency key(s), %d published event(s), and %d failed event(s).',
                $idempotencyKeyIds->count(),
                $publishedEventIds->count(),
                $failedEventIds->count(),
            ));

            return self::SUCCESS;
        }

        $idempotencyKeys = $idempotencyKeyIds->isEmpty()
            ? 0
            : ApiIdempotencyKey::query()->whereIn('id', $idempotencyKeyIds)->delete();
        $publishedEvents = $publishedEventIds->isEmpty()
            ? 0
            : DomainOutboxEvent::query()->whereIn('id', $publishedEventIds)->delete();
        $failedEvents = $failedEventIds->isEmpty()
            ? 0
            : DomainOutboxEvent::query()->whereIn('id', $failedEventIds)->delete();

        $this->info(
            "Pruned {$idempotencyKeys} idempotency key(s), {$publishedEvents} published event(s), and {$failedEvents} failed event(s).",
        );

        return self::SUCCESS;
    }
}
