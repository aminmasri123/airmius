<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SponsorPartnershipRequest;
use App\Http\Requests\SponsorProfileRequest;
use App\Models\SponsorPartnership;
use App\Services\SponsorPartnershipService;
use App\Services\SponsorWorkspaceService;
use App\Support\UploadStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SponsorWorkspaceController extends Controller
{
    public function __construct(
        private SponsorWorkspaceService $workspace,
        private SponsorPartnershipService $partnerships,
    ) {}

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
            'data' => [
                'id' => $profile->id,
                'verification_status' => $profile->verification_status,
            ],
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
                'verification_status' => $profile->verification_status,
                'variant' => $data['variant'],
                'url' => UploadStorage::url(match ($data['variant']) {
                    'light' => $profile->logo_light,
                    'dark' => $profile->logo_dark,
                    default => $profile->logo,
                }),
            ],
        ]);
    }

    public function storePartnership(SponsorPartnershipRequest $request): JsonResponse
    {
        $partnership = $this->partnerships->create($request->user(), $request->validated());

        return response()->json([
            'message' => __('sponsor.partnership.requested'),
            'data' => ['id' => $partnership->id, 'status' => $partnership->status],
        ], 201);
    }

    public function showPartnership(Request $request, int $partnership): JsonResponse
    {
        $record = $this->partnerships->findForSponsorUser($request->user(), $partnership);

        return response()->json(['data' => [
            'id' => $record->id,
            'club' => $record->club?->only(['id', 'name']),
            'status' => $record->status,
            'starts_at' => $record->starts_at?->toDateString(),
            'ends_at' => $record->ends_at?->toDateString(),
            'package_code' => $record->package_code,
            'rights_package' => $record->rights_package ?? [],
            'individual_offer_terms' => $record->individual_offer_terms,
            'renewal_deadline' => $record->renewal_deadline?->toDateString(),
            'budget_cents' => $record->budget_cents,
        ]]);
    }

    public function withdrawPartnership(Request $request, SponsorPartnership $partnership): JsonResponse
    {
        $record = $this->partnerships->withdraw($request->user(), $partnership);

        return response()->json([
            'message' => __('sponsor.partnership.withdrawn'),
            'data' => ['id' => $record->id, 'status' => $record->status],
        ]);
    }
}
