<?php

namespace App\Services;

use App\Models\ConnectedSportAccount;
use App\Models\ConnectedSportActivity;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SportIntegrationSyncService
{
    public function sync(ConnectedSportAccount $account): array
    {
        return match ($account->provider) {
            'google_fit' => $this->syncGoogleFit($account),
            'strava' => $this->syncStrava($account),
            default => $this->markUnsupported($account),
        };
    }

    private function markUnsupported(ConnectedSportAccount $account): array
    {
        $account->update([
            'last_synced_at' => now(),
            'sync_summary' => [
                'message' => 'Synchronisation vorgemerkt. Wir aktivieren den Anbieter, sobald die Freigabe bereitsteht.',
            ],
        ]);

        return ['ok' => true, 'message' => 'Synchronisationsstatus aktualisiert.'];
    }

    private function syncStrava(ConnectedSportAccount $account): array
    {
        $token = $this->validStravaAccessToken($account);

        if (! $token) {
            $account->update([
                'status' => 'error',
                'sync_summary' => [
                    'message' => 'Strava Token fehlt oder konnte nicht erneuert werden. Bitte Verbindung entfernen und neu verbinden.',
                ],
            ]);

            return ['ok' => false, 'message' => 'Strava Token konnte nicht erneuert werden.'];
        }

        $after = $account->last_synced_at
            ? $account->last_synced_at->copy()->subDays(7)->timestamp
            : now()->subDays(90)->timestamp;

        $response = Http::withToken($token)->get('https://www.strava.com/api/v3/athlete/activities', [
            'after' => $after,
            'per_page' => 100,
            'page' => 1,
        ]);

        if ($response->failed()) {
            $stravaError = $response->json('message')
                ?: $response->json('errors.0.code')
                ?: $response->body();

            $account->update([
                'last_synced_at' => now(),
                'sync_summary' => [
                    'message' => 'Strava Sync fehlgeschlagen: HTTP '.$response->status().'.',
                    'strava_status' => $response->status(),
                    'strava_error' => Str::limit((string) $stravaError, 500),
                ],
            ]);

            return ['ok' => false, 'message' => 'Strava Sync konnte nicht abgeschlossen werden.'];
        }

        $activities = $response->json();
        $imported = $this->storeStravaActivities($account, is_array($activities) ? $activities : []);

        $message = $imported > 0
            ? $imported.' Strava Aktivitaeten importiert oder aktualisiert.'
            : 'Strava hat fuer diesen Zeitraum keine neuen Aktivitaeten geliefert.';

        $account->update([
            'status' => 'connected',
            'last_synced_at' => now(),
            'sync_summary' => [
                'message' => $message,
                'imported' => $imported,
                'from' => Carbon::createFromTimestamp($after)->toDateString(),
                'to' => now()->toDateString(),
            ],
        ]);

        return ['ok' => true, 'message' => $message, 'imported' => $imported];
    }

    private function syncGoogleFit(ConnectedSportAccount $account): array
    {
        $token = $this->validGoogleFitAccessToken($account);

        if (! $token) {
            $account->update([
                'status' => 'error',
                'sync_summary' => [
                    'message' => 'Google Fit Token fehlt oder konnte nicht erneuert werden. Bitte Verbindung entfernen und neu verbinden.',
                ],
            ]);

            return ['ok' => false, 'message' => 'Google Fit Token konnte nicht erneuert werden.'];
        }

        $start = $account->last_synced_at
            ? $account->last_synced_at->copy()->subDays(2)->startOfDay()
            : now()->subDays(30)->startOfDay();
        $end = now()->endOfDay();

        $response = Http::withToken($token)->post('https://www.googleapis.com/fitness/v1/users/me/dataset:aggregate', [
            'aggregateBy' => [
                ['dataTypeName' => 'com.google.activity.segment'],
                ['dataTypeName' => 'com.google.active_minutes'],
                ['dataTypeName' => 'com.google.distance.delta'],
                ['dataTypeName' => 'com.google.calories.expended'],
            ],
            'bucketByTime' => ['durationMillis' => 86400000],
            'startTimeMillis' => $start->getTimestampMs(),
            'endTimeMillis' => $end->getTimestampMs(),
        ]);

        if ($response->failed()) {
            $googleError = $response->json('error.message')
                ?: $response->json('error_description')
                ?: $response->body();

            $account->update([
                'last_synced_at' => now(),
                'sync_summary' => [
                    'message' => str_contains((string) $googleError, 'insufficient authentication scopes')
                        ? 'Google Fit braucht eine neue Zustimmung fuer Distanzdaten. Bitte Verbindung entfernen und neu verbinden.'
                        : 'Google Fit Sync fehlgeschlagen: HTTP '.$response->status().'.',
                    'google_status' => $response->status(),
                    'google_error' => Str::limit((string) $googleError, 500),
                ],
            ]);

            return ['ok' => false, 'message' => 'Google Fit Sync konnte nicht abgeschlossen werden.'];
        }

        $imported = $this->storeGoogleFitBuckets($account, $response->json('bucket', []));
        $bucketCount = count($response->json('bucket', []));

        $message = $imported > 0
            ? $imported.' Google Fit Tagesaktivitaeten importiert oder aktualisiert.'
            : ($bucketCount > 0
                ? 'Google Fit hat '.$bucketCount.' Tagesbereiche geliefert, aber ohne Aktivitaetswerte.'
                : 'Google Fit hat fuer diesen Zeitraum keine Tagesbereiche geliefert.');

        $account->update([
            'status' => 'connected',
            'last_synced_at' => now(),
            'sync_summary' => [
                'message' => $message,
                'imported' => $imported,
                'bucket_count' => $bucketCount,
                'from' => $start->toDateString(),
                'to' => $end->toDateString(),
            ],
        ]);

        return ['ok' => true, 'message' => $message, 'imported' => $imported];
    }

    private function validGoogleFitAccessToken(ConnectedSportAccount $account): ?string
    {
        if ($account->access_token && (! $account->token_expires_at || $account->token_expires_at->isFuture())) {
            return $account->access_token;
        }

        if (! $account->refresh_token) {
            return null;
        }

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => config('services.google_fit.client_id'),
            'client_secret' => config('services.google_fit.client_secret'),
            'refresh_token' => $account->refresh_token,
            'grant_type' => 'refresh_token',
        ]);

        if ($response->failed()) {
            return null;
        }

        $token = $response->json();
        $account->forceFill([
            'access_token' => $token['access_token'] ?? null,
            'token_expires_at' => isset($token['expires_in']) ? now()->addSeconds((int) $token['expires_in']) : null,
        ])->save();

        return $token['access_token'] ?? null;
    }

    private function validStravaAccessToken(ConnectedSportAccount $account): ?string
    {
        if ($account->access_token && (! $account->token_expires_at || $account->token_expires_at->isFuture())) {
            return $account->access_token;
        }

        if (! $account->refresh_token) {
            return null;
        }

        $response = Http::asForm()->post('https://www.strava.com/oauth/token', [
            'client_id' => config('services.strava.client_id'),
            'client_secret' => config('services.strava.client_secret'),
            'refresh_token' => $account->refresh_token,
            'grant_type' => 'refresh_token',
        ]);

        if ($response->failed()) {
            return null;
        }

        $token = $response->json();
        $account->forceFill([
            'access_token' => $token['access_token'] ?? null,
            'refresh_token' => $token['refresh_token'] ?? $account->refresh_token,
            'token_expires_at' => isset($token['expires_at'])
                ? now()->setTimestamp((int) $token['expires_at'])
                : (isset($token['expires_in']) ? now()->addSeconds((int) $token['expires_in']) : null),
        ])->save();

        return $token['access_token'] ?? null;
    }

    private function storeStravaActivities(ConnectedSportAccount $account, array $activities): int
    {
        $count = 0;

        foreach ($activities as $activity) {
            if (! isset($activity['id'])) {
                continue;
            }

            $startedAt = isset($activity['start_date'])
                ? Carbon::parse($activity['start_date'])
                : null;

            ConnectedSportActivity::query()->updateOrCreate(
                ['user_id' => $account->user_id, 'provider' => 'strava', 'provider_activity_id' => (string) $activity['id']],
                [
                    'connected_sport_account_id' => $account->id,
                    'activity_type' => $this->stravaActivityLabel($activity['sport_type'] ?? $activity['type'] ?? null),
                    'title' => $activity['name'] ?? $this->stravaActivityLabel($activity['sport_type'] ?? $activity['type'] ?? null),
                    'started_at' => $startedAt,
                    'duration_seconds' => (int) ($activity['moving_time'] ?? $activity['elapsed_time'] ?? 0),
                    'distance_meters' => (int) round((float) ($activity['distance'] ?? 0)),
                    'calories' => isset($activity['calories']) ? (int) round((float) $activity['calories']) : null,
                    'metrics' => [
                        'source_kind' => 'activity',
                        'strava_type' => $activity['type'] ?? null,
                        'strava_sport_type' => $activity['sport_type'] ?? null,
                        'elapsed_time' => $activity['elapsed_time'] ?? null,
                        'moving_time' => $activity['moving_time'] ?? null,
                        'elevation_gain_meters' => $activity['total_elevation_gain'] ?? null,
                        'average_speed' => $activity['average_speed'] ?? null,
                        'max_speed' => $activity['max_speed'] ?? null,
                    ],
                ],
            );

            $count++;
        }

        return $count;
    }

    private function storeGoogleFitBuckets(ConnectedSportAccount $account, array $buckets): int
    {
        $count = 0;

        foreach ($buckets as $bucket) {
            $startMillis = isset($bucket['startTimeMillis']) ? (int) $bucket['startTimeMillis'] : null;
            if (! $startMillis) {
                continue;
            }

            $metrics = $this->googleFitBucketMetrics($bucket);
            if (($metrics['duration_seconds'] ?? 0) <= 0 && ($metrics['distance_meters'] ?? 0) <= 0 && ($metrics['calories'] ?? 0) <= 0) {
                continue;
            }

            $startedAt = Carbon::createFromTimestampMs($startMillis);
            $providerActivityId = 'google-fit:daily:'.$startedAt->toDateString();

            ConnectedSportActivity::query()->updateOrCreate(
                ['user_id' => $account->user_id, 'provider' => 'google_fit', 'provider_activity_id' => $providerActivityId],
                [
                    'connected_sport_account_id' => $account->id,
                    'activity_type' => $metrics['activity_type'],
                    'title' => $metrics['title'],
                    'started_at' => $startedAt,
                    'duration_seconds' => $metrics['duration_seconds'],
                    'distance_meters' => $metrics['distance_meters'],
                    'calories' => $metrics['calories'],
                    'metrics' => $metrics,
                ],
            );

            $count++;
        }

        return $count;
    }

    private function googleFitBucketMetrics(array $bucket): array
    {
        $durationSeconds = 0;
        $activeMinutes = 0;
        $distanceMeters = 0;
        $calories = 0;
        $activityCodes = [];
        $earliestStartNanos = null;

        foreach ($bucket['dataset'] ?? [] as $dataset) {
            foreach ($dataset['point'] ?? [] as $point) {
                $values = $point['value'] ?? [];
                $dataType = $point['dataTypeName'] ?? '';

                if ($dataType === 'com.google.activity.segment') {
                    $activityCode = $values[0]['intVal'] ?? null;
                    if ($activityCode !== null) {
                        $activityCodes[(int) $activityCode] = ($activityCodes[(int) $activityCode] ?? 0) + 1;
                    }

                    $startNanos = isset($point['startTimeNanos']) ? (int) $point['startTimeNanos'] : null;
                    $endNanos = isset($point['endTimeNanos']) ? (int) $point['endTimeNanos'] : null;
                    if ($startNanos && $endNanos && $endNanos > $startNanos) {
                        $earliestStartNanos = $earliestStartNanos === null ? $startNanos : min($earliestStartNanos, $startNanos);
                        $durationSeconds += (int) round(($endNanos - $startNanos) / 1000000000);
                    }
                }

                if ($dataType === 'com.google.active_minutes') {
                    $activeMinutes += (int) ($values[0]['intVal'] ?? 0);
                }

                if ($dataType === 'com.google.distance.delta') {
                    $distanceMeters += (float) ($values[0]['fpVal'] ?? 0);
                }

                if ($dataType === 'com.google.calories.expended') {
                    $calories += (float) ($values[0]['fpVal'] ?? 0);
                }
            }
        }

        if ($durationSeconds <= 0 && $activeMinutes > 0) {
            $durationSeconds = $activeMinutes * 60;
        }

        arsort($activityCodes);
        $activityCode = array_key_first($activityCodes);
        $activityType = $this->googleFitActivityLabel($activityCode);

        return [
            'activity_code' => $activityCode,
            'activity_codes' => $activityCodes,
            'activity_type' => $activityType,
            'title' => $activityType === 'Aktivitaet' ? 'Google Fit Tagesaktivitaet' : $activityType,
            'source_kind' => 'daily_summary',
            'earliest_start_time' => $earliestStartNanos
                ? Carbon::createFromTimestampMs((int) floor($earliestStartNanos / 1000000))->format('H:i')
                : null,
            'active_minutes' => $activeMinutes,
            'duration_seconds' => $durationSeconds,
            'distance_meters' => (int) round($distanceMeters),
            'calories' => (int) round($calories),
        ];
    }

    private function googleFitActivityLabel(?int $code): string
    {
        return [
            7 => 'Gehen',
            8 => 'Laufen',
            9 => 'Joggen',
            58 => 'Radfahren',
            82 => 'Schwimmen',
            97 => 'Workout',
        ][$code] ?? 'Aktivitaet';
    }

    private function stravaActivityLabel(?string $type): string
    {
        return [
            'Run' => 'Laufen',
            'TrailRun' => 'Trailrun',
            'Ride' => 'Radfahren',
            'MountainBikeRide' => 'Mountainbike',
            'GravelRide' => 'Gravel',
            'VirtualRide' => 'Virtuelles Radfahren',
            'Swim' => 'Schwimmen',
            'Walk' => 'Gehen',
            'Hike' => 'Wandern',
            'Workout' => 'Workout',
            'WeightTraining' => 'Krafttraining',
            'Yoga' => 'Yoga',
            'Soccer' => 'Fußball',
            'Basketball' => 'Basketball',
            'Tennis' => 'Tennis',
            'Golf' => 'Golf',
            'AlpineSki' => 'Ski Alpin',
            'NordicSki' => 'Langlauf',
            'Snowboard' => 'Snowboard',
            'Rowing' => 'Rudern',
            'Kayaking' => 'Kajak',
            'StandUpPaddling' => 'SUP',
        ][$type] ?? ($type ?: 'Aktivitaet');
    }
}
