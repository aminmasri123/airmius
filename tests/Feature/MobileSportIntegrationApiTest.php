<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ConnectedSportAccount;
use App\Models\ConnectedSportActivity;
use App\Models\SportRoute;
use App\Models\SportRouteTrack;
use App\Models\Team;
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
            ->assertJsonPath('data.providers.4.key', 'mi_fitness')
            ->assertJsonPath('data.providers.4.status', 'native_bridge')
            ->assertJsonPath('data.providers.4.request_message_key', 'fitness.providerMiFitness')
            ->assertJsonPath('data.providers.4.supports_direct_sync', false)
            ->assertJsonPath('data.normalized_import.endpoint', '/api/v1/sport-integrations/activities/import')
            ->assertJsonPath('data.normalized_import.max_samples', 5000)
            ->assertJsonPath('data.normalized_import.accepted_providers.4', 'mi_fitness')
            ->assertJsonPath('data.normalized_import.accepted_summary_fields.0', 'average_heart_rate')
            ->assertJsonPath('data.normalized_import.reference_policy', 'visible_route_and_member_team')
            ->assertJsonPath('data.normalized_import.unknown_summary_fields', 'discarded')
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

        $activityId = ConnectedSportActivity::query()
            ->where('user_id', $user->id)
            ->where('provider_activity_id', 'strava-activity-42')
            ->value('id');

        $this->putJson('/api/v1/sport-integrations/activities/'.$activityId, [
            'title' => 'UC29 Morgenlauf korrigiert',
        ])
            ->assertOk()
            ->assertJsonPath('data.activity.title', 'UC29 Morgenlauf korrigiert');

        $this->deleteJson('/api/v1/sport-integrations/activities/'.$activityId)
            ->assertOk()
            ->assertJsonPath('data.activity_id', $activityId);

        $this->assertDatabaseMissing('connected_sport_activities', ['id' => $activityId]);
    }

    public function test_mi_fitness_bridge_minimizes_health_data_and_is_idempotent(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/sport-integrations/mi-fitness/request')
            ->assertOk()
            ->assertJsonPath('data.provider', 'mi_fitness')
            ->assertJsonPath('data.status', 'native_ready')
            ->assertJsonPath('data.next_action', 'request_android_health_connect_permissions_or_import_file');

        $payload = [
            'provider' => 'mi_fitness',
            'provider_user_id' => 'device-private-id',
            'external_id' => 'mi-workout-2026-08-09',
            'title' => 'Abendlauf',
            'activity_type' => 'run',
            'started_at' => '2026-08-09T18:00:00+02:00',
            'duration_seconds' => 900,
            'summary' => [
                'average_heart_rate' => 148.4567,
                'steps' => 2431,
                'private_note' => 'must-not-be-stored',
            ],
            'samples' => [
                ['latitude' => 52.5200, 'longitude' => 13.4050, 'recorded_at' => '2026-08-09T18:00:00+02:00'],
                ['latitude' => 52.5210, 'longitude' => 13.4060, 'recorded_at' => '2026-08-09T18:15:00+02:00'],
            ],
        ];

        $this->postJson('/api/v1/sport-integrations/activities/import', $payload)
            ->assertCreated()
            ->assertJsonPath('data.activity.provider', 'mi_fitness')
            ->assertJsonPath('data.activity.started_at', '2026-08-09T16:00:00+00:00')
            ->assertJsonPath('data.activity.metrics.provider_summary.average_heart_rate', 148.457)
            ->assertJsonPath('data.activity.metrics.provider_summary.steps', 2431)
            ->assertJsonMissingPath('data.activity.metrics.provider_summary.private_note')
            ->assertJsonMissingPath('data.activity.metrics.raw_summary')
            ->assertJsonMissingPath('data.activity.metrics.provider_user_id')
            ->assertJsonPath('data.account.status', 'connected');

        $this->postJson('/api/v1/sport-integrations/activities/import', [
            ...$payload,
            'title' => 'Korrigierter Abendlauf',
            'samples' => [
                ['latitude' => 52.5200, 'longitude' => 13.4050, 'recorded_at' => '2026-08-09T18:00:00+02:00'],
                ['latitude' => 52.5220, 'longitude' => 13.4070, 'recorded_at' => '2026-08-09T18:15:00+02:00'],
            ],
        ])->assertCreated();

        $this->assertSame(1, ConnectedSportActivity::query()->where('user_id', $user->id)->count());
        $this->assertSame(1, SportRouteTrack::query()->where('user_id', $user->id)->count());
        $this->assertDatabaseHas('sport_route_tracks', [
            'user_id' => $user->id,
            'source' => 'mi_fitness',
            'source_activity_id' => 'mi-workout-2026-08-09',
            'title' => 'Korrigierter Abendlauf',
        ]);

        $activity = ConnectedSportActivity::query()->sole();
        $this->assertSame('2026-08-09T16:00:00+00:00', $activity->started_at->toIso8601String());
        $this->assertSame(
            ['average_heart_rate' => 148.457, 'steps' => 2431],
            $activity->metrics['provider_summary'],
        );
    }

    public function test_normalized_import_rejects_foreign_private_route_and_team_references(): void
    {
        $user = User::factory()->create();
        $foreignUser = User::factory()->create();
        $foreignClub = Club::factory()->create(['owner_id' => $foreignUser->id]);
        $foreignTeam = Team::factory()->create(['club_id' => $foreignClub->id]);
        $foreignRoute = SportRoute::query()->create([
            'user_id' => $foreignUser->id,
            'team_id' => $foreignTeam->id,
            'title' => 'Private Fremdroute',
            'visibility' => 'private',
            'status' => 'planned',
            'waypoints' => [],
        ]);

        Sanctum::actingAs($user);

        $basePayload = [
            'provider' => 'mi_fitness',
            'external_id' => 'foreign-reference-check',
            'started_at' => '2026-08-09T18:00:00+02:00',
        ];

        $this->postJson('/api/v1/sport-integrations/activities/import', [
            ...$basePayload,
            'sport_route_id' => $foreignRoute->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sport_route_id');

        $this->postJson('/api/v1/sport-integrations/activities/import', [
            ...$basePayload,
            'team_id' => $foreignTeam->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('team_id');

        $this->assertDatabaseCount('connected_sport_accounts', 0);
        $this->assertDatabaseCount('connected_sport_activities', 0);
        $this->assertDatabaseCount('sport_route_tracks', 0);
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

    public function test_mobile_sport_activity_update_and_delete_are_owner_scoped(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $activity = ConnectedSportActivity::query()->create([
            'user_id' => $owner->id,
            'provider' => 'manual',
            'provider_activity_id' => 'uc29-owner-check',
            'activity_type' => 'Run',
            'title' => 'Private Aktivität',
            'started_at' => now(),
        ]);

        Sanctum::actingAs($other);
        $this->putJson('/api/v1/sport-integrations/activities/'.$activity->id, ['title' => 'Fremd'])
            ->assertForbidden();
        $this->deleteJson('/api/v1/sport-integrations/activities/'.$activity->id)
            ->assertForbidden();

        $this->assertDatabaseHas('connected_sport_activities', [
            'id' => $activity->id,
            'title' => 'Private Aktivität',
        ]);
    }
}
