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
        $data['club_name'] = \App\Models\Club::findOrFail($removal->club_id)->name;

        return $request->is('api/*') ? response()->json(['data' => $data]) : Inertia::render('Auth/Dashboard/ClubMemberships/Removal', ['removal' => $data]);
    }

    public function respond(Request $request, ClubMemberRemoval $removal, ClubMemberRemovalService $service)
    {
        abort_unless((int) $removal->user_id === (int) $request->user()->id, 404);
        $data = $request->validate(['decision' => ['required', 'in:confirm,object'], 'message' => ['nullable', 'string', 'max:2000']]);
        $service->respond($removal, $request->user(), $data['decision'], $data['message'] ?? null);

        return $request->is('api/*') ? response()->json(['data' => $removal->fresh()]) : back();
    }

    public function legacy(Request $request, \App\Models\Notification $notification)
    {
        abort_unless((int) $notification->user_id === (int) $request->user()->id && $notification->type === 'club.member_removed' && empty($notification->data['removal_id']), 404);
        $club = \App\Models\Club::findOrFail($notification->data['club_id'] ?? null);
        $superseded = $club->users()->where('users.id', $request->user()->id)->exists()
            || ClubMemberRemoval::where('club_id', $club->id)->where('user_id', $request->user()->id)->exists();
        if ($request->isMethod('post')) {
            $request->validate(['message' => ['nullable', 'string', 'max:2000']]);
            abort_if($superseded, 422, 'Diese Meldung gehört zu einem früheren Vorgang.');
            \Illuminate\Support\Facades\DB::transaction(function () use ($request, $notification, $club) {
                \App\Models\Club::whereKey($club->id)->lockForUpdate()->firstOrFail();
                abort_if($club->users()->where('users.id', $request->user()->id)->exists()
                    || ClubMemberRemoval::where('club_id', $club->id)->where('user_id', $request->user()->id)->exists(), 422);
                $locked = \App\Models\Notification::lockForUpdate()->findOrFail($notification->id);
                if (!empty($locked->data['objection_submitted'])) return;
                app(ClubMembershipController::class)->objectToRemoval($request, $club);
                $locked->update(['data' => array_merge($locked->data, ['objection_submitted' => true])]);
            });
            $notification->refresh();
        }
        return response()->json(['data' => [
            'club_name' => $club->name,
            'reason' => $notification->data['reason'] ?? $notification->data['body'] ?? '',
            'status' => $superseded ? 'legacy_superseded' : (!empty($notification->data['objection_submitted']) ? 'legacy_objected' : 'legacy_removed'),
        ]]);
    }
}
