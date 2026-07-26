<?php

namespace Tests\Feature;

use App\Models\ConnectedSportAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileSportIntegrationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_sport_integrations_expose_safe_provider_contract_and_import_activity(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/sport-integrations')
            ->assertOk()
            ->assertJsonPath('data.providers.0.key', 'apple_health')
            ->assertJsonPath('data.providers.0.status', 'native_bridge')
            ->assertJsonPath('data.providers.0.request_message_key', 'fitness.providerAppleHealth')
            ->assertJsonPath('data.providers.3.request_message_key', 'fitness.providerStrava')
            ->assertJsonPath('data.normalized_import.endpoint', '/api/v1/sport-integrations/activities/import')
            ->assertJsonPath('data.normalized_import.max_samples', 5000)
            ->assertJsonPath('data.activities', []);

        $this->postJson('/api/v1/sport-integrations/garmin/request')
            ->assertOk()
            ->assertJsonPath('data.provider', 'garmin')
            ->assertJsonPath('data.status', 'requested');

        $this->getJson('/api/v1/sport-integrations')
            ->assertOk()
            ->assertJsonPath('data.providers.2.account.sync_summary.message_key', 'fitness.accountRequested');

        $this->postJson('/api/v1/sport-integrations/activities/import', [
            'provider' => 'strava',
            'external_id' => 'strava-activity-42',
            'title' => 'Morgenlauf',
            'activity_type' => 'Run',
            'started_at' => now()->subDay()->toISOString(),
            'duration_seconds' => 2700,
            'distance_meters' => 6800,
            'calories' => 410,
            'summary' => ['average_speed' => 2.5],
        ])
            ->assertCreated()
            ->assertJsonPath('data.activity.provider', 'strava')
            ->assertJsonPath('data.activity.provider_activity_id', 'strava-activity-42')
            ->assertJsonPath('data.activity.distance_meters', 6800)
            ->assertJsonPath('data.activity.duration_seconds', 2700)
            ->assertJsonPath('data.account.status', 'connected')
            ->assertJsonPath('data.account.sync_summary.message_key', 'fitness.accountConnected')
            ->assertJsonMissingPath('data.activity.access_token');

        $this->getJson('/api/v1/sport-integrations')
            ->assertOk()
            ->assertJsonPath('data.activities.0.title', 'Morgenlauf')
            ->assertJsonPath('data.activities.0.provider', 'strava')
            ->assertJsonPath('data.providers.2.account.status', 'requested');
    }

    public function test_mobile_sport_account_sync_and_disconnect_are_owner_scoped(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $account = ConnectedSportAccount::query()->create([
            'user_id' => $owner->id,
            'provider' => 'strava',
            'display_name' => 'Strava',
            'status' => 'connected',
        ]);

        Sanctum::actingAs($other);
        $this->postJson("/api/v1/sport-integrations/accounts/{$account->id}/sync")
            ->assertForbidden();
        $this->deleteJson("/api/v1/sport-integrations/accounts/{$account->id}")
            ->assertForbidden();

        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/sport-integrations/accounts/{$account->id}/sync")
            ->assertUnprocessable()
            ->assertJsonPath('data.ok', false)
            ->assertJsonPath('data.account.status', 'error');

        $this->deleteJson("/api/v1/sport-integrations/accounts/{$account->id}")
            ->assertOk()
            ->assertJsonPath('message', 'sport_integration_disconnected');
        $this->assertDatabaseMissing('connected_sport_accounts', ['id' => $account->id]);
    }
}
