<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Event;
use App\Models\File;
use App\Models\Friendship;
use App\Models\LearningCourse;
use App\Models\MarketplaceProduct;
use App\Models\Team;
use App\Models\User;
use App\Services\GlobalSearchService;
use App\Services\SearchModuleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_search_is_not_available_to_guests(): void
    {
        $this->getJson(route('auth.search', ['q' => 'Run']))->assertUnauthorized();
        $this->getJson('/api/v1/search?q=Run')->assertUnauthorized();
    }

    public function test_global_search_requires_at_least_two_characters(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson(route('auth.search', ['q' => 'a']))
            ->assertOk()
            ->assertJsonPath('results', [])
            ->assertJsonPath('meta.minimum_length', 2)
            ->assertJsonPath('meta.count', 0)
            ->assertJsonPath('meta.version', GlobalSearchService::VERSION);

        $cacheControl = (string) $response->headers->get('Cache-Control');
        foreach (['private', 'no-store', 'no-cache', 'must-revalidate', 'max-age=0'] as $directive) {
            $this->assertStringContainsString($directive, $cacheControl);
        }

        $this->actingAs($user)
            ->getJson(route('auth.search', ['q' => str_repeat('a', 101)]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('q');
    }

    public function test_search_routes_use_the_dedicated_rate_limiter(): void
    {
        foreach (['auth.search', 'api.v1.search'] as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);

            $this->assertNotNull($route);
            $this->assertContains('throttle:global-search', $route->gatherMiddleware());
        }
    }

    public function test_global_search_returns_users_clubs_and_teams_for_layout_contract(): void
    {
        $user = User::factory()->create([
            'name' => 'Runner Current',
            'email' => 'current-runner@example.test',
        ]);

        $matchedUser = User::factory()->create([
            'name' => 'Runner Match',
            'email' => 'runner-match@example.test',
        ]);

        $clubOwner = User::factory()->create();
        $club = Club::factory()->create([
            'name' => 'Runner Club',
            'owner_id' => $clubOwner->id,
            'verification_status' => 'verified',
        ]);
        $club->users()->syncWithoutDetaching([
            $user->id => ['role' => 'member', 'roles' => ['member']],
        ]);

        $team = Team::factory()->create([
            'club_id' => $club->id,
            'name' => 'Runner Team',
            'sport_type' => 'strassenlauf',
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('auth.search', ['q' => 'Runner']))
            ->assertOk();

        $results = collect($response->json('results'));

        $this->assertGreaterThanOrEqual(3, $results->count());
        $this->assertTrue($results->contains(fn (array $result) => $result['type'] === 'user' && $result['id'] === $matchedUser->id));
        $this->assertFalse($results->contains(fn (array $result) => $result['type'] === 'user' && $result['id'] === $user->id));
        $this->assertTrue($results->contains(fn (array $result) => $result['type'] === 'club' && $result['id'] === $club->id));
        $this->assertTrue($results->contains(fn (array $result) => $result['type'] === 'team' && $result['id'] === $team->id));
        $this->assertSame(route('auth.teams.join-requests.store', $team->id), $results->firstWhere('type', 'team')['join_url']);
        $this->assertArrayNotHasKey('email', $results->firstWhere('type', 'user'));
    }

    public function test_email_discoverability_is_reserved_for_user_managers(): void
    {
        $viewer = User::factory()->create();
        $target = User::factory()->create([
            'name' => 'Completely Different Name',
            'email' => 'hidden-search-address@example.test',
            'profile_visibility' => 'public',
        ]);

        $ordinaryResults = collect($this->actingAs($viewer)
            ->getJson(route('auth.search', ['q' => 'hidden-search-address@example.test']))
            ->assertOk()
            ->json('results'));

        $this->assertFalse($ordinaryResults->contains('id', $target->id));

        Permission::findOrCreate('users.view', 'web');
        $viewer->givePermissionTo('users.view');

        $managedResults = collect($this->actingAs($viewer->fresh())
            ->getJson(route('auth.search', ['q' => 'hidden-search-address@example.test']))
            ->assertOk()
            ->json('results'));

        $managedUser = $managedResults->firstWhere('id', $target->id);
        $this->assertNotNull($managedUser);
        $this->assertArrayNotHasKey('email', $managedUser);
    }

    public function test_search_returns_localized_module_destinations_without_exposing_admin_modules(): void
    {
        $viewer = User::factory()->create();

        $training = collect($this->actingAs($viewer)
            ->getJson(route('auth.search', ['q' => 'Training']))
            ->assertOk()
            ->json('results'))
            ->firstWhere('module_key', 'training');

        $this->assertNotNull($training);
        $this->assertSame('module', $training['type']);
        $this->assertSame('Funktion', $training['type_label']);
        $this->assertSame(route('auth.training.index'), $training['url']);
        $this->assertSame('las la-dumbbell', $training['icon']);

        $ordinaryAdminResults = collect($this->actingAs($viewer)
            ->getJson(route('auth.search', ['q' => 'Nutzer']))
            ->assertOk()
            ->json('results'));
        $this->assertFalse($ordinaryAdminResults->contains('module_key', 'users'));

        Permission::findOrCreate('users.view', 'web');
        $viewer->givePermissionTo('users.view');

        $managedAdminResults = collect($this->actingAs($viewer->fresh())
            ->getJson(route('auth.search', ['q' => 'Nutzer']))
            ->assertOk()
            ->json('results'));
        $this->assertSame(
            route('members.index'),
            $managedAdminResults->firstWhere('module_key', 'users')['url'] ?? null,
        );

        $arabicTraining = collect($this->actingAs($viewer->fresh())
            ->withHeader('X-Locale', 'ar')
            ->getJson('/api/v1/search?q='.rawurlencode('التدريب'))
            ->assertOk()
            ->json('results'))
            ->firstWhere('module_key', 'training');

        $this->assertSame(trans('search.modules.training', [], 'ar'), $arabicTraining['title']);
        $this->assertSame(trans('search.types.module', [], 'ar'), $arabicTraining['type_label']);
    }

    public function test_module_search_keeps_a_bounded_query_budget(): void
    {
        $viewer = User::factory()->create();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $results = app(GlobalSearchService::class)->search($viewer, 'Training');
        $selects = collect(DB::getQueryLog())
            ->filter(fn (array $query): bool => str_starts_with(strtolower(ltrim($query['query'])), 'select'))
            ->count();
        DB::disableQueryLog();

        $this->assertTrue($results->contains('module_key', 'training'));
        $this->assertLessThanOrEqual(16, $selects, "Module search used {$selects} SELECTs.");
    }

    public function test_every_module_destination_is_resolvable_in_each_supported_locale(): void
    {
        $admin = User::factory()->create();
        Role::findOrCreate('super_admin', 'web');
        $admin->assignRole('super_admin');
        $catalog = app(SearchModuleCatalog::class);
        $expectedKeys = array_keys(require lang_path('de/search.php'));
        $this->assertContains('modules', $expectedKeys);

        $moduleKeys = array_keys((require lang_path('de/search.php'))['modules']);
        $this->assertCount(27, $moduleKeys);

        foreach (['de', 'en', 'fr', 'ar'] as $locale) {
            app()->setLocale($locale);
            $translations = (require lang_path("{$locale}/search.php"))['modules'];
            $this->assertSame($moduleKeys, array_keys($translations), "{$locale} module keys differ.");

            foreach ($moduleKeys as $moduleKey) {
                $results = $catalog->search($admin, $translations[$moduleKey], 4);
                $this->assertTrue(
                    $results->contains('module_key', $moduleKey),
                    "{$locale} module {$moduleKey} is not resolvable.",
                );
            }
        }

        app()->setLocale('de');
    }

    public function test_private_profiles_are_hidden_from_strangers_in_web_and_mobile_search(): void
    {
        $viewer = User::factory()->create(['name' => 'Search Viewer']);
        $privateUser = User::factory()->create([
            'name' => 'Private Search Profile',
            'email' => 'private-search@example.test',
            'profile_visibility' => 'private',
        ]);

        foreach ([
            route('auth.search', ['q' => 'Private Search']),
            '/api/v1/search?q=Private%20Search',
        ] as $endpoint) {
            $results = collect($this->actingAs($viewer)
                ->getJson($endpoint)
                ->assertOk()
                ->json('results'));

            $this->assertFalse($results->contains('id', $privateUser->id));
        }

        Friendship::create([
            'user_id' => $viewer->id,
            'friend_id' => $privateUser->id,
        ]);
        Friendship::create([
            'user_id' => $privateUser->id,
            'friend_id' => $viewer->id,
        ]);

        $friendResult = collect($this->actingAs($viewer)
            ->getJson('/api/v1/search?q=Private%20Search')
            ->assertOk()
            ->json('results'))
            ->firstWhere('id', $privateUser->id);

        $this->assertSame('Privates Profil', $friendResult['subtitle']);
        $this->assertArrayNotHasKey('email', $friendResult);
    }

    public function test_mobile_search_returns_visible_events_courses_products_and_files(): void
    {
        $user = User::factory()->create(['name' => 'Search Owner']);

        $event = Event::create([
            'user_id' => $user->id,
            'title' => 'Launch Training',
            'type' => 'training',
            'visibility' => 'public',
            'status' => 'scheduled',
            'start_time' => now()->addDay(),
        ]);
        $course = LearningCourse::create([
            'user_id' => $user->id,
            'title' => 'Launch Course',
            'slug' => 'launch-course',
            'subtitle' => 'Course for the search contract',
            'status' => 'published',
            'is_public' => true,
            'published_at' => now(),
        ]);
        $product = MarketplaceProduct::create([
            'user_id' => $user->id,
            'title' => 'Launch Product',
            'description' => 'Searchable product',
            'status' => 'published',
            'moderation_status' => 'approved',
        ]);
        $file = File::create([
            'user_id' => $user->id,
            'display_name' => 'Launch Document.pdf',
            'path' => 'files/launch-document.pdf',
            'type' => 'application/pdf',
            'size' => 1200,
        ]);
        $foreignFile = File::create([
            'user_id' => User::factory()->create()->id,
            'display_name' => 'Launch Document private.pdf',
            'path' => 'files/launch-document-private.pdf',
            'type' => 'application/pdf',
            'size' => 1200,
        ]);

        $results = collect($this->actingAs($user)
            ->getJson('/api/v1/search?q=Launch')
            ->assertOk()
            ->json('results'));

        foreach ([
            ['type' => 'event', 'id' => $event->id],
            ['type' => 'course', 'id' => $course->id],
            ['type' => 'product', 'id' => $product->id],
            ['type' => 'file', 'id' => $file->id],
        ] as $expected) {
            $this->assertTrue(
                $results->contains(fn (array $result) => $result['type'] === $expected['type'] && $result['id'] === $expected['id']),
                "Missing {$expected['type']} search result."
            );
        }
        $this->assertFalse($results->contains(fn (array $result) => $result['id'] === $foreignFile->id));
        $this->assertTrue($results->every(fn (array $result) => filled($result['url'] ?? null)));
        $this->assertSame(
            route('guest.learning.courses.show', $course),
            $results->firstWhere('type', 'course')['url']
        );

        $arabicCourse = collect($this->actingAs($user)
            ->withHeader('X-Locale', 'ar')
            ->getJson('/api/v1/search?q=Launch')
            ->assertOk()
            ->json('results'))
            ->firstWhere('type', 'course');

        $this->assertSame(trans('search.types.course', [], 'ar'), $arabicCourse['type_label']);
    }

    public function test_private_events_are_not_discoverable_by_strangers(): void
    {
        $viewer = User::factory()->create();
        $owner = User::factory()->create();
        $privateEvent = Event::create([
            'user_id' => $owner->id,
            'title' => 'Hidden Recovery Session',
            'type' => 'training',
            'visibility' => 'private',
            'status' => 'scheduled',
            'start_time' => now()->addDay(),
        ]);

        $results = collect($this->actingAs($viewer)
            ->getJson(route('auth.search', ['q' => 'Hidden Recovery']))
            ->assertOk()
            ->json('results'));

        $this->assertFalse($results->contains(
            fn (array $result) => $result['type'] === 'event' && $result['id'] === $privateEvent->id
        ));
    }

    public function test_results_are_interleaved_and_capped_per_type(): void
    {
        $viewer = User::factory()->create(['name' => 'Search Viewer']);

        foreach (range(1, 5) as $index) {
            User::factory()->create([
                'name' => sprintf('Balanced Athlete %02d', $index),
                'profile_visibility' => 'public',
            ]);
            LearningCourse::create([
                'user_id' => $viewer->id,
                'title' => sprintf('Balanced Course %02d', $index),
                'slug' => "balanced-course-{$index}",
                'status' => 'published',
                'is_public' => true,
                'published_at' => now()->subMinutes($index),
            ]);
            MarketplaceProduct::create([
                'user_id' => $viewer->id,
                'title' => sprintf('Balanced Product %02d', $index),
                'status' => 'published',
                'moderation_status' => 'approved',
            ]);
        }

        $response = $this->actingAs($viewer)
            ->getJson(route('auth.search', ['q' => 'Balanced']))
            ->assertOk()
            ->assertJsonPath('meta.per_type_limit', GlobalSearchService::PER_TYPE_LIMIT)
            ->assertJsonPath('meta.count', 12);

        $results = collect($response->json('results'));
        $this->assertSame(
            ['user', 'course', 'product', 'user', 'course', 'product'],
            $results->take(6)->pluck('type')->all()
        );

        foreach (['user', 'course', 'product'] as $type) {
            $this->assertCount(
                GlobalSearchService::PER_TYPE_LIMIT,
                $results->where('type', $type)
            );
        }
    }
}
