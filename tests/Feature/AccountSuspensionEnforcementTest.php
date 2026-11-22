<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Permission;
use App\Support\MobileTwoFactorChallenge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Tests\TestCase;

class AccountSuspensionEnforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_bearer_tokens_cannot_read_or_write_after_suspension(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('existing phone')->plainTextToken;
        $this->withToken($token)->getJson('/api/v1/me')->assertOk();
        $user->update(['account_status' => 'suspended', 'suspended_until' => now()->addDay()]);
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/v1/me')->assertStatus(423);
        $this->withToken($token)->getJson('/api/user')->assertStatus(423);
        $this->withToken($token)->postJson('/api/v1/country-catalog', [])->assertStatus(423);
        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();
    }

    public function test_suspended_user_cannot_obtain_a_new_login_token(): void
    {
        $user = User::factory()->create(['account_status' => 'suspended']);
        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertStatus(423);
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_suspension_during_two_factor_challenge_prevents_token_issuance(): void
    {
        $user = User::factory()->create();
        app(EnableTwoFactorAuthentication::class)($user);
        $user->forceFill(['two_factor_confirmed_at' => now()])->save();
        $challenge = app(MobileTwoFactorChallenge::class)->issue($user, 'phone');
        $user->update(['account_status' => 'suspended']);
        $this->postJson('/api/v1/auth/two-factor-challenge', [
            'challenge_token' => $challenge['token'],
            'code' => '123456',
        ])->assertStatus(423);
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_existing_web_session_is_blocked_but_can_log_out(): void
    {
        $user = User::factory()->create(['account_status' => 'suspended']);
        $this->actingAs($user, 'web')->get('/dashboard')->assertStatus(423);
        $this->post('/logout')->assertRedirect();
    }

    public function test_expired_suspension_is_lifted_before_api_access(): void
    {
        $user = User::factory()->create([
            'account_status' => 'suspended',
            'suspended_until' => now()->subMinute(),
            'suspension_reason' => 'Expired',
        ]);
        $this->withToken($user->createToken('phone')->plainTextToken)
            ->getJson('/api/v1/me')->assertOk();
        $this->assertSame('active', $user->fresh()->account_status);
        $this->assertNull($user->fresh()->suspension_reason);
    }

    public function test_system_permission_does_not_bypass_an_explicit_suspension(): void
    {
        $user = User::factory()->create(['account_status' => 'suspended']);
        $user->givePermissionTo(Permission::findOrCreate('system.manage', 'web'));
        $this->actingAs($user, 'web')->get('/dashboard')->assertStatus(423);
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/me')->assertStatus(423);
    }

    public function test_anonymized_accounts_cannot_use_existing_api_tokens(): void
    {
        $user = User::factory()->create(['privacy_status' => 'anonymized']);
        $this->withToken($user->createToken('old phone')->plainTextToken)
            ->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_api_suspension_message_uses_the_requested_locale(): void
    {
        $user = User::factory()->create(['account_status' => 'suspended']);
        $this->withToken($user->createToken('phone')->plainTextToken)
            ->withHeader('X-Locale', 'en')
            ->getJson('/api/v1/me')
            ->assertStatus(423)
            ->assertJsonPath('message', trans('account_access.suspended', [], 'en'));
    }
}
