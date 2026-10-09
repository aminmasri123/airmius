<?php

namespace App\Services;

use App\Models\ClubPaymentAllocation;
use App\Models\ClubSepaFeeRechargeCredit;
use App\Models\ClubSepaSettlement;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentBookingReceipt;
use App\Models\User;
use App\Support\ClubAuditLog;
use App\Support\PaymentStatusMachine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class ClubInvoicePaymentService
{
    public function __construct(
        private readonly ClubPaymentNumberService $paymentNumbers,
        private readonly PaymentStatusMachine $statusMachine,
        private readonly PaymentBookingReceiptService $bookingReceipts,
    ) {}

    /** Call only after authorizing finance access to the invoice's club. */
    public function record(Invoice $invoice, array $data, ?User $actor = null, bool $allowOverpayment = false): Payment
    {
        return DB::transaction(function () use ($invoice, $data, $actor, $allowOverpayment) {
            ClubInvoiceCreditService::lockClub((int) $invoice->club_id);
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            $idempotencyKey = trim((string) ($data['idempotency_key'] ?? ''));

            if ($idempotencyKey !== '') {
                $existing = Payment::query()
                    ->where('club_id', $locked->club_id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    abort_if((int) $existing->invoice_id !== (int) $locked->id, 422, __('organization.club.payment_idempotency_conflict'));

                    return $existing;
                }
            }

            abort_if($locked->status === 'paid' && ! $allowOverpayment, 422, __('organization.club.paid_invoice_payment_forbidden'));
            abort_if($locked->status === 'paid' && $allowOverpayment
                && $locked->receivedCents() < (int) round((float) $locked->amount * 100), 422, __('organization.club.paid_invoice_payment_forbidden'));
            abort_if($locked->status === 'cancelled', 422, __('organization.club.cancelled_invoice_payment_forbidden'));
            abort_if($locked->status === 'waived', 422, __('organization.club.paid_invoice_payment_forbidden'));

            $amount = $data['amount'] ?? ($locked->outstandingCents() / 100);
            $this->validateAmount($amount);
            $this->validatePaidAt($data['paid_at'] ?? null);
            $amountCents = (int) round((float) $amount * 100);
            $outstandingCents = $locked->outstandingCents();
            if (($data['partial_payment'] ?? false) && $amountCents >= $outstandingCents) {
                throw ValidationException::withMessages([
                    'amount' => __('organization.club.partial_payment_must_leave_balance'),
                ]);
            }
            if (! $allowOverpayment && $amountCents > $outstandingCents) {
                throw ValidationException::withMessages([
                    'amount' => __('organization.club.payment_exceeds_outstanding'),
                ]);
            }
            $method = $this->statusMachine->normalizeMethod($data['method'] ?? 'manual');
            $paymentStatus = $data['status'] ?? $this->statusMachine->paymentStatusForMethod($method, true);
            $beforeStatus = $locked->claim_status ?? $locked->status;
            $payment = $this->paymentNumbers->create($locked->club, [
                'invoice_id' => $locked->id,
                'user_id' => $locked->user_id,
                'club_external_member_id' => $locked->club_external_member_id,
                'purpose' => 'membership_invoice',
                'amount' => $amount,
                'status' => $paymentStatus,
                'method' => $method,
                'reference' => $data['reference'] ?? null,
                'paid_at' => $paymentStatus === PaymentStatusMachine::PAYMENT_PAID ? ($data['paid_at'] ?? now()) : null,
                'notes' => $data['notes'] ?? null,
                'idempotency_key' => $idempotencyKey ?: null,
            ], $actor);
            $afterStatus = $this->synchronize($locked, $method, $paymentStatus);
            $this->bookingReceipts->record(
                $payment,
                $locked->fresh(),
                PaymentBookingReceipt::ACTION_RECORDED,
                $beforeStatus,
                $afterStatus,
                $actor,
                ['source' => $data['source'] ?? 'club_invoice_payment_service'],
            );
            ClubAuditLog::record($locked->club, $actor, 'club.payment.recorded', $locked, [
                'invoice_number' => $locked->number,
                'payment_id' => $payment->id,
                'amount' => $payment->amount,
                'method' => $payment->method,
                'external_member_id' => $payment->club_external_member_id,
                ...$locked->balancePayload(),
            ]);
            $invoice->refresh();

            return $payment;
        });
    }

    public function correct(Payment $payment, array $attributes, ?User $actor = null): void
    {
        DB::transaction(function () use ($payment, $attributes, $actor) {
            ClubInvoiceCreditService::lockClub((int) $payment->club_id);
            // Use the same invoice-first lock order as record().
            $invoice = $payment->invoice_id
                ? Invoice::query()->lockForUpdate()->findOrFail($payment->invoice_id)
                : null;
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $this->assertEditable($locked);
            abort_if(in_array($locked->status, ['cancelled', 'failed', 'returned'], true)
                || ($invoice && in_array($invoice->status, ['cancelled', 'waived'], true))
                || (Schema::hasTable('club_payment_allocations') && ClubPaymentAllocation::query()->where('payment_id', $locked->id)->exists()),
                422, __('organization.club.payment_correction_forbidden'));
            abort_if($locked->purpose === 'prepayment' && $locked->bookingReceipts()->where('action', 'credited')->exists(),
                422, __('organization.club.payment_correction_forbidden'));
            $this->validateAmount($attributes['amount']);
            $this->validatePaidAt($attributes['paid_at'] ?? null);
            if ($invoice) {
                $ownCents = $locked->status === 'paid' ? (int) round((float) $locked->amount * 100) : 0;
                $maximum = max(0, (int) round((float) $invoice->amount * 100) - $invoice->receivedCents() + $ownCents);
                if ((int) round((float) $attributes['amount'] * 100) > $maximum) {
                    throw ValidationException::withMessages(['amount' => __('organization.club.payment_exceeds_outstanding')]);
                }
            }
            $before = $locked->only(['amount', 'method', 'reference', 'paid_at', 'notes', 'user_id', 'status']);
            $beforeStatus = $invoice ? ($invoice->claim_status ?? $invoice->status) : null;
            if (array_key_exists('method', $attributes)) {
                $attributes['method'] = $this->statusMachine->normalizeMethod($attributes['method']);
            }
            $locked->update($attributes);
            $afterStatus = $invoice ? ($invoice->claim_status ?? $invoice->status) : null;
            if ($invoice && $invoice->status !== 'cancelled') {
                $afterStatus = $this->synchronize($invoice, $locked->method, $locked->status);
                $this->bookingReceipts->record(
                    $locked,
                    $invoice->fresh(),
                    PaymentBookingReceipt::ACTION_CORRECTED,
                    $beforeStatus,
                    $afterStatus,
                    $actor,
                    ['before' => $before, 'after' => $locked->only(array_keys($before))],
                );
            }
            ClubAuditLog::record($locked->club, $actor, 'club.payment.corrected', $locked, [
                'invoice_id' => $locked->invoice_id,
                'external_member_id' => $locked->club_external_member_id,
                'before' => $before,
                'after' => $locked->only(array_keys($before)),
            ]);
            $payment->refresh();
        });
    }

    public function assertEditable(Payment $payment): void
    {
        abort_if(Schema::hasTable('club_sepa_settlements') && ClubSepaSettlement::where('payment_id', $payment->id)->exists(), 422, __('sepa.payment_controlled'));
        abort_if($payment->invoice_id && Schema::hasTable('club_sepa_fee_recharge_credits')
            && ClubSepaFeeRechargeCredit::where('status', 'issued')
                ->whereHas('recharge', fn ($query) => $query->where('invoice_id', $payment->invoice_id))->exists(),
            422, __('sepa.payment_controlled'));
    }

    public function synchronize(Invoice $invoice, ?string $method = null, ?string $paymentStatus = null): string
    {
        if (in_array($invoice->status, ['cancelled', 'waived'], true)) {
            return $invoice->status;
        }
        $invoice->loadSum('settledPayments', 'amount');
        $nextStatus = $this->statusMachine->claimStatus($invoice, $invoice->receivedCents(), $method, $paymentStatus);
        $this->statusMachine->assertClaimTransition($invoice->claim_status ?? $invoice->status, $nextStatus);
        $legacyStatus = match ($nextStatus) {
            PaymentStatusMachine::CLAIM_PAID => 'paid',
            PaymentStatusMachine::CLAIM_OVERDUE => 'overdue',
            default => $invoice->due_date?->isPast() ? 'overdue' : 'open',
        };
        $invoice->update([
            'status' => $legacyStatus,
            'claim_status' => $nextStatus,
            'paid_at' => $nextStatus === PaymentStatusMachine::CLAIM_PAID ? ($invoice->settledPayments()->max('paid_at') ?? now()) : null,
        ]);

        return $nextStatus;
    }

    private function validateAmount(mixed $amount): void
    {
        if (! is_numeric($amount) || (float) $amount <= 0 || (float) $amount > 999999.99
            || abs((float) $amount * 100 - round((float) $amount * 100)) > 0.000001) {
            throw ValidationException::withMessages(['amount' => __('organization.club.payment_amount_invalid')]);
        }
    }

    private function validatePaidAt(mixed $paidAt): void
    {
        if ($paidAt === null || $paidAt === '') {
            return;
        }

        try {
            $date = Carbon::parse($paidAt)->startOfDay();
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'paid_at' => __('organization.club.payment_date_invalid'),
            ]);
        }

        if ($date->isAfter(today())) {
            throw ValidationException::withMessages([
                'paid_at' => __('organization.club.payment_date_future'),
            ]);
        }
    }
}
