<?php

namespace Tests\Feature;

use App\Models\ApiIdempotencyKey;
use App\Models\DomainOutboxEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlatformDeliveryRetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_retention_prunes_only_expired_or_out_of_policy_delivery_data(): void
    {
        $expiredKey = ApiIdempotencyKey::query()->create([
            'key' => 'expired-key-0001',
            'actor_key' => 'user:1',
            'scope' => 'PATCH|test',
            'request_hash' => hash('sha256', 'expired'),
            'expires_at' => now()->subMinute(),
        ]);
        $activeKey = ApiIdempotencyKey::query()->create([
            'key' => 'active-key-0001',
            'actor_key' => 'user:1',
            'scope' => 'PATCH|test',
            'request_hash' => hash('sha256', 'active'),
            'expires_at' => now()->addHour(),
        ]);

        $oldPublished = $this->outboxEvent([
            'published_at' => now()->subDays(31),
            'occurred_at' => now()->subDays(31),
        ]);
        $recentPublished = $this->outboxEvent([
            'published_at' => now()->subDays(2),
            'occurred_at' => now()->subDays(2),
        ]);
        $oldFailed = $this->outboxEvent([
            'attempts' => 5,
            'last_error' => 'Permanent test failure',
            'occurred_at' => now()->subDays(91),
        ]);
        $oldPending = $this->outboxEvent([
            'attempts' => 1,
            'occurred_at' => now()->subDays(91),
        ]);

        $this->artisan('airmius:prune-platform-delivery', ['--dry-run' => true])
            ->expectsOutput('Would prune 1 idempotency key(s), 1 published event(s), and 1 failed event(s).')
            ->assertSuccessful();

        $this->assertModelExists($expiredKey);
        $this->assertModelExists($oldPublished);
        $this->assertModelExists($oldFailed);

        $this->artisan('airmius:prune-platform-delivery')->assertSuccessful();

        $this->assertModelMissing($expiredKey);
        $this->assertModelExists($activeKey);
        $this->assertModelMissing($oldPublished);
        $this->assertModelExists($recentPublished);
        $this->assertModelMissing($oldFailed);
        $this->assertModelExists($oldPending);
    }

    private function outboxEvent(array $overrides = []): DomainOutboxEvent
    {
        return DomainOutboxEvent::query()->create(array_merge([
            'id' => (string) Str::uuid(),
            'event_name' => 'test.retention.v1',
            'aggregate_type' => 'test',
            'aggregate_id' => (string) Str::uuid(),
            'aggregate_version' => 1,
            'payload' => [],
            'metadata' => [],
            'audience' => [],
            'occurred_at' => now(),
            'available_at' => now(),
            'attempts' => 0,
        ], $overrides));
    }
}
