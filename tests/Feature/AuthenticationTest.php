<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\LoginLockoutNotification;
use App\Notifications\LoginSuccessfulNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(config('fortify.home'));
        Notification::assertSentTo($user, LoginSuccessfulNotification::class);
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
        Notification::assertNothingSent();
    }

    public function test_user_is_notified_after_login_lockout(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);
        }

        $this->assertGuest();
        Notification::assertSentTo($user, LoginLockoutNotification::class);
    }

    public function test_web_and_mobile_share_escalation_and_password_reset(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $lockout = app(\App\Support\ProgressiveLoginLockout::class);
        $lockout->reset($user->email);

        foreach ([180, 600, 3600] as $seconds) {
            for ($attempt = 1; $attempt <= 5; $attempt++) {
                $path = $attempt % 2 ? '/login' : '/api/v1/auth/login';
                $this->postJson($path, ['email' => $user->email, 'password' => 'wrong'])
                    ->assertStatus($attempt === 5 ? 429 : 422);
            }
            foreach (['/login', '/api/v1/auth/login'] as $path) {
                $this->postJson($path, ['email' => $user->email, 'password' => 'password'])
                    ->assertStatus(429);
            }
            $this->assertSame($seconds, $lockout->remainingSeconds($user->email));
            $this->travel($seconds)->seconds();
        }

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email, 'token' => 'invalid',
            'password' => 'New-password-123!', 'password_confirmation' => 'New-password-123!',
        ])->assertUnprocessable();
        $this->assertSame(15, $lockout->state($user->email)['attempts']);

        $token = \Illuminate\Support\Facades\Password::broker()->createToken($user);
        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email, 'token' => $token,
            'password' => 'New-password-123!', 'password_confirmation' => 'New-password-123!',
        ])->assertOk();
        $this->assertSame(0, $lockout->state($user->email)['attempts']);
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'New-password-123!'])
            ->assertOk();
        Notification::assertSentToTimes($user, LoginLockoutNotification::class, 3);
    }
}
