<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\SupportAccessService;
use App\Services\SupportSlaService;
use App\Support\AppNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class SupportTicketController extends Controller
{
    public function __construct(
        private readonly SupportSlaService $sla,
        private readonly SupportAccessService $access,
    ) {}

    public function index(Request $request)
    {
        $tickets = SupportTicket::query()
            ->where('user_id', $request->user()->id)
            ->with('club:id,name')
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
            'category' => ['required', Rule::in(['technical', 'club', 'membership', 'payment', 'privacy'])],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'club_id' => ['nullable', 'integer', Rule::exists('clubs', 'id')],
        ]);

        $user = $request->user();
        $clubId = $this->access->requesterClubId($user, $data['club_id'] ?? null, $data['category']);
        $ticket = SupportTicket::create([
            'user_id' => $user->id,
            'club_id' => $clubId,
            'name' => $user->name,
            'email' => $user->email,
            'subject' => trim($data['subject']),
            'message' => trim($data['message']),
            'category' => $data['category'],
            'priority' => $data['priority'],
            'status' => 'open',
            ...$this->sla->initialAttributes($data['priority']),
        ]);
        $ticket->load('club:id,name');

        return response()->json([
            'message' => __('support.responses.created'),
            'data' => $this->payload($ticket, false),
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
            'requester' => $ticket->relationLoaded('user') && $ticket->user ? [
                'id' => $ticket->user->id,
                'name' => $ticket->user->name,
                'email' => $ticket->user->email,
            ] : null,
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
        }

        return $payload;
    }

    public function adminIndex(Request $request)
    {
        $scope = $this->supportScope($request);
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['open', 'in_progress', 'waiting_user', 'resolved', 'closed'])],
            'priority' => ['nullable', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'category' => ['nullable', Rule::in(['technical', 'club', 'membership', 'payment', 'privacy'])],
            'club_id' => ['nullable', 'integer', Rule::exists('clubs', 'id')],
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'overdue' => ['nullable', 'boolean'],
        ]);

        if (! $scope['global'] && isset($filters['club_id'])) {
            abort_unless(in_array((int) $filters['club_id'], $scope['club_ids'], true), 403);
        }

        $baseQuery = $this->scopedSupportQuery($scope);
        $tickets = (clone $baseQuery)
            ->with(['club:id,name', 'user:id,name,email', 'assignee:id,name,email'])
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['priority'] ?? null, fn ($query, string $priority) => $query->where('priority', $priority))
            ->when($filters['category'] ?? null, fn ($query, string $category) => $query->where('category', $category))
            ->when($filters['club_id'] ?? null, fn ($query, int $clubId) => $query->where('club_id', $clubId))
            ->when($filters['assigned_to'] ?? null, fn ($query, int $assigneeId) => $query->where('assigned_to', $assigneeId))
            ->when(($filters['overdue'] ?? false) === true, fn ($query) => $this->whereSlaBreached($query))
            ->latest('id')
            ->limit(100)
            ->get();

        return response()->json(['data' => [
            'tickets' => $tickets->map(fn (SupportTicket $ticket) => $this->payload($ticket, true))->values(),
            'summary' => $this->slaSummary(clone $baseQuery),
            'tenants' => $this->tenantSlaReport(clone $baseQuery),
            'filters' => [
                'status' => $filters['status'] ?? '',
                'priority' => $filters['priority'] ?? '',
                'category' => $filters['category'] ?? '',
                'club_id' => isset($filters['club_id']) ? (int) $filters['club_id'] : null,
                'assigned_to' => isset($filters['assigned_to']) ? (int) $filters['assigned_to'] : null,
                'overdue' => (bool) ($filters['overdue'] ?? false),
            ],
            'abilities' => [
                'manage' => true,
                'cross_tenant' => $scope['global'],
                'club_ids' => $scope['global'] ? [] : $scope['club_ids'],
            ],
            'sla_policy_version' => SupportSlaService::VERSION,
        ]]);
    }

    public function adminUpdate(Request $request, SupportTicket $supportTicket)
    {
        $scope = $this->supportScope($request);
        abort_unless(
            $scope['global'] || ($supportTicket->club_id && in_array((int) $supportTicket->club_id, $scope['club_ids'], true)),
            403,
        );
        $data = $request->validate([
            'status' => ['sometimes', Rule::in(['open', 'in_progress', 'waiting_user', 'resolved', 'closed'])],
            'priority' => ['sometimes', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'escalated' => ['sometimes', 'boolean'],
            'admin_note' => ['nullable', 'string', 'max:4000'],
        ]);

        if (array_key_exists('assigned_to', $data) && $data['assigned_to'] !== null) {
            $assignee = User::query()->findOrFail((int) $data['assigned_to']);
            $assigneeScope = $this->access->operatorScope($assignee);
            $canHandleTicket = $assigneeScope['global']
                || ($supportTicket->club_id && in_array((int) $supportTicket->club_id, $assigneeScope['club_ids'], true));
            abort_unless($canHandleTicket, 403);
        }

        $originalStatus = $supportTicket->status;
        $originalPriority = $supportTicket->priority;
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
            'data' => $this->payload($supportTicket->fresh(['club:id,name', 'user:id,name,email', 'assignee:id,name,email']), true),
        ]);
    }

    /** @return array{global: bool, club_ids: array<int, int>} */
    private function supportScope(Request $request): array
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $scope = $this->access->operatorScope($user);
        abort_unless($scope['global'] || $scope['club_ids'] !== [], 403);

        return $scope;
    }

    private function scopedSupportQuery(array $scope)
    {
        return SupportTicket::query()
            ->when(! $scope['global'], fn ($query) => $query->whereIn('club_id', $scope['club_ids']));
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
}
