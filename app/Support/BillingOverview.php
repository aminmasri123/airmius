<?php

namespace App\Support;

use App\Models\Invoice;
use App\Models\SubscriptionInvoice;

class BillingOverview
{
    public const OPEN_STATUSES = ['open', 'overdue', 'awaiting_transfer'];

    public static function clubInvoiceSummary(iterable $invoices): array
    {
        $invoices = collect($invoices);

        return [
            'total_count' => $invoices->count(),
            'open_count' => $invoices->whereIn('status', self::OPEN_STATUSES)->count(),
            'paid_count' => $invoices->where('status', 'paid')->count(),
            'overdue_count' => $invoices->where('status', 'overdue')->count(),
            'cancelled_count' => $invoices->where('status', 'cancelled')->count(),
            'open_amount' => self::sumClubInvoices($invoices->whereIn('status', self::OPEN_STATUSES)),
            'paid_amount' => self::sumClubInvoices($invoices->where('status', 'paid')),
            'overdue_amount' => self::sumClubInvoices($invoices->where('status', 'overdue')),
            'cancelled_amount' => self::sumClubInvoices($invoices->where('status', 'cancelled')),
        ];
    }

    public static function memberBillingSummary(iterable $clubInvoices, iterable $subscriptionInvoices, iterable $payments = []): array
    {
        $club = self::clubInvoiceSummary($clubInvoices);
        $subscriptions = self::subscriptionInvoiceSummary($subscriptionInvoices);
        $payments = collect($payments);

        return [
            'total_count' => $club['total_count'] + $subscriptions['total_count'],
            'club_invoice_count' => $club['total_count'],
            'subscription_invoice_count' => $subscriptions['total_count'],
            'open_count' => $club['open_count'] + $subscriptions['open_count'],
            'paid_count' => $club['paid_count'] + $subscriptions['paid_count'],
            'overdue_count' => $club['overdue_count'] + $subscriptions['overdue_count'],
            'cancelled_count' => $club['cancelled_count'] + $subscriptions['cancelled_count'],
            'open_amount' => $club['open_amount'] + $subscriptions['open_amount'],
            'paid_amount' => $club['paid_amount'] + $subscriptions['paid_amount'],
            'overdue_amount' => $club['overdue_amount'] + $subscriptions['overdue_amount'],
            'payment_count' => $payments->count(),
            'club' => $club,
            'subscriptions' => $subscriptions,
        ];
    }

    public static function subscriptionInvoiceSummary(iterable $invoices): array
    {
        $invoices = collect($invoices);

        return [
            'total_count' => $invoices->count(),
            'open_count' => $invoices->whereIn('status', self::OPEN_STATUSES)->count(),
            'paid_count' => $invoices->where('status', 'paid')->count(),
            'overdue_count' => $invoices->where('status', 'overdue')->count(),
            'cancelled_count' => $invoices->where('status', 'cancelled')->count(),
            'open_amount' => self::sumSubscriptionInvoices($invoices->whereIn('status', self::OPEN_STATUSES)),
            'paid_amount' => self::sumSubscriptionInvoices($invoices->where('status', 'paid')),
            'overdue_amount' => self::sumSubscriptionInvoices($invoices->where('status', 'overdue')),
            'cancelled_amount' => self::sumSubscriptionInvoices($invoices->where('status', 'cancelled')),
        ];
    }

    public static function statusLabel(?string $status): string
    {
        return Invoice::STATUS_LABELS[$status] ?? ($status ?: 'Unbekannt');
    }

    private static function sumClubInvoices(iterable $invoices): float
    {
        return (float) collect($invoices)->sum(fn (Invoice $invoice) => (float) $invoice->amount);
    }

    private static function sumSubscriptionInvoices(iterable $invoices): float
    {
        return (float) collect($invoices)->sum(fn (SubscriptionInvoice $invoice) => ((int) $invoice->amount_cents) / 100);
    }
}
