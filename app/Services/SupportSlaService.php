<?php

namespace App\Services;

use App\Models\SupportTicket;
use Illuminate\Support\Carbon;

class SupportSlaService
{
    public const VERSION = '2026-08-09.support-sla.v1';

    private const TARGETS = [
        'urgent' => ['response' => 60, 'resolution' => 240],
        'high' => ['response' => 240, 'resolution' => 1440],
        'normal' => ['response' => 1440, 'resolution' => 4320],
        'low' => ['response' => 2880, 'resolution' => 7200],
    ];

    /** @return array{response: int, resolution: int} */
    public function targets(string $priority): array
    {
        return self::TARGETS[$priority] ?? self::TARGETS['normal'];
    }

    /** @return array<string, mixed> */
    public function initialAttributes(string $priority, ?Carbon $startedAt = null): array
    {
        $startedAt ??= now();
        $targets = $this->targets($priority);

        return [
            'sla_policy_version' => self::VERSION,
            'response_sla_target_minutes' => $targets['response'],
            'sla_target_minutes' => $targets['resolution'],
            'response_due_at' => $startedAt->copy()->addMinutes($targets['response']),
            'due_at' => $startedAt->copy()->addMinutes($targets['resolution']),
        ];
    }

    /** @return array<string, mixed> */
    public function rebasedAttributes(SupportTicket $ticket, string $priority): array
    {
        return $this->initialAttributes($priority, $ticket->created_at ?: now());
    }

    /** @return array<string, mixed> */
    public function state(SupportTicket $ticket): array
    {
        $now = now();
        $closed = in_array($ticket->status, ['resolved', 'closed'], true);
        $responseOverdue = ! $ticket->first_response_at
            && $ticket->response_due_at?->lt($now) === true;
        $resolutionOverdue = ! $closed && $ticket->due_at?->lt($now) === true;
        $responseAtRisk = ! $ticket->first_response_at
            && ! $responseOverdue
            && $this->withinRiskWindow($ticket->response_due_at, (int) $ticket->response_sla_target_minutes, $now);
        $resolutionAtRisk = ! $closed
            && ! $resolutionOverdue
            && $this->withinRiskWindow($ticket->due_at, (int) $ticket->sla_target_minutes, $now);

        return [
            'policy_version' => $ticket->sla_policy_version ?: self::VERSION,
            'state' => $responseOverdue || $resolutionOverdue
                ? 'breached'
                : ($responseAtRisk || $resolutionAtRisk ? 'at_risk' : ($closed ? 'met' : 'on_track')),
            'response_overdue' => $responseOverdue,
            'resolution_overdue' => $resolutionOverdue,
            'is_overdue' => $responseOverdue || $resolutionOverdue,
            'at_risk' => $responseAtRisk || $resolutionAtRisk,
        ];
    }

    private function withinRiskWindow(?Carbon $dueAt, int $targetMinutes, Carbon $now): bool
    {
        if (! $dueAt || $targetMinutes <= 0 || $dueAt->lte($now)) {
            return false;
        }

        return $now->diffInMinutes($dueAt) <= max(15, (int) ceil($targetMinutes * 0.25));
    }
}
