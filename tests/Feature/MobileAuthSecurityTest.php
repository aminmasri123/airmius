<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\MobileVerifyEmail;
use App\Notifications\TwoFactorLoginCodeRequested;
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

    public function test_logout_revokes_only_current_mobile_token_and_rejects_its_reuse(): void
    {
        $user = User::factory()->create();
        $current = $user->createToken('QA current phone');
        $other = $user->createToken('QA other phone');
        $this->withToken($current->plainTextToken)->postJson('/api/v1/auth/logout')->assertOk();
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $current->accessToken->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $other->accessToken->id]);
        $this->app['auth']->forgetGuards();
        $this->withToken($current->plainTextToken)->getJson('/api/v1/me')->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->withToken($other->plainTextToken)->getJson('/api/v1/me')->assertOk();
    }

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

    public function test_verified_user_can_complete_mobile_two_factor_with_email_otp(): void
    {
        Notification::fake();
        $user = User::factory()->create([
            'email' => 'email-otp@example.test',
            'email_verified_at' => now(),
            'password' => Hash::make('Secure-password-123!'),
        ]);
        app(EnableTwoFactorAuthentication::class)($user);
        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Secure-password-123!',
            'device_name' => 'Airmius Email OTP Test',
        ]);

        $login
            ->assertStatus(202)
            ->assertJsonPath('data.available_methods.2', 'email_otp');

        $challengeToken = $login->json('data.challenge_token');

        $this->postJson('/api/v1/auth/two-factor-challenge/email-code', [
            'challenge_token' => $challengeToken,
        ])
            ->assertOk()
            ->assertJsonPath('data.expires_in', 600);

        $emailCode = null;
        Notification::assertSentTo(
            $user,
            TwoFactorLoginCodeRequested::class,
            function (TwoFactorLoginCodeRequested $notification) use (&$emailCode): bool {
                $emailCode = $notification->code;

                return true;
            }
        );

        $this->postJson('/api/v1/auth/two-factor-challenge', [
            'challenge_token' => $challengeToken,
            'email_code' => $emailCode,
        ])
            ->assertOk()
            ->assertJsonStructure(['data' => ['token', 'token_type', 'user']]);

        $this->assertSame(
            'Airmius Email OTP Test',
            $user->tokens()->latest('id')->first()->name
        );
    }

    public function test_unverified_email_is_not_offered_as_mobile_two_factor_method(): void
    {
        $user = User::factory()->unverified()->create([
            'password' => Hash::make('Secure-password-123!'),
        ]);
        app(EnableTwoFactorAuthentication::class)($user);
        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Secure-password-123!',
        ]);

        $login
            ->assertStatus(202)
            ->assertJsonMissing(['email_otp']);

        $this->postJson('/api/v1/auth/two-factor-challenge/email-code', [
            'challenge_token' => $login->json('data.challenge_token'),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
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

                $this->get($url)
                    ->assertOk()
                    ->assertSee('E-Mail-Adresse bestätigt');

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
