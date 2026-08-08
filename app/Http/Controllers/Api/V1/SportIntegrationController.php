<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SportTrackResource;
use App\Models\ConnectedSportAccount;
use App\Services\SportIntegrationSyncService;
use App\Services\SportIntegrationActivityImportService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SportIntegrationController extends Controller
{
    private const DISCONNECTED = 'sport_integration_disconnected';

    public function index(Request $request)
    {
        $accounts = ConnectedSportAccount::query()
            ->where('user_id', $request->user()->id)
            ->get()
            ->keyBy('provider');

        return response()->json([
            'data' => [
                'providers' => collect($this->providers())
                    ->map(function (array $provider) use ($accounts) {
                        $account = $accounts->get($provider['key']);

                        return [
                            ...$provider,
                            'account' => $account ? $this->accountPayload($account) : null,
                        ];
                    })
                    ->values(),
                'normalized_import' => $this->normalizedImportContract(),
                'gpx' => $this->gpxContract(),
                'activities' => $request->user()
                    ->connectedSportActivities()
                    ->latest('started_at')
                    ->limit(20)
                    ->get(['id', 'provider', 'activity_type', 'title', 'started_at', 'duration_seconds', 'distance_meters', 'calories', 'metrics'])
                    ->map(fn ($activity) => [
                        'id' => $activity->id,
                        'provider' => $activity->provider,
                        'activity_type' => $activity->activity_type,
                        'title' => $activity->title,
                        'started_at' => $activity->started_at?->toIso8601String(),
                        'duration_seconds' => $activity->duration_seconds,
                        'distance_meters' => $activity->distance_meters,
                        'calories' => $activity->calories,
                        'metrics' => $activity->metrics ?? [],
                    ])
                    ->values(),
            ],
        ]);
    }

    public function sync(
        Request $request,
        ConnectedSportAccount $account,
        SportIntegrationSyncService $syncService,
    ) {
        abort_unless($account->user_id === $request->user()->id, 403);

        $result = $syncService->sync($account);
        $account = $account->fresh();

        return response()->json([
            'data' => [
                'ok' => (bool) ($result['ok'] ?? false),
                'message' => $result['message'] ?? null,
                'imported' => (int) ($result['imported'] ?? 0),
                'account' => $this->accountPayload($account),
            ],
        ], ($result['ok'] ?? false) ? 200 : 422);
    }

    public function disconnect(Request $request, ConnectedSportAccount $account)
    {
        abort_unless($account->user_id === $request->user()->id, 403);

        $account->delete();

        return response()->json([
            'message' => self::DISCONNECTED,
            'message_text' => __('sport_integrations.flash.disconnected'),
            'data' => ['account_id' => $account->id],
        ]);
    }

    public function requestProvider(Request $request, string $provider)
    {
        $provider = str_replace('-', '_', $provider);
        abort_unless(in_array($provider, SportIntegrationActivityImportService::PROVIDERS, true), 404);

        $definition = collect($this->providers())->firstWhere('key', $provider);

        $account = ConnectedSportAccount::query()->updateOrCreate(
            ['user_id' => $request->user()->id, 'provider' => $provider],
            [
                'display_name' => $definition['label'] ?? $provider,
                'status' => in_array($provider, ['apple_health'], true) ? 'native_ready' : 'requested',
                'scopes' => $definition['scopes'] ?? [],
                'sync_summary' => [
                    'message' => $definition['request_message'] ?? __('Integration wurde vorgemerkt.'),
                    'contract' => $definition['connection_mode'] ?? 'unknown',
                ],
            ],
        );

        return response()->json([
            'data' => [
                'provider' => $provider,
                'status' => $account->status,
                'account_id' => $account->id,
                'next_action' => $definition['next_action'] ?? null,
            ],
        ]);
    }

    public function importActivity(Request $request, SportIntegrationActivityImportService $importer)
    {
        $data = $request->validate([
            'provider' => ['required', 'string', Rule::in(SportIntegrationActivityImportService::PROVIDERS)],
            'provider_user_id' => ['nullable', 'string', 'max:120'],
            'external_id' => ['required', 'string', 'max:160'],
            'title' => ['nullable', 'string', 'max:160'],
            'activity_type' => ['nullable', 'string', 'max:80'],
            'started_at' => ['required', 'date'],
            'ended_at' => ['nullable', 'date', 'after_or_equal:started_at'],
            'duration_seconds' => ['nullable', 'integer', 'min:0', 'max:604800'],
            'distance_meters' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'calories' => ['nullable', 'integer', 'min:0', 'max:200000'],
            'sport_route_id' => ['nullable', 'integer', 'exists:sport_routes,id'],
            'sport_id' => ['nullable', 'integer', 'exists:sports,id'],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'summary' => ['nullable', 'array'],
            'samples' => ['nullable', 'array', 'max:5000'],
            'samples.*.latitude' => ['required_with:samples', 'numeric', 'between:-90,90'],
            'samples.*.longitude' => ['required_with:samples', 'numeric', 'between:-180,180'],
            'samples.*.elevation_m' => ['nullable', 'numeric', 'between:-500,9000'],
            'samples.*.altitude_m' => ['nullable', 'numeric', 'between:-500,9000'],
            'samples.*.recorded_at' => ['nullable', 'date'],
            'samples.*.timestamp' => ['nullable', 'date'],
            'samples.*.accuracy_m' => ['nullable', 'numeric', 'min:0', 'max:1000'],
        ]);

        $result = $importer->import($request->user(), $data);

        return response()->json([
            'data' => [
                'activity' => [
                    'id' => $result['activity']->id,
                    'provider' => $result['activity']->provider,
                    'provider_activity_id' => $result['activity']->provider_activity_id,
                    'title' => $result['activity']->title,
                    'activity_type' => $result['activity']->activity_type,
                    'started_at' => $result['activity']->started_at?->toIso8601String(),
                    'duration_seconds' => $result['activity']->duration_seconds,
                    'distance_meters' => $result['activity']->distance_meters,
                    'calories' => $result['activity']->calories,
                    'metrics' => $result['activity']->metrics ?? [],
                ],
                'track' => $result['track'] ? new SportTrackResource($result['track']) : null,
                'account' => $this->accountPayload($result['account']),
            ],
        ], 201);
    }

    private function providers(): array
    {
        return [
            [
                'key' => 'apple_health',
                'label' => $this->providerLabel('apple_health'),
                'status' => 'native_bridge',
                'connection_mode' => 'ios_healthkit_normalized_import',
                'direction' => ['import'],
                'supports_gps_samples' => true,
                'supports_background_sync' => true,
                'scopes' => ['workouts', 'workout_routes', 'heart_rate', 'active_energy'],
                'next_action' => 'request_ios_healthkit_permissions',
                'request_message' => __('Apple Health wird nativ über HealthKit importiert.'),
                'request_message_key' => 'fitness.providerAppleHealth',
            ],
            [
                'key' => 'google_fit',
                'label' => $this->providerLabel('google_fit'),
                'status' => 'live_oauth',
                'connection_mode' => 'oauth_or_android_health_connect_normalized_import',
                'direction' => ['import'],
                'supports_gps_samples' => true,
                'supports_background_sync' => true,
                'scopes' => ['fitness.activity.read', 'fitness.location.read'],
                'next_action' => 'oauth_or_android_health_permissions',
                'request_message' => __('Google Fit kann per OAuth oder normalisiertem App-Import angebunden werden.'),
                'request_message_key' => 'fitness.providerGoogleFit',
            ],
            [
                'key' => 'garmin',
                'label' => $this->providerLabel('garmin'),
                'status' => 'partner_required',
                'connection_mode' => 'garmin_health_api_or_file_bridge',
                'direction' => ['import'],
                'supports_gps_samples' => true,
                'supports_background_sync' => false,
                'scopes' => ['activities', 'wellness'],
                'next_action' => 'collect_partner_interest',
                'request_message' => __('Garmin Health API braucht Partnerfreigabe; normalisierte Importe bleiben vorbereitet.'),
                'request_message_key' => 'fitness.providerGarmin',
            ],
            [
                'key' => 'strava',
                'label' => $this->providerLabel('strava'),
                'status' => 'live_oauth',
                'connection_mode' => 'oauth_import_gpx_export',
                'direction' => ['import', 'export_gpx'],
                'supports_gps_samples' => true,
                'supports_background_sync' => true,
                'scopes' => ['read', 'activity:read_all'],
                'next_action' => 'oauth_connect_or_upload_gpx',
                'request_message' => __('Strava Import ist per OAuth verfügbar; Export läuft über GPX.'),
                'request_message_key' => 'fitness.providerStrava',
            ],
        ];
    }

    private function normalizedImportContract(): array
    {
        return [
            'endpoint' => '/api/v1/sport-integrations/activities/import',
            'idempotency_key' => 'provider + external_id',
            'accepted_providers' => SportIntegrationActivityImportService::PROVIDERS,
            'gps_samples_create_track' => true,
            'max_samples' => 5000,
            'required_fields' => ['provider', 'external_id', 'started_at'],
            'optional_fields' => ['title', 'activity_type', 'duration_seconds', 'distance_meters', 'calories', 'samples'],
        ];
    }

    private function gpxContract(): array
    {
        return [
            'mime_type' => 'application/gpx+xml',
            'route_import_endpoint' => '/api/v1/sport-routes/import-gpx',
            'track_import_endpoint' => '/api/v1/sport-tracks/import-gpx',
            'route_export_template' => '/api/v1/sport-routes/{sportRoute}/gpx',
            'track_export_template' => '/api/v1/sport-tracks/{sportRouteTrack}/gpx',
            'schema' => 'GPX 1.1',
            'creator' => 'Airmius',
        ];
    }

    private function providerLabel(string $provider): string
    {
        return [
            'apple_health' => 'Apple Health',
            'google_fit' => 'Google Fit',
            'garmin' => 'Garmin',
            'strava' => 'Strava',
        ][$provider] ?? $provider;
    }

    private function accountPayload(ConnectedSportAccount $account): array
    {
        return [
            'id' => $account->id,
            'provider' => $account->provider,
            'status' => $account->status,
            'display_name' => $account->display_name,
            'last_synced_at' => $account->last_synced_at?->toIso8601String(),
            'sync_summary' => [
                ...($account->sync_summary ?? []),
                'message_key' => $this->accountMessageKey($account),
            ],
        ];
    }

    private function accountMessageKey(ConnectedSportAccount $account): string
    {
        return match ($account->status) {
            'connected' => 'fitness.accountConnected',
            'requested' => 'fitness.accountRequested',
            'native_ready' => 'fitness.statusNative',
            'error' => 'fitness.accountError',
            default => 'fitness.statusPlanned',
        };
    }
}
