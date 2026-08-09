<?php

namespace App\Services\Training;

use App\Models\SportRoute;
use App\Models\SportRouteTrack;
use App\Models\TrainingPlanItem;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class TrainingRouteLinkService
{
    /**
     * A bounded, coordinate-free route list for training forms.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function selectableRoutes(User $user, int $limit = 50): Collection
    {
        return SportRoute::query()
            ->visibleTo($user)
            ->latest('updated_at')
            ->limit(min(max($limit, 1), 50))
            ->get($this->routeColumns())
            ->map(fn (SportRoute $route) => $this->routeSummary($route));
    }

    /**
     * Only the athlete's own completed traces may be attached to a log.
     * This keeps raw location histories from being re-purposed by trainers.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function selectableTracks(User $user, int $limit = 30): Collection
    {
        return SportRouteTrack::query()
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->latest('ended_at')
            ->latest('id')
            ->limit(min(max($limit, 1), 30))
            ->get($this->trackColumns())
            ->map(fn (SportRouteTrack $track) => $this->trackSummary($track));
    }

    public function resolvePlanRoute(User $actor, mixed $routeId): ?SportRoute
    {
        return $this->resolveVisibleRoute($actor, $routeId, __('server.training.route_not_visible'));
    }

    public function resolveVisibleRoute(User $actor, mixed $routeId, string $message): ?SportRoute
    {
        if (! filled($routeId)) {
            return null;
        }

        $route = SportRoute::query()
            ->visibleTo($actor)
            ->whereKey((int) $routeId)
            ->first();

        if (! $route) {
            throw ValidationException::withMessages([
                'sport_route_id' => $message,
            ]);
        }

        return $route;
    }

    /**
     * @return array{sport_route_id: int|null, sport_route_track_id: int|null}
     */
    public function resolveLogLinks(User $actor, int $athleteId, array $data, ?TrainingPlanItem $planItem = null): array
    {
        $routeId = filled($data['sport_route_id'] ?? null)
            ? (int) $data['sport_route_id']
            : $planItem?->sport_route_id;
        $trackId = filled($data['sport_route_track_id'] ?? null)
            ? (int) $data['sport_route_track_id']
            : null;

        $route = $this->resolvePlanRoute($actor, $routeId);
        $track = null;

        if ($trackId) {
            if ((int) $actor->id !== $athleteId) {
                throw ValidationException::withMessages([
                    'sport_route_track_id' => __('server.training.track_owner_only'),
                ]);
            }

            $track = SportRouteTrack::query()
                ->whereKey($trackId)
                ->where('user_id', $actor->id)
                ->where('status', 'completed')
                ->first();

            if (! $track) {
                throw ValidationException::withMessages([
                    'sport_route_track_id' => __('server.training.track_not_available'),
                ]);
            }

            if ($route && $track->sport_route_id && (int) $track->sport_route_id !== (int) $route->id) {
                throw ValidationException::withMessages([
                    'sport_route_track_id' => __('server.training.track_route_mismatch'),
                ]);
            }

            if (! $route && $track->sport_route_id) {
                $route = $this->resolvePlanRoute($actor, $track->sport_route_id);
            }
        }

        return [
            'sport_route_id' => $route?->id,
            'sport_route_track_id' => $track?->id,
        ];
    }

    /** @return array<string, mixed> */
    public function routeSummary(SportRoute $route): array
    {
        return [
            'id' => $route->id,
            'title' => $route->title,
            'sport_type' => $route->sport_type,
            'visibility' => $route->visibility,
            'status' => $route->status,
            'difficulty' => $route->difficulty,
            'surface' => $route->surface,
            'start_name' => $route->start_name,
            'end_name' => $route->end_name,
            'distance_meters' => $route->distance_meters,
            'estimated_duration_seconds' => $route->estimated_duration_seconds,
            'elevation_gain_meters' => $route->elevation_gain_meters,
            'navigation_url' => route('auth.sport-map.index', ['route_id' => $route->id]),
        ];
    }

    /** @return array<string, mixed> */
    public function trackSummary(SportRouteTrack $track): array
    {
        return [
            'id' => $track->id,
            'sport_route_id' => $track->sport_route_id,
            'title' => $track->title,
            'sport_type' => $track->sport_type,
            'status' => $track->status,
            'source' => $track->source,
            'started_at' => $track->started_at?->toIso8601String(),
            'ended_at' => $track->ended_at?->toIso8601String(),
            'distance_meters' => $track->distance_meters,
            'duration_seconds' => $track->duration_seconds,
            'elevation_gain_meters' => $track->elevation_gain_meters,
        ];
    }

    /** @return list<string> */
    public function routeColumns(): array
    {
        return [
            'id', 'user_id', 'title', 'sport_type', 'visibility', 'status',
            'difficulty', 'surface', 'distance_meters',
            'start_name', 'end_name',
            'estimated_duration_seconds', 'elevation_gain_meters', 'updated_at',
        ];
    }

    /** @return list<string> */
    public function trackColumns(): array
    {
        return [
            'id', 'user_id', 'sport_route_id', 'title', 'sport_type', 'status',
            'source', 'started_at', 'ended_at', 'distance_meters',
            'duration_seconds', 'elevation_gain_meters',
        ];
    }
}
