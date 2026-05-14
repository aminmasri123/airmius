<?php

namespace App\Support;

use App\Models\CommerceOrder;
use App\Models\MarketplaceProduct;
use App\Models\User;
use App\Support\Roles;

class CommerceOrderNotifier
{
    public function notifySalesRecipients(CommerceOrder $order): void
    {
        if (! in_array($order->type, ['marketplace_product', 'marketplace_cart'], true)) {
            return;
        }

        $order->loadMissing(['items.orderable', 'orderable', 'user']);
        $recipientIds = $this->recipientIds($order);

        foreach ($recipientIds as $userId) {
            $recipient = User::query()->find($userId);

            if (! $recipient) {
                continue;
            }

            AppNotification::send($recipient, 'commerce.order.created', [
                'title' => 'Neue Marketplace-Bestellung',
                'body' => $this->body($order),
                'url' => $recipient->can('commerce.orders.manage')
                    ? route('admin.commerce.index', ['tab' => 'orders'])
                    : route('auth.commerce.index', ['tab' => 'invoices']),
                'order_id' => $order->id,
                'amount_cents' => $order->amount_cents,
                'currency' => $order->currency,
            ]);
        }
    }

    public function notifyIssueReported(CommerceOrder $order): void
    {
        if (! in_array($order->type, ['marketplace_product', 'marketplace_cart'], true)) {
            return;
        }

        $order->loadMissing(['items.orderable', 'orderable', 'user']);
        $recipientIds = $this->recipientIds($order);
        $customer = $order->user?->name ?: $order->guest_name ?: $order->guest_email ?: 'Ein Kunde';
        $note = trim((string) $order->issue_note);
        $body = $customer.' hat ein Problem zu Bestellung #'.$order->id.' gemeldet.';

        if ($note !== '') {
            $body .= ' Nachricht: '.str($note)->limit(160);
        }

        foreach ($recipientIds as $userId) {
            $recipient = User::query()->find($userId);

            if (! $recipient) {
                continue;
            }

            AppNotification::send($recipient, 'commerce.order.issue_reported', [
                'title' => 'Problem zu Bestellung gemeldet',
                'body' => $body,
                'url' => $recipient->can('commerce.orders.manage')
                    ? route('admin.commerce.index', ['tab' => 'orders'])
                    : route('auth.commerce.index', ['tab' => 'invoices']),
                'order_id' => $order->id,
                'issue_status' => $order->issue_status,
            ]);
        }
    }

    public function notifyBuyerCancelled(CommerceOrder $order): void
    {
        if (! in_array($order->type, ['marketplace_product', 'marketplace_cart'], true)) {
            return;
        }

        $order->loadMissing(['items.orderable', 'orderable', 'user']);
        $recipientIds = $this->recipientIds($order);
        $customer = $order->user?->name ?: $order->guest_name ?: $order->guest_email ?: 'Ein Kunde';
        $body = $customer.' hat Bestellung #'.$order->id.' vor dem Versand storniert.';

        foreach ($recipientIds as $userId) {
            $recipient = User::query()->find($userId);

            if (! $recipient) {
                continue;
            }

            AppNotification::send($recipient, 'commerce.order.cancelled_by_buyer', [
                'title' => 'Bestellung storniert',
                'body' => $body,
                'url' => $recipient->can('commerce.orders.manage')
                    ? route('admin.commerce.index', ['tab' => 'orders'])
                    : route('auth.commerce.index', ['tab' => 'invoices', 'order' => $order->id]),
                'order_id' => $order->id,
            ]);
        }
    }

    private function recipientIds(CommerceOrder $order): array
    {
        $sellerIds = $order->items
            ->map(fn ($item) => $item->orderable instanceof MarketplaceProduct ? $item->orderable->user_id : null)
            ->push($order->orderable instanceof MarketplaceProduct ? $order->orderable->user_id : null)
            ->filter()
            ->map(fn ($id) => (int) $id);

        $managerIds = User::query()
            ->where(function ($query) {
                $query
                    ->permission('commerce.orders.manage')
                    ->orWhereHas('roles', fn ($roles) => $roles->whereIn('name', array_merge(Roles::FULL_ACCESS, Roles::MARKETPLACE_OPERATIONS)));
            })
            ->pluck('users.id')
            ->map(fn ($id) => (int) $id);

        return $sellerIds
            ->merge($managerIds)
            ->unique()
            ->values()
            ->all();
    }

    private function body(CommerceOrder $order): string
    {
        $customer = $order->user?->name ?: $order->guest_name ?: $order->guest_email ?: 'Ein Kunde';
        $amount = number_format(((int) $order->amount_cents) / 100, 2, ',', '.').' '.($order->currency ?: 'EUR');

        return $customer.' hat eine Bestellung über '.$amount.' aufgegeben.';
    }
}
