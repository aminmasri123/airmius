<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\ClubDepartment;
use App\Models\SupportTicket;
use App\Models\SupportTicketConfidentialAudit;
use App\Models\Team;
use App\Models\User;
use App\Services\SupportAccessService;
use App\Services\SupportSlaService;
use App\Support\AppNotification;
use App\Support\ClubPermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class SupportTicketController extends Controller
{
    private const SUPPORT_CATEGORIES = ['technical', 'club', 'membership', 'payment', 'privacy', 'safety'];

    private const SAFETY_REPORT_TYPES = ['safeguarding', 'accident', 'insurance', 'conduct', 'privacy', 'other'];

    private const CONFIDENTIAL_CASE_GROUPS = [
        'safeguarding_minor',
        'anti_violence',
        'privacy_incident',
        'medical_emergency',
        'conduct_review',
    ];

    public function __construct(
        private readonly SupportSlaService $sla,
        private readonly SupportAccessService $access,
    ) {}

    public function index(Request $request)
    {
        $tickets = SupportTicket::query()
            ->where('user_id', $request->user()->id)
            ->with(['club:id,name', 'department:id,name', 'team:id,name'])
            ->latest('id')
            ->limit(50)
            ->get();

        return response()->json(['data' => $tickets->map(fn (SupportTicket $ticket) => $this->payload($ticket, false))]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:160'],
            'message' => ['required', 'string', 'min:10', 'max:10000'],
            'category' => ['required', Rule::in(self::SUPPORT_CATEGORIES)],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'club_id' => ['nullable', 'integer', Rule::exists('clubs', 'id')],
            'club_department_id' => ['nullable', 'integer', 'min:1'],
            'team_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $user = $request->user();
        $clubId = $this->access->requesterClubId($user, $data['club_id'] ?? null, $data['category']);
        $context = $this->access->requesterContext(
            $user,
            $clubId,
            $data['club_department_id'] ?? null,
            $data['team_id'] ?? null,
        );
        $ticket = SupportTicket::create([
            'user_id' => $user->id,
            'club_id' => $clubId,
            ...$context,
            'name' => $user->name,
            'email' => $user->email,
            'subject' => trim($data['subject']),
            'message' => trim($data['message']),
            'category' => $data['category'],
            'priority' => $data['priority'],
            'status' => 'open',
            ...$this->sla->initialAttributes($data['priority']),
        ]);
        $ticket->load(['club:id,name', 'department:id,name', 'team:id,name']);

        return response()->json([
            'message' => __('support.responses.created'),
            'data' => $this->payload($ticket, false),
        ], 201);
    }

    public function storeSafetyReport(Request $request)
    {
        $data = $request->validate([
            'club_id' => ['required', 'integer', Rule::exists('clubs', 'id')],
            'club_department_id' => ['nullable', 'integer', 'min:1'],
            'team_id' => ['nullable', 'integer', 'min:1'],
            'subject' => ['required', 'string', 'max:160'],
            'message' => ['required', 'string', 'min:20', 'max:10000'],
            'report_type' => ['required', Rule::in(self::SAFETY_REPORT_TYPES)],
            'case_group' => ['nullable', Rule::in(self::CONFIDENTIAL_CASE_GROUPS)],
            'priority' => ['nullable', Rule::in(['normal', 'high', 'urgent'])],
            'anonymous' => ['sometimes', 'boolean'],
            'allow_follow_up' => ['sometimes', 'boolean'],
            'contact_name' => ['nullable', 'string', 'max:160', 'required_unless:anonymous,true'],
            'contact_email' => ['nullable', 'email:rfc', 'max:255', 'required_unless:anonymous,true'],
            'affected_person_reference' => ['nullable', 'string', 'max:160'],
        ]);

        $context = $this->access->publicClubContext(
            (int) $data['club_id'],
            $data['club_department_id'] ?? null,
            $data['team_id'] ?? null,
        );
        $anonymous = (bool) ($data['anonymous'] ?? false);
        $allowFollowUp = $anonymous ? false : (bool) ($data['allow_follow_up'] ?? true);
        $priority = $data['priority'] ?? 'high';

        $ticket = SupportTicket::query()->create([
            'user_id' => null,
            'club_id' => (int) $data['club_id'],
            ...$context,
            'name' => $anonymous ? null : trim((string) $data['contact_name']),
            'email' => $anonymous ? null : trim((string) $data['contact_email']),
            'subject' => trim($data['subject']),
            'message' => trim($data['message']),
            'category' => 'safety',
            'priority' => $priority,
            'status' => 'open',
            'is_confidential' => true,
            'is_anonymous' => $anonymous,
            'allow_follow_up' => $allowFollowUp,
            'safety_report_type' => $data['report_type'],
            'confidential_case_group' => $data['case_group'] ?? $this->defaultCaseGroup($data['report_type']),
            'affected_person_reference' => filled($data['affected_person_reference'] ?? null)
                ? trim($data['affected_person_reference'])
                : null,
            'report_source' => 'public_safety_channel',
            'confidential_at' => now(),
            ...$this->sla->initialAttributes($priority),
        ]);
        $this->appendConfidentialAudit($ticket, null, 'created', [
            'case_group' => $ticket->confidential_case_group,
            'status' => $ticket->status,
            'priority' => $ticket->priority,
            'club_id' => $ticket->club_id,
            'department_id' => $ticket->club_department_id,
            'team_id' => $ticket->team_id,
            'anonymous' => (bool) $ticket->is_anonymous,
        ]);
        $ticket->load(['club:id,name', 'department:id,name', 'team:id,name']);

        return response()->json([
            'message' => __('support.responses.created'),
            'data' => $this->publicSafetyPayload($ticket),
        ], 201);
    }

    private function payload(SupportTicket $ticket, bool $internal): array
    {
        $sla = $this->sla->state($ticket);
        $payload = [
            'id' => $ticket->id,
            'subject' => $ticket->subject,
            'message' => $ticket->message,
            'category' => $ticket->category,
            'priority' => $ticket->priority,
            'status' => $ticket->status,
            'is_confidential' => (bool) $ticket->is_confidential,
            'is_anonymous' => (bool) $ticket->is_anonymous,
            'allow_follow_up' => (bool) $ticket->allow_follow_up,
            'safety_report_type' => $ticket->safety_report_type,
            'confidential_case_group' => $ticket->confidential_case_group,
            'created_at' => $ticket->created_at?->toJSON(),
            'updated_at' => $ticket->updated_at?->toJSON(),
            'last_reply_at' => $ticket->last_reply_at?->toJSON(),
            'response_due_at' => $ticket->response_due_at?->toJSON(),
            'first_response_at' => $ticket->first_response_at?->toJSON(),
            'due_at' => $ticket->due_at?->toJSON(),
            'escalated_at' => $ticket->escalated_at?->toJSON(),
            'resolved_at' => $ticket->resolved_at?->toJSON(),
            'club' => $ticket->relationLoaded('club') && $ticket->club ? [
                'id' => $ticket->club->id,
                'name' => $ticket->club->name,
            ] : null,
            'department' => $ticket->relationLoaded('department') && $ticket->department ? [
                'id' => $ticket->department->id,
                'name' => $ticket->department->name,
            ] : null,
            'team' => $ticket->relationLoaded('team') && $ticket->team ? [
                'id' => $ticket->team->id,
                'name' => $ticket->team->name,
            ] : null,
            'requester' => $this->requesterPayload($ticket),
            'assignee' => $ticket->relationLoaded('assignee') && $ticket->assignee ? [
                'id' => $ticket->assignee->id,
                'name' => $ticket->assignee->name,
                'email' => $ticket->assignee->email,
            ] : null,
            'sla' => $sla,
            'is_overdue' => $sla['is_overdue'],
        ];

        if ($internal) {
            $payload['admin_note'] = $ticket->admin_note;
            $payload['affected_person_reference'] = $ticket->affected_person_reference;
            $payload['report_source'] = $ticket->report_source;
            $payload['confidential_at'] = $ticket->confidential_at?->toJSON();
            $payload['responsible'] = $ticket->relationLoaded('responsibleUser') && $ticket->responsibleUser ? [
                'id' => $ticket->responsibleUser->id,
                'name' => $ticket->responsibleUser->name,
                'email' => $ticket->responsibleUser->email,
            ] : null;
            $payload['conflict_user_ids'] = array_values(array_map('intval', $ticket->conflict_user_ids ?? []));
            $payload['protective_action_summary'] = $ticket->protective_action_summary;
            if ($ticket->is_anonymous) {
                $payload['requester'] = null;
            }
        }

        return $payload;
    }

    private function publicSafetyPayload(SupportTicket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'category' => $ticket->category,
            'priority' => $ticket->priority,
            'status' => $ticket->status,
            'is_confidential' => true,
            'is_anonymous' => (bool) $ticket->is_anonymous,
            'allow_follow_up' => (bool) $ticket->allow_follow_up,
            'safety_report_type' => $ticket->safety_report_type,
            'confidential_case_group' => $ticket->confidential_case_group,
            'created_at' => $ticket->created_at?->toJSON(),
            'club' => $ticket->relationLoaded('club') && $ticket->club ? [
                'id' => $ticket->club->id,
                'name' => $ticket->club->name,
            ] : null,
            'department' => $ticket->relationLoaded('department') && $ticket->department ? [
                'id' => $ticket->department->id,
                'name' => $ticket->department->name,
            ] : null,
            'team' => $ticket->relationLoaded('team') && $ticket->team ? [
                'id' => $ticket->team->id,
                'name' => $ticket->team->name,
            ] : null,
        ];
    }

    private function requesterPayload(SupportTicket $ticket): ?array
    {
        if ($ticket->relationLoaded('user') && $ticket->user) {
            return [
                'id' => $ticket->user->id,
                'name' => $ticket->user->name,
                'email' => $ticket->user->email,
            ];
        }

        if ($ticket->name || $ticket->email) {
            return [
                'id' => null,
                'name' => $ticket->name,
                'email' => $ticket->email,
            ];
        }

        return null;
    }

    public function adminIndex(Request $request)
    {
        $scope = $this->supportScope($request);
        $actionScopes = $this->actionScopes($request->user());
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['open', 'in_progress', 'waiting_user', 'resolved', 'closed'])],
            'priority' => ['nullable', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'category' => ['nullable', Rule::in(self::SUPPORT_CATEGORIES)],
            'club_id' => ['nullable', 'integer', Rule::exists('clubs', 'id')],
            'club_department_id' => ['nullable', 'integer', 'min:1', Rule::exists('club_departments', 'id')],
            'team_id' => ['nullable', 'integer', 'min:1', Rule::exists('teams', 'id')],
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'overdue' => ['nullable', 'boolean'],
        ]);

        if (! $scope['global'] && isset($filters['club_id'])) {
            abort_unless($this->scopeAllowsClub($scope, (int) $filters['club_id']), 403);
        }
        if (! $scope['global'] && isset($filters['club_department_id'])) {
            abort_unless($this->scopeAllowsDepartment($scope, (int) $filters['club_department_id']), 403);
        }
        if (! $scope['global'] && isset($filters['team_id'])) {
            abort_unless($this->scopeAllowsTeam($scope, (int) $filters['team_id']), 403);
        }

        $baseQuery = $this->scopedSupportQuery($scope);
        $tickets = (clone $baseQuery)
            ->with(['club:id,name', 'department:id,name', 'team:id,name', 'user:id,name,email', 'assignee:id,name,email', 'responsibleUser:id,name,email'])
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['priority'] ?? null, fn ($query, string $priority) => $query->where('priority', $priority))
            ->when($filters['category'] ?? null, fn ($query, string $category) => $query->where('category', $category))
            ->when($filters['club_id'] ?? null, fn ($query, int $clubId) => $query->where('club_id', $clubId))
            ->when($filters['club_department_id'] ?? null, fn ($query, int $departmentId) => $query->where('club_department_id', $departmentId))
            ->when($filters['team_id'] ?? null, fn ($query, int $teamId) => $query->where('team_id', $teamId))
            ->when($filters['assigned_to'] ?? null, fn ($query, int $assigneeId) => $query->where('assigned_to', $assigneeId))
            ->when(($filters['overdue'] ?? false) === true, fn ($query) => $this->whereSlaBreached($query))
            ->latest('id')
            ->limit(100)
            ->get();

        return response()->json(['data' => [
            'tickets' => $tickets->map(fn (SupportTicket $ticket) => [
                ...$this->payload($ticket, true),
                ...$this->ticketAbilities($ticket, $actionScopes),
            ])->values(),
            'summary' => $this->slaSummary(clone $baseQuery),
            'tenants' => $this->tenantSlaReport(clone $baseQuery),
            'filters' => [
                'status' => $filters['status'] ?? '',
                'priority' => $filters['priority'] ?? '',
                'category' => $filters['category'] ?? '',
                'club_id' => isset($filters['club_id']) ? (int) $filters['club_id'] : null,
                'club_department_id' => isset($filters['club_department_id']) ? (int) $filters['club_department_id'] : null,
                'team_id' => isset($filters['team_id']) ? (int) $filters['team_id'] : null,
                'assigned_to' => isset($filters['assigned_to']) ? (int) $filters['assigned_to'] : null,
                'overdue' => (bool) ($filters['overdue'] ?? false),
            ],
            'abilities' => [
                'manage' => true,
                'edit' => $this->scopeCanOperate($actionScopes[ClubPermissions::SUPPORT_EDIT]),
                'assign' => $this->scopeCanOperate($actionScopes[ClubPermissions::SUPPORT_ASSIGN]),
                'resolve' => $this->scopeCanOperate($actionScopes[ClubPermissions::SUPPORT_RESOLVE]),
                'cross_tenant' => $scope['global'],
                'club_ids' => $scope['global'] ? [] : $scope['club_ids'],
                'department_ids' => $scope['global'] ? [] : $scope['department_ids'],
                'team_ids' => $scope['global'] ? [] : $scope['team_ids'],
            ],
            'sla_policy_version' => SupportSlaService::VERSION,
        ]]);
    }

    public function adminUpdate(Request $request, SupportTicket $supportTicket)
    {
        $scope = $this->supportScope($request);
        abort_unless($this->scopeAllowsTicket($scope, $supportTicket), 403);
        $data = $request->validate([
            'status' => ['sometimes', Rule::in(['open', 'in_progress', 'waiting_user', 'resolved', 'closed'])],
            'priority' => ['sometimes', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'responsible_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'conflict_user_ids' => ['sometimes', 'array', 'max:20'],
            'conflict_user_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')],
            'protective_action_summary' => ['nullable', 'string', 'max:240'],
            'escalated' => ['sometimes', 'boolean'],
            'admin_note' => ['nullable', 'string', 'max:4000'],
        ]);

        $actionScopes = $this->actionScopes($request->user());
        $statusChanged = array_key_exists('status', $data) && $data['status'] !== $supportTicket->status;
        $editChanged = (array_key_exists('priority', $data) && $data['priority'] !== $supportTicket->priority)
            || (array_key_exists('admin_note', $data) && $data['admin_note'] !== $supportTicket->admin_note)
            || (array_key_exists('responsible_user_id', $data) && ($data['responsible_user_id'] === null ? null : (int) $data['responsible_user_id']) !== $supportTicket->responsible_user_id)
            || (array_key_exists('conflict_user_ids', $data) && $this->normalizedConflictIds($data['conflict_user_ids']) !== $this->normalizedConflictIds($supportTicket->conflict_user_ids ?? []))
            || (array_key_exists('protective_action_summary', $data) && $data['protective_action_summary'] !== $supportTicket->protective_action_summary)
            || (array_key_exists('escalated', $data) && (bool) $data['escalated'] !== (bool) $supportTicket->escalated_at);
        $assignmentChanged = array_key_exists('assigned_to', $data)
            && ($data['assigned_to'] === null ? null : (int) $data['assigned_to']) !== $supportTicket->assigned_to;

        if ($statusChanged) {
            $statusPermission = in_array($data['status'], ['resolved', 'closed'], true)
                ? ClubPermissions::SUPPORT_RESOLVE
                : ClubPermissions::SUPPORT_EDIT;
            abort_unless($this->scopeAllowsTicket($actionScopes[$statusPermission], $supportTicket), 403);
        }
        if ($editChanged) {
            abort_unless($this->scopeAllowsTicket($actionScopes[ClubPermissions::SUPPORT_EDIT], $supportTicket), 403);
        }
        if ($assignmentChanged) {
            abort_unless($this->scopeAllowsTicket($actionScopes[ClubPermissions::SUPPORT_ASSIGN], $supportTicket), 403);
        }

        if (array_key_exists('assigned_to', $data) && $data['assigned_to'] !== null) {
            $assignee = User::query()->findOrFail((int) $data['assigned_to']);
            $assigneeScope = $this->access->operatorScope($assignee, ClubPermissions::SUPPORT_EDIT);
            abort_unless($this->scopeAllowsTicket($assigneeScope, $supportTicket), 403);
        }
        $conflictUserIds = array_key_exists('conflict_user_ids', $data)
            ? $this->normalizedConflictIds($data['conflict_user_ids'])
            : $this->normalizedConflictIds($supportTicket->conflict_user_ids ?? []);
        $responsibleUserId = array_key_exists('responsible_user_id', $data)
            ? ($data['responsible_user_id'] === null ? null : (int) $data['responsible_user_id'])
            : $supportTicket->responsible_user_id;
        if ($responsibleUserId !== null) {
            $responsible = User::query()->findOrFail($responsibleUserId);
            $responsibleScope = $this->access->operatorScope($responsible, ClubPermissions::SUPPORT_EDIT);
            abort_unless($this->scopeAllowsTicket($responsibleScope, $supportTicket), 403);
            abort_if(in_array($responsibleUserId, $conflictUserIds, true), 422, __('validation.support_scope'));
        }
        if (array_key_exists('assigned_to', $data) && $data['assigned_to'] !== null) {
            abort_if(in_array((int) $data['assigned_to'], $conflictUserIds, true), 422, __('validation.support_scope'));
        }

        $originalStatus = $supportTicket->status;
        $originalPriority = $supportTicket->priority;
        $originalAudit = $this->confidentialAuditState($supportTicket);
        $status = $data['status'] ?? $originalStatus;
        $priority = $data['priority'] ?? $originalPriority;
        $firstResponseAt = $supportTicket->first_response_at;
        if (! $firstResponseAt && $status !== 'open') {
            $firstResponseAt = now();
        }
        $updates = [
            'status' => $status,
            'priority' => $priority,
            'assigned_to' => array_key_exists('assigned_to', $data) ? $data['assigned_to'] : $supportTicket->assigned_to,
            'responsible_user_id' => $responsibleUserId,
            'conflict_user_ids' => $conflictUserIds,
            'protective_action_summary' => array_key_exists('protective_action_summary', $data) ? $data['protective_action_summary'] : $supportTicket->protective_action_summary,
            'admin_note' => array_key_exists('admin_note', $data) ? $data['admin_note'] : $supportTicket->admin_note,
            'first_response_at' => $firstResponseAt,
            'last_reply_at' => $status !== $originalStatus || array_key_exists('admin_note', $data)
                ? now()
                : $supportTicket->last_reply_at,
            'resolved_at' => in_array($status, ['resolved', 'closed'], true)
                ? ($supportTicket->resolved_at ?: now())
                : null,
            'escalated_at' => ($data['escalated'] ?? false)
                ? ($supportTicket->escalated_at ?: now())
                : ($supportTicket->escalated_at && ! array_key_exists('escalated', $data) ? $supportTicket->escalated_at : null),
        ];

        if (array_key_exists('priority', $data)) {
            $updates = [...$updates, ...$this->sla->rebasedAttributes($supportTicket, $priority)];
        }

        $supportTicket->update($updates);
        $freshTicket = $supportTicket->fresh(['club:id,name', 'department:id,name', 'team:id,name', 'user:id,name,email', 'assignee:id,name,email', 'responsibleUser:id,name,email']);
        if ($freshTicket->is_confidential && $originalAudit !== $this->confidentialAuditState($freshTicket)) {
            $this->appendConfidentialAudit($freshTicket, $request->user(), 'updated', [
                'status' => $freshTicket->status,
                'priority' => $freshTicket->priority,
                'assigned_to' => $freshTicket->assigned_to,
                'responsible_user_id' => $freshTicket->responsible_user_id,
                'conflict_user_count' => count($freshTicket->conflict_user_ids ?? []),
                'protective_action_recorded' => filled($freshTicket->protective_action_summary),
            ]);
        }

        if ($supportTicket->user_id && ($status !== $originalStatus || $priority !== $originalPriority)) {
            AppNotification::sendLocalized(
                $supportTicket->user_id,
                'support.ticket_updated',
                'support.notifications.updated_title',
                'support.notifications.updated_body',
                [
                    'subject' => $supportTicket->subject,
                    'status' => AppNotification::translatedReplacement('support.statuses.'.$status, $status),
                ],
                [
                    'url' => '/support',
                    'support_ticket_id' => $supportTicket->id,
                ],
            );
        }

        return response()->json([
            'message' => __('support.responses.updated'),
            'data' => [
                ...$this->payload($freshTicket, true),
                ...$this->ticketAbilities($supportTicket, $actionScopes),
            ],
        ]);
    }

    /** @return array<string, array{global: bool, club_ids: array<int, int>, department_ids: array<int, int>, team_ids: array<int, int>}> */
    private function actionScopes(User $user): array
    {
        return collect([
            ClubPermissions::SUPPORT_EDIT,
            ClubPermissions::SUPPORT_ASSIGN,
            ClubPermissions::SUPPORT_RESOLVE,
        ])->mapWithKeys(fn (string $permission) => [
            $permission => $this->access->operatorScope($user, $permission),
        ])->all();
    }

    /** @param array<string, array{global: bool, club_ids: array<int, int>, department_ids: array<int, int>, team_ids: array<int, int>}> $scopes */
    private function ticketAbilities(SupportTicket $ticket, array $scopes): array
    {
        return [
            'can_edit' => $this->scopeAllowsTicket($scopes[ClubPermissions::SUPPORT_EDIT], $ticket),
            'can_assign' => $this->scopeAllowsTicket($scopes[ClubPermissions::SUPPORT_ASSIGN], $ticket),
            'can_resolve' => $this->scopeAllowsTicket($scopes[ClubPermissions::SUPPORT_RESOLVE], $ticket),
        ];
    }

    /** @param array{global: bool, club_ids: array<int, int>, department_ids: array<int, int>, team_ids: array<int, int>} $scope */
    private function scopeAllowsTicket(array $scope, SupportTicket $ticket): bool
    {
        if ($scope['global'] || ($ticket->club_id && in_array((int) $ticket->club_id, $scope['club_ids'], true))) {
            return true;
        }
        if ($ticket->team_id) {
            return in_array((int) $ticket->team_id, $scope['team_ids'], true);
        }

        return $ticket->club_department_id
            && in_array((int) $ticket->club_department_id, $scope['department_ids'], true);
    }

    /** @param array{global: bool, club_ids: array<int, int>, department_ids: array<int, int>, team_ids: array<int, int>} $scope */
    private function scopeCanOperate(array $scope): bool
    {
        return $scope['global'] || $scope['club_ids'] !== [] || $scope['department_ids'] !== [] || $scope['team_ids'] !== [];
    }

    /** @return array{global: bool, club_ids: array<int, int>, department_ids: array<int, int>, team_ids: array<int, int>} */
    private function supportScope(Request $request): array
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $scope = $this->access->operatorScope($user);
        abort_unless($this->scopeCanOperate($scope), 403);

        return $scope;
    }

    private function scopedSupportQuery(array $scope)
    {
        return SupportTicket::query()->when(! $scope['global'], function ($query) use ($scope): void {
            $query->where(function ($visible) use ($scope): void {
                $visible->whereIn('club_id', $scope['club_ids'])
                    ->orWhereIn('club_department_id', $scope['department_ids'])
                    ->orWhereIn('team_id', $scope['team_ids']);
            });
        });
    }

    private function scopeAllowsClub(array $scope, int $clubId): bool
    {
        if (in_array($clubId, $scope['club_ids'], true)) {
            return true;
        }

        return ClubDepartment::query()->where('club_id', $clubId)->whereIn('id', $scope['department_ids'])->exists()
            || Team::query()->where('club_id', $clubId)->whereIn('id', $scope['team_ids'])->exists();
    }

    private function scopeAllowsDepartment(array $scope, int $departmentId): bool
    {
        $department = ClubDepartment::query()->find($departmentId);

        return $department && (in_array((int) $department->id, $scope['department_ids'], true)
            || in_array((int) $department->club_id, $scope['club_ids'], true));
    }

    private function scopeAllowsTeam(array $scope, int $teamId): bool
    {
        $team = Team::query()->find($teamId);

        return $team && (in_array((int) $team->id, $scope['team_ids'], true)
            || in_array((int) $team->club_id, $scope['club_ids'], true));
    }

    private function whereSlaBreached($query): void
    {
        $query->where(function ($breached): void {
            $breached
                ->where(function ($response): void {
                    $response->whereNotNull('response_due_at')
                        ->where(function ($state): void {
                            $state->where(function ($pending): void {
                                $pending->whereNull('first_response_at')->where('response_due_at', '<', now());
                            })->orWhereColumn('first_response_at', '>', 'response_due_at');
                        });
                })
                ->orWhere(function ($resolution): void {
                    $resolution->whereNotNull('due_at')
                        ->where(function ($state): void {
                            $state->where(function ($pending): void {
                                $pending->whereNull('resolved_at')->where('due_at', '<', now());
                            })->orWhereColumn('resolved_at', '>', 'due_at');
                        });
                });
        });
    }

    /** @return array<string, int> */
    private function slaSummary($query): array
    {
        $responseBreach = '((first_response_at IS NULL AND response_due_at < ?) OR first_response_at > response_due_at)';
        $resolutionBreach = '((resolved_at IS NULL AND due_at < ?) OR resolved_at > due_at)';
        $row = $query
            ->selectRaw('COUNT(*) as total_count')
            ->selectRaw("SUM(CASE WHEN status IN ('open', 'in_progress', 'waiting_user') THEN 1 ELSE 0 END) as open_count")
            ->selectRaw("SUM(CASE WHEN priority = 'urgent' AND status NOT IN ('resolved', 'closed') THEN 1 ELSE 0 END) as urgent_count")
            ->selectRaw("SUM(CASE WHEN escalated_at IS NOT NULL AND status NOT IN ('resolved', 'closed') THEN 1 ELSE 0 END) as escalated_count")
            ->selectRaw("SUM(CASE WHEN {$responseBreach} THEN 1 ELSE 0 END) as response_overdue_count", [now()])
            ->selectRaw("SUM(CASE WHEN {$resolutionBreach} THEN 1 ELSE 0 END) as resolution_overdue_count", [now()])
            ->selectRaw("SUM(CASE WHEN ({$responseBreach} OR {$resolutionBreach}) THEN 1 ELSE 0 END) as overdue_count", [now(), now()])
            ->first();
        $total = (int) ($row?->total_count ?? 0);
        $overdue = (int) ($row?->overdue_count ?? 0);

        return [
            'total' => $total,
            'open' => (int) ($row?->open_count ?? 0),
            'urgent' => (int) ($row?->urgent_count ?? 0),
            'overdue' => $overdue,
            'response_overdue' => (int) ($row?->response_overdue_count ?? 0),
            'resolution_overdue' => (int) ($row?->resolution_overdue_count ?? 0),
            'escalated' => (int) ($row?->escalated_count ?? 0),
            'sla_health_percent' => $total > 0 ? (int) round((($total - $overdue) / $total) * 100) : 100,
        ];
    }

    private function tenantSlaReport($query): Collection
    {
        $rows = $query
            ->whereNotNull('club_id')
            ->selectRaw('club_id, COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status IN ('open', 'in_progress', 'waiting_user') THEN 1 ELSE 0 END) as open")
            ->selectRaw('SUM(CASE WHEN ((first_response_at IS NULL AND response_due_at < ?) OR first_response_at > response_due_at OR (resolved_at IS NULL AND due_at < ?) OR resolved_at > due_at) THEN 1 ELSE 0 END) as overdue', [now(), now()])
            ->groupBy('club_id')
            ->orderByDesc('overdue')
            ->orderByDesc('open')
            ->limit(100)
            ->get();
        $clubNames = Club::query()
            ->whereIn('id', $rows->pluck('club_id'))
            ->pluck('name', 'id');

        return $rows->map(function ($row) use ($clubNames): array {
            $total = (int) $row->total;
            $overdue = (int) $row->overdue;

            return [
                'club_id' => (int) $row->club_id,
                'club_name' => $clubNames[$row->club_id] ?? __('support.tenant.unknown'),
                'total' => $total,
                'open' => (int) $row->open,
                'overdue' => $overdue,
                'sla_health_percent' => $total > 0 ? (int) round((($total - $overdue) / $total) * 100) : 100,
            ];
        })->values();
    }

    private function defaultCaseGroup(string $reportType): string
    {
        return match ($reportType) {
            'safeguarding' => 'safeguarding_minor',
            'accident', 'insurance' => 'medical_emergency',
            'privacy' => 'privacy_incident',
            default => 'conduct_review',
        };
    }

    private function normalizedConflictIds(array $ids): array
    {
        return collect($ids)->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();
    }

    private function confidentialAuditState(SupportTicket $ticket): array
    {
        return [
            'status' => $ticket->status,
            'priority' => $ticket->priority,
            'assigned_to' => $ticket->assigned_to,
            'responsible_user_id' => $ticket->responsible_user_id,
            'conflict_user_ids' => $this->normalizedConflictIds($ticket->conflict_user_ids ?? []),
            'protective_action_recorded' => filled($ticket->protective_action_summary),
        ];
    }

    private function appendConfidentialAudit(SupportTicket $ticket, ?User $actor, string $event, array $metadata): void
    {
        $previous = SupportTicketConfidentialAudit::query()
            ->where('support_ticket_id', $ticket->id)
            ->latest('id')
            ->first();
        $payload = [
            'support_ticket_id' => (int) $ticket->id,
            'actor_id' => $actor?->id,
            'event' => $event,
            'metadata' => $metadata,
            'previous_hash' => $previous?->event_hash,
        ];
        $payload['event_hash'] = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        SupportTicketConfidentialAudit::query()->create($payload);
    }
}
