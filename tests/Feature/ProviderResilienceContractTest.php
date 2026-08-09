<?php

namespace Tests\Feature;

use App\Models\MailDelivery;
use App\Support\TransactionalMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Tests\TestCase;

final class ProviderResilienceContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::clear();
    }

    public function test_transactional_mail_uses_fallback_and_records_one_success(): void
    {
        $recipient = new class
        {
            public string $email = 'provider-smoke@example.test';

            public string $name = 'Provider Smoke';

            public int $attempts = 0;

            public function notify(object $notification): void
            {
                $this->attempts++;
                if ($this->attempts === 1) {
                    throw new RuntimeException('Primary SMTP unavailable.');
                }
            }
        };

        $sent = app(TransactionalMail::class)->notifyWithFallback(
            $recipient,
            fn (array $transport): object => (object) $transport,
            'billing',
            'support',
            'provider-resilience-fallback',
            60,
            ['mail_type' => 'provider.smoke'],
        );

        $this->assertTrue($sent);
        $this->assertSame(2, $recipient->attempts);
        $this->assertDatabaseCount('mail_deliveries', 1);
        $delivery = MailDelivery::query()->sole();
        $this->assertSame('sent', $delivery->status);
        $this->assertSame('support', $delivery->used_category);
    }

    public function test_failed_mail_without_fallback_releases_dedupe_key_for_safe_retry(): void
    {
        $recipient = new class
        {
            public string $email = 'provider-retry@example.test';

            public string $name = 'Provider Retry';

            public bool $fail = true;

            public int $attempts = 0;

            public function notify(object $notification): void
            {
                $this->attempts++;
                if ($this->fail) {
                    throw new RuntimeException('SMTP unavailable.');
                }
            }
        };
        $send = fn (): bool => app(TransactionalMail::class)->notifyWithFallback(
            $recipient,
            fn (array $transport): object => (object) $transport,
            'system',
            null,
            'provider-resilience-retry',
            60,
            ['mail_type' => 'provider.retry'],
        );

        $this->assertFalse($send());
        $recipient->fail = false;
        $this->assertTrue($send());
        $this->assertSame(2, $recipient->attempts);
        $this->assertDatabaseCount('mail_deliveries', 2);
        $this->assertSame(['failed', 'sent'], MailDelivery::query()->orderBy('id')->pluck('status')->all());
    }
}
