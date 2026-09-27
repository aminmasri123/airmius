<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Services\ClubDeletionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ClubDeletionController extends Controller
{
    public function show(Request $request, Club $club, ClubDeletionService $service)
    {
        Gate::authorize('delete', $club);

        return response()->json(['data' => $service->status($club)]);
    }

    public function destroy(Request $request, Club $club, ClubDeletionService $service)
    {
        $club = $service->cancel($club, $request->user());

        return response()->json(['data' => $service->status($club), 'message' => __('club_deletion.cancelled_title')]);
    }
}
