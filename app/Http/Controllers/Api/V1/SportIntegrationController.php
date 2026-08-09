<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SportTrackResource;
use App\Models\ConnectedSportAccount;
use App\Services\SportIntegrationActivityImportService;
use App\Services\SportIntegrationSyncService;
use App\Support\SportIntegrationProviderRegistry;
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
                'status' => ($definition['status'] ?? null) === 'native_bridge' ? 'native_ready' : 'requested',
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
            'summary' => ['nullable', 'array', 'max:12'],
            'summary.average_heart_rate' => ['nullable', 'numeric', 'between:20,260'],
            'summary.max_heart_rate' => ['nullable', 'numeric', 'between:20,280'],
            'summary.average_speed' => ['nullable', 'numeric', 'between:0,100'],
            'summary.average_speed_mps' => ['nullable', 'numeric', 'between:0,100'],
            'summary.max_speed_mps' => ['nullable', 'numeric', 'between:0,150'],
            'summary.average_cadence' => ['nullable', 'numeric', 'between:0,400'],
            'summary.max_cadence' => ['nullable', 'numeric', 'between:0,500'],
            'summary.average_power_watts' => ['nullable', 'numeric', 'between:0,5000'],
            'summary.max_power_watts' => ['nullable', 'numeric', 'between:0,10000'],
            'summary.steps' => ['nullable', 'integer', 'between:0,1000000'],
            'summary.elevation_gain_meters' => ['nullable', 'numeric', 'between:0,50000'],
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
        return SportIntegrationProviderRegistry::mobileDefinitions();
    }

    private function normalizedImportContract(): array
    {
        return [
            'endpoint' => '/api/v1/sport-integrations/activities/import',
            'idempotency_key' => 'provider + external_id',
            'accepted_providers' => SportIntegrationActivityImportService::PROVIDERS,
            'accepted_summary_fields' => SportIntegrationActivityImportService::SUMMARY_FIELDS,
            'gps_samples_create_track' => true,
            'max_samples' => 5000,
            'required_fields' => ['provider', 'external_id', 'started_at'],
            'optional_fields' => ['provider_user_id', 'title', 'activity_type', 'duration_seconds', 'distance_meters', 'calories', 'sport_route_id', 'sport_id', 'team_id', 'summary', 'samples'],
            'reference_policy' => 'visible_route_and_member_team',
            'unknown_summary_fields' => 'discarded',
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
