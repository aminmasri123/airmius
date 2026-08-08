<?php

namespace App\Http\Controllers;

use App\Models\Follow;
use App\Models\User;
use App\Support\AppNotification;
use Illuminate\Http\Request;

class FollowController extends Controller
{
    public function store(Request $request, User $user)
    {
        abort_unless($request->user()->can('follow.user'), 403);
        abort_if($request->user()->is($user), 422, __('platform.social.self_follow'));

        $follow = Follow::firstOrCreate([
            'follower_id' => $request->user()->id,
            'followed_id' => $user->id,
        ]);

        if ($follow->wasRecentlyCreated) {
            AppNotification::sendLocalized(
                $user,
                'user.followed',
                'platform.social.follow_title',
                'platform.social.follow_body',
                ['name' => $request->user()->name],
                [
                'url' => route('auth.users.show', $request->user()->id),
                'actor_id' => $request->user()->id,
                'actor_name' => $request->user()->name,
                'follow_id' => $follow->id,
                ],
            );
        }

        return back()->with('success', __('platform.social.follow_started'));
    }

    public function destroy(Request $request, User $user)
    {
        Follow::query()
            ->where('follower_id', $request->user()->id)
            ->where('followed_id', $user->id)
            ->delete();

        return back()->with('success', __('platform.social.follow_stopped'));
    }
}
