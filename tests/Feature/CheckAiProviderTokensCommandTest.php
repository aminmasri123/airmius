<?php

namespace Tests\Feature;

use App\Events\NotificationCreated;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CheckAiProviderTokensCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_missing_provider_alert_is_sent_at_most_once_per_day(): void
    {
        Event::fake([NotificationCreated::class]);
        Carbon::setTestNow('2026-08-30 08:00:00');

        $admin = User::factory()->create();
        Permission::findOrCreate('system.manage', 'web');
        $admin->givePermissionTo('system.manage');

        config([
            'airmius_ai.primary_provider' => 'openai',
            'airmius_ai.fallback_provider' => null,
            'airmius_ai.features' => [],
            'airmius_ai.providers' => [
                'openai' => [
                    'label' => 'OpenAI',
                    'api_key' => null,
                    'model' => 'gpt-test',
                ],
            ],
        ]);

        $this->artisan('airmius:check-ai-provider-tokens')
            ->assertExitCode(Command::SUCCESS);
        $this->artisan('airmius:check-ai-provider-tokens')
            ->expectsOutputToContain('0 Benachrichtigung(en) gesendet')
            ->assertExitCode(Command::SUCCESS);

        $this->assertDatabaseCount('notifications', 1);
        $notification = Notification::query()->sole();
        $this->assertSame('admin.ai_token.problem', $notification->type);
        $this->assertSame('openai', $notification->data['provider']);
        $this->assertSame('missing_key', $notification->data['status']);
        $this->assertNotNull($notification->dedupe_key);

        Carbon::setTestNow('2026-08-31 08:00:00');

        $this->artisan('airmius:check-ai-provider-tokens')
            ->assertExitCode(Command::SUCCESS);

        $this->assertDatabaseCount('notifications', 2);
    }
}
