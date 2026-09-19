<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\V1\SportMatchingController as ApiSportMatchingController;
use App\Http\Resources\Api\V1\SportMatchingResource;
use App\Models\Sport;
use App\Models\SportMatching;
use App\Models\SportMatchingApplication;
use App\Models\Team;
use App\Models\UserBlock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SportMatchingController extends Controller
{
    public function index(Request $request)
    {
        $coordinates = $request->validate([
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'radius_km' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);
        $hasCoordinates = isset($coordinates['latitude'], $coordinates['longitude']);
        $latitude = (float) ($coordinates['latitude'] ?? 0);
        $longitude = (float) ($coordinates['longitude'] ?? 0);
        $cosine = 'cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude))';
        $clamped = DB::getDriverName() === 'sqlite'
            ? 'min(1, max(-1, '.$cosine.'))' : 'least(1, greatest(-1, '.$cosine.'))';
        $distance = '(6371 * acos('.$clamped.'))';
        $matchings = SportMatching::query()
            ->with(['user:id,name,profile_photo_path', 'sport:id,name,slug', 'team.club', 'applications.user', 'applications.team', 'applications.attendance', 'attendances'])
            ->withCount([
                'applications',
                'applications as accepted_count' => fn ($q) => $q->where('status', 'accepted'),
            ])
            ->whereDoesntHave('dismissals', fn ($q) => $q->where('user_id', $request->user()->id))
            ->whereNotIn('user_id', UserBlock::query()->select('blocked_user_id')->where('user_id', $request->user()->id))
            ->whereNotIn('user_id', UserBlock::query()->select('user_id')->where('blocked_user_id', $request->user()->id))
            ->addSelect([
                'my_application' => SportMatchingApplication::query()
                    ->select('status')
                    ->whereColumn('sport_matching_id', 'sport_matchings.id')
                    ->where('user_id', $request->user()->id)
                    ->limit(1),
            ])
            ->where('starts_at', '>=', now())
            ->when($request->input('mode'), fn ($q, $value) => $q->where('mode', $value))
            ->when($request->integer('sport_id'), fn ($q, $value) => $q->where('sport_id', $value))
            ->when($request->input('location') ?: $request->input('city'), function ($q, $value) {
                $q->where(function ($query) use ($value) {
                    $query->where('city', 'like', '%'.$value.'%')
                        ->orWhere('postal_code', 'like', '%'.$value.'%')
                        ->orWhere('location_name', 'like', '%'.$value.'%');
                });
            })
            ->when($hasCoordinates, fn ($q) => $q->whereNotNull('latitude')->whereNotNull('longitude')
                ->whereRaw($distance.' <= ?', [$latitude, $longitude, $latitude, (int) ($coordinates['radius_km'] ?? 25)])
                ->orderByRaw($distance.' asc', [$latitude, $longitude, $latitude]))
            ->when($request->input('skill_level'), fn ($q, $value) => $q->whereIn('skill_level', [$value, 'all']))
            ->where('status', $request->input('status', 'open'))
            ->orderBy('starts_at')
            ->paginate(min(max($request->integer('per_page', 24), 1), 50))
            ->withQueryString();

        return Inertia::render('Auth/Dashboard/SportMatching/Index', [
            'matchings' => SportMatchingResource::collection($matchings),
            'sports' => Sport::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'slug']),
            'teams' => Team::query()->whereHas('users', fn ($q) => $q->where('users.id', $request->user()->id))
                ->orderBy('name')->get(['id', 'name', 'sport_type']),
            'filters' => $request->only(['mode', 'sport_id', 'location', 'city', 'radius_km', 'latitude', 'longitude', 'skill_level', 'status', 'view']),
            'skillLevels' => SportMatching::SKILL_LEVELS,
        ]);
    }

    public function store(Request $request, ApiSportMatchingController $api)
    {
        $api->store($request);

        return back()->with('success', __('sport_matching.flash.published'));
    }

    public function apply(Request $request, SportMatching $sportMatching, ApiSportMatchingController $api)
    {
        $api->apply($request, $sportMatching);

        return back()->with('success', __('sport_matching.flash.application_sent'));
    }

    public function withdraw(Request $request, SportMatching $sportMatching, ApiSportMatchingController $api)
    {
        $api->withdraw($request, $sportMatching);

        return back()->with('success', __('sport_matching.flash.dismissed'));
    }

    public function dismiss(Request $request, SportMatching $sportMatching, ApiSportMatchingController $api)
    {
        $api->dismiss($request, $sportMatching);

        return back()->with('success', __('sport_matching.flash.dismissed'));
    }

    public function decide(Request $request, SportMatching $sportMatching, SportMatchingApplication $application, ApiSportMatchingController $api)
    {
        $response = $api->decide($request, $sportMatching, $application);
        $conversationId = data_get($response->getData(true), 'conversation_id');

        if ($request->input('status') === 'accepted' && $conversationId) {
            return redirect()
                ->route('auth.conversations.show', $conversationId)
                ->with('success', __('sport_matching.flash.accepted_chat_opened'));
        }

        return back()->with('success', __('sport_matching.flash.application_processed'));
    }

    public function cancel(Request $request, SportMatching $sportMatching, ApiSportMatchingController $api)
    {
        $api->cancel($request, $sportMatching);

        return back()->with('success', __('sport_matching.flash.closed'));
    }

    public function updateAttendance(Request $request, SportMatching $sportMatching, ApiSportMatchingController $api)
    {
        $api->updateAttendance($request, $sportMatching);

        return back()->with('success', __('sport_matching.flash.attendance_updated'));
    }

    public function reportNoShow(Request $request, SportMatching $sportMatching, ApiSportMatchingController $api)
    {
        $api->reportNoShow($request, $sportMatching);

        return back()->with('success', __('sport_matching.flash.no_show_reported'));
    }
}
