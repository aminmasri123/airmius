<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Event;
use App\Models\SportRoute;
use App\Models\Team;
use App\Models\User;
use App\Support\TeamRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EventRouteWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_private_event_explicitly_shares_a_coordinate_free_route_with_its_team(): void
    {
        [$owner, $member, $team] = $this->teamFixture();
        $outsider = User::factory()->create();
        $route = $this->privateRoute($owner, 'Waldintervall');

        $this->actingAs($owner)
            ->post(route('auth.events.store'), [
                'title' => 'Intervalltraining im Wald',
                'type' => 'training',
                'visibility' => 'private',
                'team_id' => $team->id,
                'sport_route_id' => $route->id,
                'start_time' => now()->addDay()->toIso8601String(),
            ])
            ->assertRedirect();

        $event = Event::query()->where('title', 'Intervalltraining im Wald')->firstOrFail();
        $this->assertSame($route->id, $event->sport_route_id);

        Sanctum::actingAs($member);

        $this->getJson("/api/v1/events/{$event->id}")
            ->assertOk()
            ->assertJsonPath('data.sport_route.id', $route->id)
            ->assertJsonPath('data.sport_route.start_name', 'Waldtor')
            ->assertJsonPath('data.sport_route.end_name', 'Waldstadion')
            ->assertJsonMissingPath('data.sport_route.route_geometry')
            ->assertJsonMissingPath('data.sport_route.waypoints')
            ->assertJsonMissingPath('data.sport_route.start_latitude');

        $this->getJson("/api/v1/sport-routes/{$route->id}")
            ->assertOk()
            ->assertJsonPath('data.route_geometry.type', 'LineString');

        Sanctum::actingAs($outsider);
        $this->getJson("/api/v1/events/{$event->id}")->assertNotFound();
        $this->getJson("/api/v1/sport-routes/{$route->id}")->assertNotFound();
    }

    public function test_event_forms_and_mobile_workspace_use_bounded_route_references_and_reject_foreign_routes(): void
    {
        [$owner, $member, $team] = $this->teamFixture();
        $outsider = User::factory()->create();
        $route = $this->privateRoute($owner, 'Eventrunde');
        $foreignRoute = $this->privateRoute($outsider, 'Fremde Privatrunde');
        $event = Event::query()->create([
            'user_id' => $owner->id,
            'team_id' => $team->id,
            'sport_route_id' => $route->id,
            'title' => 'Team-Routenlauf',
            'type' => 'training',
            'visibility' => 'private',
            'status' => 'scheduled',
            'start_time' => now()->addDay(),
        ]);

        $this->actingAs($member)
            ->get(route('auth.events.show', $event))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Dashboard/Events/Show')
                ->where('event.sport_route_reference.id', $route->id)
                ->missing('event.sport_route_reference.route_geometry')
                ->where('sportRoutes.0.id', $route->id));

        $this->actingAs($member)
            ->get(route('auth.training.logs.create', ['event_id' => $event->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Dashboard/Training/LogCreate')
                ->where('prefillEvent.id', $event->id)
                ->where('prefillEvent.sport_route_id', $route->id));

        Sanctum::actingAs($member);
        $this->getJson('/api/v1/events')
            ->assertOk()
            ->assertJsonFragment(['id' => $route->id, 'title' => 'Eventrunde'])
            ->assertJsonMissing(['title' => 'Fremde Privatrunde'])
            ->assertJsonMissingPath('sport_routes.0.route_geometry')
            ->assertJsonMissingPath('sport_routes.0.waypoints');

        $this->postJson('/api/v1/events', [
            'title' => 'Ungültige Fremdroute',
            'type' => 'training',
            'visibility' => 'public',
            'sport_route_id' => $foreignRoute->id,
            'start_time' => now()->addDays(2)->toIso8601String(),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sport_route_id');
    }

    public function test_flutter_event_source_keeps_route_selection_and_map_navigation_native(): void
    {
        $models = file_get_contents(base_path('mobile/airmius_mobile/lib/core/airmius_api_models.dart'));
        $center = file_get_contents(base_path('mobile/airmius_mobile/lib/screens/training_center_screen.dart'));
        $detail = file_get_contents(base_path('mobile/airmius_mobile/lib/screens/training_event_detail_screen.dart'));
        $translations = file_get_contents(base_path('mobile/airmius_mobile/lib/core/airmius_l10n.dart'));

        $this->assertStringContainsString('class AirmiusSportRouteReference', $models);
        $this->assertStringContainsString("'sport_route_id': _sportRouteId", $center);
        $this->assertStringContainsString('SportMapCenterScreen(initialRouteId: route.id)', $detail);
        $this->assertStringContainsString("'sport_route_id': _sportRouteId", $detail);
        $this->assertSame(4, substr_count($translations, "'events.routeShareHint':"));
    }

    /** @return array{User, User, Team} */
    private function teamFixture(): array
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $team = Team::factory()->create(['club_id' => $club->id]);
        $team->users()->attach($owner->id, ['role' => TeamRoles::COACH]);
        $team->users()->attach($member->id, ['role' => TeamRoles::PLAYER]);

        return [$owner, $member, $team];
    }

    private function privateRoute(User $owner, string $title): SportRoute
    {
        return SportRoute::query()->create([
            'user_id' => $owner->id,
            'title' => $title,
            'sport_type' => 'running',
            'visibility' => 'private',
            'status' => 'planned',
            'start_name' => 'Waldtor',
            'end_name' => 'Waldstadion',
            'start_latitude' => 49.2401,
            'start_longitude' => 6.9969,
            'end_latitude' => 49.2451,
            'end_longitude' => 7.0069,
            'distance_meters' => 6200,
            'waypoints' => [
                ['latitude' => 49.2401, 'longitude' => 6.9969],
                ['latitude' => 49.2451, 'longitude' => 7.0069],
            ],
            'route_geometry' => [
                'type' => 'LineString',
                'coordinates' => [[6.9969, 49.2401], [7.0069, 49.2451]],
            ],
        ]);
    }
}
