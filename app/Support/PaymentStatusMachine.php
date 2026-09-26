<?php

namespace App\Support;

use App\Models\Invoice;
use Illuminate\Validation\ValidationException;

final class PaymentStatusMachine
{
    public const CLAIM_OPEN = 'open';
    public const CLAIM_PARTIALLY_PAID = 'partially_paid';
    public const CLAIM_AWAITING_BANK_TRANSFER = 'awaiting_transfer';
    public const CLAIM_AWAITING_DIRECT_DEBIT = 'awaiting_direct_debit';
    public const CLAIM_PROCESSING_ONLINE = 'processing_online';
    public const CLAIM_PAID = 'paid';
    public const CLAIM_OVERDUE = 'overdue';
    public const CLAIM_FAILED = 'failed';
    public const CLAIM_CANCELLED = 'cancelled';

    public const PAYMENT_PENDING = 'pending';
    public const PAYMENT_PAID = 'paid';
    public const PAYMENT_FAILED = 'failed';
    public const PAYMENT_CANCELLED = 'cancelled';

    public const METHODS = ['bank_transfer', 'sepa_debit', 'cash', 'online'];

    private const CLAIM_TRANSITIONS = [
        self::CLAIM_OPEN => [
            self::CLAIM_OPEN,
            self::CLAIM_PARTIALLY_PAID,
            self::CLAIM_AWAITING_BANK_TRANSFER,
            self::CLAIM_AWAITING_DIRECT_DEBIT,
            self::CLAIM_PROCESSING_ONLINE,
            self::CLAIM_PAID,
            self::CLAIM_OVERDUE,
            self::CLAIM_FAILED,
            self::CLAIM_CANCELLED,
        ],
        self::CLAIM_PARTIALLY_PAID => [
            self::CLAIM_PARTIALLY_PAID,
            self::CLAIM_PAID,
            self::CLAIM_OVERDUE,
            self::CLAIM_FAILED,
            self::CLAIM_CANCELLED,
        ],
        self::CLAIM_AWAITING_BANK_TRANSFER => [
            self::CLAIM_AWAITING_BANK_TRANSFER,
            self::CLAIM_PARTIALLY_PAID,
            self::CLAIM_PAID,
            self::CLAIM_OVERDUE,
            self::CLAIM_FAILED,
            self::CLAIM_CANCELLED,
        ],
        self::CLAIM_AWAITING_DIRECT_DEBIT => [
            self::CLAIM_AWAITING_DIRECT_DEBIT,
            self::CLAIM_PARTIALLY_PAID,
            self::CLAIM_PAID,
            self::CLAIM_OVERDUE,
            self::CLAIM_FAILED,
            self::CLAIM_CANCELLED,
        ],
        self::CLAIM_PROCESSING_ONLINE => [
            self::CLAIM_PROCESSING_ONLINE,
            self::CLAIM_PAID,
            self::CLAIM_FAILED,
            self::CLAIM_CANCELLED,
        ],
        self::CLAIM_OVERDUE => [
            self::CLAIM_OVERDUE,
            self::CLAIM_PARTIALLY_PAID,
            self::CLAIM_PAID,
            self::CLAIM_FAILED,
            self::CLAIM_CANCELLED,
        ],
        self::CLAIM_FAILED => [self::CLAIM_FAILED, self::CLAIM_OPEN, self::CLAIM_CANCELLED],
        self::CLAIM_PAID => [
            self::CLAIM_PAID,
            self::CLAIM_PARTIALLY_PAID,
            self::CLAIM_OVERDUE,
            self::CLAIM_FAILED,
        ],
        self::CLAIM_CANCELLED => [self::CLAIM_CANCELLED],
    ];

    public function normalizeMethod(?string $method): string
    {
        return match ($method) {
            'bank', 'bank_import', 'manual_bank', 'transfer' => 'bank_transfer',
            'sepa', 'direct_debit' => 'sepa_debit',
            'card', 'paypal', 'stripe' => 'online',
            'manual' => 'cash',
            default => in_array($method, self::METHODS, true) ? $method : 'cash',
        };
    }

    public function pendingClaimStatusForMethod(string $method): string
    {
        return match ($this->normalizeMethod($method)) {
            'bank_transfer' => self::CLAIM_AWAITING_BANK_TRANSFER,
            'sepa_debit' => self::CLAIM_AWAITING_DIRECT_DEBIT,
            'online' => self::CLAIM_PROCESSING_ONLINE,
            default => self::CLAIM_OPEN,
        };
    }

    public function paymentStatusForMethod(string $method, bool $settled = true): string
    {
        return $settled ? self::PAYMENT_PAID : self::PAYMENT_PENDING;
    }

    public function claimStatus(Invoice $invoice, int $receivedCents, ?string $method = null, ?string $paymentStatus = null): string
    {
        if ($invoice->status === self::CLAIM_CANCELLED) {
            return self::CLAIM_CANCELLED;
        }

        $totalCents = (int) round((float) $invoice->amount * 100);
        if ($receivedCents >= $totalCents) {
            return self::CLAIM_PAID;
        }

        if ($receivedCents > 0) {
            return $invoice->due_date?->isPast() ? self::CLAIM_OVERDUE : self::CLAIM_PARTIALLY_PAID;
        }

        if ($paymentStatus === self::PAYMENT_PENDING && $method) {
            return $this->pendingClaimStatusForMethod($method);
        }

        if ($paymentStatus === self::PAYMENT_FAILED) {
            return self::CLAIM_FAILED;
        }

        return $invoice->status === self::CLAIM_OVERDUE || $invoice->due_date?->isPast()
            ? self::CLAIM_OVERDUE
            : self::CLAIM_OPEN;
    }

    public function assertClaimTransition(?string $from, string $to): void
    {
        $from = $from ?: self::CLAIM_OPEN;
        if (in_array($to, self::CLAIM_TRANSITIONS[$from] ?? [], true)) {
            return;
        }

        throw ValidationException::withMessages([
            'status' => "Invalid payment claim transition from {$from} to {$to}.",
        ]);
    }

    public function isImmutableClaimStatus(string $status): bool
    {
        return in_array($status, [self::CLAIM_PAID, self::CLAIM_CANCELLED], true);
    }
}
