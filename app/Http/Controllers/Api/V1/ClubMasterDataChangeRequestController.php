<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ClubMasterDataChangeRequestResource;
use App\Models\Club;
use App\Models\ClubMasterDataChangeRequest;
use App\Services\ClubMasterDataChangeRequestService;
use App\Support\ClubPermissions;
use App\Support\Validation\ClubProfileRules;
use Illuminate\Http\Request;

class ClubMasterDataChangeRequestController extends Controller
{
    public function __construct(private readonly ClubMasterDataChangeRequestService $changes) {}

    public function index(Request $request, Club $club)
    {
        abort_unless(
            ClubPermissions::allows($club, $request->user(), ClubPermissions::CLUB_LEGAL_EDIT)
                || ClubPermissions::allows($club, $request->user(), ClubPermissions::CLUB_CONTACT_EDIT),
            403,
        );

        $changes = $club->masterDataChangeRequests()
            ->with(['requester:id,name,email', 'reviewer:id,name,email'])
            ->latest('id')
            ->paginate((int) min(max($request->integer('per_page', 15), 1), 50));

        return ClubMasterDataChangeRequestResource::collection($changes);
    }

    public function store(Request $request, Club $club)
    {
        $data = $request->validate(ClubProfileRules::update());
        $change = $this->changes->create($club, $request->user(), $data);

        return (new ClubMasterDataChangeRequestResource($change->load(['requester:id,name,email', 'reviewer:id,name,email'])))
            ->response()
            ->setStatusCode($change->status === 'applied' ? 200 : 201);
    }

    public function approve(Request $request, Club $club, ClubMasterDataChangeRequest $changeRequest)
    {
        abort_unless((int) $changeRequest->club_id === (int) $club->id, 404);
        $data = $request->validate(['review_note' => ['nullable', 'string', 'max:1000']]);

        $change = $this->changes->approve($changeRequest, $request->user(), $data['review_note'] ?? null);

        return new ClubMasterDataChangeRequestResource($change->load(['requester:id,name,email', 'reviewer:id,name,email']));
    }

    public function reject(Request $request, Club $club, ClubMasterDataChangeRequest $changeRequest)
    {
        abort_unless((int) $changeRequest->club_id === (int) $club->id, 404);
        $data = $request->validate(['review_note' => ['nullable', 'string', 'max:1000']]);

        $change = $this->changes->reject($changeRequest, $request->user(), $data['review_note'] ?? null);

        return new ClubMasterDataChangeRequestResource($change->load(['requester:id,name,email', 'reviewer:id,name,email']));
    }
}
