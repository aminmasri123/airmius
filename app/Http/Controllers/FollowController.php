<?php

namespace App\Http\Controllers;

use App\Models\Follow;
use App\Models\User;
use Illuminate\Http\Request;

class FollowController extends Controller
{
    public function store(Request $request, User $user)
    {
        abort_unless($request->user()->can('follow.user'), 403);
        abort_if($request->user()->is($user), 422, 'Du kannst dir nicht selbst folgen.');

        Follow::firstOrCreate([
            'follower_id' => $request->user()->id,
            'followed_id' => $user->id,
        ]);

        return back()->with('success', 'Du folgst diesem Profil jetzt.');
    }

    public function destroy(Request $request, User $user)
    {
        Follow::query()
            ->where('follower_id', $request->user()->id)
            ->where('followed_id', $user->id)
            ->delete();

        return back()->with('success', 'Du folgst diesem Profil nicht mehr.');
    }
}
