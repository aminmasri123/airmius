<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SportProfileScoutService;
use Illuminate\Http\Request;

class SportProfileController extends Controller
{
    public function me(Request $request, SportProfileScoutService $profiles)
    {
        return response()->json([
            'data' => $profiles->sportCv($request->user(), $request->user()),
        ]);
    }

    public function show(Request $request, User $user, SportProfileScoutService $profiles)
    {
        return response()->json([
            'data' => $profiles->sportCv($user, $request->user()),
        ]);
    }

    public function scoutSearch(Request $request, SportProfileScoutService $profiles)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'sport' => ['nullable', 'string', 'max:80'],
            'skill' => ['nullable', 'string', 'max:80'],
            'min_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:30'],
        ]);

        return response()->json([
            'data' => $profiles->scoutSearch($request->user(), $filters),
        ]);
    }
}
