<?php

namespace Tests\Feature;

use App\Models\Ride;
use App\Models\User;
use App\Services\RidePricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RidePricingFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['rides.geocoding_key' => null]);
    }

    public function test_currency_follows_account_country_instead_of_language_or_route(): void
    {
        $user = $this->user('MA');
        $user->update(['language' => 'de']);
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/rides')->assertOk()->assertJsonPath('data.pricing_catalog.default_currency', 'MAD');
        $catalog = app(RidePricingService::class);
        foreach (['DE' => 'EUR', 'MA' => 'MAD', 'US' => 'USD', 'JP' => 'JPY', 'KW' => 'KWD', 'CH' => 'CHF', 'GB' => 'GBP', 'TN' => 'TND', 'AE' => 'AED'] as $country => $currency) {
            $user->country = $country;
            $this->assertSame($currency, $catalog->catalog($user)['default_currency']);
        }
        $user->country = null;
        $this->assertNull($catalog->catalog($user)['default_currency']);
        $this->assertGreaterThan(100, count($catalog->catalog($user)['currencies']));
    }

    public function test_complete_request_approval_price_change_confirmation_and_decline_flow(): void
    {
        $driver = $this->user('DE');
        $accepted = $this->user('MA');
        $pending = $this->user('US');
        $outsider = $this->user('JP');
        Sanctum::actingAs($driver);
        $ride = $this->postJson('/api/v1/rides', $this->payload())->assertCreated()
            ->assertJsonPath('data.pricing.amount_minor', 1000)->assertJsonPath('data.pricing.version', 1)->json('data.id');
        $base = '/api/v1/rides/'.$ride;
        foreach ([$accepted, $pending] as $person) {
            Sanctum::actingAs($person);
            $this->postJson($base.'/join')->assertUnprocessable();
            $this->postJson($base.'/join', ['price_version' => 0, 'price_acknowledged' => true])->assertUnprocessable();
            $this->postJson($base.'/join', ['price_version' => 1, 'price_acknowledged' => true])->assertOk();
        }
        Sanctum::actingAs($driver);
        $this->postJson($base.'/requests/'.$accepted->id.'/approve')->assertOk();
        $this->putJson($base, [...$this->payload(), 'cost_amount' => '125,50', 'cost_currency' => 'MAD'])->assertOk()
            ->assertJsonPath('data.pricing.version', 2)->assertJsonPath('data.participants_count', 2);
        $this->postJson($base.'/requests/'.$pending->id.'/approve')->assertUnprocessable();
        $this->assertDatabaseHas('ride_users', ['ride_id' => $ride, 'user_id' => $accepted->id,
            'status' => 'accepted', 'agreed_cost_currency' => 'EUR', 'agreed_cost_amount_minor' => 1000, 'price_confirmation_required' => true]);
        Sanctum::actingAs($outsider);
        $this->postJson($base.'/price/confirm', ['price_version' => 2, 'price_acknowledged' => true])->assertForbidden();
        Sanctum::actingAs($accepted);
        $this->getJson($base)->assertOk()->assertJsonPath('data.pricing.requires_confirmation', true)
            ->assertJsonPath('data.pricing.agreed.currency', 'EUR')->assertJsonPath('data.pricing.currency', 'MAD');
        $this->postJson($base.'/price/confirm', ['price_version' => 1, 'price_acknowledged' => true])->assertUnprocessable();
        $this->postJson($base.'/price/confirm', ['price_version' => 2, 'price_acknowledged' => false])->assertUnprocessable();
        $this->postJson($base.'/price/confirm', ['price_version' => 2, 'price_acknowledged' => true])->assertOk()
            ->assertJsonPath('data.pricing.requires_confirmation', false)->assertJsonPath('data.pricing.agreed.amount_minor', 12550);
        $this->getJson($base)->assertOk()->assertJsonPath('data.pricing.agreed.currency', 'MAD');
        Sanctum::actingAs($pending);
        $this->postJson($base.'/leave')->assertOk();
        $this->assertDatabaseMissing('ride_users', ['ride_id' => $ride, 'user_id' => $pending->id]);
        Sanctum::actingAs($driver);
        $this->putJson($base, [...$this->payload(), 'cost_mode' => 'free'])->assertOk()->assertJsonPath('data.pricing.version', 3);
        Sanctum::actingAs($accepted);
        $this->getJson($base)->assertOk()->assertJsonPath('data.pricing.mode', 'free')
            ->assertJsonPath('data.pricing.agreed.currency', 'MAD')->assertJsonPath('data.pricing.requires_confirmation', true);
        $this->postJson($base.'/leave')->assertOk()->assertJsonPath('data.participants_count', 1);
    }

    public function test_pending_passenger_can_confirm_new_terms_then_driver_can_approve(): void
    {
        $driver = $this->user();
        $passenger = $this->user();
        Sanctum::actingAs($driver);
        $id = $this->postJson('/api/v1/rides', [...$this->payload(), 'cost_mode' => 'free'])->assertCreated()->json('data.id');
        Sanctum::actingAs($passenger);
        $this->postJson("/api/v1/rides/$id/join", ['price_version' => 1, 'price_acknowledged' => true])->assertOk();
        Sanctum::actingAs($driver);
        $this->putJson("/api/v1/rides/$id", $this->payload())->assertOk();
        $this->postJson("/api/v1/rides/$id/requests/{$passenger->id}/approve")->assertUnprocessable();
        Sanctum::actingAs($passenger);
        $this->postJson("/api/v1/rides/$id/price/confirm", ['price_version' => 2, 'price_acknowledged' => true])->assertOk();
        Sanctum::actingAs($driver);
        $this->postJson("/api/v1/rides/$id/requests/{$passenger->id}/approve")->assertOk();
        $this->putJson("/api/v1/rides/$id", [...$this->payload(), 'pickup_house_number' => '12'])->assertOk()->assertJsonPath('data.pricing.version', 2);
        Sanctum::actingAs($passenger);
        $this->getJson("/api/v1/rides/$id")->assertJsonPath('data.pricing.requires_confirmation', false);
    }

    #[DataProvider('amounts')]
    public function test_international_precision_and_validation(string $currency, string $amount, ?int $minor): void
    {
        Sanctum::actingAs($this->user());
        $response = $this->postJson('/api/v1/rides', [...$this->payload(), 'cost_currency' => $currency, 'cost_amount' => $amount]);
        if ($minor === null) {
            $response->assertUnprocessable();
        } else {
            $response->assertCreated()->assertJsonPath('data.pricing.amount_minor', $minor)->assertJsonPath('data.pricing.currency', $currency);
        }
    }

    public static function amounts(): array
    {
        return [['EUR', '12,50', 1250], ['MAD', '١٢٫٥٠', 1250], ['JPY', '1200', 1200], ['KWD', '1.234', 1234],
            ['JPY', '1.20', null], ['EUR', '1.234', null], ['EUR', '-1', null], ['EUR', '0', null],
            ['EUR', 'NaN', null], ['ZZZ', '10', null], ['EUR', '99999999999999', null]];
    }

    public function test_legacy_rides_are_not_silently_marked_free_and_new_clients_must_choose(): void
    {
        Sanctum::actingAs($this->user());
        $data = $this->payload();
        unset($data['cost_mode'], $data['cost_currency'], $data['cost_amount']);
        $this->postJson('/api/v1/rides', $data)->assertUnprocessable()->assertJsonValidationErrors('cost_mode');
        unset($data['pricing_details']);
        $this->postJson('/api/v1/rides', $data)->assertCreated()->assertJsonPath('data.pricing.mode', null);
    }

    public function test_web_uses_same_terms_and_country_default_and_requires_reconfirmation(): void
    {
        $driver = $this->user('MA');
        $passenger = $this->user('DE');
        $this->actingAs($driver)->post(route('auth.rides.store'), [...$this->payload(), 'cost_currency' => 'MAD'])->assertSessionHasNoErrors()->assertRedirect();
        $id = Ride::firstOrFail()->id;
        $this->actingAs($passenger)->post(route('auth.rides.join', $id), ['price_version' => 1, 'price_acknowledged' => true])->assertSessionHasNoErrors();
        $this->actingAs($driver)->put(route('auth.rides.update', $id), [...$this->payload(), 'cost_currency' => 'JPY', 'cost_amount' => '100'])->assertSessionHasNoErrors();
        $this->actingAs($passenger)->post(route('auth.rides.price.confirm', $id), ['price_version' => 2, 'price_acknowledged' => true])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ride_users', ['ride_id' => $id, 'user_id' => $passenger->id, 'agreed_cost_currency' => 'JPY', 'agreed_cost_amount_minor' => 100, 'price_confirmation_required' => false]);
    }

    private function user(string $country = 'DE'): User
    {
        $user = User::factory()->create(['country' => $country]);
        $user->assignRole(Role::findOrCreate('player', 'web'));

        return $user;
    }

    private function payload(): array
    {
        return ['visibility' => 'public', 'from' => 'Start', 'to' => 'Finish', 'departure_time' => now()->addDay()->toIso8601String(), 'seats' => 3,
            'pricing_details' => true, 'cost_mode' => 'contribution', 'cost_currency' => 'EUR', 'cost_amount' => '10.00'];
    }
}
