<?php

namespace App\Services;

use App\Models\ConnectedSportAccount;
use App\Models\ConnectedSportActivity;
use App\Models\SportRouteTrack;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class SportIntegrationActivityImportService
{
    public const PROVIDERS = [
        'apple_health',
        'google_fit',
        'garmin',
        'strava',
    ];

    public function __construct(private readonly SportRouteMetricService $metrics)
    {
    }

    public function import(User $user, array $data): array
    {
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
                'status' => $provider === 'garmin' ? 'partner_import' : 'connected',
                'scopes' => $this->defaultScopes($provider),
                'sync_summary' => [
                    'message' => __('Native Aktivitäten können importiert werden.'),
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
                    'provider_user_id' => $data['provider_user_id'] ?? null,
                    'track_id' => $track?->id,
                    'sample_count' => count($samples),
                    'imported_at' => now()->toIso8601String(),
                    'raw_summary' => $data['summary'] ?? [],
                ],
            ],
        );

        $account->forceFill([
            'status' => 'connected',
            'last_synced_at' => now(),
            'sync_summary' => [
                'message' => __('Aktivität importiert.'),
                'last_provider' => $provider,
                'last_activity_id' => $externalId,
                'created_track' => $track !== null,
            ],
        ])->save();

        return [
            'activity' => $activity,
            'track' => $track,
            'account' => $account,
        ];
    }

    private function storeTrack(User $user, string $provider, string $externalId, array $data, array $samples, Carbon $startedAt, ?Carbon $endedAt): SportRouteTrack
    {
        $summary = $this->metrics->summarize($samples, $data['activity_type'] ?? null, $startedAt, $endedAt);
        $title = $data['title'] ?? $this->fallbackTitle($provider, $data['activity_type'] ?? null);

        return SportRouteTrack::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'source' => $provider,
                'title' => $title,
                'started_at' => $startedAt,
            ],
            [
                'sport_route_id' => $data['sport_route_id'] ?? null,
                'sport_id' => $data['sport_id'] ?? null,
                'team_id' => $data['team_id'] ?? null,
                'sport_type' => $data['activity_type'] ?? null,
                'status' => 'completed',
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
                    'route_intelligence' => $summary['route_intelligence'],
                    'gpx_export_ready' => true,
                ],
            ],
        );
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
        return [
            'apple_health' => 'Apple Health',
            'google_fit' => 'Google Fit',
            'garmin' => 'Garmin',
            'strava' => 'Strava',
        ][$provider] ?? Str::headline($provider);
    }

    private function defaultScopes(string $provider): array
    {
        return [
            'apple_health' => ['workouts', 'routes', 'heart_rate', 'energy_burned'],
            'google_fit' => ['activity', 'location', 'calories'],
            'garmin' => ['activities', 'wellness'],
            'strava' => ['read', 'activity:read_all'],
        ][$provider] ?? [];
    }
}

