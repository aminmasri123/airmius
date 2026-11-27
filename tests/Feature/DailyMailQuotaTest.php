<?php

namespace Tests\Feature;

use App\Notifications\TwoFactorLoginCodeRequested;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class DailyMailQuotaTest extends TestCase
{
    public function test_recipient_limits_and_security_reserve_apply_before_transport(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        (require database_path('migrations/2026_11_27_000005_create_daily_mail_usage_table.php'))->up();
        $day = now('UTC')->toDateString();
        $usage = DB::table('daily_mail_usage')->where('day', $day);
        $usage->insert(['day' => $day, 'recipients' => 147]);
        $message = (new Email())->to('a@example.org')->cc('b@example.org')->bcc('c@example.org');
        $ordinary = new MessageSending($message, ['mailer' => 'smtp']);
        Event::until($ordinary);
        $this->assertEquals(150, (clone $usage)->value('recipients'));
        try {
            Event::until($ordinary);
            $this->fail('Ordinary mail exceeded its quota.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Tageskontingent', $e->getMessage());
        }

        $usage->update(['recipients' => 197]);
        $security = new MessageSending($message, ['mailer' => 'smtp', '__laravel_notification' => TwoFactorLoginCodeRequested::class]);
        Event::until($security);
        $this->assertEquals(200, (clone $usage)->value('recipients'));
        $this->expectExceptionMessage('Tageskontingent');
        Event::until($security);
    }
}
