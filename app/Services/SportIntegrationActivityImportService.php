<?php

namespace App\Services;

use App\Models\ConnectedSportAccount;
use App\Models\ConnectedSportActivity;
use App\Models\SportRoute;
use App\Models\SportRouteTrack;
use App\Models\User;
use App\Support\SportIntegrationProviderRegistry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SportIntegrationActivityImportService
{
    public const PROVIDERS = SportIntegrationProviderRegistry::NORMALIZED_IMPORT_PROVIDERS;

    public const SUMMARY_FIELDS = [
        'average_heart_rate',
        'max_heart_rate',
        'average_speed',
        'average_speed_mps',
        'max_speed_mps',
        'average_cadence',
        'max_cadence',
        'average_power_watts',
        'max_power_watts',
        'steps',
        'elevation_gain_meters',
    ];

    public function __construct(private readonly SportRouteMetricService $metrics) {}

    public function import(User $user, array $data): array
    {
        $data = $this->resolveReferences($user, $data);

        return DB::transaction(function () use ($user, $data) {
            $provider = (string) $data['provider'];
            $externalId = (string) $data['external_id'];
            $samples = $this->normalizeSamples($data['samples'] ?? []);
            $startedAt = Carbon::parse($data['started_at']);
            $endedAt = isset($data['ended_at']) && $data['ended_at']
                ? Carbon::parse($data['ended_at'])
                : $this->endedAtFromDuration($startedAt, $data['duration_seconds'] ?? null);

            $account = ConnectedSportAccount::query()->firstOrCreate(
                ['user_id' => $user->id, 'provider' => $provider],
                [
                    'display_name' => $this->providerLabel($provider),
                    'provider_user_id' => $data['provider_user_id'] ?? null,
                    'status' => 'connected',
                    'scopes' => $this->defaultScopes($provider),
                    'sync_summary' => [
                        'message' => __('sport_integrations.summary.normalized_import_ready'),
                        'source' => 'mobile_normalized_import',
                    ],
                ],
            );

            $track = count($samples) >= 2
                ? $this->storeTrack($user, $provider, $externalId, $data, $samples, $startedAt, $endedAt)
                : null;

            $activity = ConnectedSportActivity::query()->updateOrCreate(
                ['user_id' => $user->id, 'provider' => $provider, 'provider_activity_id' => $externalId],
                [
                    'connected_sport_account_id' => $account->id,
                    'activity_type' => $this->activityType($data['activity_type'] ?? null),
                    'title' => $data['title'] ?? $this->fallbackTitle($provider, $data['activity_type'] ?? null),
                    'started_at' => $startedAt,
                    'duration_seconds' => (int) ($data['duration_seconds'] ?? ($endedAt ? $startedAt->diffInSeconds($endedAt) : 0)),
                    'distance_meters' => (int) ($data['distance_meters'] ?? ($track?->distance_meters ?? 0)),
                    'calories' => isset($data['calories']) ? (int) $data['calories'] : null,
                    'metrics' => [
                        'source_kind' => 'normalized_mobile_import',
                        'provider' => $provider,
                        'track_id' => $track?->id,
                        'sample_count' => count($samples),
                        'imported_at' => now()->toIso8601String(),
                        'provider_summary' => $this->normalizeSummary($data['summary'] ?? []),
                    ],
                ],
            );

            $account->forceFill([
                'status' => 'connected',
                'last_synced_at' => now(),
                'sync_summary' => [
                    'message' => __('sport_integrations.summary.activity_imported'),
                    'created_track' => $track !== null,
                ],
            ])->save();

            return [
                'activity' => $activity,
                'track' => $track,
                'account' => $account,
            ];
        });
    }

    private function storeTrack(User $user, string $provider, string $externalId, array $data, array $samples, Carbon $startedAt, ?Carbon $endedAt): SportRouteTrack
    {
        $summary = $this->metrics->summarize($samples, $data['activity_type'] ?? null, $startedAt, $endedAt);
        $title = $data['title'] ?? $this->fallbackTitle($provider, $data['activity_type'] ?? null);

        $track = SportRouteTrack::query()
            ->withTrashed()
            ->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'source' => $provider,
                    'source_activity_id' => $externalId,
                ],
                [
                    'title' => $title,
                    'status' => 'completed',
                    'started_at' => $startedAt,
                ],
            );

        if ($track->trashed()) {
            $track->restore();
        }

        $track->fill([
            'sport_route_id' => $data['sport_route_id'] ?? null,
            'sport_id' => $data['sport_id'] ?? null,
            'team_id' => $data['team_id'] ?? null,
            'title' => $title,
            'sport_type' => $data['activity_type'] ?? null,
            'status' => 'completed',
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'distance_meters' => (int) ($data['distance_meters'] ?? $summary['distance_meters']),
            'duration_seconds' => (int) ($data['duration_seconds'] ?? $summary['duration_seconds']),
            'elevation_gain_meters' => $summary['elevation_gain_meters'],
            'elevation_loss_meters' => $summary['elevation_loss_meters'],
            'average_speed_mps' => $summary['average_speed_mps'],
            'max_speed_mps' => $summary['max_speed_mps'],
            'track_points' => $samples,
            'track_geometry' => $summary['geometry'],
            'metrics' => [
                'imported_from' => $provider,
                'external_activity_id' => $externalId,
                'source_kind' => 'normalized_mobile_import',
                'bounds' => $summary['bounds'],
                'route_intelligence' => $summary['route_intelligence'] ?? [],
                'gpx_export_ready' => true,
            ],
        ])->save();

        return $track;
    }

    private function normalizeSamples(array $samples): array
    {
        return $this->metrics->normalizePoints(collect($samples)
            ->filter(fn ($sample) => is_array($sample) && isset($sample['latitude'], $sample['longitude']))
            ->map(fn (array $sample) => [
                'latitude' => $sample['latitude'],
                'longitude' => $sample['longitude'],
                'elevation_m' => $sample['elevation_m'] ?? $sample['altitude_m'] ?? null,
                'recorded_at' => $sample['recorded_at'] ?? $sample['timestamp'] ?? null,
                'accuracy_m' => $sample['accuracy_m'] ?? null,
            ])
            ->values()
            ->all());
    }

    private function endedAtFromDuration(Carbon $startedAt, mixed $durationSeconds): ?Carbon
    {
        return $durationSeconds !== null ? $startedAt->copy()->addSeconds((int) $durationSeconds) : null;
    }

    private function activityType(?string $type): string
    {
        return $type ? Str::headline($type) : 'Activity';
    }

    private function fallbackTitle(string $provider, ?string $type): string
    {
        return $this->providerLabel($provider).' '.$this->activityType($type);
    }

    private function providerLabel(string $provider): string
    {
        return SportIntegrationProviderRegistry::label($provider);
    }

    private function defaultScopes(string $provider): array
    {
        return SportIntegrationProviderRegistry::defaultImportScopes($provider);
    }

    private function resolveReferences(User $user, array $data): array
    {
        $route = null;

        if (! empty($data['sport_route_id'])) {
            $route = SportRoute::query()
                ->visibleTo($user)
                ->whereKey($data['sport_route_id'])
                ->first();

            if (! $route) {
                throw ValidationException::withMessages([
                    'sport_route_id' => __('sport_integrations.errors.route_unavailable'),
                ]);
            }
        }

        if (! empty($data['team_id'])) {
            $belongsToTeam = $user->teams()
                ->where('teams.id', $data['team_id'])
                ->exists();

            if (! $belongsToTeam) {
                throw ValidationException::withMessages([
                    'team_id' => __('sport_integrations.errors.team_unavailable'),
                ]);
            }

            if ($route?->team_id && (int) $route->team_id !== (int) $data['team_id']) {
                throw ValidationException::withMessages([
                    'team_id' => __('sport_integrations.errors.route_team_mismatch'),
                ]);
            }
        }

        return $data;
    }

    private function normalizeSummary(mixed $summary): array
    {
        if (! is_array($summary)) {
            return [];
        }

        return collect($summary)
            ->only(self::SUMMARY_FIELDS)
            ->filter(fn ($value) => is_numeric($value))
            ->map(fn ($value, string $key) => $key === 'steps'
                ? (int) $value
                : round((float) $value, 3))
            ->all();
    }
}
