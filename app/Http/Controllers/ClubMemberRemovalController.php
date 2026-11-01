<?php

namespace App\Http\Controllers;

use App\Models\ClubMemberRemoval;
use App\Services\ClubMemberRemovalService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ClubMemberRemovalController extends Controller
{
    public function show(Request $request, ClubMemberRemoval $removal)
    {
        abort_unless((int) $removal->user_id === (int) $request->user()->id, 404);
        $data = $removal->only(['id', 'status', 'reason', 'objection', 'expires_at', 'completed_at']);

        return $request->is('api/*') ? response()->json(['data' => $data]) : Inertia::render('Auth/Dashboard/ClubMemberships/Removal', ['removal' => $data]);
    }

    public function respond(Request $request, ClubMemberRemoval $removal, ClubMemberRemovalService $service)
    {
        abort_unless((int) $removal->user_id === (int) $request->user()->id, 404);
        $data = $request->validate(['decision' => ['required', 'in:confirm,object'], 'message' => ['nullable', 'string', 'max:2000']]);
        $service->respond($removal, $request->user(), $data['decision'], $data['message'] ?? null);

        return $request->is('api/*') ? response()->json(['data' => $removal->fresh()]) : back();
    }
}
