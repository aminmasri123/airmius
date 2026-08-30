<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class ChallengeController extends Controller
{
    public function __invoke(Request $request)
    {
        $inviteeId = $request->integer('invite_user');
        $inviteeId = $inviteeId > 0 && $request->user()->friendships()->where('friend_id', $inviteeId)->exists()
            ? $inviteeId
            : null;

        return Inertia::render('Auth/Dashboard/Challenges/Index', [
            'initialInviteeId' => $inviteeId,
        ]);
    }
}
