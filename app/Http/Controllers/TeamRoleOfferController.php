<?php

namespace App\Http\Controllers;

use App\Http\Resources\Api\V1\TeamResource;
use App\Models\TeamRoleOffer;
use App\Services\TeamRoleOfferService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TeamRoleOfferController extends Controller
{
    public function respond(Request $request, TeamRoleOffer $offer, string $decision, TeamRoleOfferService $service)
    {
        abort_unless(in_array($decision, ['accept', 'decline', 'cancel'], true), 404);
        Gate::authorize('view', $offer->team);
        $service->respond($offer, $request->user(), $decision);
        if ($request->expectsJson()) {
            return new TeamResource($offer->team->fresh()->load(['club.users', 'users', 'joinRequests.user'])->loadCount(['users', 'events']));
        }

        return back()->with('success', __('team_role_offers.'.$decision));
    }
}
