<?php

namespace Tests\Feature;

use App\Models\Ride;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RideAddressTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['rides.geocoding_key' => null]);
    }

    private function user(): User
    {
        Role::findOrCreate('player', 'web');
        $user = User::factory()->create();
        $user->assignRole('player');

        return $user;
    }

    private function data(): array
    {
        return ['visibility' => 'public', 'from' => 'Bahnhof', 'to' => 'Sporthalle',
            'departure_time' => now()->addDay()->toIso8601String(), 'seats' => 4,
            'address_details' => true, 'pickup_postal_code' => '66271', 'pickup_city' => 'Kleinblittersdorf', 'pickup_country' => 'de',
            'pickup_street' => 'Private Startstraße', 'pickup_house_number' => '2',
            'destination_postal_code' => '57000', 'destination_city' => 'Metz', 'destination_country' => 'fr',
            'destination_street' => 'Private Zielstraße', 'destination_house_number' => '4'];
    }

    public function test_addresses_are_required_for_new_clients_and_old_clients_remain_compatible(): void
    {
        Sanctum::actingAs($this->user());
        $this->postJson('/api/v1/rides', [...$this->data(), 'destination_city' => ''])
            ->assertUnprocessable()->assertJsonValidationErrors('destination_city');
        $this->postJson('/api/v1/rides', $this->data())->assertCreated()
            ->assertJsonPath('data.pickup_country', 'DE')->assertJsonPath('data.destination_country', 'FR');
        $legacy = $this->data();
        unset($legacy['address_details']);
        foreach (array_keys($legacy) as $key) {
            if (str_starts_with($key, 'destination_') || str_starts_with($key, 'pickup_')) {
                unset($legacy[$key]);
            }
        }
        $this->postJson('/api/v1/rides', $legacy)->assertCreated();
    }

    public function test_search_combines_start_and_destination_without_revealing_private_addresses(): void
    {
        $driver = $this->user();
        Sanctum::actingAs($driver);
        $id = $this->postJson('/api/v1/rides', $this->data())->assertCreated()->json('data.id');
        $this->postJson('/api/v1/rides', [...$this->data(), 'destination_postal_code' => '75001', 'destination_city' => 'Paris'])->assertCreated();
        $this->postJson('/api/v1/rides', [...$this->data(), 'visibility' => 'friends'])->assertCreated();
        $passenger = $this->user();
        Sanctum::actingAs($passenger);
        $this->getJson('/api/v1/rides?from_q=66271%20Kleinblittersdorf&to_q=Metz')->assertOk()
            ->assertJsonCount(1, 'data.rides')->assertJsonPath('data.rides.0.id', $id)
            ->assertJsonPath('data.rides.0.pickup_street', null)->assertJsonPath('data.rides.0.destination_street', null)
            ->assertJsonPath('data.rides.0.destination_private_label', null);
        $this->getJson('/api/v1/rides?to_q=Private%20Zielstraße')->assertJsonCount(0, 'data.rides');
        $this->actingAs($passenger)->get(route('auth.rides.index', ['from_q' => '66271', 'to_q' => '57000']))
            ->assertInertia(fn (Assert $page) => $page->has('rides', 1)->where('rides.0.destination_street', null));
        $this->postJson("/api/v1/rides/$id/join")->assertOk();
        $this->getJson('/api/v1/rides?to_q=57000')->assertJsonPath('data.rides.0.destination_street', null);
        Sanctum::actingAs($driver);
        $this->postJson("/api/v1/rides/$id/requests/{$passenger->id}/approve")->assertOk();
        Sanctum::actingAs($passenger);
        $this->getJson('/api/v1/rides?to_q=57000')->assertJsonPath('data.rides.0.destination_street', 'Private Zielstraße');
    }

    public function test_accepted_passenger_gets_updated_house_numbers_notes_and_contact_from_notification_detail_endpoint(): void
    {
        $driver = $this->user();
        $passenger = $this->user();
        $other = $this->user();
        Sanctum::actingAs($driver);
        $id = $this->postJson('/api/v1/rides', $this->data())->assertCreated()->json('data.id');
        Sanctum::actingAs($passenger);
        $this->postJson('/api/v1/rides/'.$id.'/join')->assertOk();
        Sanctum::actingAs($driver);
        $this->postJson('/api/v1/rides/'.$id.'/requests/'.$passenger->id.'/approve')->assertOk();
        $this->putJson('/api/v1/rides/'.$id, [...$this->data(),
            'pickup_house_number' => '18b', 'destination_house_number' => '42',
            'pickup_note' => 'Am Seiteneingang', 'contact_details' => 'Nur nach Zusage',
        ])->assertOk();
        Sanctum::actingAs($passenger);
        $response = $this->getJson('/api/v1/rides/'.$id)->assertOk()
            ->assertJsonPath('data.pickup_house_number', '18b')
            ->assertJsonPath('data.destination_house_number', '42')
            ->assertJsonPath('data.pickup_note', 'Am Seiteneingang')
            ->assertJsonPath('data.contact_details', 'Nur nach Zusage');
        $this->assertStringContainsString('18b', $response->json('data.pickup_private_label'));
        $this->assertStringContainsString('42', $response->json('data.destination_private_label'));
        Sanctum::actingAs($other);
        $this->getJson('/api/v1/rides/'.$id)->assertOk()
            ->assertJsonPath('data.pickup_house_number', null)
            ->assertJsonPath('data.destination_house_number', null)
            ->assertJsonPath('data.pickup_note', null)
            ->assertJsonPath('data.contact_details', null);
    }

    public function test_web_saves_destination_and_old_edits_do_not_erase_it(): void
    {
        $user = $this->user();
        $this->actingAs($user)->post(route('auth.rides.store'), $this->data())->assertSessionHasNoErrors()->assertRedirect();
        $ride = Ride::firstOrFail();
        $this->assertSame('Metz', $ride->destination_city);
        Sanctum::actingAs($user);
        $this->putJson('/api/v1/rides/'.$ride->id, ['visibility' => 'public', 'from' => 'Bahnhof', 'to' => 'Sporthalle', 'departure_time' => now()->addDay()->toIso8601String(), 'seats' => 4])->assertOk();
        $this->assertSame('57000', $ride->fresh()->destination_postal_code);
    }
}
