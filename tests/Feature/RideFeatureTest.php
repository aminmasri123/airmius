<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RideFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_ride_keeps_private_details_hidden_until_request_is_approved(): void
    {
        $driver = $this->player();
        $passenger = $this->player();
        $ride = $this->rideFor($driver, [
            'visibility' => 'public',
            'from' => 'Koeln',
            'to' => 'Bonn',
            'pickup_name' => 'Sporthalle',
            'pickup_street' => 'Trainingsweg',
            'pickup_house_number' => '7',
            'pickup_postal_code' => '50667',
            'pickup_city' => 'Koeln',
            'pickup_country' => 'DE',
            'pickup_note' => 'Eingang Nord',
            'contact_details' => 'Telefon nach Zusage',
        ]);

        $this->actingAs($passenger)
            ->get(route('auth.rides.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Dashboard/Rides/Index')
                ->has('rides', 1)
                ->where('rides.0.id', $ride->id)
                ->where('rides.0.can_join', true)
                ->where('rides.0.contact_details', null)
                ->where('rides.0.pickup_private_label', null)
                ->where('rides.0.pickup_public_label', 'Sporthalle - 50667 Koeln')
            );

        $this->actingAs($passenger)
            ->post(route('auth.rides.join', $ride), [
                'message' => 'Ich bin puenktlich am Treffpunkt.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('ride_users', [
            'ride_id' => $ride->id,
            'user_id' => $passenger->id,
            'status' => Ride::MEMBER_STATUS_REQUESTED,
            'message' => 'Ich bin puenktlich am Treffpunkt.',
        ]);

        $this->actingAs($driver)
            ->post(route('auth.rides.requests.approve', [$ride, $passenger]))
            ->assertRedirect();

        $this->assertDatabaseHas('ride_users', [
            'ride_id' => $ride->id,
            'user_id' => $passenger->id,
            'status' => Ride::MEMBER_STATUS_ACCEPTED,
        ]);

        $this->actingAs($passenger)
            ->get(route('auth.rides.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rides.0.is_joined', true)
                ->where('rides.0.contact_details', 'Telefon nach Zusage')
                ->where('rides.0.pickup_private_label', 'Sporthalle, Trainingsweg 7, 50667 Koeln, DE, Eingang Nord')
            );
    }

    public function test_club_ride_is_visible_to_club_members_but_hidden_from_outsiders(): void
    {
        $driver = $this->player();
        $member = $this->player();
        $outsider = $this->player();
        $club = Club::factory()->create(['owner_id' => $driver->id]);

        $club->users()->syncWithoutDetaching([
            $driver->id => ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active'],
            $member->id => ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active'],
        ]);

        $ride = $this->rideFor($driver, [
            'club_id' => $club->id,
            'visibility' => 'club',
            'from' => 'Clubhaus',
            'to' => 'Auswaertsspiel',
        ]);

        $this->actingAs($member)
            ->get(route('auth.rides.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('rides', 1)
                ->where('rides.0.id', $ride->id)
                ->where('rides.0.can_join', true)
            );

        $this->actingAs($outsider)
            ->get(route('auth.rides.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('rides', 0)
            );
    }

    private function player(): User
    {
        $role = Role::query()->firstOrCreate([
            'name' => 'player',
            'guard_name' => 'web',
        ]);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function rideFor(User $driver, array $overrides = []): Ride
    {
        $ride = Ride::query()->create(array_merge([
            'club_id' => null,
            'team_id' => null,
            'driver_id' => $driver->id,
            'visibility' => 'public',
            'from' => 'Start',
            'to' => 'Ziel',
            'pickup_name' => 'Sportplatz',
            'pickup_street' => null,
            'pickup_house_number' => null,
            'pickup_postal_code' => '50667',
            'pickup_city' => 'Koeln',
            'pickup_country' => 'DE',
            'pickup_note' => null,
            'departure_time' => now()->addDay(),
            'seats' => 3,
            'contact_details' => 'Kontakt nach Zusage',
        ], $overrides));

        $ride->users()->attach($driver->id, [
            'status' => Ride::MEMBER_STATUS_ACCEPTED,
            'responded_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $ride;
    }
}
