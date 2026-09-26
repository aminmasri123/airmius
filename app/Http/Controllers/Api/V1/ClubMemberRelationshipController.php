<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\ClubMemberRelationship;
use App\Models\User;
use App\Services\ClubMemberRelationshipService;
use App\Support\ClubPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClubMemberRelationshipController extends Controller
{
    public function __construct(private readonly ClubMemberRelationshipService $relationships) {}

    public function index(Request $request, Club $club, User $user): JsonResponse
    {
        $member = $user;
        $this->authorizeClubMember($request, $club, $member);

        $relationships = ClubMemberRelationship::query()
            ->where('club_id', $club->id)
            ->where('member_user_id', $member->id)
            ->with(['relatedUser:id,name,email'])
            ->latest('is_primary')
            ->orderBy('relationship_type')
            ->get()
            ->map(fn (ClubMemberRelationship $relationship) => $this->relationshipData($relationship))
            ->values();

        return response()->json([
            'data' => [
                'club' => ['id' => $club->id, 'name' => $club->name],
                'member' => ['id' => $member->id, 'name' => $member->name, 'email' => $member->email],
                'summary' => [
                    'total' => $relationships->count(),
                    'active' => $relationships->where('status', ClubMemberRelationship::STATUS_ACTIVE)->count(),
                    'guardians' => $relationships->filter(fn ($item) => in_array(ClubMemberRelationship::PURPOSE_GUARDIAN, $item['purposes'], true))->count(),
                    'contribution_payers' => $relationships->filter(fn ($item) => in_array(ClubMemberRelationship::PURPOSE_CONTRIBUTION_PAYER, $item['purposes'], true))->count(),
                    'emergency_contacts' => $relationships->filter(fn ($item) => in_array(ClubMemberRelationship::PURPOSE_EMERGENCY_CONTACT, $item['purposes'], true))->count(),
                    'pickup_authorized' => $relationships->filter(fn ($item) => in_array(ClubMemberRelationship::PURPOSE_PICKUP_AUTHORIZED, $item['purposes'], true))->count(),
                ],
                'relationships' => $relationships,
            ],
        ]);
    }

    public function store(Request $request, Club $club, User $user): JsonResponse
    {
        $member = $user;
        $this->authorizeClubMember($request, $club, $member);

        $data = $request->validate([
            'related_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'related_email' => ['nullable', 'email', 'max:255'],
            'related_name' => ['nullable', 'string', 'max:120'],
            'relationship_type' => ['nullable', 'string', 'max:50'],
            'purposes' => ['required', 'array', 'min:1'],
            'purposes.*' => ['string', Rule::in([
                ClubMemberRelationship::PURPOSE_CONTRIBUTION_PAYER,
                ClubMemberRelationship::PURPOSE_GUARDIAN,
                ClubMemberRelationship::PURPOSE_EMERGENCY_CONTACT,
                ClubMemberRelationship::PURPOSE_PICKUP_AUTHORIZED,
            ])],
            'contact_methods' => ['nullable', 'array'],
            'contact_methods.*' => ['string', Rule::in([
                ClubMemberRelationship::CONTACT_EMAIL,
                ClubMemberRelationship::CONTACT_PHONE,
                ClubMemberRelationship::CONTACT_IN_APP,
                ClubMemberRelationship::CONTACT_POSTAL,
            ])],
            'status' => ['nullable', Rule::in([
                ClubMemberRelationship::STATUS_ACTIVE,
                ClubMemberRelationship::STATUS_INVITED,
                ClubMemberRelationship::STATUS_DECLINED,
                ClubMemberRelationship::STATUS_REVOKED,
                ClubMemberRelationship::STATUS_AMBIGUOUS,
            ])],
            'primary' => ['sometimes', 'boolean'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'metadata' => ['nullable', 'array'],
        ]);

        $relationship = $this->relationships->upsert(
            $club,
            $member,
            isset($data['related_user_id']) ? User::query()->findOrFail($data['related_user_id']) : null,
            $data['related_email'] ?? null,
            $data['related_name'] ?? null,
            $data['relationship_type'] ?? 'contact',
            $data['purposes'],
            $data['contact_methods'] ?? [],
            $data['status'] ?? ClubMemberRelationship::STATUS_ACTIVE,
            (bool) ($data['primary'] ?? false),
            $data['valid_from'] ?? null,
            $data['valid_until'] ?? null,
            $request->user(),
            metadata: $data['metadata'] ?? [],
        );

        return response()->json([
            'message' => 'club_member_relationship_saved',
            'data' => $this->relationshipData($relationship->load('relatedUser:id,name,email')),
        ], 201);
    }

    private function authorizeClubMember(Request $request, Club $club, User $member): void
    {
        abort_unless(ClubPermissions::allows($club, $request->user(), ClubPermissions::MEMBERS_EDIT), 403);

        $belongsToClub = $member->clubs()->whereKey($club->id)->exists()
            || $member->teams()->where('club_id', $club->id)->exists();

        abort_unless($belongsToClub, 404);
    }

    private function relationshipData(ClubMemberRelationship $relationship): array
    {
        return [
            'id' => $relationship->id,
            'club_id' => $relationship->club_id,
            'member_user_id' => $relationship->member_user_id,
            'related_user' => $relationship->relatedUser ? [
                'id' => $relationship->relatedUser->id,
                'name' => $relationship->relatedUser->name,
                'email' => $relationship->relatedUser->email,
            ] : null,
            'related_email' => $relationship->related_email,
            'related_name' => $relationship->related_name,
            'relationship_type' => $relationship->relationship_type,
            'purposes' => $relationship->purposes ?? [],
            'contact_methods' => $relationship->contact_methods ?? [],
            'status' => $relationship->status,
            'is_primary' => $relationship->is_primary,
            'valid_from' => $relationship->valid_from?->toDateString(),
            'valid_until' => $relationship->valid_until?->toDateString(),
            'legacy_source' => $relationship->legacy_source,
        ];
    }
}
