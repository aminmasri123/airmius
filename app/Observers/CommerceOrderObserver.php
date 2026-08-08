<?php

namespace App\Observers;

use App\Models\CommerceOrder;
use App\Services\DomainEventPublisher;

class CommerceOrderObserver
{
    private const STATUS_FIELDS = [
        'status',
        'shipping_status',
        'issue_status',
        'payout_status',
    ];

    public function __construct(private DomainEventPublisher $domainEvents) {}

    public function created(CommerceOrder $order): void
    {
        $this->domainEvents->record(
            'commerce.order.created.v1',
            $order,
            payload: [
                'type' => $order->type,
                'provider' => $order->provider,
                'status' => $order->status,
                'amount_cents' => $order->amount_cents,
                'currency' => $order->currency,
                'club_id' => $order->club_id,
                'orderable_type' => $order->orderable_type,
                'orderable_id' => $order->orderable_id,
            ],
            audience: ['users' => array_values(array_filter([$order->user_id]))],
        );
    }

    public function updated(CommerceOrder $order): void
    {
        $changed = collect(self::STATUS_FIELDS)
            ->filter(fn (string $field) => $order->wasChanged($field))
            ->mapWithKeys(fn (string $field) => [$field => [
                'from' => $order->getOriginal($field),
                'to' => $order->getAttribute($field),
            ]])
            ->all();

        if ($changed === []) {
            return;
        }

        $this->domainEvents->record(
            'commerce.order.status_changed.v1',
            $order,
            payload: ['changes' => $changed],
            audience: ['users' => array_values(array_filter([$order->user_id]))],
        );
    }
}
