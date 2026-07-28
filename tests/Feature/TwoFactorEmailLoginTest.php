<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\TwoFactorLoginCodeRequested;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Tests\TestCase;

class TwoFactorEmailLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_complete_browser_two_factor_challenge_with_email_otp(): void
    {
        Notification::fake();
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'password' => Hash::make('Secure-password-123!'),
        ]);
        app(EnableTwoFactorAuthentication::class)($user);
        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        $this->withSession([
            'login.id' => $user->getKey(),
            'login.remember' => false,
        ])->post(route('two-factor.email.send'))->assertOk();

        $emailCode = null;
        Notification::assertSentTo(
            $user,
            TwoFactorLoginCodeRequested::class,
            function (TwoFactorLoginCodeRequested $notification) use (&$emailCode): bool {
                $emailCode = $notification->code;

                return true;
            }
        );

        $this->post(route('two-factor.email.login'), [
            'email_code' => $emailCode,
        ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_browser_email_otp_does_not_authenticate_user(): void
    {
        Notification::fake();
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        app(EnableTwoFactorAuthentication::class)($user);
        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        $this->withSession([
            'login.id' => $user->getKey(),
            'login.remember' => false,
        ])->post(route('two-factor.email.send'))->assertOk();

        $this->post(route('two-factor.email.login'), [
            'email_code' => '000000',
        ])
            ->assertSessionHasErrors('email_code');

        $this->assertGuest();
    }
}
