<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureApiProcessingPurpose;
use App\Models\Club;
use App\Models\ClubMembershipRequest;
use App\Models\User;
use App\Support\AirmiusRoleMatrix;
use App\Support\CriticalJourneyRegistry;
use App\Support\PlatformModuleRegistry;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CriticalJourneyContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_five_critical_journeys_reference_live_personas_modules_and_routes(): void
    {
        $journeys = CriticalJourneyRegistry::definitions();
        $modules = PlatformModuleRegistry::definitions();
        $routeUris = collect(Route::getRoutes()->getRoutes())
            ->map(fn (IlluminateRoute $route) => $this->normalizedRoutePath($route->uri()))
            ->all();

        $this->assertSame([
            'athlete_day',
            'coach_week',
            'member_lifecycle',
            'sponsor_measurement',
            'recruiting_to_team',
        ], array_keys($journeys));

        foreach ($journeys as $key => $journey) {
            $this->assertNotEmpty($journey['contract'], "{$key} contract");
            $this->assertNotEmpty($journey['responsible'], "{$key} responsible");
            $this->assertNotEmpty($journey['accountable'], "{$key} accountable");
            $this->assertGreaterThanOrEqual(2, count($journey['privacy_boundaries']), "{$key} privacy boundaries");
            $this->assertGreaterThanOrEqual(5, count($journey['steps']), "{$key} connected steps");

            foreach ($journey['personas'] as $persona) {
                $this->assertArrayHasKey($persona, AirmiusRoleMatrix::all(), "{$key} persona {$persona}");
            }

            foreach ($journey['steps'] as $step) {
                $this->assertArrayHasKey($step['module'], $modules, "{$key}.{$step['key']} module");
                $this->assertTrue(Route::has($step['web_route']), "{$key}.{$step['key']} web route {$step['web_route']}");
                $this->assertContains($step['api_path'], $routeUris, "{$key}.{$step['key']} API path {$step['api_path']}");
                $this->assertSame(
                    $step['module'],
                    PlatformModuleRegistry::forApiPath($step['api_path'])['key'] ?? null,
                    "{$key}.{$step['key']} API governance",
                );
            }
        }
    }

    public function test_every_api_route_using_purpose_middleware_resolves_to_one_owned_module(): void
    {
        $governed = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (IlluminateRoute $route) => in_array(EnsureApiProcessingPurpose::class, $route->gatherMiddleware(), true));

        $this->assertGreaterThan(300, $governed->count());

        foreach ($governed as $route) {
            $path = $this->normalizedRoutePath($route->uri());
            $resolved = PlatformModuleRegistry::forApiPath($path);

            $this->assertNotNull($resolved, ($route->getName() ?? $path).' has no module owner');
            $this->assertNotEmpty($resolved['module']['processing_purpose'] ?? null, ($route->getName() ?? $path).' has no purpose');
        }
    }

    public function test_meta_publishes_versioned_journeys_and_complete_sponsor_persona(): void
    {
        $response = $this->getJson('/api/v1/meta')
            ->assertOk()
            ->assertJsonPath('data.critical_journeys.contract', CriticalJourneyRegistry::CONTRACT)
            ->assertJsonPath('data.critical_journeys.items.0.key', 'athlete_day')
            ->assertJsonPath('data.critical_journeys.items.0.steps.0.api_path', 'api/v1/dashboard/daily-flow')
            ->assertJsonPath('data.critical_journeys.items.3.key', 'sponsor_measurement')
            ->assertJsonPath('data.critical_journeys.items.3.contract', 'growth-workspace.v1')
            ->assertJsonPath('data.critical_journeys.items.4.key', 'recruiting_to_team')
            ->assertJsonPath('data.role_matrix.4.key', AirmiusRoleMatrix::SPONSOR)
            ->assertJsonPath('data.role_matrix.4.scope', 'sponsor')
            ->assertJsonPath('data.role_matrix.4.platform_roles.0', 'sponsor')
            ->assertJsonPath('data.role_matrix.4.platform_roles.1', 'sponsor_manager');

        $response->assertJsonMissingPath('data.critical_journeys.items.0.responsible');
        $response->assertJsonMissingPath('data.critical_journeys.items.0.accountable');
        $response->assertJsonMissingPath('data.critical_journeys.items.0.privacy_boundaries');
        $response->assertJsonMissingPath('data.critical_journeys.items.0.steps.0.web_route');
    }

    public function test_critical_public_and_authenticated_paths_publish_correct_governance_headers(): void
    {
        $this->getJson('/api/v1/public/recruiting/jobs')
            ->assertOk()
            ->assertHeader('X-Airmius-Module', 'recruiting')
            ->assertHeader('X-Airmius-Data-Purpose', 'organization');

        $this->getJson('/api/v1/public/sponsors')
            ->assertOk()
            ->assertHeader('X-Airmius-Module', 'sponsors')
            ->assertHeader('X-Airmius-Data-Purpose', 'marketing');

        $this->seed(RolesPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('coach');
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/dashboard/daily-flow')
            ->assertOk()
            ->assertHeader('X-Airmius-Module', 'training')
            ->assertHeader('X-Airmius-Data-Purpose', 'training');

        $this->getJson('/api/v1/trainer-cockpit')
            ->assertOk()
            ->assertHeader('X-Airmius-Module', 'training')
            ->assertHeader('X-Airmius-Data-Purpose', 'training');

        $club = Club::factory()->create(['owner_id' => User::factory()->create()->id]);
        $membershipRequest = ClubMembershipRequest::query()->create([
            'club_id' => $club->id,
            'user_id' => $user->id,
            'type' => 'membership',
            'status' => 'pending',
        ]);

        $this->getJson('/api/v1/membership-applications/'.$membershipRequest->id)
            ->assertOk()
            ->assertHeader('X-Airmius-Module', 'members')
            ->assertHeader('X-Airmius-Data-Purpose', 'organization');
    }

    private function normalizedRoutePath(string $uri): string
    {
        $uri = str_replace('{slash}', '', $uri);

        return preg_replace('/\{[^}]+\??\}/', '1', $uri);
    }
}
