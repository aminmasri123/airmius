<?php

namespace Tests\Feature;

use App\Models\Ride;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RideGeocodingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['rides.geocoding_key' => 'test-only', 'rides.geocoding_url' => 'https://geo.example.test/geocode']);
        Http::preventStrayRequests();
    }

    private function user(): User
    {
        Role::findOrCreate('player', 'web');
        $user = User::factory()->create();
        $user->assignRole('player');

        return $user;
    }

    private function hit(array $extra = []): array
    {
        return [...['name' => 'Metz', 'city' => 'Metz', 'postcode' => '57000', 'countrycode' => 'fr', 'country' => 'France', 'osm_key' => 'place', 'osm_value' => 'city', 'point' => ['lat' => 49.12, 'lng' => 6.18]], ...$extra];
    }

    public function test_geocoder_is_authenticated_cached_normalized_and_area_search_omits_streets(): void
    {
        Http::fake(['geo.example.test/*' => Http::response(['hits' => [$this->hit(), $this->hit(['street' => 'Rue privée', 'housenumber' => '8']), ['name' => 'invalid']]])]);
        $this->getJson('/api/v1/rides/locations?q=Metz')->assertUnauthorized();
        $user = $this->user();
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/rides/locations?q=Metz')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.country', 'FR')->assertJsonPath('data.0.longitude', 6.18);
        $this->getJson('/api/v1/rides/locations?q=Metz')->assertOk();
        Http::assertSentCount(1);
        $this->getJson('/api/v1/rides/locations?q=Metz&area=1')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($user)->getJson(route('auth.rides.locations', ['q' => 'Metz', 'area' => 1]))->assertOk();
        Http::assertSentCount(2);
    }

    public function test_lookup_failure_does_not_expose_provider_details_or_block_manual_creation(): void
    {
        Http::fake(['geo.example.test/*' => Http::response(['message' => 'provider secret'], 429)]);
        Sanctum::actingAs($this->user());
        $this->getJson('/api/v1/rides/locations?q=Metz')->assertStatus(503)->assertDontSee('secret')->assertDontSee('test-only');
        $this->postJson('/api/v1/rides', $this->data())->assertCreated()->assertJsonPath('data.area_search_ready', false);
    }

    private function data(): array
    {
        return ['visibility' => 'public', 'from' => 'Start', 'to' => 'Ziel', 'seats' => 4, 'departure_time' => now()->addDay()->toIso8601String(),
            'address_details' => true, 'pickup_city' => 'Metz', 'pickup_country' => 'FR', 'pickup_postal_code' => '57000',
            'destination_city' => 'Metz', 'destination_country' => 'FR', 'destination_postal_code' => '57000',
            'pickup_street' => 'Private Street', 'destination_street' => 'Other private street'];
    }

    public function test_save_uses_locality_centres_and_recomputes_when_city_changes(): void
    {
        Http::fake(fn ($request) => Http::response(['hits' => [$this->hit()]]));
        Sanctum::actingAs($this->user());
        $id = $this->postJson('/api/v1/rides', $this->data())->assertCreated()->assertJsonPath('data.area_search_ready', true)->json('data.id');
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => ! str_contains($request['q'], 'private') && ! str_contains($request['q'], 'Private'));
        $this->assertEquals(49.12, Ride::findOrFail($id)->pickup_area_latitude);
        $this->getJson('/api/v1/rides')->assertDontSee('area_latitude')->assertDontSee('area_longitude');
        $this->putJson('/api/v1/rides/'.$id, [...$this->data(), 'pickup_city' => 'Unknown', 'pickup_postal_code' => '99999'])->assertOk()->assertJsonPath('data.area_search_ready', false);
        $this->assertNull(Ride::findOrFail($id)->pickup_area_latitude);
    }

    public function test_radius_five_ten_and_twenty_five_respects_exact_distance_and_visibility_on_both_ends(): void
    {
        $driver = $this->user();
        $make = fn ($km, $visibility = 'public', $destinationKm = 0) => Ride::create([
            'driver_id' => $driver->id, 'from' => 'Neighbouring town', 'to' => 'Other town', 'seats' => 4, 'visibility' => $visibility,
            'departure_time' => now()->addDay(), 'pickup_area_latitude' => rad2deg($km / 6371.0088), 'pickup_area_longitude' => 0,
            'destination_area_latitude' => rad2deg($destinationKm / 6371.0088), 'destination_area_longitude' => 0,
        ]);
        $near = $make(4.9);
        $middle = $make(9.9);
        $far = $make(24.9);
        $make(25.1);
        $diagonal = $make(20);
        $diagonal->update(['pickup_area_longitude' => rad2deg(20 / 6371.0088)]);
        $make(1, 'friends');
        $make(1, 'public', 40);
        $unknown = $make(1);
        $unknown->update(['pickup_area_latitude' => null]);
        $user = $this->user();
        Sanctum::actingAs($user);
        foreach ([5 => [$near->id], 10 => [$near->id, $middle->id], 25 => [$near->id, $middle->id, $far->id]] as $radius => $ids) {
            $query = ['radius_km' => $radius, 'from_lat' => 0, 'from_lng' => 0, 'to_lat' => 0, 'to_lng' => 0];
            $rows = $this->getJson('/api/v1/rides?'.http_build_query($query))->assertOk()->json('data.rides');
            $this->assertEqualsCanonicalizing($ids, array_column($rows, 'id'));
            $this->actingAs($user)->get(route('auth.rides.index', $query))->assertInertia(fn (Assert $page) => $page->has('rides', count($ids)));
        }
        $this->getJson('/api/v1/rides?radius_km=7&from_lat=0&from_lng=0')->assertUnprocessable();
        $this->getJson('/api/v1/rides?radius_km=5&from_q=Metz')->assertUnprocessable();
        $this->getJson('/api/v1/rides?radius_km=5&from_lat=0')->assertUnprocessable();
    }

    public function test_backfill_is_bounded_and_can_continue_after_unresolved_rides(): void
    {
        Http::fake(['geo.example.test/*' => Http::response(['hits' => [$this->hit()]])]);
        $driver = $this->user();
        $data = $this->data();
        unset($data['address_details']);
        $first = Ride::create([...$data, 'driver_id' => $driver->id, 'pickup_city' => null]);
        $second = Ride::create([...$data, 'driver_id' => $driver->id]);
        $this->artisan('rides:geocode-areas', ['--limit' => 1])->assertSuccessful();
        $this->assertNull($first->fresh()->pickup_area_latitude);
        $this->assertNull($second->fresh()->pickup_area_latitude);
        $this->artisan('rides:geocode-areas', ['--limit' => 1, '--after-id' => $first->id])->assertSuccessful();
        $this->assertEquals(49.12, $second->fresh()->pickup_area_latitude);
    }
}
