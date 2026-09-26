<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\ClubGovernanceAssignment;
use App\Models\ClubGovernanceBody;
use App\Support\ClubAuditLog;
use App\Support\ClubPermissions;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClubGovernanceController extends Controller
{
    public function index(Request $request, Club $club)
    {
        [$canView, $canEdit, $canDelete] = $this->viewerAccess($request, $club);
        $includeInternal = $canView;
        $bodies = $club->governanceBodies()
            ->when(! $includeInternal, fn ($query) => $query->where('is_public', true))
            ->with(['assignments' => fn ($query) => $query
                ->when(! $includeInternal, fn ($query) => $query->where('is_public', true))
                ->with(['user:id,name', 'externalMember:id,name'])
                ->orderBy('position_title')])
            ->orderByRaw("CASE type WHEN 'board' THEN 1 WHEN 'committee' THEN 2 ELSE 3 END")
            ->orderBy('name')
            ->get();

        return response()->json(['data' => [
            'bodies' => $bodies->map(fn (ClubGovernanceBody $body) => $this->bodyPayload($body, $canEdit)),
            'member_options' => $canEdit ? $this->memberOptions($club) : [],
            'can_manage' => $canEdit || $canDelete,
            'can_edit' => $canEdit,
            'can_delete' => $canDelete,
        ]]);
    }

    public function storeBody(Request $request, Club $club)
    {
        $this->authorizeManage($request, $club, ClubPermissions::GOVERNANCE_EDIT);
        $body = $club->governanceBodies()->create($this->bodyData($request, $club));
        $this->audit($club, $request, 'club.governance.body.created', $body, 'body');

        return response()->json(['data' => $this->bodyPayload($body->load('assignments'), true)], 201);
    }

    public function updateBody(Request $request, Club $club, ClubGovernanceBody $governanceBody)
    {
        $this->authorizeBody($request, $club, $governanceBody, ClubPermissions::GOVERNANCE_EDIT);
        $governanceBody->update($this->bodyData($request, $club, $governanceBody));
        $this->audit($club, $request, 'club.governance.body.updated', $governanceBody, 'body');

        return response()->json(['data' => $this->bodyPayload($governanceBody->refresh()->load(['assignments.user', 'assignments.externalMember']), true)]);
    }

    public function destroyBody(Request $request, Club $club, ClubGovernanceBody $governanceBody)
    {
        $this->authorizeBody($request, $club, $governanceBody, ClubPermissions::GOVERNANCE_DELETE);
        if ($governanceBody->assignments()->exists()) {
            throw ValidationException::withMessages(['governance_body' => __('validation.governance_body_in_use')]);
        }
        $this->audit($club, $request, 'club.governance.body.deleted', $governanceBody, 'body');
        $governanceBody->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function storeAssignment(Request $request, Club $club, ClubGovernanceBody $governanceBody)
    {
        $this->authorizeBody($request, $club, $governanceBody, ClubPermissions::GOVERNANCE_EDIT);
        $assignment = $governanceBody->assignments()->create([
            ...$this->assignmentData($request, $club),
            'club_id' => $club->id,
        ]);
        $this->audit($club, $request, 'club.governance.assignment.created', $assignment, 'assignment');

        return response()->json(['data' => $this->assignmentPayload($assignment->load(['user', 'externalMember']), true)], 201);
    }

    public function updateAssignment(Request $request, Club $club, ClubGovernanceBody $governanceBody, ClubGovernanceAssignment $assignment)
    {
        $this->authorizeAssignment($request, $club, $governanceBody, $assignment, ClubPermissions::GOVERNANCE_EDIT);
        $assignment->update($this->assignmentData($request, $club));
        $this->audit($club, $request, 'club.governance.assignment.updated', $assignment, 'assignment');

        return response()->json(['data' => $this->assignmentPayload($assignment->refresh()->load(['user', 'externalMember']), true)]);
    }

    public function destroyAssignment(Request $request, Club $club, ClubGovernanceBody $governanceBody, ClubGovernanceAssignment $assignment)
    {
        $this->authorizeAssignment($request, $club, $governanceBody, $assignment, ClubPermissions::GOVERNANCE_DELETE);
        $this->audit($club, $request, 'club.governance.assignment.deleted', $assignment, 'assignment');
        $assignment->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    private function viewerAccess(Request $request, Club $club): array
    {
        $user = $request->user();
        $canView = (bool) $user && ClubPermissions::allows($club, $user, ClubPermissions::GOVERNANCE_VIEW);
        $canEdit = (bool) $user && ClubPermissions::allows($club, $user, ClubPermissions::GOVERNANCE_EDIT);
        $canDelete = (bool) $user && ClubPermissions::allows($club, $user, ClubPermissions::GOVERNANCE_DELETE);
        abort_unless($canView || $club->is_listed, 404);

        return [$canView, $canEdit, $canDelete];
    }

    private function authorizeManage(Request $request, Club $club, string $permission): void
    {
        abort_unless($request->user() && ClubPermissions::allows($club, $request->user(), $permission), 403);
    }

    private function authorizeBody(Request $request, Club $club, ClubGovernanceBody $body, string $permission): void
    {
        $this->authorizeManage($request, $club, $permission);
        abort_unless((int) $body->club_id === (int) $club->id, 404);
    }

    private function authorizeAssignment(Request $request, Club $club, ClubGovernanceBody $body, ClubGovernanceAssignment $assignment, string $permission): void
    {
        $this->authorizeBody($request, $club, $body, $permission);
        abort_unless((int) $assignment->club_id === (int) $club->id && (int) $assignment->club_governance_body_id === (int) $body->id, 404);
    }

    private function bodyData(Request $request, Club $club, ?ClubGovernanceBody $body = null): array
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(ClubGovernanceBody::TYPES)],
            'name' => [
                'required', 'string', 'max:160',
                Rule::unique('club_governance_bodies')->where(fn ($query) => $query
                    ->where('club_id', $club->id)
                    ->where('type', $request->input('type')))->ignore($body),
            ],
            'description' => ['nullable', 'string', 'max:3000'],
            'starts_on' => ['nullable', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'is_public' => ['required', 'boolean'],
        ]);

        return $this->normalize($data, ['name', 'description']);
    }

    private function assignmentData(Request $request, Club $club): array
    {
        $data = $request->validate([
            'user_id' => [
                'nullable', 'required_without:club_external_member_id',
                Rule::prohibitedIf($request->filled('club_external_member_id')),
                Rule::exists('club_user', 'user_id')->where('club_id', $club->id),
            ],
            'club_external_member_id' => [
                'nullable', 'required_without:user_id',
                Rule::prohibitedIf($request->filled('user_id')),
                Rule::exists('club_external_members', 'id')->where('club_id', $club->id),
            ],
            'position_title' => ['required', 'string', 'max:160'],
            'responsibilities' => ['nullable', 'string', 'max:3000'],
            'starts_on' => ['nullable', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'is_public' => ['required', 'boolean'],
        ]);

        return $this->normalize($data, ['position_title', 'responsibilities']);
    }

    private function normalize(array $data, array $fields): array
    {
        foreach ($fields as $field) {
            $value = trim((string) ($data[$field] ?? ''));
            $data[$field] = $value === '' ? null : $value;
        }

        return $data;
    }

    private function bodyPayload(ClubGovernanceBody $body, bool $canManage): array
    {
        return [
            'id' => $body->id,
            'club_id' => $body->club_id,
            'type' => $body->type,
            'name' => $body->name,
            'description' => $body->description,
            'starts_on' => $body->starts_on?->format('Y-m-d'),
            'ends_on' => $body->ends_on?->format('Y-m-d'),
            'is_public' => (bool) $body->is_public,
            'assignments' => $body->assignments->map(fn (ClubGovernanceAssignment $assignment) => $this->assignmentPayload($assignment, $canManage))->values(),
        ];
    }

    private function assignmentPayload(ClubGovernanceAssignment $assignment, bool $canManage): array
    {
        return [
            'id' => $assignment->id,
            'position_title' => $assignment->position_title,
            'responsibilities' => $assignment->responsibilities,
            'starts_on' => $assignment->starts_on?->format('Y-m-d'),
            'ends_on' => $assignment->ends_on?->format('Y-m-d'),
            'is_public' => (bool) $assignment->is_public,
            'person' => ['name' => $assignment->user?->name ?? $assignment->externalMember?->name],
            ...($canManage ? [
                'user_id' => $assignment->user_id,
                'club_external_member_id' => $assignment->club_external_member_id,
            ] : []),
        ];
    }

    private function memberOptions(Club $club): array
    {
        $users = $club->users()->orderBy('name')->get(['users.id', 'name'])->map(fn ($user) => [
            'kind' => 'user', 'id' => $user->id, 'name' => $user->name,
        ]);
        $external = $club->externalMembers()->orderBy('name')->get(['id', 'name'])->map(fn ($member) => [
            'kind' => 'external', 'id' => $member->id, 'name' => $member->name,
        ]);

        return $users->concat($external)->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values()->all();
    }

    private function audit(Club $club, Request $request, string $type, $subject, string $entityType): void
    {
        ClubAuditLog::record($club, $request->user(), $type, $subject, ['entity_type' => $entityType]);
    }
}
