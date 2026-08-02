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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_search_requires_at_least_two_characters(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('auth.search', ['q' => 'a']))
            ->assertOk()
            ->assertExactJson(['results' => []]);
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
    }
}
