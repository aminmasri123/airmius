<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureFeatureRollout;
use App\Models\Club;
use App\Models\User;
use App\Services\RolloutDecisionService;
use App\Support\RolloutAcceptanceRegistry;
use App\Support\StagedRolloutReadinessReport;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class StagedRolloutAcceptanceContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_contract_and_repository_report_cover_stages_kill_switches_routes_guest_safety_and_raci(): void
    {
        $registry = RolloutAcceptanceRegistry::definitions();
        $report = app(StagedRolloutReadinessReport::class)->make();

        $this->assertSame('staged-rollout.v1', $registry['contract']);
        $this->assertSame([0, 5, 25, 100], $registry['stages']);
        $this->assertCount(4, $registry['features']);
        $this->assertSame('global_kill_switch', $registry['precedence'][0]);
        $this->assertSame('full_rollout_short_circuit', $registry['precedence'][2]);
        $this->assertSame('authorized_pilot_club_override', $registry['precedence'][3]);
        $this->assertTrue($registry['guest_safety']['shared_cache_preserved']);
        $this->assertFalse($registry['guest_safety']['tracking_added']);
        $this->assertSame('CTO', $registry['responsibility']['accountable']);
        $this->assertTrue($report['automated_checks_passed']);
        $this->assertSame('no_go', $report['decision']);
        $this->assertSame(0, $report['summary']['fail']);
        $this->assertSame('pending', $this->checkStatus($report, 'external.staged_rollout'));
        $this->assertFalse($report['privacy']['stores_assignments']);
        $this->assertFalse($report['privacy']['outputs_buckets']);
        $this->assertFalse($report['privacy']['adds_guest_tracking']);
    }

    public function test_assignment_is_stable_and_matches_the_configured_stage_without_storage(): void
    {
        config([
            'airmius_rollout.salt' => 'test-rollout-secret',
            'airmius_rollout.features.club_operating_system.stage' => 25,
            'airmius_rollout.pilot_override' => false,
        ]);
        $service = app(RolloutDecisionService::class);
        $request = Request::create('/club-cockpit');

        foreach (range(1, 120) as $id) {
            $user = new User;
            $user->forceFill(['id' => $id]);
            $first = $service->forRequest('club_operating_system', $user, $request);
            $second = $service->forRequest('club_operating_system', $user, $request);

            $this->assertSame($first, $second);
            $this->assertSame($service->bucketFor('club_operating_system', (string) $id) < 25, $first['enabled']);
            $this->assertContains($first['reason'], ['assigned', 'outside_stage']);
        }
    }

    public function test_global_and_feature_kill_switches_win_and_invalid_stages_fail_closed(): void
    {
        $user = new User;
        $user->forceFill(['id' => 41]);
        $request = Request::create('/club-cockpit');
        $service = app(RolloutDecisionService::class);

        config(['airmius_rollout.global_kill_switch' => true]);
        $this->assertSame('global_kill_switch', $service->forRequest('club_operating_system', $user, $request)['reason']);

        config([
            'airmius_rollout.global_kill_switch' => false,
            'airmius_rollout.features.club_operating_system.kill_switch' => true,
        ]);
        $this->assertSame('feature_kill_switch', $service->forRequest('club_operating_system', $user, $request)['reason']);

        config([
            'airmius_rollout.features.club_operating_system.kill_switch' => false,
            'airmius_rollout.features.club_operating_system.stage' => 42,
        ]);
        $decision = $service->forRequest('club_operating_system', $user, $request);
        $this->assertFalse($decision['enabled']);
        $this->assertSame('invalid_configuration', $decision['reason']);
    }

    public function test_pilot_override_requires_a_real_linked_club_and_never_bypasses_a_kill_switch(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        config([
            'airmius_pilot.enabled' => true,
            'airmius_pilot.club_ids' => [$club->id],
            'airmius_rollout.pilot_override' => true,
            'airmius_rollout.features.club_operating_system.stage' => 0,
        ]);
        $request = Request::create('/club-cockpit', server: ['HTTP_X_CLUB_ID' => (string) $club->id]);
        $service = app(RolloutDecisionService::class);

        $outsiderDecision = $service->forRequest('club_operating_system', $outsider, $request);
        $ownerDecision = $service->forRequest('club_operating_system', $owner, $request);
        $this->assertFalse($outsiderDecision['enabled']);
        $this->assertSame('stage_zero', $outsiderDecision['reason']);
        $this->assertTrue($ownerDecision['enabled']);
        $this->assertSame('authorized_pilot_club', $ownerDecision['reason']);

        config(['airmius_rollout.features.club_operating_system.kill_switch' => true]);
        $this->assertSame('feature_kill_switch', $service->forRequest('club_operating_system', $owner, $request)['reason']);
    }

    public function test_disabled_workspaces_use_localized_safe_web_and_api_fallbacks(): void
    {
        config([
            'airmius_rollout.features.coach_daily_control.stage' => 0,
            'airmius_rollout.pilot_override' => false,
        ]);
        app()->setLocale('fr');
        $user = new User;
        $user->forceFill(['id' => 82]);
        $middleware = app(EnsureFeatureRollout::class);

        $apiRequest = Request::create('/api/v1/dashboard/daily-flow', 'GET', server: ['HTTP_ACCEPT' => 'application/json']);
        $apiRequest->setUserResolver(static fn (): User => $user);
        $apiResponse = $middleware->handle($apiRequest, static fn () => response()->json(['unexpected' => true]), 'coach_daily_control');
        $apiPayload = json_decode((string) $apiResponse->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(503, $apiResponse->getStatusCode());
        $this->assertSame('feature_rollout_unavailable', $apiPayload['code']);
        $this->assertTrue($apiPayload['error']['retryable']);
        $this->assertSame('300', $apiResponse->headers->get('Retry-After'));
        $this->assertStringContainsString('progressivement', $apiPayload['message']);

        $webRequest = Request::create('/trainer-cockpit');
        $webRequest->setUserResolver(static fn (): User => $user);
        $webRequest->setLaravelSession(app('session')->driver());
        $webResponse = $middleware->handle($webRequest, static fn () => response('unexpected'), 'coach_daily_control');
        $this->assertSame(303, $webResponse->getStatusCode());
        $this->assertStringEndsWith('/workspaces', $webResponse->headers->get('Location'));
        $this->assertStringContainsString('progressivement', (string) $webRequest->session()->get('message'));
    }

    public function test_global_kill_switch_does_not_gate_guest_pages_or_public_apis(): void
    {
        config(['airmius_rollout.global_kill_switch' => true]);

        $response = $this->get('/vereine');

        $response->assertOk();
        $response->assertHeaderMissing('Retry-After');
        $this->assertStringNotContainsString('feature_rollout_unavailable', (string) $response->getContent());
    }

    public function test_audit_command_passes_technical_checks_but_strict_mode_keeps_real_rollout_open(): void
    {
        $exitCode = Artisan::call('airmius:audit-staged-rollout', ['--json' => true]);
        $output = Artisan::output();

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringNotContainsString((string) config('airmius_rollout.salt'), $output);
        $this->artisan('airmius:audit-staged-rollout', ['--json' => true, '--strict' => true])
            ->assertExitCode(Command::FAILURE);
    }

    /** @param array<string, mixed> $report */
    private function checkStatus(array $report, string $id): string
    {
        $check = collect($report['checks'])->firstWhere('id', $id);
        $this->assertIsArray($check, "Missing rollout check: {$id}");

        return $check['status'];
    }
}
