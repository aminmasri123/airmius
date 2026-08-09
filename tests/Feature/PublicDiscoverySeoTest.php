<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Event;
use App\Models\Sport;
use App\Models\Team;
use App\Models\User;
use App\Services\PublicDiscoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicDiscoverySeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_club_pages_respect_listing_and_hidden_team_settings(): void
    {
        $owner = User::factory()->create(['email' => 'private-owner@example.test']);
        $publicClub = $this->createClub($owner, [
            'name' => 'Berlin Privacy Sport',
            'teams_are_listed' => false,
            'street' => 'Private Club Street',
            'sepa_iban' => 'DE02120300000000202051',
        ]);
        $hiddenClub = $this->createClub($owner, [
            'name' => 'Hidden Internal Club',
            'city' => 'Secret City',
            'is_listed' => false,
        ]);
        Team::factory()->create([
            'club_id' => $publicClub->id,
            'name' => 'Hidden Youth Team',
            'sport_type' => 'laufen',
        ]);

        $this->get(route('guest.vereine'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Guest/Vereine')
                ->has('clubs', 1)
                ->where('clubs.0.name', 'Berlin Privacy Sport')
                ->where('clubs.0.teams_count', 0)
                ->missing('clubs.0.owner_id')
                ->missing('clubs.0.street')
                ->missing('clubs.0.sepa_iban'))
            ->assertDontSee('Hidden Internal Club')
            ->assertDontSee('private-owner@example.test')
            ->assertDontSee('Private Club Street')
            ->assertDontSee('DE02120300000000202051');

        $this->get(route('guest.clubs.show', $publicClub))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Guest/Discovery/Show')
                ->where('kind', 'club')
                ->where('entity.name', 'Berlin Privacy Sport')
                ->where('stats.teams', 0)
                ->has('teams', 0)
                ->missing('entity.owner_id')
                ->missing('entity.street')
                ->missing('entity.sepa_iban'))
            ->assertDontSee('Hidden Youth Team')
            ->assertSee('SportsOrganization');

        $this->get(route('guest.clubs.show', $hiddenClub))->assertNotFound();
    }

    public function test_public_event_payload_excludes_private_fields_and_private_events(): void
    {
        $owner = User::factory()->create(['email' => 'event-owner@example.test']);
        $club = $this->createClub($owner, [
            'name' => 'Public Event Club',
            'teams_are_listed' => false,
        ]);
        $team = Team::factory()->create([
            'club_id' => $club->id,
            'name' => 'Unlisted Event Team',
            'sport_type' => 'laufen',
        ]);
        $publicEvent = $this->createEvent($owner, [
            'club_id' => $club->id,
            'team_id' => $team->id,
            'title' => 'Public Privacy Run',
            'notes' => 'Secret tactical notes',
            'location_street' => 'Private Event Street',
            'location_latitude' => 52.520008,
            'location_longitude' => 13.404954,
        ]);
        $privateEvent = $this->createEvent($owner, [
            'title' => 'Private Board Meeting',
            'visibility' => 'private',
        ]);

        $this->get(route('guest.events'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Guest/Events')
                ->has('events.data', 1)
                ->where('events.data.0.title', 'Public Privacy Run')
                ->where('events.data.0.team', null)
                ->missing('events.data.0.notes')
                ->missing('events.data.0.location_street')
                ->missing('events.data.0.location_latitude'))
            ->assertDontSee('Private Board Meeting')
            ->assertDontSee('Unlisted Event Team');

        $this->get(route('guest.events.show', $publicEvent))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Guest/Discovery/Show')
                ->where('kind', 'event')
                ->where('entity.title', 'Public Privacy Run')
                ->where('entity.team', null)
                ->missing('entity.notes')
                ->missing('entity.location_street')
                ->missing('entity.location_latitude')
                ->missing('entity.participants'))
            ->assertDontSee('Secret tactical notes')
            ->assertDontSee('Private Event Street')
            ->assertDontSee('event-owner@example.test')
            ->assertSee('SportsEvent');

        $this->get(route('guest.events.show', $privateEvent))->assertNotFound();
    }

    public function test_sport_pages_count_only_public_clubs_and_listed_teams_and_are_localized(): void
    {
        $owner = User::factory()->create();
        $sport = Sport::query()->create([
            'name' => 'Laufen',
            'slug' => 'laufen',
            'category' => 'Ausdauer',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $inactive = Sport::query()->create([
            'name' => 'Interner Sport',
            'slug' => 'interner-sport',
            'category' => 'Intern',
            'is_active' => false,
            'sort_order' => 2,
        ]);
        $listed = $this->createClub($owner, ['name' => 'Listed Running Club']);
        $teamsHidden = $this->createClub($owner, [
            'name' => 'Club With Hidden Teams',
            'teams_are_listed' => false,
        ]);
        $unlisted = $this->createClub($owner, [
            'name' => 'Unlisted Running Club',
            'is_listed' => false,
        ]);
        Team::factory()->create(['club_id' => $listed->id, 'sport_type' => 'laufen']);
        Team::factory()->create(['club_id' => $teamsHidden->id, 'sport_type' => 'laufen']);
        Team::factory()->create(['club_id' => $unlisted->id, 'sport_type' => 'laufen']);

        $this->get(route('guest.sports'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Guest/Sportarten')
                ->has('sports', 1)
                ->where('sports.0.clubs_count', 2)
                ->where('sports.0.teams_count', 1));

        $this->withHeader('Accept-Language', 'en-US,en;q=0.9')
            ->get(route('guest.sports.show', $sport->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Guest/Discovery/Show')
                ->where('kind', 'sport')
                ->where('stats.clubs', 2)
                ->where('stats.teams', 1))
            ->assertSee('<title inertia>Laufen: clubs, teams, and events | Airmius</title>', false)
            ->assertSee('CollectionPage');

        $this->get(route('guest.sports.show', $inactive->slug))->assertNotFound();
    }

    public function test_city_discovery_uses_only_public_clubs_and_public_event_locations(): void
    {
        $owner = User::factory()->create();
        $this->createClub($owner, ['name' => 'Berlin Public Club']);
        $this->createClub($owner, [
            'name' => 'Secret City Club',
            'city' => 'Geheimstadt',
            'is_listed' => false,
        ]);
        $this->createEvent($owner, [
            'title' => 'Hamburg Public Event',
            'location_city' => 'Hamburg',
        ]);
        $this->createEvent($owner, [
            'title' => 'Private City Event',
            'visibility' => 'private',
            'location_city' => 'Privatstadt',
        ]);

        $this->get(route('guest.cities'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Guest/Discovery/Cities')
                ->where('cities', fn ($cities): bool => collect($cities)->pluck('name')->sort()->values()->all() === ['Berlin', 'Hamburg']))
            ->assertDontSee('Geheimstadt')
            ->assertDontSee('Privatstadt');

        $this->get(route('guest.cities.show', ['country' => 'de', 'city' => 'berlin']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Guest/Discovery/Show')
                ->where('kind', 'city')
                ->where('entity.name', 'Berlin')
                ->where('stats.clubs', 1))
            ->assertSee('CollectionPage');

        $this->get(route('guest.cities.show', ['country' => 'de', 'city' => 'unbekannt']))
            ->assertNotFound();
    }

    public function test_sitemap_contains_only_public_discovery_entities(): void
    {
        $owner = User::factory()->create();
        $publicClub = $this->createClub($owner, ['name' => 'Sitemap Public Club']);
        $hiddenClub = $this->createClub($owner, [
            'name' => 'Sitemap Hidden Club',
            'is_listed' => false,
        ]);
        $publicEvent = $this->createEvent($owner, ['title' => 'Sitemap Public Event']);
        $privateEvent = $this->createEvent($owner, [
            'title' => 'Sitemap Private Event',
            'visibility' => 'private',
        ]);
        $activeSport = Sport::query()->create([
            'name' => 'Sitemap Sport',
            'slug' => 'sitemap-sport',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $inactiveSport = Sport::query()->create([
            'name' => 'Hidden Sitemap Sport',
            'slug' => 'hidden-sitemap-sport',
            'is_active' => false,
            'sort_order' => 2,
        ]);

        $this->get(route('sitemap'))
            ->assertOk()
            ->assertSee(route('guest.clubs.show', $publicClub), false)
            ->assertSee(route('guest.events.show', $publicEvent), false)
            ->assertSee(route('guest.sports.show', $activeSport->slug), false)
            ->assertSee(route('guest.cities.show', ['country' => 'de', 'city' => 'berlin']), false)
            ->assertDontSee(route('guest.clubs.show', $hiddenClub), false)
            ->assertDontSee(route('guest.events.show', $privateEvent), false)
            ->assertDontSee(route('guest.sports.show', $inactiveSport->slug), false);
    }

    public function test_city_detail_has_a_constant_select_query_budget(): void
    {
        $owner = User::factory()->create();

        foreach (range(1, 12) as $position) {
            $club = $this->createClub($owner, ['name' => "Berlin Club {$position}"]);
            Team::factory()->create([
                'club_id' => $club->id,
                'name' => "Berlin Team {$position}",
                'sport_type' => 'laufen',
            ]);
            $this->createEvent($owner, [
                'club_id' => $club->id,
                'title' => "Berlin Event {$position}",
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $payload = app(PublicDiscoveryService::class)->city('de', 'berlin');
            $selectQueries = collect(DB::getQueryLog())
                ->filter(fn (array $query): bool => str_starts_with(strtolower(ltrim($query['query'])), 'select'));
        } finally {
            DB::disableQueryLog();
        }

        $this->assertNotNull($payload);
        $this->assertCount(12, $payload['clubs']);
        $this->assertCount(12, $payload['events']);
        $this->assertLessThanOrEqual(12, $selectQueries->count());
    }

    public function test_public_discovery_has_the_required_composite_indexes(): void
    {
        $this->assertIndex('clubs', 'clubs_public_city_idx', ['verification_status', 'is_listed', 'city']);
        $this->assertIndex('clubs', 'clubs_public_sport_idx', ['verification_status', 'is_listed', 'sport_type']);
        $this->assertIndex('events', 'events_public_city_start_idx', ['visibility', 'status', 'location_city', 'start_time']);
        $this->assertIndex('teams', 'teams_sport_club_idx', ['sport_type', 'club_id']);
    }

    public function test_public_discovery_translation_contract_matches_in_all_languages(): void
    {
        $locales = ['de', 'en', 'fr', 'ar'];
        $serverCatalogs = collect($locales)->mapWithKeys(fn (string $locale): array => [
            $locale => Arr::dot(require base_path("lang/{$locale}/public_discovery.php")),
        ]);
        $frontendCatalogs = collect($locales)->mapWithKeys(function (string $locale): array {
            $catalog = json_decode(
                file_get_contents(resource_path("js/lang/{$locale}.json")),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );

            return [$locale => Arr::dot($catalog['public_discovery'])];
        });

        foreach ($locales as $locale) {
            $this->assertSame(array_keys($serverCatalogs['de']), array_keys($serverCatalogs[$locale]), "Server translation keys differ for {$locale}.");
            $this->assertSame(array_keys($frontendCatalogs['de']), array_keys($frontendCatalogs[$locale]), "Frontend translation keys differ for {$locale}.");

            foreach ($serverCatalogs['de'] as $key => $reference) {
                $this->assertSame(
                    $this->placeholders($reference, '/:[a-z_][a-z0-9_]*/i'),
                    $this->placeholders($serverCatalogs[$locale][$key], '/:[a-z_][a-z0-9_]*/i'),
                    "Server placeholders differ for {$locale}.{$key}.",
                );
            }

            foreach ($frontendCatalogs['de'] as $key => $reference) {
                $this->assertSame(
                    $this->placeholders($reference, '/\{[a-z_][a-z0-9_]*\}/i'),
                    $this->placeholders($frontendCatalogs[$locale][$key], '/\{[a-z_][a-z0-9_]*\}/i'),
                    "Frontend placeholders differ for {$locale}.{$key}.",
                );
            }

            app()->setLocale($locale);
            $this->assertNotSame('public_discovery.cities.meta_title', __('public_discovery.cities.meta_title'));
        }
    }

    /** @param array<string, mixed> $overrides */
    private function createClub(User $owner, array $overrides = []): Club
    {
        return Club::factory()->create(array_merge([
            'owner_id' => $owner->id,
            'name' => 'Berlin Running Club',
            'sport_type' => 'laufen',
            'verification_status' => 'verified',
            'verified_at' => now(),
            'is_listed' => true,
            'teams_are_listed' => true,
            'country' => 'DE',
            'postal_code' => '10115',
            'city' => 'Berlin',
        ], $overrides));
    }

    /** @param array<string, mixed> $overrides */
    private function createEvent(User $owner, array $overrides = []): Event
    {
        return Event::query()->create(array_merge([
            'user_id' => $owner->id,
            'title' => 'Berlin Public Run',
            'type' => 'training',
            'visibility' => 'public',
            'status' => 'scheduled',
            'start_time' => now()->addWeek(),
            'end_time' => now()->addWeek()->addHour(),
            'location' => 'Public Stadium',
            'location_city' => 'Berlin',
            'location_country' => 'DE',
        ], $overrides));
    }

    /** @param array<int, string> $columns */
    private function assertIndex(string $table, string $name, array $columns): void
    {
        $index = collect(Schema::getIndexes($table))->firstWhere('name', $name);

        $this->assertNotNull($index, "Missing index {$name} on {$table}.");
        $this->assertSame($columns, $index['columns']);
    }

    /** @return array<int, string> */
    private function placeholders(string $value, string $pattern): array
    {
        preg_match_all($pattern, $value, $matches);
        $placeholders = array_values(array_unique($matches[0]));
        sort($placeholders);

        return $placeholders;
    }
}
