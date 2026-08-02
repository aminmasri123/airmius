<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\NavigationModules;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NavigationModulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_navigation_matrix_matches_each_persona(): void
    {
        $this->seed(RolesPermissionsSeeder::class);

        $athlete = User::factory()->create();
        $athlete->assignRole('player');

        $coach = User::factory()->create();
        $coach->assignRole('coach');

        $club = User::factory()->create();
        $club->assignRole('club_owner');

        $this->assertSame(
            [NavigationModules::ATHLETE],
            NavigationModules::enabledFor($athlete),
        );
        $this->assertSame(
            [NavigationModules::ATHLETE, NavigationModules::COACH],
            NavigationModules::enabledFor($coach),
        );
        $this->assertSame(
            NavigationModules::keys(),
            NavigationModules::enabledFor($club),
        );
    }

    public function test_user_can_disable_available_navigation_modules_without_granting_new_ones(): void
    {
        $this->seed(RolesPermissionsSeeder::class);

        $coach = User::factory()->create([
            'country' => 'DE',
        ]);
        $coach->assignRole('coach');

        $this->actingAs($coach)
            ->put(route('auth.settings.update'), [
                'country' => 'DE',
                'enabled_navigation_modules' => [NavigationModules::COACH, NavigationModules::CLUB],
            ])
            ->assertRedirect();

        $coach->refresh();

        $this->assertSame([NavigationModules::COACH], $coach->enabled_navigation_modules);
        $this->assertSame([NavigationModules::COACH], NavigationModules::enabledFor($coach));
        $this->assertNotContains(NavigationModules::CLUB, NavigationModules::enabledFor($coach));

        $this->actingAs($coach)
            ->get(route('auth.settings'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Dashboard/Settings/Index')
                ->where('navigationModules.enabled', [NavigationModules::COACH])
                ->where('auth.user.navigation_modules.enabled', [NavigationModules::COACH])
            );
    }

    public function test_feed_route_remains_available_as_the_common_area(): void
    {
        $this->seed(RolesPermissionsSeeder::class);

        $club = User::factory()->create();
        $club->assignRole('club_owner');
        $club->forceFill([
            'enabled_navigation_modules' => [NavigationModules::CLUB],
        ])->save();

        $this->actingAs($club)
            ->get(route('auth.feed.index'))
            ->assertOk();
    }
}
