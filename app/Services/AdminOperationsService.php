<?php

namespace App\Services;

use App\Models\Club;
use App\Models\CommerceAuditLog;
use App\Models\CommerceOrder;
use App\Models\CommerceReturnRequest;
use App\Models\ContentReport;
use App\Models\MailDelivery;
use App\Models\MarketplacePayout;
use App\Models\MarketplaceSellerApplication;
use App\Models\ModerationFlag;
use App\Models\ModerationLog;
use App\Models\OutfitDelivery;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\UserRoleApplication;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AdminOperationsService
{
    public const WORKSPACE_PLATFORM = 'platform';

    public const WORKSPACE_TRUST = 'trust';

    public const WORKSPACE_REVENUE = 'revenue';

    private const SOURCE_LIMIT = 8;

    private const TIMELINE_LIMIT = 12;

    /** @return array<int, array{key: string, label: string, description: string, icon: string, access_mode: string}> */
    public function workspaces(User $user): array
    {
        return collect([
            $this->canPlatform($user) ? [
                'key' => self::WORKSPACE_PLATFORM,
                'label' => __('admin_operations.workspaces.platform.label'),
                'description' => __('admin_operations.workspaces.platform.description'),
                'icon' => 'las la-server',
                'access_mode' => $user->can('system.manage') || $user->can('support.tickets') ? 'operate' : 'metadata',
            ] : null,
            $this->canTrust($user) ? [
                'key' => self::WORKSPACE_TRUST,
                'label' => __('admin_operations.workspaces.trust.label'),
                'description' => __('admin_operations.workspaces.trust.description'),
                'icon' => 'las la-shield-alt',
                'access_mode' => 'operate',
            ] : null,
            $this->canRevenue($user) ? [
                'key' => self::WORKSPACE_REVENUE,
                'label' => __('admin_operations.workspaces.revenue.label'),
                'description' => __('admin_operations.workspaces.revenue.description'),
                'icon' => 'las la-chart-line',
                'access_mode' => 'operate',
            ] : null,
        ])->filter()->values()->all();
    }

    public function canView(User $user): bool
    {
        return $this->workspaces($user) !== [];
    }

    /** @return array<string, mixed> */
    public function payload(User $user, string $workspace): array
    {
        $allowed = collect($this->workspaces($user))->firstWhere('key', $workspace);
        abort_unless($allowed, 403);

        [$cases, $sourceState, $timeline] = match ($workspace) {
            self::WORKSPACE_PLATFORM => $this->platformPayload($user),
            self::WORKSPACE_TRUST => $this->trustPayload($user),
            self::WORKSPACE_REVENUE => $this->revenuePayload($user),
            default => abort(404),
        };

        $cases = $this->sortCases($cases);

        return [
            'version' => 1,
            'workspace' => $workspace,
            'generated_at' => now()->toIso8601String(),
            'summary' => [
                'visible' => count($cases),
                'urgent' => collect($cases)->where('priority', 'urgent')->count(),
                'overdue' => collect($cases)->where('is_overdue', true)->count(),
                'sources' => count($sourceState),
                'has_more' => collect($sourceState)->contains('has_more', true),
            ],
            'sources' => $sourceState,
            'cases' => $cases,
            'timeline' => $timeline,
            'privacy' => [
                'projection' => 'minimum-necessary',
                'contains_personal_contact_data' => false,
                'contains_free_text' => false,
                'contains_raw_audit_payloads' => false,
            ],
        ];
    }

    /** @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>, 2: array<int, array<string, mixed>>} */
    private function platformPayload(User $user): array
    {
        $cases = [];
        $sources = [];

        if ($user->can('system.manage')) {
            [$rows, $more] = $this->limited(
                Club::query()
                    ->whereIn('verification_status', ['pending', 'pending_verification'])
                    ->latest('verification_requested_at')
                    ->latest('id'),
                ['id', 'verification_status', 'verification_requested_at', 'created_at'],
            );
            foreach ($rows as $club) {
                $opened = $club->verification_requested_at ?: $club->created_at;
                $cases[] = $this->case(
                    self::WORKSPACE_PLATFORM,
                    'club_verification',
                    $club->id,
                    $club->verification_status,
                    'normal',
                    $opened,
                    $opened?->copy()->addDays(3),
                    route('admin.club-verifications.index', absolute: false),
                );
            }
            $sources[] = $this->source('club_verification', count($rows), $more);

            [$rows, $more] = $this->limited(
                UserRoleApplication::query()
                    ->where('type', UserRoleApplication::TYPE_TRAINER)
                    ->where('status', UserRoleApplication::STATUS_PENDING)
                    ->latest('requested_at')
                    ->latest('id'),
                ['id', 'type', 'status', 'requested_at', 'created_at'],
            );
            foreach ($rows as $application) {
                $opened = $application->requested_at ?: $application->created_at;
                $cases[] = $this->case(
                    self::WORKSPACE_PLATFORM,
                    'trainer_application',
                    $application->id,
                    $application->status,
                    'normal',
                    $opened,
                    $opened?->copy()->addDays(2),
                    route('admin.trainer-applications.index', absolute: false),
                );
            }
            $sources[] = $this->source('trainer_application', count($rows), $more);
        }

        if ($user->can('system.manage') || $user->can('logs.view') || $user->can('api.manage')) {
            [$rows, $more] = $this->limited(
                MailDelivery::query()->where('status', 'failed')->latest('id'),
                ['id', 'mail_type', 'status', 'created_at'],
            );
            foreach ($rows as $delivery) {
                $opened = $delivery->created_at;
                $cases[] = $this->case(
                    self::WORKSPACE_PLATFORM,
                    'mail_delivery',
                    $delivery->id,
                    $delivery->status,
                    'high',
                    $opened,
                    $opened?->copy()->addHour(),
                    $user->can('system.manage')
                        ? route('admin.mail-center.index', ['status' => 'failed'], false)
                        : null,
                    ['channel' => $this->safeToken($delivery->mail_type)],
                );
            }
            $sources[] = $this->source('mail_delivery', count($rows), $more);
        }

        if ($user->can('support.tickets') || $user->can('system.manage')) {
            [$rows, $more] = $this->limited(
                SupportTicket::query()
                    ->whereIn('status', ['open', 'in_progress', 'waiting_user'])
                    ->orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'normal' THEN 3 ELSE 4 END")
                    ->latest('id'),
                ['id', 'category', 'priority', 'status', 'response_due_at', 'first_response_at', 'due_at', 'resolved_at', 'created_at'],
            );
            foreach ($rows as $ticket) {
                $responseDue = $ticket->first_response_at ? null : $ticket->response_due_at;
                $cases[] = $this->case(
                    self::WORKSPACE_PLATFORM,
                    'support_ticket',
                    $ticket->id,
                    $ticket->status,
                    in_array($ticket->priority, ['low', 'normal', 'high', 'urgent'], true) ? $ticket->priority : 'normal',
                    $ticket->created_at,
                    $this->earliest($responseDue, $ticket->resolved_at ? null : $ticket->due_at),
                    route('auth.support.index', ['mode' => 'operations'], false),
                    ['category' => $this->safeToken($ticket->category)],
                );
            }
            $sources[] = $this->source('support_ticket', count($rows), $more);
        }

        $timeline = collect($cases)
            ->sortByDesc('opened_at')
            ->take(self::TIMELINE_LIMIT)
            ->map(fn (array $case) => $this->caseTimeline($case))
            ->values()
            ->all();

        return [$cases, $sources, $timeline];
    }

    /** @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>, 2: array<int, array<string, mixed>>} */
    private function trustPayload(User $user): array
    {
        $target = $user->can('moderation.manage')
            ? route('admin.moderation.index', absolute: false)
            : null;
        $cases = [];
        $sources = [];

        [$rows, $more] = $this->limited(
            ModerationFlag::query()->where('status', 'open')->latest('id'),
            ['id', 'severity', 'status', 'created_at'],
        );
        foreach ($rows as $flag) {
            $priority = match ($flag->severity) {
                'critical', 'high' => 'urgent',
                'medium' => 'high',
                default => 'normal',
            };
            $hours = match ($priority) {
                'urgent' => 4,
                'high' => 24,
                default => 72,
            };
            $cases[] = $this->case(
                self::WORKSPACE_TRUST,
                'moderation_flag',
                $flag->id,
                $flag->status,
                $priority,
                $flag->created_at,
                $flag->created_at?->copy()->addHours($hours),
                $target,
            );
        }
        $sources[] = $this->source('moderation_flag', count($rows), $more);

        [$rows, $more] = $this->limited(
            ContentReport::query()
                ->where(fn (Builder $query) => $query
                    ->where('status', 'open')
                    ->orWhere('appeal_status', 'pending'))
                ->latest('id'),
            ['id', 'status', 'appeal_status', 'appealed_at', 'created_at'],
        );
        foreach ($rows as $report) {
            $isAppeal = $report->appeal_status === 'pending';
            $opened = $isAppeal ? ($report->appealed_at ?: $report->created_at) : $report->created_at;
            $cases[] = $this->case(
                self::WORKSPACE_TRUST,
                $isAppeal ? 'content_appeal' : 'content_report',
                $report->id,
                $isAppeal ? 'appeal_pending' : $report->status,
                $isAppeal ? 'urgent' : 'high',
                $opened,
                $opened?->copy()->addHours($isAppeal ? 24 : 48),
                $target,
            );
        }
        $sources[] = $this->source('content_report', count($rows), $more);

        $timeline = ModerationLog::query()
            ->latest('id')
            ->limit(self::TIMELINE_LIMIT)
            ->get(['id', 'case_type', 'case_id', 'action', 'new_status', 'created_at'])
            ->map(fn (ModerationLog $log) => [
                'key' => 'moderation-log:'.$log->id,
                'kind' => $log->case_type === ContentReport::class ? 'content_report' : 'moderation_flag',
                'label' => __('admin_operations.timeline.review_recorded'),
                'event' => $this->safeToken($log->action),
                'status' => $this->safeToken($log->new_status),
                'occurred_at' => $log->created_at?->toIso8601String(),
                'target_url' => $target,
            ])
            ->values()
            ->all();

        return [$cases, $sources, $timeline];
    }

    /** @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>, 2: array<int, array<string, mixed>>} */
    private function revenuePayload(User $user): array
    {
        $target = $user->can('subscriptions.manage')
            ? route('admin.commerce.index', absolute: false)
            : null;
        $cases = [];
        $sources = [];

        [$rows, $more] = $this->limited(
            CommerceOrder::query()
                ->whereIn('issue_status', ['reported', 'reviewing'])
                ->latest('issue_reported_at')
                ->latest('id'),
            ['id', 'status', 'issue_status', 'issue_reported_at', 'amount_cents', 'currency', 'created_at'],
        );
        foreach ($rows as $order) {
            $opened = $order->issue_reported_at ?: $order->created_at;
            $cases[] = $this->case(
                self::WORKSPACE_REVENUE,
                'order_issue',
                $order->id,
                $order->issue_status,
                $order->issue_status === 'reported' ? 'urgent' : 'high',
                $opened,
                $opened?->copy()->addDay(),
                $target,
                $this->amountMeta($order->amount_cents, $order->currency),
            );
        }
        $sources[] = $this->source('order_issue', count($rows), $more);

        [$rows, $more] = $this->limited(
            CommerceReturnRequest::query()
                ->whereIn('status', ['requested', 'approved', 'received'])
                ->latest('requested_at')
                ->latest('id'),
            ['id', 'status', 'requested_amount_cents', 'currency', 'requested_at', 'created_at'],
        );
        foreach ($rows as $returnRequest) {
            $opened = $returnRequest->requested_at ?: $returnRequest->created_at;
            $cases[] = $this->case(
                self::WORKSPACE_REVENUE,
                'return_request',
                $returnRequest->id,
                $returnRequest->status,
                $returnRequest->status === 'requested' ? 'high' : 'normal',
                $opened,
                $opened?->copy()->addDays(3),
                $target,
                $this->amountMeta($returnRequest->requested_amount_cents, $returnRequest->currency),
            );
        }
        $sources[] = $this->source('return_request', count($rows), $more);

        [$rows, $more] = $this->limited(
            MarketplaceSellerApplication::query()->where('status', 'pending')->latest('id'),
            ['id', 'status', 'created_at'],
        );
        foreach ($rows as $application) {
            $cases[] = $this->case(
                self::WORKSPACE_REVENUE,
                'seller_application',
                $application->id,
                $application->status,
                'normal',
                $application->created_at,
                $application->created_at?->copy()->addDays(3),
                $target,
            );
        }
        $sources[] = $this->source('seller_application', count($rows), $more);

        [$rows, $more] = $this->limited(
            MarketplacePayout::query()
                ->where(fn (Builder $query) => $query
                    ->whereIn('status', ['requested', 'prepared'])
                    ->orWhere('reconciliation_status', 'seller_recovery_required'))
                ->latest('id'),
            ['id', 'status', 'reconciliation_status', 'amount_cents', 'recovery_cents', 'currency', 'created_at'],
        );
        foreach ($rows as $payout) {
            $recovery = $payout->reconciliation_status === 'seller_recovery_required';
            $cases[] = $this->case(
                self::WORKSPACE_REVENUE,
                'payout',
                $payout->id,
                $recovery ? $payout->reconciliation_status : $payout->status,
                $recovery ? 'urgent' : 'normal',
                $payout->created_at,
                $payout->created_at?->copy()->addDays($recovery ? 1 : 3),
                $target,
                $this->amountMeta($recovery ? $payout->recovery_cents : $payout->amount_cents, $payout->currency),
            );
        }
        $sources[] = $this->source('payout', count($rows), $more);

        if ($user->can('outfit-subscriptions.manage')) {
            [$rows, $more] = $this->limited(
                OutfitDelivery::query()
                    ->whereIn('issue_status', ['open', 'reviewing', 'approved', 'return_waiting', 'replacement_preparing'])
                    ->latest('issue_requested_at')
                    ->latest('id'),
                ['id', 'issue_status', 'issue_requested_at', 'created_at'],
            );
            foreach ($rows as $delivery) {
                $opened = $delivery->issue_requested_at ?: $delivery->created_at;
                $cases[] = $this->case(
                    self::WORKSPACE_REVENUE,
                    'outfit_issue',
                    $delivery->id,
                    $delivery->issue_status,
                    in_array($delivery->issue_status, ['open', 'reviewing'], true) ? 'high' : 'normal',
                    $opened,
                    $opened?->copy()->addDays(2),
                    route('admin.outfit-subscriptions.index', absolute: false),
                );
            }
            $sources[] = $this->source('outfit_issue', count($rows), $more);
        }

        $timeline = CommerceAuditLog::query()
            ->latest('id')
            ->limit(self::TIMELINE_LIMIT)
            ->get(['id', 'auditable_type', 'action', 'created_at'])
            ->map(fn (CommerceAuditLog $log) => [
                'key' => 'commerce-log:'.$log->id,
                'kind' => $this->commerceAuditKind($log->auditable_type),
                'label' => __('admin_operations.timeline.commerce_recorded'),
                'event' => $this->safeToken($log->action),
                'status' => null,
                'occurred_at' => $log->created_at?->toIso8601String(),
                'target_url' => $target,
            ])
            ->values()
            ->all();

        return [$cases, $sources, $timeline];
    }

    private function canPlatform(User $user): bool
    {
        return $user->can('system.manage')
            || $user->can('support.tickets')
            || $user->can('logs.view')
            || $user->can('api.manage');
    }

    private function canTrust(User $user): bool
    {
        return $user->can('moderation.manage');
    }

    private function canRevenue(User $user): bool
    {
        return $user->can('subscriptions.manage')
            || $user->can('billing.manage')
            || $user->can('finance.edit')
            || $user->can('marketplace.manage')
            || $user->can('commerce.orders.manage')
            || $user->can('outfit-subscriptions.manage');
    }

    /** @return array{0: Collection<int, mixed>, 1: bool} */
    private function limited(Builder $query, array $columns): array
    {
        $rows = $query->limit(self::SOURCE_LIMIT + 1)->get($columns);
        $hasMore = $rows->count() > self::SOURCE_LIMIT;

        return [$rows->take(self::SOURCE_LIMIT)->values(), $hasMore];
    }

    /** @return array<string, mixed> */
    private function case(
        string $workspace,
        string $kind,
        int|string $id,
        ?string $status,
        string $priority,
        ?CarbonInterface $openedAt,
        ?CarbonInterface $dueAt,
        ?string $targetUrl,
        array $meta = [],
    ): array {
        $overdue = $dueAt?->isPast() ?? false;

        return [
            'key' => $kind.':'.$id,
            'reference' => strtoupper(substr($workspace, 0, 3)).'-'.$this->kindCode($kind).'-'.str_pad((string) $id, 6, '0', STR_PAD_LEFT),
            'kind' => $kind,
            'kind_label' => __('admin_operations.kinds.'.$kind),
            'title' => __('admin_operations.case_title', [
                'kind' => __('admin_operations.kinds.'.$kind),
                'id' => $id,
            ]),
            'status' => $this->safeToken($status),
            'status_label' => __('admin_operations.statuses.'.$this->safeToken($status)),
            'priority' => $priority,
            'priority_label' => __('admin_operations.priorities.'.$priority),
            'opened_at' => $openedAt?->toIso8601String(),
            'due_at' => $dueAt?->toIso8601String(),
            'is_overdue' => $overdue,
            'target_url' => $targetUrl,
            'target_label' => $targetUrl ? __('admin_operations.actions.open_workspace') : null,
            'meta' => array_filter($meta, fn ($value) => $value !== null && $value !== ''),
        ];
    }

    /** @return array{kind: string, label: string, visible: int, has_more: bool} */
    private function source(string $kind, int $visible, bool $hasMore): array
    {
        return [
            'kind' => $kind,
            'label' => __('admin_operations.kinds.'.$kind),
            'visible' => $visible,
            'has_more' => $hasMore,
        ];
    }

    /** @param array<int, array<string, mixed>> $cases */
    private function sortCases(array $cases): array
    {
        $rank = ['urgent' => 4, 'high' => 3, 'normal' => 2, 'low' => 1];

        usort($cases, function (array $left, array $right) use ($rank): int {
            $overdue = ((int) $right['is_overdue']) <=> ((int) $left['is_overdue']);
            if ($overdue !== 0) {
                return $overdue;
            }

            $priority = ($rank[$right['priority']] ?? 0) <=> ($rank[$left['priority']] ?? 0);
            if ($priority !== 0) {
                return $priority;
            }

            return strcmp((string) $right['opened_at'], (string) $left['opened_at']);
        });

        return array_values($cases);
    }

    /** @return array<string, mixed> */
    private function caseTimeline(array $case): array
    {
        return [
            'key' => 'case-opened:'.$case['key'],
            'kind' => $case['kind'],
            'label' => __('admin_operations.timeline.case_opened'),
            'event' => 'case_opened',
            'status' => $case['status'],
            'occurred_at' => $case['opened_at'],
            'target_url' => $case['target_url'],
        ];
    }

    private function earliest(?CarbonInterface ...$dates): ?CarbonInterface
    {
        return collect($dates)->filter()->sortBy(fn (CarbonInterface $date) => $date->getTimestamp())->first();
    }

    /** @return array{amount_cents: int, currency: string}|array{} */
    private function amountMeta(mixed $amount, mixed $currency): array
    {
        if ($amount === null) {
            return [];
        }

        return [
            'amount_cents' => (int) $amount,
            'currency' => strtoupper($this->safeToken($currency) ?: 'EUR'),
        ];
    }

    private function commerceAuditKind(?string $type): string
    {
        return match ($type) {
            CommerceReturnRequest::class => 'return_request',
            MarketplacePayout::class => 'payout',
            MarketplaceSellerApplication::class => 'seller_application',
            OutfitDelivery::class => 'outfit_issue',
            default => 'order_issue',
        };
    }

    private function kindCode(string $kind): string
    {
        return match ($kind) {
            'club_verification' => 'CLB',
            'trainer_application' => 'TRN',
            'mail_delivery' => 'EML',
            'support_ticket' => 'SUP',
            'moderation_flag' => 'FLG',
            'content_report' => 'RPT',
            'content_appeal' => 'APL',
            'order_issue' => 'ORD',
            'return_request' => 'RTN',
            'seller_application' => 'SEL',
            'payout' => 'PAY',
            'outfit_issue' => 'OUT',
            default => 'OPS',
        };
    }

    private function safeToken(mixed $value): string
    {
        return substr((string) preg_replace('/[^a-zA-Z0-9_.-]/', '', (string) $value), 0, 80);
    }
}
