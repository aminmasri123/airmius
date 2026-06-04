<?php

namespace App\Support;

use App\Models\CommerceOrder;
use App\Models\CommerceOrderItem;
use App\Models\CommerceReturnRequest;
use App\Models\MarketplaceProduct;
use Illuminate\Support\Carbon;

class CommerceOrderSupport
{
    private const OPEN_RETURN_STATUSES = ['requested', 'approved', 'received'];

    private const ACTIVE_ISSUE_STATUSES = ['reported', 'reviewing'];

    public function summary(CommerceOrder $order): array
    {
        $order->loadMissing(['items.orderable', 'returnRequests']);
        $order->items->each(fn (CommerceOrderItem $item) => $item->setRelation('order', $order));

        $issueStatus = $order->issue_status ?: 'none';
        $hasShippableItems = $order->items->contains(fn (CommerceOrderItem $item) => (bool) $item->is_shippable);
        $returnableItems = $order->items
            ->filter(fn (CommerceOrderItem $item) => (bool) $item->is_shippable && $this->itemStillReturnable($item))
            ->values();
        $openReturn = $order->returnRequests
            ->first(fn (CommerceReturnRequest $return) => in_array($return->status, self::OPEN_RETURN_STATUSES, true));
        $latestReturn = $order->returnRequests->sortByDesc('created_at')->first();

        $returnBlockedReason = $this->returnBlockedReason($order, $hasShippableItems, $returnableItems->isNotEmpty(), (bool) $openReturn);
        $canRequestReturn = $returnBlockedReason === null;
        $canReportIssue = $order->status === 'completed' && ! in_array($issueStatus, self::ACTIVE_ISSUE_STATUSES, true);

        return [
            'can_report_issue' => $canReportIssue,
            'can_request_return' => $canRequestReturn,
            'has_shippable_items' => $hasShippableItems,
            'is_delivered' => $order->shipping_status === 'delivered',
            'return_blocked_reason' => $returnBlockedReason,
            'return_window_days' => $returnableItems
                ->map(fn (CommerceOrderItem $item) => $this->itemReturnWindowDays($item))
                ->filter(fn (int $days) => $days > 0)
                ->min(),
            'return_deadline' => $returnableItems
                ->map(fn (CommerceOrderItem $item) => $this->itemReturnDeadline($item)?->toDateString())
                ->filter()
                ->sort()
                ->first(),
            'returnable_items' => $returnableItems->map(fn (CommerceOrderItem $item) => [
                'id' => $item->id,
                'title' => $item->title,
                'quantity' => $item->quantity,
                'deadline' => $this->itemReturnDeadline($item)?->toDateString(),
                'window_days' => $this->itemReturnWindowDays($item),
            ])->values(),
            'has_open_return_request' => (bool) $openReturn,
            'latest_return_request_status' => $latestReturn?->status,
            'issue_status' => $issueStatus,
            'issue_reported_at' => $order->issue_reported_at?->toIso8601String(),
            'issue_response_available' => (bool) $order->issue_response,
            'next_step' => $this->nextStep($order, $canRequestReturn, $canReportIssue, (bool) $openReturn),
        ];
    }

    public function returnBlockedReasonMessage(?string $reason): string
    {
        return match ($reason) {
            'payment_pending' => 'Rücksendungen sind erst nach bestätigter Zahlung möglich.',
            'digital_or_service' => 'Dieses digitale Angebot oder diese Dienstleistung kann nicht rückgesendet werden.',
            'shipping_pending' => 'Rücksendungen sind erst möglich, nachdem die Bestellung zugestellt wurde.',
            'return_already_requested' => 'Für diese Bestellung läuft bereits eine Rücksendung.',
            'return_window_closed' => 'Die Rücksendefrist ist abgelaufen oder ausgeschlossen.',
            default => 'Für diese Bestellung ist keine Rücksendung möglich.',
        };
    }

    public function itemStillReturnable(CommerceOrderItem $item): bool
    {
        $window = $this->itemReturnWindowDays($item);

        if ($window <= 0) {
            return false;
        }

        $completedAt = $item->order?->delivered_at ?: $item->order?->completed_at ?: $item->order?->created_at;

        return $completedAt ? $completedAt->copy()->addDays($window)->endOfDay()->isFuture() : true;
    }

    public function itemReturnDeadline(CommerceOrderItem $item): ?Carbon
    {
        $window = $this->itemReturnWindowDays($item);

        if ($window <= 0) {
            return null;
        }

        $completedAt = $item->order?->delivered_at ?: $item->order?->completed_at ?: $item->order?->created_at;

        return $completedAt ? $completedAt->copy()->addDays($window)->endOfDay() : null;
    }

    private function returnBlockedReason(CommerceOrder $order, bool $hasShippableItems, bool $hasReturnableItems, bool $hasOpenReturn): ?string
    {
        if (! in_array($order->type, ['marketplace_product', 'marketplace_cart'], true)) {
            return 'not_marketplace';
        }

        if ($order->status !== 'completed') {
            return 'payment_pending';
        }

        if (! $hasShippableItems) {
            return 'digital_or_service';
        }

        if ($order->shipping_status !== 'delivered') {
            return 'shipping_pending';
        }

        if ($hasOpenReturn) {
            return 'return_already_requested';
        }

        if (! $hasReturnableItems) {
            return 'return_window_closed';
        }

        return null;
    }

    private function nextStep(CommerceOrder $order, bool $canRequestReturn, bool $canReportIssue, bool $hasOpenReturn): string
    {
        if (in_array($order->issue_status ?: 'none', self::ACTIVE_ISSUE_STATUSES, true)) {
            return 'issue_under_review';
        }

        if ($hasOpenReturn) {
            return 'return_under_review';
        }

        if ($canRequestReturn) {
            return 'return_available';
        }

        if ($canReportIssue) {
            return 'issue_available';
        }

        return 'no_action_needed';
    }

    private function itemReturnWindowDays(CommerceOrderItem $item): int
    {
        $item->loadMissing(['order', 'orderable']);
        $product = $item->orderable instanceof MarketplaceProduct ? $item->orderable : null;
        $policy = $product?->return_policy_type ?: ($item->is_shippable ? 'standard' : 'digital');
        $window = (int) ($product?->return_window_days ?? 14);

        if (in_array($policy, ['digital', 'service', 'hygiene'], true)) {
            return 0;
        }

        return max(0, $window);
    }
}
