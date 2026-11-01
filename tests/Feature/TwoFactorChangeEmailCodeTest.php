<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\TwoFactorChangeCodeRequested;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Fortify;
use Laravel\Sanctum\Sanctum;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorChangeEmailCodeTest extends TestCase
{
    use RefreshDatabase;

    private function issue(User $user, string $purpose = 'enable'): array
    {
        $code = null;
        $response = $this->postJson('/api/v1/me/two-factor-authentication/email-code', ['purpose' => $purpose])->assertOk();
        Notification::assertSentTo($user, TwoFactorChangeCodeRequested::class, function ($notice) use (&$code, $purpose) {
            if ($notice->purpose !== $purpose) {
                return false;
            }
            $code = $notice->code;

            return true;
        });

        $code = Notification::sent($user, TwoFactorChangeCodeRequested::class)->where('purpose', $purpose)->last()->code;

        return ['challenge_token' => $response->json('data.challenge_token'), 'email_code' => $code];
    }

    public function test_google_style_account_can_enable_confirm_regenerate_and_disable_without_password(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $credentials = $this->issue($user);
        $response = $this->postJson('/api/v1/me/two-factor-authentication', $credentials)->assertCreated()
            ->assertJsonPath('data.pending_confirmation', true)->assertJsonPath('data.enabled', false);
        $this->assertNotEmpty($response->json('data.setup_key'));
        $this->assertNotEmpty($response->json('data.qr_code'));
        $this->postJson('/api/v1/me/two-factor-authentication', $credentials)->assertUnprocessable();

        $secret = Fortify::currentEncrypter()->decrypt($user->fresh()->two_factor_secret);
        $this->postJson('/api/v1/me/two-factor-authentication/confirm', [
            'code' => (new Google2FA)->getCurrentOtp($secret),
        ])->assertOk()->assertJsonPath('data.enabled', true)->assertJsonCount(8, 'data.recovery_codes');
        $this->postJson('/api/v1/me/two-factor-recovery-codes', $this->issue($user, 'recovery'))
            ->assertOk()->assertJsonCount(8, 'data.recovery_codes');
        $this->deleteJson('/api/v1/me/two-factor-authentication', $this->issue($user, 'disable'))
            ->assertOk()->assertJsonPath('data.enabled', false);
    }

    public function test_code_is_bound_to_session_and_action(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->withHeader('Authorization', 'Bearer session-a');
        $credentials = $this->issue($user);
        $this->withHeader('Authorization', 'Bearer session-b')
            ->postJson('/api/v1/me/two-factor-authentication', $credentials)->assertUnprocessable();
        $this->withHeader('Authorization', 'Bearer session-a')
            ->deleteJson('/api/v1/me/two-factor-authentication', $credentials)->assertUnprocessable();
        $this->postJson('/api/v1/me/two-factor-authentication', $credentials)->assertCreated();
    }

    public function test_expired_code_is_rejected(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $credentials = $this->issue($user);
        $this->travel(601)->seconds();
        $this->postJson('/api/v1/me/two-factor-authentication', $credentials)->assertUnprocessable();
        $this->assertNull($user->fresh()->two_factor_secret);
    }

    public function test_new_code_replaces_old_proof_and_cannot_be_used_by_another_account(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $old = $this->issue($user);
        $new = $this->issue($user);
        $this->postJson('/api/v1/me/two-factor-authentication', $old)->assertUnprocessable();
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/v1/me/two-factor-authentication', $new)->assertUnprocessable();
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/me/two-factor-authentication', $new)->assertCreated();
    }

    public function test_resending_invalidates_previous_code_and_does_not_reset_failed_attempts(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $credentials = $this->issue($user);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/me/two-factor-authentication', [...$credentials, 'email_code' => '000000'])->assertUnprocessable();
        }
        $this->travel(61)->seconds();
        $newCredentials = $this->issue($user);
        $this->postJson('/api/v1/me/two-factor-authentication', $newCredentials)->assertStatus(429);
        $this->assertNull($user->fresh()->two_factor_secret);
    }

    public function test_unverified_email_and_unauthenticated_requests_cannot_get_codes(): void
    {
        Notification::fake();
        $this->postJson('/api/v1/me/two-factor-authentication/email-code', ['purpose' => 'enable'])->assertUnauthorized();
        $user = User::factory()->unverified()->create();
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/me/two-factor-authentication/email-code', ['purpose' => 'enable'])->assertUnprocessable();
        Notification::assertNothingSent();
    }

    public function test_web_can_use_email_confirmation_without_confirming_a_password(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->actingAs($user, 'web');
        $this->withSession(['test_security_confirmation' => true]);
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->withCredentials();
        $response = $this->postJson('/user/security/two-factor/email-code', ['purpose' => 'enable'])->assertOk();
        $code = Notification::sent($user, TwoFactorChangeCodeRequested::class)->last()->code;
        $this->postJson('/user/security/two-factor', [
            'challenge_token' => $response->json('data.challenge_token'), 'email_code' => $code,
        ])->assertCreated();
        $this->assertFalse(session()->has('auth.password_confirmed_at'));
    }

    public function test_password_confirmation_still_works_and_missing_or_wrong_proofs_fail(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/me/two-factor-authentication')->assertUnprocessable();
        $this->postJson('/api/v1/me/two-factor-authentication', ['current_password' => 'wrong'])->assertUnprocessable();
        $this->postJson('/api/v1/me/two-factor-authentication', ['current_password' => 'password'])->assertCreated();
    }
}
