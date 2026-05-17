<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesSportMapPayloads;
use App\Http\Resources\Api\V1\SportPlaceResource;
use App\Http\Resources\Api\V1\SportRouteResource;
use App\Http\Resources\Api\V1\SportTrackResource;
use App\Models\Sport;
use App\Models\SportPlace;
use App\Models\SportRoute;
use App\Models\SportRouteTrack;
use App\Services\SportRouteMetricService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SportMapController extends Controller
{
    use ManagesSportMapPayloads;

    public function index(Request $request, SportRouteMetricService $metrics)
    {
        $user = $request->user();

        $routes = SportRoute::query()
            ->visibleTo($user)
            ->with(['creator:id,name,first_name,last_name,email,profile_photo_path', 'sport:id,name,slug,category', 'team:id,name'])
            ->withCount('tracks')
            ->latest('updated_at')
            ->limit(40)
            ->get();

        $tracks = SportRouteTrack::query()
            ->visibleTo($user)
            ->with(['route:id,title,distance_meters', 'sport:id,name,slug,category'])
            ->latest('started_at')
            ->latest('id')
            ->limit(30)
            ->get();

        $placesQuery = SportPlace::query()
            ->visibleTo($user)
            ->where('status', 'active')
            ->with(['creator:id,name,first_name,last_name,email,profile_photo_path', 'sport:id,name,slug,category'])
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->input('type')))
            ->when($request->filled('sport_type'), fn ($query) => $query->whereJsonContains('sport_types', $request->input('sport_type')));

        $this->filterNearbyPlaces($placesQuery, $request, $metrics);

        $places = $this->decoratePlacesWithDistance(
            $placesQuery
                ->latest('updated_at')
                ->limit($this->hasNearbyCoordinates($request) ? 250 : 80)
                ->get(),
            $request,
            $metrics,
            true,
        )->take(80)->values();

        return Inertia::render('Auth/Dashboard/SportMap/Index', [
            'sportTypes' => $this->sportTypes(),
            'placeTypes' => $this->placeTypes(),
            'routes' => SportRouteResource::collection($routes)->resolve(),
            'tracks' => SportTrackResource::collection($tracks)->resolve(),
            'places' => SportPlaceResource::collection($places)->resolve(),
            'sportCatalog' => Sport::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'category']),
            'teams' => $user->teams()
                ->orderBy('name')
                ->get(['teams.id', 'teams.name'])
                ->map(fn ($team) => ['id' => $team->id, 'name' => $team->name]),
        ]);
    }

    public function storeRoute(Request $request, SportRouteMetricService $metrics)
    {
        $route = SportRoute::query()->create(
            $this->routePayload($request->user(), $this->validateRouteData($request), $metrics)
        );

        return back()->with('success', 'Route "'.$route->title.'" wurde geplant.');
    }

    public function updateRoute(Request $request, SportRoute $sportRoute, SportRouteMetricService $metrics)
    {
        abort_unless((int) $sportRoute->user_id === (int) $request->user()->id, 403);

        $sportRoute->update(
            $this->routePayload($request->user(), $this->validateRouteData($request, true), $metrics, $sportRoute)
        );

        return back()->with('success', 'Route wurde aktualisiert.');
    }

    public function destroyRoute(Request $request, SportRoute $sportRoute)
    {
        abort_unless((int) $sportRoute->user_id === (int) $request->user()->id, 403);

        $sportRoute->delete();

        return back()->with('success', 'Route wurde geloescht.');
    }

    public function storeTrack(Request $request, SportRouteMetricService $metrics)
    {
        $track = SportRouteTrack::query()->create(
            $this->trackPayload($request->user(), $this->validateTrackData($request), $metrics)
        );

        return back()->with('success', 'Strecke "'.$track->title.'" wurde gespeichert.');
    }

    public function updateTrack(Request $request, SportRouteTrack $sportRouteTrack, SportRouteMetricService $metrics)
    {
        abort_unless((int) $sportRouteTrack->user_id === (int) $request->user()->id, 403);

        $sportRouteTrack->update(
            $this->trackPayload($request->user(), $this->validateTrackData($request, true), $metrics, $sportRouteTrack)
        );

        return back()->with('success', 'Strecke wurde aktualisiert.');
    }

    public function destroyTrack(Request $request, SportRouteTrack $sportRouteTrack)
    {
        abort_unless((int) $sportRouteTrack->user_id === (int) $request->user()->id, 403);

        $sportRouteTrack->delete();

        return back()->with('success', 'Strecke wurde geloescht.');
    }

    public function storePlace(Request $request)
    {
        $place = SportPlace::query()->create(
            $this->placePayload($request->user(), $this->validatePlaceData($request))
        );

        return back()->with('success', 'Sportplatz "'.$place->name.'" wurde hinzugefuegt.');
    }

    public function updatePlace(Request $request, SportPlace $sportPlace)
    {
        abort_unless((int) $sportPlace->user_id === (int) $request->user()->id, 403);

        $sportPlace->update(
            $this->placePayload($request->user(), $this->validatePlaceData($request, true), $sportPlace)
        );

        return back()->with('success', 'Sportplatz wurde aktualisiert.');
    }

    public function destroyPlace(Request $request, SportPlace $sportPlace)
    {
        abort_unless((int) $sportPlace->user_id === (int) $request->user()->id, 403);

        $sportPlace->delete();

        return back()->with('success', 'Sportplatz wurde geloescht.');
    }
}
