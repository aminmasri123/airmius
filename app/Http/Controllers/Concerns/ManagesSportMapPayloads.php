<?php

namespace App\Http\Controllers\Concerns;

use App\Models\SportPlace;
use App\Models\SportRoute;
use App\Models\SportRouteTrack;
use App\Services\MediaOptimizer;
use App\Models\User;
use App\Services\SportRouteMetricService;
use App\Services\SportRouteRoutingService;
use App\Support\UploadStorage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

trait ManagesSportMapPayloads
{
    private function sportTypes(): array
    {
        return config('sport_map.sport_types', []);
    }

    private function placeTypes(): array
    {
        return config('sport_map.place_types', []);
    }

    private function sportTypeKeys(): array
    {
        return collect($this->sportTypes())->pluck('key')->all();
    }

    private function placeTypeKeys(): array
    {
        return collect($this->placeTypes())->pluck('key')->all();
    }

    private function validateRouteData(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';
        $nullable = $partial ? 'sometimes' : 'nullable';

        return $request->validate([
            'title' => [$required, 'string', 'max:160'],
            'description' => [$nullable, 'string', 'max:4000'],
            'sport_id' => [$nullable, 'integer', 'exists:sports,id'],
            'sport_type' => [$nullable, Rule::in($this->sportTypeKeys())],
            'visibility' => [$nullable, Rule::in(['private', 'public', 'team'])],
            'team_id' => [$nullable, 'integer', 'exists:teams,id'],
            'status' => [$nullable, Rule::in(['planned', 'active', 'completed', 'archived'])],
            'difficulty' => [$nullable, Rule::in(['easy', 'moderate', 'hard', 'expert'])],
            'surface' => [$nullable, 'string', 'max:60'],
            'waypoints' => [$required, 'array', 'min:2', 'max:200'],
            'waypoints.*.name' => ['nullable', 'string', 'max:120'],
            'waypoints.*.latitude' => ['required_with:waypoints', 'numeric', 'between:-90,90'],
            'waypoints.*.longitude' => ['required_with:waypoints', 'numeric', 'between:-180,180'],
            'waypoints.*.elevation_m' => ['nullable', 'numeric', 'between:-500,9000'],
        ]);
    }

    private function validateRouteProposalData(Request $request): array
    {
        return $request->validate([
            'title' => ['nullable', 'string', 'max:160'],
            'sport_type' => ['nullable', Rule::in($this->sportTypeKeys())],
            'route_type' => ['nullable', Rule::in(['roundtrip', 'point_to_point'])],
            'target_mode' => ['nullable', Rule::in(['distance', 'duration'])],
            'distance_km' => ['nullable', 'numeric', 'min:1', 'max:80'],
            'duration_minutes' => ['nullable', 'numeric', 'min:10', 'max:600'],
            'surface' => ['nullable', Rule::in(['any', 'asphalt', 'firm', 'forest', 'gravel', 'trail'])],
            'environment' => ['nullable', Rule::in(['nature', 'forest', 'park', 'water'])],
            'elevation' => ['nullable', Rule::in(['flat', 'mixed', 'hilly'])],
            'difficulty' => ['nullable', Rule::in(['easy', 'moderate', 'hard'])],
            'low_traffic' => ['nullable', 'boolean'],
            'lit' => ['nullable', 'boolean'],
            'water_breaks' => ['nullable', 'boolean'],
            'include_places' => ['nullable', 'string', 'max:500'],
            'avoid_places' => ['nullable', 'string', 'max:500'],
            'variant_seed' => ['nullable', 'integer', 'min:0'],
            'start' => ['required_without:waypoints', 'array'],
            'start.name' => ['nullable', 'string', 'max:120'],
            'start.latitude' => ['required_without:waypoints', 'numeric', 'between:-90,90'],
            'start.longitude' => ['required_without:waypoints', 'numeric', 'between:-180,180'],
            'waypoints' => ['nullable', 'array', 'min:2', 'max:12'],
            'waypoints.*.name' => ['nullable', 'string', 'max:120'],
            'waypoints.*.latitude' => ['required_with:waypoints', 'numeric', 'between:-90,90'],
            'waypoints.*.longitude' => ['required_with:waypoints', 'numeric', 'between:-180,180'],
            'waypoints.*.elevation_m' => ['nullable', 'numeric', 'between:-500,9000'],
        ]);
    }

    private function validateTrackData(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';
        $nullable = $partial ? 'sometimes' : 'nullable';

        return $request->validate([
            'title' => [$required, 'string', 'max:160'],
            'sport_route_id' => [$nullable, 'integer', 'exists:sport_routes,id'],
            'sport_id' => [$nullable, 'integer', 'exists:sports,id'],
            'team_id' => [$nullable, 'integer', 'exists:teams,id'],
            'sport_type' => [$nullable, Rule::in($this->sportTypeKeys())],
            'status' => [$nullable, Rule::in(['recording', 'paused', 'completed', 'discarded'])],
            'started_at' => [$nullable, 'date'],
            'ended_at' => [$nullable, 'date'],
            'track_points' => [$required, 'array', 'min:1', 'max:5000'],
            'track_points.*.latitude' => ['required_with:track_points', 'numeric', 'between:-90,90'],
            'track_points.*.longitude' => ['required_with:track_points', 'numeric', 'between:-180,180'],
            'track_points.*.elevation_m' => ['nullable', 'numeric', 'between:-500,9000'],
            'track_points.*.recorded_at' => ['nullable', 'date'],
            'track_points.*.accuracy_m' => ['nullable', 'numeric', 'min:0', 'max:10000'],
        ]);
    }

    private function validateTrackPointData(Request $request): array
    {
        return $request->validate([
            'track_points' => ['required', 'array', 'min:1', 'max:1000'],
            'track_points.*.latitude' => ['required', 'numeric', 'between:-90,90'],
            'track_points.*.longitude' => ['required', 'numeric', 'between:-180,180'],
            'track_points.*.elevation_m' => ['nullable', 'numeric', 'between:-500,9000'],
            'track_points.*.recorded_at' => ['nullable', 'date'],
            'track_points.*.accuracy_m' => ['nullable', 'numeric', 'min:0', 'max:10000'],
        ]);
    }

    private function validatePlaceData(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';
        $nullable = $partial ? 'sometimes' : 'nullable';

        return $request->validate([
            'name' => [$required, 'string', 'max:160'],
            'type' => [$required, Rule::in($this->placeTypeKeys())],
            'description' => [$nullable, 'string', 'max:4000'],
            'sport_id' => [$nullable, 'integer', 'exists:sports,id'],
            'team_id' => [$nullable, 'integer', 'exists:teams,id'],
            'latitude' => [$required, 'numeric', 'between:-90,90'],
            'longitude' => [$required, 'numeric', 'between:-180,180'],
            'address' => [$nullable, 'string', 'max:255'],
            'city' => [$nullable, 'string', 'max:120'],
            'country_code' => [$nullable, 'string', 'size:2'],
            'visibility' => [$nullable, Rule::in(['private', 'public', 'team'])],
            'sport_types' => [$nullable, 'array', 'max:20'],
            'sport_types.*' => ['nullable', 'string', 'max:80'],
            'amenities' => [$nullable, 'array', 'max:30'],
            'amenities.*' => ['nullable', 'string', 'max:80'],
            'surfaces' => [$nullable, 'array', 'max:20'],
            'surfaces.*' => ['nullable', 'string', 'max:80'],
            'opening_hours' => [$nullable, 'string', 'max:255'],
            'gallery_images' => [$nullable, 'array', 'max:8'],
            'gallery_images.*' => ['nullable', 'url', 'max:2048'],
            'image_uploads' => [$nullable, 'array', 'max:6'],
            'image_uploads.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ]);
    }

    private function routePayload(User $user, array $data, SportRouteMetricService $metrics, ?SportRoute $route = null): array
    {
        $waypoints = array_key_exists('waypoints', $data)
            ? $metrics->normalizePoints($data['waypoints'])
            : ($route?->waypoints ?? []);
        $summary = app(SportRouteRoutingService::class)->summarizeRoute($waypoints, $data['sport_type'] ?? $route?->sport_type);
        $first = $waypoints[0] ?? null;
        $last = $waypoints[count($waypoints) - 1] ?? null;
        $visibility = $data['visibility'] ?? $route?->visibility ?? 'private';
        $teamId = $this->resolveTeamId($user, $data['team_id'] ?? $route?->team_id, $visibility);

        return [
            'user_id' => $route?->user_id ?? $user->id,
            'sport_id' => array_key_exists('sport_id', $data) ? $data['sport_id'] : $route?->sport_id,
            'team_id' => $teamId,
            'title' => $data['title'] ?? $route?->title,
            'description' => array_key_exists('description', $data) ? $data['description'] : $route?->description,
            'sport_type' => $data['sport_type'] ?? $route?->sport_type,
            'visibility' => $visibility,
            'status' => $data['status'] ?? $route?->status ?? 'planned',
            'difficulty' => array_key_exists('difficulty', $data) ? $data['difficulty'] : $route?->difficulty,
            'surface' => array_key_exists('surface', $data) ? $data['surface'] : $route?->surface,
            'start_latitude' => $first['latitude'] ?? null,
            'start_longitude' => $first['longitude'] ?? null,
            'end_latitude' => $last['latitude'] ?? null,
            'end_longitude' => $last['longitude'] ?? null,
            'start_name' => $first['name'] ?? null,
            'end_name' => $last['name'] ?? null,
            'distance_meters' => $summary['distance_meters'],
            'estimated_duration_seconds' => $summary['estimated_duration_seconds'],
            'elevation_gain_meters' => $summary['elevation_gain_meters'],
            'elevation_loss_meters' => $summary['elevation_loss_meters'],
            'waypoints' => $waypoints,
            'route_geometry' => $summary['geometry'],
            'navigation_cues' => $summary['navigation_cues'],
            'metrics' => array_filter([
                'bounds' => $summary['bounds'],
                'calculation' => $summary['calculation'] ?? 'airmius_haversine_estimate',
                'routing_provider' => $summary['routing_provider'] ?? 'local',
                'routing_profile' => $summary['routing_profile'] ?? null,
                'routing_status' => $summary['routing_status'] ?? 'estimated',
                'routing_error' => $summary['routing_error'] ?? null,
                'supports_offline_navigation' => true,
            ], fn ($value) => $value !== null && $value !== ''),
            'completed_at' => ($data['status'] ?? $route?->status) === 'completed' ? ($route?->completed_at ?? now()) : $route?->completed_at,
        ];
    }

    private function trackPayload(User $user, array $data, SportRouteMetricService $metrics, ?SportRouteTrack $track = null): array
    {
        $points = array_key_exists('track_points', $data)
            ? $metrics->normalizePoints($data['track_points'])
            : ($track?->track_points ?? []);
        $startedAt = isset($data['started_at']) ? Carbon::parse($data['started_at']) : $track?->started_at;
        $endedAt = isset($data['ended_at']) ? Carbon::parse($data['ended_at']) : $track?->ended_at;
        $summary = $metrics->summarize($points, $data['sport_type'] ?? $track?->sport_type, $startedAt, $endedAt);
        $route = $this->routeForTrack($user, $data['sport_route_id'] ?? $track?->sport_route_id);
        $visibility = $route?->visibility ?? 'private';
        $teamId = $this->resolveTeamId($user, $data['team_id'] ?? $route?->team_id ?? $track?->team_id, $visibility === 'team' ? 'team' : 'private');

        return [
            'user_id' => $track?->user_id ?? $user->id,
            'sport_route_id' => $route?->id,
            'sport_id' => array_key_exists('sport_id', $data) ? $data['sport_id'] : ($route?->sport_id ?? $track?->sport_id),
            'team_id' => $teamId,
            'title' => $data['title'] ?? $track?->title,
            'sport_type' => $data['sport_type'] ?? $route?->sport_type ?? $track?->sport_type,
            'status' => $data['status'] ?? $track?->status ?? 'recording',
            'source' => $track?->source ?? 'airmius',
            'started_at' => $startedAt ?? $this->timeFromPoint($points[0] ?? null) ?? now(),
            'ended_at' => ($data['status'] ?? $track?->status) === 'completed'
                ? ($endedAt ?? $this->timeFromPoint($points[count($points) - 1] ?? null) ?? now())
                : $endedAt,
            'distance_meters' => $summary['distance_meters'],
            'duration_seconds' => $summary['duration_seconds'],
            'elevation_gain_meters' => $summary['elevation_gain_meters'],
            'elevation_loss_meters' => $summary['elevation_loss_meters'],
            'average_speed_mps' => $summary['average_speed_mps'],
            'max_speed_mps' => $summary['max_speed_mps'],
            'track_points' => $points,
            'track_geometry' => $summary['geometry'],
            'metrics' => [
                'bounds' => $summary['bounds'],
                'point_count' => count($points),
                'calculation' => 'airmius_haversine_track',
            ],
        ];
    }

    private function placePayload(User $user, array $data, ?SportPlace $place = null): array
    {
        $visibility = $data['visibility'] ?? $place?->visibility ?? 'public';
        $teamId = $this->resolveTeamId($user, $data['team_id'] ?? $place?->team_id, $visibility);

        return [
            'user_id' => $place?->user_id ?? $user->id,
            'sport_id' => array_key_exists('sport_id', $data) ? $data['sport_id'] : $place?->sport_id,
            'team_id' => $teamId,
            'name' => $data['name'] ?? $place?->name,
            'type' => $data['type'] ?? $place?->type,
            'description' => array_key_exists('description', $data) ? $data['description'] : $place?->description,
            'latitude' => array_key_exists('latitude', $data) ? round((float) $data['latitude'], 7) : $place?->latitude,
            'longitude' => array_key_exists('longitude', $data) ? round((float) $data['longitude'], 7) : $place?->longitude,
            'address' => array_key_exists('address', $data) ? $data['address'] : $place?->address,
            'city' => array_key_exists('city', $data) ? $data['city'] : $place?->city,
            'country_code' => isset($data['country_code']) ? strtoupper($data['country_code']) : $place?->country_code,
            'visibility' => $visibility,
            'status' => $place?->status ?? 'active',
            'sport_types' => array_key_exists('sport_types', $data) ? $this->cleanList($data['sport_types'] ?? []) : ($place?->sport_types ?? []),
            'amenities' => array_key_exists('amenities', $data) ? $this->cleanList($data['amenities'] ?? []) : ($place?->amenities ?? []),
            'surfaces' => array_key_exists('surfaces', $data) ? $this->cleanList($data['surfaces'] ?? []) : ($place?->surfaces ?? []),
            'opening_hours' => array_key_exists('opening_hours', $data) ? $data['opening_hours'] : $place?->opening_hours,
            'gallery_images' => $this->sportPlaceGalleryImages($data, $place),
            'metrics' => [
                'submitted_from' => request()->expectsJson() ? 'api' : 'web',
            ],
        ];
    }

    private function sportPlaceGalleryImages(array $data, ?SportPlace $place = null): array
    {
        $images = collect($place?->gallery_images ?? []);

        if (array_key_exists('gallery_images', $data)) {
            $images = collect($data['gallery_images'] ?? []);
        }

        foreach (($data['image_uploads'] ?? []) as $upload) {
            if (! $upload) {
                continue;
            }

            $stored = app(MediaOptimizer::class)->store($upload, 'sport-places');
            $url = UploadStorage::url($stored['path'] ?? null);

            if ($url) {
                $images->push($url);
            }
        }

        return $images
            ->map(fn ($image) => trim((string) $image))
            ->filter()
            ->unique()
            ->take(8)
            ->values()
            ->all();
    }

    private function filterNearbyPlaces(Builder $query, Request $request, SportRouteMetricService $metrics): Builder
    {
        $nearby = $this->nearbyParameters($request);

        if (! $nearby) {
            return $query;
        }

        $latitude = $nearby['latitude'];
        $longitude = $nearby['longitude'];
        $radiusKm = $nearby['radius_km'];
        $latitudeDelta = $radiusKm / 111;
        $longitudeDelta = $radiusKm / max(cos(deg2rad($latitude)) * 111, 0.1);

        return $query
            ->whereBetween('latitude', [$latitude - $latitudeDelta, $latitude + $latitudeDelta])
            ->whereBetween('longitude', [$longitude - $longitudeDelta, $longitude + $longitudeDelta]);
    }

    private function hasNearbyCoordinates(Request $request): bool
    {
        return $this->nearbyParameters($request) !== null;
    }

    private function decoratePlacesWithDistance(Collection $places, Request $request, SportRouteMetricService $metrics, bool $filterByRadius = false): Collection
    {
        $nearby = $this->nearbyParameters($request);

        if (! $nearby) {
            return $places;
        }

        $radiusMeters = (int) round($nearby['radius_km'] * 1000);

        return $places
            ->map(function (SportPlace $place) use ($nearby, $metrics) {
                $place->distance_meters = $metrics->nearbyDistanceMeters(
                    $nearby['latitude'],
                    $nearby['longitude'],
                    $place->latitude,
                    $place->longitude,
                );

                return $place;
            })
            ->when($filterByRadius, fn (Collection $collection) => $collection->filter(fn (SportPlace $place) => $place->distance_meters <= $radiusMeters))
            ->sortBy('distance_meters')
            ->values();
    }

    private function nearbyParameters(Request $request): ?array
    {
        if (! $request->filled(['latitude', 'longitude'])) {
            return null;
        }

        if (! is_numeric($request->input('latitude')) || ! is_numeric($request->input('longitude'))) {
            return null;
        }

        $latitude = (float) $request->input('latitude');
        $longitude = (float) $request->input('longitude');

        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            return null;
        }

        return [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'radius_km' => min(max((float) $request->input('radius_km', 25), 1), 250),
        ];
    }

    private function resolveTeamId(User $user, mixed $teamId, string $visibility): ?int
    {
        if ($visibility !== 'team') {
            return null;
        }

        abort_unless($teamId, 422, __('sport_map.team_visibility_requires_team'));

        $teamId = (int) $teamId;

        abort_unless($user->teams()->where('teams.id', $teamId)->exists(), 403);

        return $teamId;
    }

    private function routeForTrack(User $user, mixed $routeId): ?SportRoute
    {
        if (! $routeId) {
            return null;
        }

        return SportRoute::query()
            ->visibleTo($user)
            ->whereKey($routeId)
            ->firstOrFail();
    }

    private function trackPayloadWithAppendedPoints(SportRouteTrack $track, array $points, SportRouteMetricService $metrics): array
    {
        $merged = array_merge($track->track_points ?? [], $metrics->normalizePoints($points));

        return $this->trackPayload($track->creator, [
            'title' => $track->title,
            'sport_route_id' => $track->sport_route_id,
            'sport_id' => $track->sport_id,
            'team_id' => $track->team_id,
            'sport_type' => $track->sport_type,
            'status' => $track->status,
            'started_at' => $track->started_at?->toIso8601String(),
            'ended_at' => $track->ended_at?->toIso8601String(),
            'track_points' => $merged,
        ], $metrics, $track);
    }

    private function cleanList(array $values): array
    {
        return collect($values)
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function timeFromPoint(?array $point): ?Carbon
    {
        return isset($point['recorded_at']) ? Carbon::parse($point['recorded_at']) : null;
    }
}
