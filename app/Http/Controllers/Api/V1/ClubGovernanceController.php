<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\ClubGovernanceAssignment;
use App\Models\ClubGovernanceBody;
use App\Models\ClubGovernancePosition;
use App\Support\ClubAuditLog;
use App\Support\ClubGovernanceTemplateCatalog;
use App\Support\ClubPermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClubGovernanceController extends Controller
{
    private ?bool $positionSchemaReady = null;

    public function index(Request $request, Club $club)
    {
        [$canView, $canEdit, $canDelete] = $this->viewerAccess($request, $club);
        $includeInternal = $canView;
        $relations = [
            'assignments' => fn ($query) => $query
                ->when(! $includeInternal, fn ($query) => $query
                    ->where('is_public', true)
                    ->when($this->supportsPositions(), fn ($query) => $query
                        ->where(fn ($visibility) => $visibility
                            ->whereNull('club_governance_position_id')
                            ->orWhereHas('position', fn ($position) => $position->where('is_public', true)))))
                ->with(['user:id,name', 'externalMember:id,name'])
                ->orderBy('position_title'),
        ];
        if ($this->supportsPositions()) {
            $relations += [
                'positions' => fn ($query) => $query
                    ->where('is_active', true)
                    ->when(! $includeInternal, fn ($query) => $query->where('is_public', true))
                    ->orderBy('sort_order')
                    ->orderBy('id'),
                'positions.assignments' => fn ($query) => $query
                    ->when(! $includeInternal, fn ($query) => $query->where('is_public', true))
                    ->with(['user:id,name', 'externalMember:id,name'])
                    ->orderBy('starts_on')
                    ->orderBy('id'),
            ];
        }
        $bodies = $club->governanceBodies()
            ->when(! $includeInternal, fn ($query) => $query->where('is_public', true))
            ->with($relations)
            ->orderByRaw("CASE type WHEN 'board' THEN 1 WHEN 'committee' THEN 2 ELSE 3 END")
            ->orderBy('name')
            ->get();

        return response()->json(['data' => [
            'bodies' => $bodies->map(fn (ClubGovernanceBody $body) => $this->bodyPayload($body, $canEdit)),
            'member_options' => $canEdit ? $this->memberOptions($club) : [],
            'templates' => $canEdit && $this->supportsPositions() ? ClubGovernanceTemplateCatalog::all() : [],
            'can_manage' => $canEdit || $canDelete,
            'can_edit' => $canEdit,
            'can_delete' => $canDelete,
            'can_view_meetings' => $canView && ($canEdit || $club->users()
                ->where('users.id', $request->user()->id)->wherePivot('membership_status', 'active')->exists()),
        ]]);
    }

    public function storeBody(Request $request, Club $club)
    {
        $this->authorizeManage($request, $club, ClubPermissions::GOVERNANCE_EDIT);
        $bodyData = $this->bodyData($request, $club);
        $positions = $this->positionData($request, $club);

        $body = DB::transaction(function () use ($club, $request, $bodyData, $positions) {
            $body = $club->governanceBodies()->create($bodyData);
            if ($positions !== null) {
                $this->syncPositions($club, $body, $positions, $request);
            }
            $this->audit($club, $request, 'club.governance.body.created', $body, 'body');

            return $body;
        });

        return response()->json(['data' => $this->bodyPayload($this->loadBody($body), true)], 201);
    }

    public function updateBody(Request $request, Club $club, ClubGovernanceBody $governanceBody)
    {
        $this->authorizeBody($request, $club, $governanceBody, ClubPermissions::GOVERNANCE_EDIT);
        $bodyData = $this->bodyData($request, $club, $governanceBody);
        $positions = $this->positionData($request, $club);

        DB::transaction(function () use ($club, $request, $governanceBody, $bodyData, $positions): void {
            $governanceBody->update($bodyData);
            if ($positions !== null) {
                $this->syncPositions($club, $governanceBody, $positions, $request);
            }
            $this->audit($club, $request, 'club.governance.body.updated', $governanceBody, 'body');
        });

        return response()->json(['data' => $this->bodyPayload($this->loadBody($governanceBody->refresh()), true)]);
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
            ...$this->assignmentData($request, $club, $governanceBody),
            'club_id' => $club->id,
        ]);
        $this->audit($club, $request, 'club.governance.assignment.created', $assignment, 'assignment');

        return response()->json(['data' => $this->assignmentPayload($assignment->load(['user', 'externalMember']), true)], 201);
    }

    public function updateAssignment(Request $request, Club $club, ClubGovernanceBody $governanceBody, ClubGovernanceAssignment $assignment)
    {
        $this->authorizeAssignment($request, $club, $governanceBody, $assignment, ClubPermissions::GOVERNANCE_EDIT);
        $assignment->update($this->assignmentData($request, $club, $governanceBody));
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
        $rules = [
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
        ];
        if ($this->supportsPositions()) {
            $rules['template_key'] = ['nullable', 'string', Rule::in(ClubGovernanceTemplateCatalog::keys())];
        }
        $data = $request->validate($rules);

        return $this->normalize($data, ['name', 'description']);
    }

    private function assignmentData(Request $request, Club $club, ClubGovernanceBody $body): array
    {
        $rules = [
            'user_id' => [
                'nullable', 'required_without:club_external_member_id',
                Rule::prohibitedIf($request->filled('club_external_member_id')),
                Rule::exists('club_user', 'user_id')
                    ->where('club_id', $club->id)
                    ->where('membership_status', 'active'),
            ],
            'club_external_member_id' => [
                'nullable', 'required_without:user_id',
                Rule::prohibitedIf($request->filled('user_id')),
                Rule::exists('club_external_members', 'id')
                    ->where('club_id', $club->id)
                    ->where('membership_status', 'active'),
            ],
            'position_title' => ['required', 'string', 'max:160'],
            'responsibilities' => ['nullable', 'string', 'max:3000'],
            'starts_on' => ['nullable', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'is_public' => ['required', 'boolean'],
        ];
        if ($this->supportsPositions()) {
            $rules['club_governance_position_id'] = [
                'nullable', 'integer',
                Rule::exists('club_governance_positions', 'id')
                    ->where('club_id', $club->id)
                    ->where('club_governance_body_id', $body->id),
            ];
        }
        $data = $request->validate($rules);

        return $this->normalize($data, ['position_title', 'responsibilities']);
    }

    private function positionData(Request $request, Club $club): ?array
    {
        if (! $request->has('positions')) {
            return null;
        }
        if (! $this->supportsPositions()) {
            throw ValidationException::withMessages([
                'positions' => 'Die Gremienmigration muss vor dem Speichern von Funktionsstellen ausgeführt werden.',
            ]);
        }

        $validated = $request->validate([
            'positions' => ['array', 'max:30'],
            'replace_positions' => ['sometimes', 'boolean'],
            'positions.*.id' => ['nullable', 'integer'],
            'positions.*.title' => ['required', 'string', 'max:160'],
            'positions.*.responsibilities' => ['nullable', 'string', 'max:3000'],
            'positions.*.sort_order' => ['required', 'integer', 'min:0', 'max:999'],
            'positions.*.is_required' => ['required', 'boolean'],
            'positions.*.allows_multiple' => ['required', 'boolean'],
            'positions.*.is_public' => ['required', 'boolean'],
            'positions.*.replace_assignments' => ['sometimes', 'boolean'],
            'positions.*.assignments' => ['sometimes', 'array', 'max:20'],
            'positions.*.assignments.*.id' => ['nullable', 'integer'],
            'positions.*.assignments.*.user_id' => [
                'nullable', 'integer', Rule::exists('club_user', 'user_id')
                    ->where('club_id', $club->id)
                    ->where('membership_status', 'active'),
            ],
            'positions.*.assignments.*.club_external_member_id' => [
                'nullable', 'integer', Rule::exists('club_external_members', 'id')
                    ->where('club_id', $club->id)
                    ->where('membership_status', 'active'),
            ],
            'positions.*.assignments.*.starts_on' => ['nullable', 'date_format:Y-m-d'],
            'positions.*.assignments.*.ends_on' => ['nullable', 'date_format:Y-m-d'],
            'positions.*.assignments.*.is_public' => ['required', 'boolean'],
        ]);

        return [
            'items' => $validated['positions'],
            'replace' => $validated['replace_positions'] ?? false,
        ];
    }

    private function syncPositions(Club $club, ClubGovernanceBody $body, array $composition, Request $request): void
    {
        $positionIds = [];
        $positionTitles = [];
        $seenPositionIds = [];
        $seenAssignmentIds = [];
        $positions = $composition['items'];
        foreach ($positions as $positionIndex => $positionData) {
            $normalizedTitle = mb_strtolower(trim($positionData['title']));
            if (isset($positionTitles[$normalizedTitle])) {
                throw ValidationException::withMessages([
                    "positions.{$positionIndex}.title" => 'Jede Funktion darf innerhalb eines Gremiums nur einmal vorkommen.',
                ]);
            }
            $positionTitles[$normalizedTitle] = true;

            $position = isset($positionData['id'])
                ? $body->positions()->whereKey($positionData['id'])->first()
                : null;

            if (isset($positionData['id']) && ! $position) {
                throw ValidationException::withMessages([
                    "positions.{$positionIndex}.id" => 'Die Funktionsstelle gehört nicht zu diesem Gremium.',
                ]);
            }
            if ($position && isset($seenPositionIds[$position->id])) {
                throw ValidationException::withMessages([
                    "positions.{$positionIndex}.id" => 'Die Funktionsstelle wurde mehrfach übermittelt.',
                ]);
            }
            if ($position) {
                $seenPositionIds[$position->id] = true;
            }

            $position ??= new ClubGovernancePosition([
                'club_id' => $club->id,
                'club_governance_body_id' => $body->id,
            ]);
            $position->fill([
                'title' => trim($positionData['title']),
                'responsibilities' => $this->nullableTrim($positionData['responsibilities'] ?? null),
                'sort_order' => $positionData['sort_order'],
                'is_required' => $positionData['is_required'],
                'allows_multiple' => $positionData['allows_multiple'],
                'is_public' => $positionData['is_public'],
                'is_active' => true,
            ]);
            $position->save();
            $positionIds[] = $position->id;

            $assignments = $positionData['assignments'] ?? [];
            if (! $position->allows_multiple && count($assignments) > 1) {
                throw ValidationException::withMessages([
                    "positions.{$positionIndex}.assignments" => 'Diese Funktion kann nur einmal besetzt werden.',
                ]);
            }

            $identities = [];
            $assignmentIds = [];
            foreach ($assignments as $assignmentIndex => $assignmentData) {
                $userId = $assignmentData['user_id'] ?? null;
                $externalId = $assignmentData['club_external_member_id'] ?? null;
                if (($userId === null) === ($externalId === null)) {
                    throw ValidationException::withMessages([
                        "positions.{$positionIndex}.assignments.{$assignmentIndex}" => 'Bitte genau eine interne oder externe Person auswählen.',
                    ]);
                }
                if (! empty($assignmentData['starts_on']) && ! empty($assignmentData['ends_on'])
                    && $assignmentData['ends_on'] < $assignmentData['starts_on']) {
                    throw ValidationException::withMessages([
                        "positions.{$positionIndex}.assignments.{$assignmentIndex}.ends_on" => 'Das Ende darf nicht vor dem Beginn liegen.',
                    ]);
                }

                $identity = $userId !== null ? 'user:'.$userId : 'external:'.$externalId;
                if (isset($identities[$identity])) {
                    throw ValidationException::withMessages([
                        "positions.{$positionIndex}.assignments.{$assignmentIndex}" => 'Diese Person wurde für die Funktion bereits ausgewählt.',
                    ]);
                }
                $identities[$identity] = true;

                $assignment = isset($assignmentData['id'])
                    ? $body->assignments()->whereKey($assignmentData['id'])->first()
                    : null;
                if (isset($assignmentData['id']) && ! $assignment) {
                    throw ValidationException::withMessages([
                        "positions.{$positionIndex}.assignments.{$assignmentIndex}.id" => 'Die Besetzung gehört nicht zu diesem Gremium.',
                    ]);
                }
                if ($assignment && isset($seenAssignmentIds[$assignment->id])) {
                    throw ValidationException::withMessages([
                        "positions.{$positionIndex}.assignments.{$assignmentIndex}.id" => 'Die Besetzung wurde mehrfach übermittelt.',
                    ]);
                }
                if ($assignment && $assignment->club_governance_position_id
                    && (int) $assignment->club_governance_position_id !== (int) $position->id) {
                    throw ValidationException::withMessages([
                        "positions.{$positionIndex}.assignments.{$assignmentIndex}.id" => 'Die Besetzung gehört zu einer anderen Funktionsstelle.',
                    ]);
                }
                if ($assignment) {
                    $seenAssignmentIds[$assignment->id] = true;
                }

                $created = ! $assignment;
                $assignment ??= new ClubGovernanceAssignment([
                    'club_id' => $club->id,
                    'club_governance_body_id' => $body->id,
                ]);
                $assignment->fill([
                    'club_governance_position_id' => $position->id,
                    'user_id' => $userId,
                    'club_external_member_id' => $externalId,
                    'position_title' => $position->title,
                    'responsibilities' => $position->responsibilities,
                    'starts_on' => $assignmentData['starts_on'] ?? $body->starts_on,
                    'ends_on' => $assignmentData['ends_on'] ?? $body->ends_on,
                    'is_public' => $position->is_public && $assignmentData['is_public'],
                ]);
                $assignment->save();
                $assignmentIds[] = $assignment->id;
                $this->audit(
                    $club,
                    $request,
                    $created ? 'club.governance.assignment.created' : 'club.governance.assignment.updated',
                    $assignment,
                    'assignment',
                );
            }

            if ($positionData['replace_assignments'] ?? false) {
                $position->assignments()
                    ->when($assignmentIds !== [], fn ($query) => $query->whereNotIn('id', $assignmentIds))
                    ->get()
                    ->each(function (ClubGovernanceAssignment $assignment) use ($club, $request): void {
                        $this->audit($club, $request, 'club.governance.assignment.deleted', $assignment, 'assignment');
                        $assignment->delete();
                    });
            }
        }

        if ($composition['replace']) {
            $body->positions()
                ->where('is_active', true)
                ->when($positionIds !== [], fn ($query) => $query->whereNotIn('id', $positionIds))
                ->get()
                ->each(function (ClubGovernancePosition $position) use ($club, $request): void {
                    $position->assignments->each(function (ClubGovernanceAssignment $assignment) use ($club, $request): void {
                        $this->audit($club, $request, 'club.governance.assignment.deleted', $assignment, 'assignment');
                        $assignment->delete();
                    });
                    $position->update(['is_active' => false]);
                });
        }
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
            'template_key' => $this->supportsPositions() ? $body->template_key : null,
            'name' => $body->name,
            'description' => $body->description,
            'starts_on' => $body->starts_on?->format('Y-m-d'),
            'ends_on' => $body->ends_on?->format('Y-m-d'),
            'is_public' => (bool) $body->is_public,
            'positions' => $body->relationLoaded('positions')
                ? $body->positions->map(fn (ClubGovernancePosition $position) => $this->positionPayload($position, $canManage))->values()
                : [],
            'assignments' => $body->assignments->map(fn (ClubGovernanceAssignment $assignment) => $this->assignmentPayload($assignment, $canManage))->values(),
        ];
    }

    private function positionPayload(ClubGovernancePosition $position, bool $canManage): array
    {
        return [
            'id' => $position->id,
            'title' => $position->title,
            'responsibilities' => $position->responsibilities,
            'sort_order' => $position->sort_order,
            'is_required' => (bool) $position->is_required,
            'allows_multiple' => (bool) $position->allows_multiple,
            'is_public' => (bool) $position->is_public,
            'assignments' => $position->assignments
                ->map(fn (ClubGovernanceAssignment $assignment) => $this->assignmentPayload($assignment, $canManage))
                ->values(),
        ];
    }

    private function assignmentPayload(ClubGovernanceAssignment $assignment, bool $canManage): array
    {
        return [
            'id' => $assignment->id,
            'club_governance_position_id' => $assignment->club_governance_position_id,
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
        $users = $club->users()
            ->wherePivot('membership_status', 'active')
            ->select(['users.id', 'users.name'])
            ->distinct()
            ->get()
            ->map(fn ($user) => [
                'kind' => 'user', 'id' => $user->id, 'name' => $user->name,
            ]);
        $external = $club->externalMembers()
            ->where('membership_status', 'active')
            ->select(['id', 'name'])
            ->get()
            ->map(fn ($member) => [
                'kind' => 'external', 'id' => $member->id, 'name' => $member->name,
            ]);

        return $users
            ->concat($external)
            ->unique(fn (array $person) => $person['kind'].':'.$person['id'])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    private function loadBody(ClubGovernanceBody $body): ClubGovernanceBody
    {
        $relations = [
            'assignments.user',
            'assignments.externalMember',
        ];
        if ($this->supportsPositions()) {
            $relations = array_merge($relations, [
                'positions' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')->orderBy('id'),
                'positions.assignments.user',
                'positions.assignments.externalMember',
            ]);
        }

        return $body->load($relations);
    }

    private function nullableTrim(mixed $value): ?string
    {
        $trimmed = trim((string) ($value ?? ''));

        return $trimmed === '' ? null : $trimmed;
    }

    private function supportsPositions(): bool
    {
        return $this->positionSchemaReady ??= Schema::hasTable('club_governance_positions')
            && Schema::hasColumn('club_governance_bodies', 'template_key')
            && Schema::hasColumn('club_governance_assignments', 'club_governance_position_id');
    }

    private function audit(Club $club, Request $request, string $type, $subject, string $entityType): void
    {
        ClubAuditLog::record($club, $request->user(), $type, $subject, ['entity_type' => $entityType]);
    }
}
