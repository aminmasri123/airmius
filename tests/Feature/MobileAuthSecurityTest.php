<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\MobileVerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class MobileAuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_login_is_not_blocked_by_a_browser_origin(): void
    {
        config(['sanctum.stateful' => ['airmius.com']]);

        $response = $this
            ->withHeader('Origin', 'https://airmius.com')
            ->withHeader('Accept', 'application/json')
            ->postJson('/api/v1/auth/login', [
                'email' => 'not-a-real-user@example.invalid',
                'password' => 'wrong-password',
                'device_name' => 'Airmius Browser Contract Test',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'auth.failed')
            ->assertHeader('Access-Control-Allow-Origin', 'https://airmius.com');
    }

    public function test_mobile_login_requires_two_factor_before_issuing_a_token(): void
    {
        $user = User::factory()->create([
            'email' => 'secure@example.test',
            'password' => Hash::make('Secure-password-123!'),
        ]);
        app(EnableTwoFactorAuthentication::class)($user);
        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Secure-password-123!',
            'device_name' => 'Airmius Android Test',
        ]);

        $login
            ->assertStatus(202)
            ->assertJsonPath('data.two_factor_required', true)
            ->assertJsonMissingPath('data.token');
        $this->assertCount(0, $user->tokens()->get());

        $challengeToken = $login->json('data.challenge_token');
        $secret = Fortify::currentEncrypter()->decrypt($user->fresh()->two_factor_secret);
        $validCode = $this->currentValidCode($secret);

        $this->postJson('/api/v1/auth/two-factor-challenge', [
            'challenge_token' => $challengeToken,
            'code' => $validCode,
        ])
            ->assertOk()
            ->assertJsonStructure(['data' => ['token', 'token_type', 'user']]);

        $this->assertSame(
            'Airmius Android Test',
            $user->tokens()->latest('id')->first()->name
        );

        $this->postJson('/api/v1/auth/two-factor-challenge', [
            'challenge_token' => $challengeToken,
            'code' => $validCode,
        ])->assertUnprocessable()->assertJsonValidationErrors('challenge_token');
    }

    public function test_invalid_mobile_two_factor_code_is_rejected_without_token(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('Secure-password-123!'),
        ]);
        app(EnableTwoFactorAuthentication::class)($user);
        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Secure-password-123!',
        ]);

        $this->postJson('/api/v1/auth/two-factor-challenge', [
            'challenge_token' => $login->json('data.challenge_token'),
            'code' => '000000',
        ])->assertUnprocessable()->assertJsonValidationErrors('code');

        $this->assertCount(0, $user->tokens()->get());
    }

    public function test_mobile_two_factor_can_be_managed_with_password_confirmation(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('Secure-password-123!'),
        ]);
        $token = $user->createToken('Security settings')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/me/two-factor-authentication', [
            'current_password' => 'wrong',
        ])->assertUnprocessable()->assertJsonValidationErrors('current_password');

        $setup = $this->withToken($token)
            ->postJson('/api/v1/me/two-factor-authentication', [
                'current_password' => 'Secure-password-123!',
            ])
            ->assertCreated()
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.pending_confirmation', true)
            ->assertJsonStructure(['data' => ['setup_key', 'otpauth_url']]);

        $validCode = $this->currentValidCode($setup->json('data.setup_key'));

        $this->withToken($token)
            ->postJson('/api/v1/me/two-factor-authentication/confirm', [
                'code' => $validCode,
            ])
            ->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonCount(8, 'data.recovery_codes');

        $oldCodes = $user->fresh()->recoveryCodes();
        $regenerated = $this->withToken($token)
            ->postJson('/api/v1/me/two-factor-recovery-codes', [
                'current_password' => 'Secure-password-123!',
            ])
            ->assertOk()
            ->assertJsonCount(8, 'data.recovery_codes')
            ->json('data.recovery_codes');
        $this->assertCount(8, array_diff($oldCodes, $regenerated));

        $this->withToken($token)
            ->deleteJson('/api/v1/me/two-factor-authentication', [
                'current_password' => 'Secure-password-123!',
            ])
            ->assertOk()
            ->assertJsonPath('data.enabled', false);
        $this->assertNull($user->fresh()->two_factor_secret);
    }

    public function test_mobile_email_verification_link_is_signed_and_marks_user_verified(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        $token = $user->createToken('Verification test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/me/email/verification-notification')
            ->assertOk()
            ->assertJsonPath('data.verified', false);

        Notification::assertSentTo(
            $user,
            MobileVerifyEmail::class,
            function (MobileVerifyEmail $notification) use ($user): bool {
                $url = $notification->toMail($user)->actionUrl;

                $this->getJson($url)
                    ->assertOk()
                    ->assertJsonPath('data.verified', true);

                return true;
            }
        );

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    private function currentValidCode(string $secret): string
    {
        return app(Google2FA::class)->getCurrentOtp($secret);
    }
}
