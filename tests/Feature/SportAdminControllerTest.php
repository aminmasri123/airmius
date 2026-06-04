<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Permission;
use App\Models\Post;
use App\Models\Sport;
use App\Models\Team;
use App\Models\User;
use App\Models\UserSport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SportAdminControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_manager_can_view_sport_catalog_with_usage_summary(): void
    {
        $admin = User::factory()->create();
        $this->grantPermissions($admin, ['system.manage']);

        $football = Sport::query()->create([
            'name' => 'Football',
            'slug' => 'football',
            'category' => 'Ballsport',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        Sport::query()->create([
            'name' => 'Yoga',
            'slug' => 'yoga',
            'category' => 'Mindbody',
            'is_active' => false,
            'sort_order' => 2,
        ]);

        $athlete = User::factory()->create();
        $club = Club::factory()->create([
            'owner_id' => $admin->id,
            'sport_type' => 'Football',
        ]);
        Team::factory()->create([
            'club_id' => $club->id,
            'sport_type' => 'football',
        ]);
        UserSport::query()->create([
            'user_id' => $athlete->id,
            'sport_id' => $football->id,
            'status' => 'active',
            'experience_level' => 'advanced',
        ]);
        Post::factory()->create([
            'user_id' => $athlete->id,
            'sport_id' => $football->id,
            'visibility' => 'public',
        ]);

        $this
            ->actingAs($admin)
            ->get(route('admin.sports.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Dashboard/Sports/Index')
                ->where('summary.sports_count', 2)
                ->where('summary.active_count', 1)
                ->where('summary.teams_count', 1)
                ->where('summary.clubs_count', 1)
                ->where('sports.0.name', 'Football')
                ->where('sports.0.teams_count', 1)
                ->where('sports.0.clubs_count', 1)
                ->where('sports.0.profiles_count', 1)
                ->where('sports.0.posts_count', 1)
                ->where('sports.0.usage_count', 4)
                ->where('categories.0', 'Ballsport')
            );
    }

    public function test_system_manager_can_create_sport_with_generated_slug(): void
    {
        $admin = User::factory()->create();
        $this->grantPermissions($admin, ['system.manage']);

        $this
            ->actingAs($admin)
            ->post(route('admin.sports.store'), [
                'name' => 'Padel Tennis',
                'slug' => '',
                'category' => 'Racketsport',
                'sort_order' => 12,
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('sports', [
            'name' => 'Padel Tennis',
            'slug' => 'padel-tennis',
            'category' => 'Racketsport',
            'sort_order' => 12,
            'is_active' => true,
        ]);
    }

    public function test_updating_sport_slug_rewrites_team_and_club_legacy_sport_type(): void
    {
        $admin = User::factory()->create();
        $this->grantPermissions($admin, ['system.manage']);

        $sport = Sport::query()->create([
            'name' => 'Football',
            'slug' => 'football',
            'category' => 'Ballsport',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $club = Club::factory()->create([
            'owner_id' => $admin->id,
            'sport_type' => 'Football',
        ]);
        $team = Team::factory()->create([
            'club_id' => $club->id,
            'sport_type' => 'football',
        ]);

        $this
            ->actingAs($admin)
            ->put(route('admin.sports.update', $sport), [
                'name' => 'Soccer',
                'slug' => 'soccer',
                'category' => 'Ballsport',
                'sort_order' => 3,
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertSame('soccer', $sport->fresh()->slug);
        $this->assertSame('soccer', $team->fresh()->sport_type);
        $this->assertSame('soccer', $club->fresh()->sport_type);
    }

    public function test_used_sport_cannot_be_deleted(): void
    {
        $admin = User::factory()->create();
        $athlete = User::factory()->create();
        $this->grantPermissions($admin, ['system.manage']);

        $sport = Sport::query()->create([
            'name' => 'Running',
            'slug' => 'running',
            'category' => 'Ausdauer',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        UserSport::query()->create([
            'user_id' => $athlete->id,
            'sport_id' => $sport->id,
            'status' => 'active',
            'experience_level' => 'beginner',
        ]);

        $this
            ->actingAs($admin)
            ->delete(route('admin.sports.destroy', $sport), [
                'confirmation' => 'delete',
            ])
            ->assertStatus(422);

        $this->assertDatabaseHas('sports', [
            'id' => $sport->id,
        ]);
    }

    private function grantPermissions(User $user, array $permissions): void
    {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $user->givePermissionTo($permissions);
    }
}
