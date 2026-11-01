<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\MobileVerifyEmail;
use App\Notifications\VerifyEmailNotification;
use App\Notifications\TwoFactorChangeCodeRequested;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerificationMailSenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_factor_change_code_uses_the_configured_security_sender(): void
    {
        $user = User::factory()->create();
        $message = (new TwoFactorChangeCodeRequested('123456', 'enable'))->toMail($user);

        $this->assertSame(config('mail.default', 'array'), $message->mailer);
        $this->assertSame([
            config('airmius_mail.senders.security.address'),
            config('airmius_mail.senders.security.name'),
        ], $message->from);
    }

    public function test_web_verification_mail_uses_security_sender(): void
    {
        $user = User::factory()->unverified()->create([
            'name' => 'Max Mustermann',
        ]);

        $message = (new VerifyEmailNotification)->toMail($user);

        $this->assertSame(config('mail.default', 'array'), $message->mailer);
        $this->assertSame([
            config('airmius_mail.senders.security.address'),
            config('airmius_mail.senders.security.name'),
        ], $message->from);
    }

    public function test_mobile_verification_mail_uses_security_sender(): void
    {
        $user = User::factory()->unverified()->create([
            'name' => 'Max Mustermann',
        ]);

        $message = (new MobileVerifyEmail)->toMail($user);

        $this->assertSame(config('mail.default', 'array'), $message->mailer);
        $this->assertSame([
            config('airmius_mail.senders.security.address'),
            config('airmius_mail.senders.security.name'),
        ], $message->from);
    }
}
