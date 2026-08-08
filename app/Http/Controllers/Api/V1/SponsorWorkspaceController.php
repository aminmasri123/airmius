<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SponsorProfileRequest;
use App\Services\SponsorWorkspaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SponsorWorkspaceController extends Controller
{
    public function __construct(private SponsorWorkspaceService $workspace) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($this->workspace->canOpen($request->user()), 403);

        return response()->json(['data' => $this->workspace->payload($request->user())]);
    }

    public function updateProfile(SponsorProfileRequest $request): JsonResponse
    {
        $profile = $this->workspace->saveOwnProfile($request->user(), $request->validated());

        return response()->json([
            'message' => __('sponsor.flash.profile_saved'),
            'data' => ['id' => $profile->id],
        ]);
    }
}
