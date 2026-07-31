<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\V1\SportMatchingController as ApiSportMatchingController;
use App\Http\Resources\Api\V1\SportMatchingResource;
use App\Models\Sport;
use App\Models\SportMatching;
use App\Models\SportMatchingApplication;
use App\Models\Team;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SportMatchingController extends Controller
{
    public function index(Request $request)
    {
        $matchings = SportMatching::query()
            ->with(['user:id,name,profile_photo_path', 'sport:id,name,slug', 'team.club', 'applications.user', 'applications.team'])
            ->withCount([
                'applications',
                'applications as accepted_count' => fn ($q) => $q->where('status', 'accepted'),
            ])
            ->addSelect([
                'my_application' => SportMatchingApplication::query()
                    ->select('status')
                    ->whereColumn('sport_matching_id', 'sport_matchings.id')
                    ->where('user_id', $request->user()->id)
                    ->limit(1),
            ])
            ->where('starts_at', '>=', now()->subHours(3))
            ->when($request->input('mode'), fn ($q, $value) => $q->where('mode', $value))
            ->when($request->integer('sport_id'), fn ($q, $value) => $q->where('sport_id', $value))
            ->when($request->input('location') ?: $request->input('city'), function ($q, $value) {
                $q->where(function ($query) use ($value) {
                    $query->where('city', 'like', '%'.$value.'%')
                        ->orWhere('postal_code', 'like', '%'.$value.'%')
                        ->orWhere('location_name', 'like', '%'.$value.'%');
                });
            })
            ->where('status', $request->input('status', 'open'))
            ->orderBy('starts_at')
            ->paginate(24)
            ->withQueryString();

        return Inertia::render('Auth/Dashboard/SportMatching/Index', [
            'matchings' => SportMatchingResource::collection($matchings),
            'sports' => Sport::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'slug']),
            'teams' => Team::query()->whereHas('users', fn ($q) => $q->where('users.id', $request->user()->id))
                ->orderBy('name')->get(['id', 'name', 'sport_type']),
            'filters' => $request->only(['mode', 'sport_id', 'location', 'city', 'status']),
            'skillLevels' => SportMatching::SKILL_LEVELS,
        ]);
    }

    public function store(Request $request, ApiSportMatchingController $api)
    {
        $api->store($request);
        return back()->with('success', 'Matching-Angebot veröffentlicht.');
    }

    public function apply(Request $request, SportMatching $sportMatching, ApiSportMatchingController $api)
    {
        $api->apply($request, $sportMatching);
        return back()->with('success', 'Anfrage wurde gesendet.');
    }

    public function decide(Request $request, SportMatching $sportMatching, SportMatchingApplication $application, ApiSportMatchingController $api)
    {
        $api->decide($request, $sportMatching, $application);
        return back()->with('success', 'Anfrage wurde bearbeitet.');
    }

    public function cancel(Request $request, SportMatching $sportMatching, ApiSportMatchingController $api)
    {
        $api->cancel($request, $sportMatching);
        return back()->with('success', 'Matching-Angebot wurde geschlossen.');
    }
}
