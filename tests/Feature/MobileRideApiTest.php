<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MobileRideApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_private_pickup_and_contact_are_revealed_only_after_driver_approval(): void
    {
        $driver = $this->withRole('player');
        $passenger = $this->withRole('player');

        Sanctum::actingAs($driver);
        $created = $this->postJson('/api/v1/rides', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.is_driver', true)
            ->assertJsonPath('data.pickup_private_label', 'Sporthalle, Trainingsweg 7, 50667 Koeln, DE, Eingang Nord')
            ->assertJsonPath('data.contact_details', 'Telefon nach Zusage');
        $rideId = $created->json('data.id');

        Sanctum::actingAs($passenger);
        $this->getJson('/api/v1/rides')
            ->assertOk()
            ->assertJsonPath('data.rides.0.id', $rideId)
            ->assertJsonPath('data.rides.0.pickup_public_label', 'Sporthalle - 50667 Koeln')
            ->assertJsonPath('data.rides.0.pickup_street', null)
            ->assertJsonPath('data.rides.0.pickup_private_label', null)
            ->assertJsonPath('data.rides.0.contact_details', null)
            ->assertJsonPath('data.rides.0.can_join', true);

        $this->postJson("/api/v1/rides/{$rideId}/join", [
            'message' => 'Ich bin pünktlich am Treffpunkt.',
        ])
            ->assertOk()
            ->assertJsonPath('data.has_pending_request', true)
            ->assertJsonPath('data.pickup_private_label', null);

        Sanctum::actingAs($driver);
        $this->getJson('/api/v1/rides')
            ->assertOk()
            ->assertJsonPath('data.rides.0.pending_requests.0.id', $passenger->id)
            ->assertJsonPath(
                'data.rides.0.pending_requests.0.message',
                'Ich bin pünktlich am Treffpunkt.',
            );

        $this->postJson("/api/v1/rides/{$rideId}/requests/{$passenger->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.participants_count', 2);

        Sanctum::actingAs($passenger);
        $this->getJson('/api/v1/rides')
            ->assertOk()
            ->assertJsonPath('data.rides.0.is_joined', true)
            ->assertJsonPath(
                'data.rides.0.pickup_private_label',
                'Sporthalle, Trainingsweg 7, 50667 Koeln, DE, Eingang Nord',
            )
            ->assertJsonPath('data.rides.0.contact_details', 'Telefon nach Zusage');
    }

    public function test_visibility_membership_and_driver_actions_are_enforced_server_side(): void
    {
        $driver = $this->withRole('player');
        $member = $this->withRole('player');
        $outsider = $this->withRole('player');
        $club = Club::factory()->create(['owner_id' => $driver->id]);
        $foreignClub = Club::factory()->create(['owner_id' => $outsider->id]);
        $team = Team::factory()->create(['club_id' => $club->id]);

        $club->users()->syncWithoutDetaching([
            $driver->id => ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active'],
            $member->id => ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active'],
        ]);
        $team->users()->attach($driver->id, ['role' => 'Player']);
        $team->users()->attach($member->id, ['role' => 'Player']);

        Sanctum::actingAs($driver);
        $rideId = $this->postJson('/api/v1/rides', $this->payload([
            'visibility' => 'team',
            'club_id' => $club->id,
            'team_id' => $team->id,
        ]))
            ->assertCreated()
            ->assertJsonPath('data.club_id', $club->id)
            ->assertJsonPath('data.team_id', $team->id)
            ->json('data.id');

        Sanctum::actingAs($member);
        $this->getJson('/api/v1/rides')
            ->assertOk()
            ->assertJsonCount(1, 'data.rides')
            ->assertJsonPath('data.rides.0.id', $rideId);
        $this->putJson("/api/v1/rides/{$rideId}", $this->payload())
            ->assertForbidden();
        $this->deleteJson("/api/v1/rides/{$rideId}")
            ->assertForbidden();

        Sanctum::actingAs($outsider);
        $this->getJson('/api/v1/rides')
            ->assertOk()
            ->assertJsonCount(0, 'data.rides');

        $this->postJson('/api/v1/rides', $this->payload([
            'visibility' => 'club',
            'club_id' => $club->id,
        ]))->assertForbidden();

        $this->postJson('/api/v1/rides', $this->payload([
            'visibility' => 'team',
            'club_id' => $foreignClub->id,
            'team_id' => $team->id,
        ]))->assertForbidden();
    }

    public function test_capacity_lifecycle_and_minor_consent_gate_are_enforced(): void
    {
        $driver = $this->withRole('player');
        $passenger = $this->withRole('player');
        $minorWithoutConsent = $this->withRole('minor_pending_consent', [
            'birth_date' => now()->subYears(13)->toDateString(),
        ]);

        Sanctum::actingAs($driver);
        $rideId = $this->postJson('/api/v1/rides', $this->payload(['seats' => 2]))
            ->assertCreated()
            ->json('data.id');

        Sanctum::actingAs($passenger);
        $this->postJson("/api/v1/rides/{$rideId}/join")
            ->assertOk();

        Sanctum::actingAs($driver);
        $this->postJson("/api/v1/rides/{$rideId}/requests/{$passenger->id}/approve")
            ->assertOk();

        $this->putJson("/api/v1/rides/{$rideId}", $this->payload(['seats' => 1]))
            ->assertUnprocessable();

        Sanctum::actingAs($passenger);
        $this->postJson("/api/v1/rides/{$rideId}/leave")
            ->assertOk()
            ->assertJsonPath('data.participants_count', 1);

        Sanctum::actingAs($driver);
        $this->deleteJson("/api/v1/rides/{$rideId}")
            ->assertOk();
        $this->assertDatabaseMissing('rides', ['id' => $rideId]);

        Sanctum::actingAs($minorWithoutConsent);
        $this->getJson('/api/v1/rides')->assertForbidden();
        $this->postJson('/api/v1/rides', $this->payload())->assertForbidden();
    }

    private function withRole(string $roleName, array $attributes = []): User
    {
        $role = Role::query()->firstOrCreate([
            'name' => $roleName,
            'guard_name' => 'web',
        ]);
        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'visibility' => 'public',
            'club_id' => null,
            'team_id' => null,
            'from' => 'Koeln',
            'to' => 'Bonn',
            'pickup_name' => 'Sporthalle',
            'pickup_street' => 'Trainingsweg',
            'pickup_house_number' => '7',
            'pickup_postal_code' => '50667',
            'pickup_city' => 'Koeln',
            'pickup_country' => 'de',
            'pickup_note' => 'Eingang Nord',
            'departure_time' => now()->addHours(2)->toIso8601String(),
            'seats' => 3,
            'contact_details' => 'Telefon nach Zusage',
        ], $overrides);
    }
}
