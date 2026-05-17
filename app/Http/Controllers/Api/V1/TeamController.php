<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TeamResource;
use App\Models\Team;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    public function index(Request $request)
    {
        $teams = Team::visibleTo($request->user())
            ->with(['club'])
            ->withCount(['users', 'events'])
            ->orderBy('name')
            ->paginate($this->perPage($request));

        return TeamResource::collection($teams);
    }

    public function show(Request $request, Team $team)
    {
        abort_unless(
            Team::visibleTo($request->user())->whereKey($team->id)->exists(),
            404
        );

        return new TeamResource(
            $team->loadMissing(['club', 'users'])->loadCount(['users', 'events'])
        );
    }

    private function perPage(Request $request): int
    {
        return min(max((int) $request->integer('per_page', 20), 1), 50);
    }
}
