<?php

namespace Tests\Unit;

use App\Models\Club;
use App\Models\User;
use App\Services\Ai\AiGatewayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;
use Tests\TestCase;

class AiGatewayServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_redacts_sensitive_context_before_prompt_use(): void
    {
        $gateway = app(AiGatewayService::class);

        $redacted = $gateway->redactContext([
            'goal' => 'Bitte Max Muster unter max@example.com anrufen: +49 151 12345678.',
            'athlete_profile' => [
                'email' => 'child@example.test',
                'iban' => 'DE89370400440532013000',
                'notes' => 'IBAN DE89370400440532013000 steht im Altprofil.',
            ],
        ]);

        $this->assertSame('[redacted]', $redacted['athlete_profile']['email']);
        $this->assertSame('[redacted]', $redacted['athlete_profile']['iban']);
        $this->assertStringContainsString('[redacted-email]', $redacted['goal']);
        $this->assertStringContainsString('[redacted-phone]', $redacted['goal']);
        $this->assertStringContainsString('[redacted-iban]', $redacted['athlete_profile']['notes']);
    }

    public function test_it_enforces_tenant_scope_for_club_bound_ai_requests(): void
    {
        $gateway = app(AiGatewayService::class);
        $user = User::factory()->create();
        $allowedClub = Club::factory()->create(['owner_id' => $user->id]);
        $otherClub = Club::factory()->create(['owner_id' => User::factory()]);

        $user->clubs()->updateExistingPivot($allowedClub->id, [
            'role' => 'admin',
            'membership_status' => 'active',
        ]);

        $context = $gateway->context($user, 'training_plan_generation', $allowedClub->id);

        $this->assertSame('ai-gateway.v1', $context['gateway_version']);
        $this->assertSame('training-plan.v1', $context['prompt_version']);
        $this->assertSame($allowedClub->id, $context['tenant_scope']['club_id']);
        $this->assertContains($allowedClub->id, $context['tenant_scope']['allowed_club_ids']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('KI-Mandantenscope');

        $gateway->context($user, 'training_plan_generation', $otherClub->id);
    }

    public function test_it_filters_disabled_or_unconfigured_providers(): void
    {
        config([
            'airmius_ai.primary_provider' => 'disabled',
            'airmius_ai.fallback_provider' => 'ready',
            'airmius_ai.features.training_plan_generation' => [
                'enabled' => true,
                'primary_provider' => 'disabled',
                'fallback_provider' => 'ready',
                'prompt_version' => 'training-plan.test',
            ],
            'airmius_ai.providers' => [
                'disabled' => [
                    'enabled' => false,
                    'api_key' => 'secret',
                    'model' => 'model-a',
                ],
                'missing_model' => [
                    'enabled' => true,
                    'api_key' => 'secret',
                    'model' => null,
                ],
                'ready' => [
                    'enabled' => true,
                    'api_key' => 'secret',
                    'model' => 'model-b',
                ],
            ],
        ]);

        $gateway = app(AiGatewayService::class);

        $this->assertFalse($gateway->providerAvailable('disabled'));
        $this->assertFalse($gateway->providerAvailable('missing_model'));
        $this->assertTrue($gateway->providerAvailable('ready'));
        $this->assertSame(['ready'], $gateway->providersForFeature('training_plan_generation'));
    }

    public function test_it_rate_limits_per_user_and_tenant_scope(): void
    {
        RateLimiter::clear('ai-gateway:training_plan_generation:user:1');
        RateLimiter::clear('ai-gateway:training_plan_generation:club:10');
        config([
            'airmius_ai.rate_limit.max_attempts' => 1,
            'airmius_ai.rate_limit.decay_seconds' => 120,
        ]);

        $gateway = app(AiGatewayService::class);
        $user = User::factory()->create(['id' => 1]);

        $gateway->beforeAttempt($user, 'training_plan_generation');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('KI-Rate-Limit');

        $gateway->beforeAttempt($user, 'training_plan_generation');
    }
}
