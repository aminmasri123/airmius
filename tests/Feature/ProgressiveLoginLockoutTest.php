<?php

namespace Tests\Feature;

use App\Support\ProgressiveLoginLockout;
use Illuminate\Auth\Events\PasswordReset;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProgressiveLoginLockoutTest extends TestCase
{
    public function test_escalation_cooldowns_and_reset(): void
    {
        Cache::flush();
        $lockout = app(ProgressiveLoginLockout::class);
        $email = 'parent@example.test';
        foreach ([180, 600, 3600, 3600] as $stage => $seconds) {
            for ($i = 1; $i <= 5; $i++) {
                $this->assertSame($i === 5, $lockout->recordFailure($email));
            }
            $this->assertSame($seconds, $lockout->remainingSeconds(' PARENT@example.test '));
            $this->assertFalse($lockout->recordFailure($email));
            $this->assertSame(($stage + 1) * 5, $lockout->state($email)['attempts']);
            try {
                $lockout->ensureAllowed($email);
                $this->fail('Active lockout must reject login.');
            } catch (ValidationException $exception) {
                $this->assertSame(429, $exception->status);
                $this->assertArrayHasKey('email', $exception->errors());
            }
            $this->travel($seconds)->seconds();
            $lockout->ensureAllowed($email);
        }
        event(new PasswordReset(new User(['email' => $email])));
        $this->assertSame(0, $lockout->state($email)['attempts']);
        $this->assertSame(0, $lockout->remainingSeconds($email));
        $lockout->recordFailure($email);
        $this->assertSame(1, $lockout->state($email)['attempts']);
    }

    public function test_password_reset_removes_an_active_lockout(): void
    {
        Cache::flush();
        $lockout = app(ProgressiveLoginLockout::class);
        for ($i = 0; $i < 5; $i++) {
            $lockout->recordFailure('user@example.test');
        }
        event(new PasswordReset(new User(['email' => 'USER@example.test'])));
        $lockout->ensureAllowed('user@example.test');
        $this->assertSame(0, $lockout->state('user@example.test')['attempts']);
    }
}
