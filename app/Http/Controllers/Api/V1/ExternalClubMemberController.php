<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ExternalClubMemberResource;
use App\Models\Club;
use App\Models\ClubExternalMember;
use App\Support\Api\V1\ApiContract;
use App\Support\Api\V1\ApiPagination;
use App\Support\ClubAuditLog;
use App\Support\ClubPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExternalClubMemberController extends Controller
{
    public function index(Request $request, Club $club): JsonResponse
    {
        $this->authorizeExternal($request, $club, 'external.members:read');

        $page = $club->externalMembers()
            ->when($request->filled('status'), fn ($query) => $query->where('membership_status', $request->string('status')))
            ->orderBy('id')
            ->paginate(ApiPagination::perPage($request, 25))
            ->withQueryString();

        return response()->json(ApiPagination::payload(
            $page,
            ExternalClubMemberResource::collection($page->getCollection())->resolve($request),
            ApiContract::meta($request, [
                'tenant' => [
                    'type' => 'club',
                    'id' => $club->id,
                ],
            ]),
        ));
    }

    public function show(Request $request, Club $club, ClubExternalMember $externalMember): JsonResponse
    {
        $this->authorizeExternal($request, $club, 'external.members:read');
        abort_unless((int) $externalMember->club_id === (int) $club->id, 404);

        return response()->json([
            'data' => (new ExternalClubMemberResource($externalMember))->resolve($request),
            'meta' => ApiContract::meta($request, [
                'tenant' => [
                    'type' => 'club',
                    'id' => $club->id,
                ],
            ]),
        ]);
    }

    public function store(Request $request, Club $club): JsonResponse
    {
        $this->authorizeExternal($request, $club, 'external.members:write');

        $data = $this->validated($request, $club);
        $member = $club->externalMembers()->create(array_merge($data, [
            'created_by' => $request->user()->id,
            'email' => strtolower(trim($data['email'])),
            'country' => isset($data['country']) ? strtoupper(trim($data['country'])) : null,
            'role' => $data['role'] ?? 'member',
            'membership_status' => $data['membership_status'] ?? 'active',
        ]));

        ClubAuditLog::record($club, $request->user(), 'club.member.external_updated', $member, [
            'external_member_id' => $member->id,
            'source' => 'external_api',
            'operation' => 'created',
        ]);

        return response()->json([
            'data' => (new ExternalClubMemberResource($member))->resolve($request),
            'meta' => ApiContract::meta($request, [
                'tenant' => [
                    'type' => 'club',
                    'id' => $club->id,
                ],
            ]),
        ], 201);
    }

    public function update(Request $request, Club $club, ClubExternalMember $externalMember): JsonResponse
    {
        $this->authorizeExternal($request, $club, 'external.members:write');
        abort_unless((int) $externalMember->club_id === (int) $club->id, 404);

        $data = $this->validated($request, $club, $externalMember);
        $before = $externalMember->only(array_keys($data));

        if (array_key_exists('email', $data)) {
            $data['email'] = strtolower(trim($data['email']));
        }
        if (array_key_exists('country', $data)) {
            $data['country'] = $data['country'] ? strtoupper(trim($data['country'])) : null;
        }

        $externalMember->update($data);
        $externalMember->refresh();

        $changedFields = collect(array_keys($data))
            ->filter(fn (string $field) => (string) ($before[$field] ?? '') !== (string) ($externalMember->getAttribute($field) ?? ''))
            ->values()
            ->all();

        if ($changedFields !== []) {
            ClubAuditLog::record($club, $request->user(), 'club.member.external_updated', $externalMember, [
                'external_member_id' => $externalMember->id,
                'source' => 'external_api',
                'operation' => 'updated',
                'changed_fields' => $changedFields,
            ]);
        }

        return response()->json([
            'data' => (new ExternalClubMemberResource($externalMember))->resolve($request),
            'meta' => ApiContract::meta($request, [
                'tenant' => [
                    'type' => 'club',
                    'id' => $club->id,
                ],
            ]),
        ]);
    }

    private function authorizeExternal(Request $request, Club $club, string $ability): void
    {
        abort_unless($request->user()?->tokenCan($ability), 403);
        abort_unless(ClubPermissions::allows($club, $request->user(), ClubPermissions::MEMBERS_MANAGE), 403);
    }

    private function validated(Request $request, Club $club, ?ClubExternalMember $externalMember = null): array
    {
        return $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('club_external_members', 'email')
                    ->where('club_id', $club->id)
                    ->ignore($externalMember?->id),
            ],
            'phone' => ['nullable', 'string', 'max:40'],
            'country' => ['nullable', 'string', 'size:2'],
            'street' => ['nullable', 'string', 'max:255'],
            'house_number' => ['nullable', 'string', 'max:40'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'city' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', Rule::in(['member', 'trainer', 'manager', 'owner'])],
            'membership_status' => ['nullable', Rule::in(['active', 'pending', 'paused', 'former'])],
            'member_number' => ['nullable', 'string', 'max:80'],
            'athlete_license_number' => ['nullable', 'string', 'max:120'],
            'athlete_license_valid_until' => ['nullable', 'date'],
            'joined_on' => ['nullable', 'date'],
            'membership_ends_on' => ['nullable', 'date'],
            'membership_notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
