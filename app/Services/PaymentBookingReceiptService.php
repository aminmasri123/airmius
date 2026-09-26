<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentBookingReceipt;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class PaymentBookingReceiptService
{
    public function record(
        Payment $payment,
        Invoice $invoice,
        string $action,
        ?string $claimStatusBefore,
        string $claimStatusAfter,
        ?User $actor = null,
        array $payload = [],
    ): PaymentBookingReceipt {
        return DB::transaction(function () use ($payment, $invoice, $action, $claimStatusBefore, $claimStatusAfter, $actor, $payload) {
            $previous = PaymentBookingReceipt::query()
                ->where('club_id', $payment->club_id)
                ->lockForUpdate()
                ->latest('id')
                ->first();

            $attributes = [
                'club_id' => $payment->club_id,
                'invoice_id' => $payment->invoice_id,
                'payment_id' => $payment->id,
                'user_id' => $payment->user_id,
                'actor_id' => $actor?->id,
                'action' => $action,
                'claim_status_before' => $claimStatusBefore,
                'claim_status_after' => $claimStatusAfter,
                'payment_status' => $payment->status,
                'payment_method' => $payment->method,
                'amount_cents' => (int) round((float) $payment->amount * 100),
                'currency' => 'EUR',
                'receipt_number' => $payment->receipt_number,
                'reference' => $payment->reference,
                'payload' => $payload,
                'previous_hash' => $previous?->hash,
                'booked_at' => now(),
            ];
            $attributes['hash'] = $this->hash($attributes);

            return PaymentBookingReceipt::query()->create($attributes);
        });
    }

    private function hash(array $attributes): string
    {
        return hash('sha256', json_encode([
            'club_id' => $attributes['club_id'],
            'invoice_id' => $attributes['invoice_id'],
            'payment_id' => $attributes['payment_id'],
            'user_id' => $attributes['user_id'],
            'actor_id' => $attributes['actor_id'],
            'action' => $attributes['action'],
            'claim_status_before' => $attributes['claim_status_before'],
            'claim_status_after' => $attributes['claim_status_after'],
            'payment_status' => $attributes['payment_status'],
            'payment_method' => $attributes['payment_method'],
            'amount_cents' => $attributes['amount_cents'],
            'currency' => $attributes['currency'],
            'receipt_number' => $attributes['receipt_number'],
            'reference' => $attributes['reference'],
            'payload' => $attributes['payload'],
            'previous_hash' => $attributes['previous_hash'],
            'booked_at' => $attributes['booked_at']->toJSON(),
        ], JSON_THROW_ON_ERROR));
    }
}
