<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ManagesSportMapPayloads;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SportPlaceResource;
use App\Http\Resources\Api\V1\SportRouteResource;
use App\Http\Resources\Api\V1\SportTrackResource;
use App\Models\SportPlace;
use App\Models\SportRoute;
use App\Models\SportRouteTrack;
use App\Services\SportMapEntitlementService;
use App\Services\SportRouteMetricService;
use App\Services\SportRouteRoutingService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class SportMapController extends Controller
{
    use ManagesSportMapPayloads;

    public function routes(Request $request)
    {
        $routes = SportRoute::query()
            ->visibleTo($request->user())
            ->with(['creator:id,name,first_name,last_name,email,profile_photo_path', 'sport:id,name,slug,category', 'team:id,name'])
            ->withCount('tracks')
            ->when($request->filled('sport_type'), fn ($query) => $query->where('sport_type', $request->input('sport_type')))
            ->when($request->filled('visibility'), fn ($query) => $query->where('visibility', $request->input('visibility')))
            ->latest('updated_at')
            ->paginate($this->perPage($request));

        return SportRouteResource::collection($routes);
    }

    public function storeRoute(Request $request, SportRouteMetricService $metrics)
    {
        $route = SportRoute::query()->create(
            $this->routePayload($request->user(), $this->validateRouteData($request), $metrics)
        );

        return (new SportRouteResource($route->load(['creator', 'sport', 'team'])->loadCount('tracks')))
            ->response()
            ->setStatusCode(201);
    }

    public function generateRouteProposal(Request $request, SportRouteRoutingService $routing, SportMapEntitlementService $entitlements)
    {
        $entitlements->ensureCanGenerateRoute($request->user());

        return response()->json([
            'data' => $routing->generateProposal($this->validateRouteProposalData($request)),
        ]);
    }

    public function showRoute(Request $request, SportRoute $sportRoute)
    {
        abort_unless(SportRoute::query()->visibleTo($request->user())->whereKey($sportRoute->id)->exists(), 404);

        return new SportRouteResource($sportRoute->load(['creator', 'sport', 'team'])->loadCount('tracks'));
    }

    public function updateRoute(Request $request, SportRoute $sportRoute, SportRouteMetricService $metrics)
    {
        abort_unless((int) $sportRoute->user_id === (int) $request->user()->id, 403);

        $sportRoute->update(
            $this->routePayload($request->user(), $this->validateRouteData($request, true), $metrics, $sportRoute)
        );

        return new SportRouteResource($sportRoute->fresh(['creator', 'sport', 'team'])->loadCount('tracks'));
    }

    public function duplicateRoute(Request $request, SportRoute $sportRoute)
    {
        abort_unless(SportRoute::query()->visibleTo($request->user())->whereKey($sportRoute->id)->exists(), 404);

        $copy = $sportRoute->replicate([
            'user_id',
            'visibility',
            'team_id',
            'status',
            'completed_at',
        ]);
        $copy->user_id = $request->user()->id;
        $copy->visibility = 'private';
        $copy->team_id = null;
        $copy->status = 'planned';
        $copy->completed_at = null;
        $copy->title = trim($sportRoute->title.' '.__('sport_map.copy_suffix'));
        $copy->save();

        return (new SportRouteResource($copy->load(['creator', 'sport', 'team'])->loadCount('tracks')))
            ->response()
            ->setStatusCode(201);
    }

    public function destroyRoute(Request $request, SportRoute $sportRoute)
    {
        abort_unless((int) $sportRoute->user_id === (int) $request->user()->id, 403);

        $sportRoute->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function tracks(Request $request)
    {
        $tracks = SportRouteTrack::query()
            ->visibleTo($request->user())
            ->with(['route:id,title,distance_meters', 'sport:id,name,slug,category'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->latest('started_at')
            ->latest('id')
            ->paginate($this->perPage($request));

        return SportTrackResource::collection($tracks);
    }

    public function storeTrack(Request $request, SportRouteMetricService $metrics)
    {
        $track = SportRouteTrack::query()->create(
            $this->trackPayload($request->user(), $this->validateTrackData($request), $metrics)
        );

        return (new SportTrackResource($track->load(['route', 'sport'])))
            ->response()
            ->setStatusCode(201);
    }

    public function updateTrack(Request $request, SportRouteTrack $sportRouteTrack, SportRouteMetricService $metrics)
    {
        abort_unless((int) $sportRouteTrack->user_id === (int) $request->user()->id, 403);

        $sportRouteTrack->update(
            $this->trackPayload($request->user(), $this->validateTrackData($request, true), $metrics, $sportRouteTrack)
        );

        return new SportTrackResource($sportRouteTrack->fresh(['route', 'sport']));
    }

    public function appendTrackPoints(Request $request, SportRouteTrack $sportRouteTrack, SportRouteMetricService $metrics)
    {
        abort_unless((int) $sportRouteTrack->user_id === (int) $request->user()->id, 403);
        abort_unless(in_array($sportRouteTrack->status, ['recording', 'paused'], true), 422);

        $data = $this->validateTrackPointData($request);
        $sportRouteTrack->loadMissing('creator');
        $sportRouteTrack->update($this->trackPayloadWithAppendedPoints($sportRouteTrack, $data['track_points'], $metrics));

        return new SportTrackResource($sportRouteTrack->fresh(['route', 'sport']));
    }

    public function completeTrack(Request $request, SportRouteTrack $sportRouteTrack, SportRouteMetricService $metrics)
    {
        abort_unless((int) $sportRouteTrack->user_id === (int) $request->user()->id, 403);

        $completion = $request->validate([
            'active_duration_seconds' => ['nullable', 'integer', 'min:0', 'max:604800'],
        ]);

        $sportRouteTrack->update(
            $this->trackPayload($request->user(), [
                'title' => $sportRouteTrack->title,
                'sport_route_id' => $sportRouteTrack->sport_route_id,
                'sport_id' => $sportRouteTrack->sport_id,
                'team_id' => $sportRouteTrack->team_id,
                'sport_type' => $sportRouteTrack->sport_type,
                'status' => 'completed',
                'started_at' => $sportRouteTrack->started_at?->toIso8601String(),
                'ended_at' => now()->toIso8601String(),
                'track_points' => $sportRouteTrack->track_points ?? [],
                'active_duration_seconds' => $completion['active_duration_seconds'] ?? null,
            ], $metrics, $sportRouteTrack)
        );

        return new SportTrackResource($sportRouteTrack->fresh(['route', 'sport']));
    }

    public function destroyTrack(Request $request, SportRouteTrack $sportRouteTrack)
    {
        abort_unless((int) $sportRouteTrack->user_id === (int) $request->user()->id, 403);

        $sportRouteTrack->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function places(Request $request, SportRouteMetricService $metrics)
    {
        $placesQuery = SportPlace::query()
            ->visibleTo($request->user())
            ->where('status', 'active')
            ->with(['creator:id,name,first_name,last_name,email,profile_photo_path', 'sport:id,name,slug,category'])
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->input('type')))
            ->when($request->filled('sport_type'), fn ($query) => $query->whereJsonContains('sport_types', $request->input('sport_type')));

        $this->filterNearbyPlaces($placesQuery, $request, $metrics);

        if ($this->hasNearbyCoordinates($request)) {
            $perPage = $this->perPage($request);
            $page = max((int) $request->integer('page', 1), 1);
            $places = $this->decoratePlacesWithDistance(
                $placesQuery->latest('updated_at')->limit(500)->get(),
                $request,
                $metrics,
                true,
            );
            $paginator = new LengthAwarePaginator(
                $places->forPage($page, $perPage)->values(),
                $places->count(),
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()],
            );

            return SportPlaceResource::collection($paginator);
        }

        $places = $placesQuery
            ->latest('updated_at')
            ->paginate($this->perPage($request));

        return SportPlaceResource::collection($places);
    }

    public function storePlace(Request $request)
    {
        $place = SportPlace::query()->create(
            $this->placePayload($request->user(), $this->validatePlaceData($request))
        );

        return (new SportPlaceResource($place->load(['creator', 'sport'])))
            ->response()
            ->setStatusCode(201);
    }

    public function showPlace(Request $request, SportPlace $sportPlace)
    {
        abort_unless(SportPlace::query()->visibleTo($request->user())->whereKey($sportPlace->id)->exists(), 404);

        return new SportPlaceResource($sportPlace->load(['creator', 'sport']));
    }

    public function updatePlace(Request $request, SportPlace $sportPlace)
    {
        abort_unless((int) $sportPlace->user_id === (int) $request->user()->id, 403);

        $sportPlace->update(
            $this->placePayload($request->user(), $this->validatePlaceData($request, true), $sportPlace)
        );

        return new SportPlaceResource($sportPlace->fresh(['creator', 'sport']));
    }

    public function destroyPlace(Request $request, SportPlace $sportPlace)
    {
        abort_unless((int) $sportPlace->user_id === (int) $request->user()->id, 403);

        $sportPlace->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    private function perPage(Request $request): int
    {
        return min(max((int) $request->integer('per_page', 20), 1), 50);
    }
}
