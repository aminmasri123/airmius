<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SponsorProfileRequest;
use App\Services\SponsorWorkspaceService;
use App\Support\UploadStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SponsorWorkspaceController extends Controller
{
    public function __construct(private SponsorWorkspaceService $workspace) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($this->workspace->canOpen($request->user()), 403);

        return response()->json([
            'data' => $this->workspace->payload($request->user(), $request->integer('sponsor_id') ?: null),
        ]);
    }

    public function updateProfile(SponsorProfileRequest $request): JsonResponse
    {
        $profile = $this->workspace->saveOwnProfile(
            $request->user(),
            $request->validated(),
            $request->integer('sponsor_id') ?: null,
        );

        return response()->json([
            'message' => __('sponsor.flash.profile_saved'),
            'data' => ['id' => $profile->id],
        ]);
    }

    public function uploadLogo(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sponsor_id' => ['nullable', 'integer'],
            'variant' => ['required', 'in:primary,light,dark'],
            'logo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
        $profile = $this->workspace->uploadOwnLogo(
            $request->user(),
            $request->file('logo'),
            $data['variant'],
            $request->integer('sponsor_id') ?: null,
        );

        return response()->json([
            'message' => __('sponsor.flash.profile_saved'),
            'data' => [
                'id' => $profile->id,
                'variant' => $data['variant'],
                'url' => UploadStorage::url(match ($data['variant']) {
                    'light' => $profile->logo_light,
                    'dark' => $profile->logo_dark,
                    default => $profile->logo,
                }),
            ],
        ]);
    }
}
