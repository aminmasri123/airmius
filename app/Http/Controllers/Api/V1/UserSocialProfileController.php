<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserSocialProfileService;
use Illuminate\Http\Request;

class UserSocialProfileController extends Controller
{
    public function __construct(private readonly UserSocialProfileService $social) {}

    public function follow(Request $request, User $user)
    {
        return response()->json(['data' => $this->social->follow($user, $request->user())]);
    }

    public function unfollow(Request $request, User $user)
    {
        return response()->json(['data' => $this->social->unfollow($user, $request->user())]);
    }

    public function block(Request $request, User $user)
    {
        return response()->json(['data' => $this->social->block($user, $request->user())]);
    }

    public function unblock(Request $request, User $user)
    {
        return response()->json(['data' => $this->social->unblock($user, $request->user())]);
    }
}
