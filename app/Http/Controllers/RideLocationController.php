<?php

namespace App\Http\Controllers;

use App\Models\Ride;
use App\Services\RideGeocodingService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class RideLocationController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(Request $request, RideGeocodingService $geocoding)
    {
        $this->authorize('viewAny', Ride::class);
        $data = $request->validate(['q' => ['required', 'string', 'min:3', 'max:200'], 'area' => ['nullable', 'boolean'], 'locale' => ['nullable', 'in:de,en,fr,ar']]);
        try {
            return response()->json(['data' => $geocoding->search($data['q'], $data['locale'] ?? app()->getLocale(), $request->boolean('area'))]);
        } catch (\Throwable) {
            return response()->json(['message' => __('ride_locations.unavailable')], 503);
        }
    }
}
