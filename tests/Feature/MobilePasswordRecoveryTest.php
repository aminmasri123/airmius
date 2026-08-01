<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\MobilePasswordResetRequested;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MobilePasswordRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_reset_link_can_be_requested_without_email_enumeration(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'mobile@example.com']);

        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])
            ->assertOk()
            ->assertJsonPath(
                'data.message',
                'Wenn ein Konto zu dieser E-Mail-Adresse existiert, wurde ein sicherer Reset-Link gesendet.'
            );

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'unknown@example.com'])
            ->assertOk()
            ->assertJsonPath(
                'data.message',
                'Wenn ein Konto zu dieser E-Mail-Adresse existiert, wurde ein sicherer Reset-Link gesendet.'
            );

        Notification::assertSentTo($user, MobilePasswordResetRequested::class, function (MobilePasswordResetRequested $notification) use ($user): bool {
            $mail = $notification->toMail($user);

            $this->assertStringContainsString(
                '/reset-password/'.$notification->token,
                (string) $mail->actionUrl,
            );
            $this->assertStringContainsString('email='.rawurlencode($user->email), (string) $mail->actionUrl);

            return true;
        });
    }

    public function test_password_can_be_reset_and_existing_mobile_sessions_are_revoked(): void
    {
        Notification::fake();
        $user = User::factory()->create([
            'email' => 'mobile@example.com',
            'password' => Hash::make('Old-password-123!'),
        ]);
        $user->createToken('Airmius Android App');

        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo(
            $user,
            MobilePasswordResetRequested::class,
            function (MobilePasswordResetRequested $notification) use ($user): bool {
                $this->postJson('/api/v1/auth/reset-password', [
                    'token' => $notification->token,
                    'email' => $user->email,
                    'password' => 'New-password-456!',
                    'password_confirmation' => 'New-password-456!',
                ])
                    ->assertOk()
                    ->assertJsonPath(
                        'data.message',
                        'Dein Passwort wurde zurückgesetzt. Du kannst dich jetzt anmelden.'
                    );

                return true;
            }
        );

        $this->assertTrue(Hash::check('New-password-456!', $user->refresh()->password));
        $this->assertCount(0, $user->tokens()->get());
    }

    public function test_invalid_mobile_reset_token_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => 'invalid',
            'email' => $user->email,
            'password' => 'New-password-456!',
            'password_confirmation' => 'New-password-456!',
        ])->assertUnprocessable()->assertJsonValidationErrors('token');
    }
}
