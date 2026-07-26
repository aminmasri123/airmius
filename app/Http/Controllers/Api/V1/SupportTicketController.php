<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Support\AppNotification;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupportTicketController extends Controller
{
    public function index(Request $request)
    {
        $tickets = SupportTicket::query()
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->limit(50)
            ->get();

        return response()->json(['data' => $tickets->map(fn (SupportTicket $ticket) => $this->payload($ticket))]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:160'],
            'message' => ['required', 'string', 'min:10', 'max:10000'],
            'category' => ['required', Rule::in(['technical', 'club', 'membership', 'payment', 'privacy'])],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
        ]);

        $user = $request->user();
        $ticket = SupportTicket::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'subject' => trim($data['subject']),
            'message' => trim($data['message']),
            'category' => $data['category'],
            'priority' => $data['priority'],
            'status' => 'open',
            'due_at' => now()->addHours($this->slaHours($data['priority'])),
        ]);

        return response()->json([
            'message' => 'Support-Ticket erstellt.',
            'data' => $this->payload($ticket),
        ], 201);
    }

    private function payload(SupportTicket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'subject' => $ticket->subject,
            'message' => $ticket->message,
            'category' => $ticket->category,
            'priority' => $ticket->priority,
            'status' => $ticket->status,
            'created_at' => $ticket->created_at?->toJSON(),
            'updated_at' => $ticket->updated_at?->toJSON(),
            'last_reply_at' => $ticket->last_reply_at?->toJSON(),
            'due_at' => $ticket->due_at?->toJSON(),
            'escalated_at' => $ticket->escalated_at?->toJSON(),
            'resolved_at' => $ticket->resolved_at?->toJSON(),
            'admin_note' => $ticket->admin_note,
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
            'is_overdue' => $ticket->due_at?->isPast() && ! in_array($ticket->status, ['resolved', 'closed'], true),
        ];
    }

    public function adminIndex(Request $request)
    {
        $this->authorizeSupport($request);
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['open', 'in_progress', 'waiting_user', 'resolved', 'closed'])],
            'priority' => ['nullable', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'overdue' => ['nullable', 'boolean'],
        ]);

        $tickets = SupportTicket::query()
            ->with(['user:id,name,email', 'assignee:id,name,email'])
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['priority'] ?? null, fn ($query, string $priority) => $query->where('priority', $priority))
            ->when(($filters['overdue'] ?? false) === true, fn ($query) => $query
                ->whereNotNull('due_at')
                ->where('due_at', '<', now())
                ->whereNotIn('status', ['resolved', 'closed']))
            ->latest('id')
            ->limit(100)
            ->get();

        return response()->json(['data' => [
            'tickets' => $tickets->map(fn (SupportTicket $ticket) => $this->payload($ticket))->values(),
            'summary' => [
                'open' => SupportTicket::query()->whereIn('status', ['open', 'in_progress', 'waiting_user'])->count(),
                'urgent' => SupportTicket::query()->where('priority', 'urgent')->whereNotIn('status', ['resolved', 'closed'])->count(),
                'overdue' => SupportTicket::query()->whereNotNull('due_at')->where('due_at', '<', now())->whereNotIn('status', ['resolved', 'closed'])->count(),
                'escalated' => SupportTicket::query()->whereNotNull('escalated_at')->whereNotIn('status', ['resolved', 'closed'])->count(),
            ],
            'filters' => [
                'status' => $filters['status'] ?? '',
                'priority' => $filters['priority'] ?? '',
                'overdue' => (bool) ($filters['overdue'] ?? false),
            ],
            'abilities' => ['manage' => true],
        ]]);
    }

    public function adminUpdate(Request $request, SupportTicket $supportTicket)
    {
        $this->authorizeSupport($request);
        $data = $request->validate([
            'status' => ['sometimes', Rule::in(['open', 'in_progress', 'waiting_user', 'resolved', 'closed'])],
            'priority' => ['sometimes', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'escalated' => ['sometimes', 'boolean'],
            'admin_note' => ['nullable', 'string', 'max:4000'],
        ]);

        $originalStatus = $supportTicket->status;
        $originalPriority = $supportTicket->priority;
        $status = $data['status'] ?? $originalStatus;
        $priority = $data['priority'] ?? $originalPriority;
        $updates = [
            'status' => $status,
            'priority' => $priority,
            'assigned_to' => array_key_exists('assigned_to', $data) ? $data['assigned_to'] : $supportTicket->assigned_to,
            'admin_note' => array_key_exists('admin_note', $data) ? $data['admin_note'] : $supportTicket->admin_note,
            'due_at' => array_key_exists('priority', $data) && ! in_array($status, ['resolved', 'closed'], true)
                ? now()->addHours($this->slaHours($priority))
                : $supportTicket->due_at,
            'resolved_at' => in_array($status, ['resolved', 'closed'], true)
                ? ($supportTicket->resolved_at ?: now())
                : null,
            'escalated_at' => ($data['escalated'] ?? false)
                ? ($supportTicket->escalated_at ?: now())
                : ($supportTicket->escalated_at && ! array_key_exists('escalated', $data) ? $supportTicket->escalated_at : null),
        ];

        $supportTicket->update($updates);

        if ($supportTicket->user_id && ($status !== $originalStatus || $priority !== $originalPriority)) {
            AppNotification::send($supportTicket->user_id, 'support.ticket_updated', [
                'title' => 'Support-Ticket aktualisiert',
                'body' => $supportTicket->subject.' ist jetzt '.$status.'.',
                'url' => '/support',
                'support_ticket_id' => $supportTicket->id,
            ]);
        }

        return response()->json([
            'message' => 'Support-Ticket aktualisiert.',
            'data' => $this->payload($supportTicket->fresh(['user:id,name,email', 'assignee:id,name,email'])),
        ]);
    }

    private function authorizeSupport(Request $request): void
    {
        abort_unless($request->user()?->can('support.tickets') || $request->user()?->can('system.manage'), 403);
    }

    private function slaHours(string $priority): int
    {
        return match ($priority) {
            'urgent' => 4,
            'high' => 24,
            'low' => 120,
            default => 72,
        };
    }
}
