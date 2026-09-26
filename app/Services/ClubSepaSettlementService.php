<?php

namespace App\Services;

use App\Models\Club;
use App\Models\ClubSepaBatch;
use App\Models\ClubSepaBatchItem;
use App\Models\ClubSepaSettlement;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Support\ClubAuditLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ClubSepaSettlementService
{
    public function settle(ClubSepaBatchItem $item, User $actor, array $data): ClubSepaSettlement
    {
        return DB::transaction(function () use ($item, $actor, $data) {
            [$item, $invoice] = $this->lockItem($item);
            $reference = $this->reference($data['reference']);
            $existing = $item->settlement()->lockForUpdate()->first();
            if ($existing) {
                abort_unless($existing->status === 'settled' && $existing->settlement_reference === $reference
                    && $existing->settled_on->toDateString() === $data['booked_on']
                    && ((isset($data['payment_id']) && (int) $data['payment_id'] === (int) $existing->payment_id)
                        || (empty($data['payment_id']) && $existing->created_payment)), 422, __('sepa.result_recorded'));

                return $existing;
            }
            $this->checkDate($item, $data['booked_on']);
            abort_if(ClubSepaSettlement::where('club_id', $item->batch->club_id)->where('settlement_reference', $reference)->exists(), 422, __('sepa.bank_reference_used'));
            if (! empty($data['payment_id'])) {
                $payment = Payment::lockForUpdate()->find($data['payment_id']);
                abort_unless($payment && $payment->invoice_id === $invoice->id && $payment->club_id === $invoice->club_id
                    && $payment->status === 'paid' && in_array($payment->method, ['bank_transfer', 'bank_import', 'sepa_debit'], true)
                    && (int) round((float) $payment->amount * 100) === $item->amount_cents
                    && $payment->paid_at?->toDateString() === $data['booked_on'], 422, __('sepa.payment_mismatch'));
                abort_if(ClubSepaSettlement::where('payment_id', $payment->id)->exists(), 422, __('sepa.payment_controlled'));
            } else {
                abort_unless($invoice->user_id, 422, __('sepa.payment_mismatch'));
                $payment = app(ClubInvoicePaymentService::class)->record($invoice, [
                    'amount' => $item->amount_cents / 100, 'method' => 'sepa_debit',
                    'reference' => $reference, 'paid_at' => $data['booked_on'],
                ], $actor, allowOverpayment: true);
            }
            $result = $item->settlement()->create([
                'club_id' => $item->batch->club_id, 'payment_id' => $payment->id,
                'created_payment' => empty($data['payment_id']), 'status' => 'settled',
                'settled_by' => $actor->id, 'settled_on' => $data['booked_on'], 'settlement_reference' => $reference,
            ]);
            $this->audit($item, $actor, 'settled', $result);

            return $result;
        });
    }

    public function returnDebit(ClubSepaBatchItem $item, User $actor, array $data): ClubSepaSettlement
    {
        return DB::transaction(function () use ($item, $actor, $data) {
            [$item, $invoice] = $this->lockItem($item);
            $reference = $this->reference($data['reference']);
            $result = $item->settlement()->lockForUpdate()->first();
            if ($result?->status === 'returned') {
                abort_unless($result->return_reference === $reference && $result->returned_on->toDateString() === $data['booked_on']
                    && $result->return_reason === trim($data['reason']) && $result->return_fee_cents === ($data['fee_cents'] ?? 0), 422, __('sepa.result_recorded'));

                return $result;
            }
            $this->checkDate($item, $data['booked_on']);
            abort_if($result?->settled_on && Carbon::parse($data['booked_on'])->lt($result->settled_on), 422, __('sepa.bank_date'));
            abort_if(ClubSepaSettlement::where('club_id', $item->batch->club_id)->where('return_reference', $reference)->exists(), 422, __('sepa.bank_reference_used'));
            if ($result?->payment_id) {
                $payment = Payment::lockForUpdate()->findOrFail($result->payment_id);
                abort_unless($payment->status === 'paid' && $payment->invoice_id === $invoice->id
                    && $payment->club_id === $invoice->club_id
                    && (int) round((float) $payment->amount * 100) === $item->amount_cents, 422, __('sepa.payment_mismatch'));
                // Preserve the original incoming record and its timestamp. Only the
                // linked SEPA receipt ceases to count as settled; unrelated payments stay.
                $payment->update(['status' => 'returned']);
                app(ClubInvoicePaymentService::class)->synchronize($invoice);
            }
            $attributes = [
                'status' => 'returned', 'returned_by' => $actor->id, 'returned_on' => $data['booked_on'],
                'return_reference' => $reference, 'return_reason' => trim($data['reason']),
                'return_fee_cents' => $data['fee_cents'] ?? 0,
            ];
            if ($result) {
                $result->update($attributes);
            } else {
                // Rejection before a credited payment: no artificial negative payment.
                $result = $item->settlement()->create($attributes + ['club_id' => $item->batch->club_id]);
            }
            $this->audit($item, $actor, 'returned', $result);

            return $result;
        });
    }

    public function authorizeRetry(ClubSepaBatchItem $item, User $actor, string $reason): ClubSepaSettlement
    {
        return DB::transaction(function () use ($item, $actor, $reason) {
            [$item, $invoice] = $this->lockItem($item);
            $result = $item->settlement()->lockForUpdate()->first();
            abort_unless($result?->status === 'returned', 422, __('sepa.return_required'));
            if ($result->retry_authorized_at) {
                return $result;
            }
            abort_if((int) $result->returned_by === $actor->id, 422, __('sepa.second_person'));
            abort_unless(in_array($invoice->status, ['open', 'overdue'], true) && $invoice->outstandingCents() > 0, 422, __('sepa.invoices'));
            // Approval releases only this historical reservation. The new run will
            // check the current balance/mandate and require new approval and notice.
            abort_unless((int) $item->reserved_invoice_id === $invoice->id, 422, __('sepa.result_recorded'));
            $result->update(['retry_authorized_by' => $actor->id, 'retry_authorized_at' => now(), 'retry_reason' => trim($reason)]);
            $item->update(['reserved_invoice_id' => null]);
            $this->audit($item, $actor, 'retry_authorized', $result);

            return $result;
        });
    }

    private function lockItem(ClubSepaBatchItem $item): array
    {
        $batch = ClubSepaBatch::findOrFail($item->club_sepa_batch_id);
        Club::lockForUpdate()->findOrFail($batch->club_id);
        $batch = ClubSepaBatch::lockForUpdate()->findOrFail($batch->id);
        abort_unless($batch->status === 'exported', 422, __('sepa.export_required'));
        $item = ClubSepaBatchItem::lockForUpdate()->findOrFail($item->id);
        $item->setRelation('batch', $batch);
        $invoice = Invoice::lockForUpdate()->findOrFail($item->invoice_id);
        abort_unless($invoice->club_id === $batch->club_id, 422, __('sepa.payment_mismatch'));

        return [$item, $invoice];
    }

    private function checkDate(ClubSepaBatchItem $item, string $date): void
    {
        $date = Carbon::parse($date)->startOfDay();
        abort_if($date->gt(today()) || $date->lt($item->batch->collection_date), 422, __('sepa.bank_date'));
    }

    private function reference(string $value): string
    {
        $reference = mb_strtoupper(trim($value));
        // Unicode case conversion may expand a valid input (for example ß → SS).
        // Validate the stored value before it can exceed the database column.
        Validator::make(['reference' => $reference], [
            'reference' => ['required', 'string', 'max:180'],
        ])->validate();

        return $reference;
    }

    private function audit(ClubSepaBatchItem $item, User $actor, string $event, ClubSepaSettlement $result): void
    {
        ClubAuditLog::record($item->batch->club, $actor, 'club.sepa.'.$event, $item->batch, [
            'item_id' => $item->id, 'invoice_id' => $item->invoice_id, 'payment_id' => $result->payment_id,
            'amount_cents' => $item->amount_cents, 'settlement_id' => $result->id,
        ]);
    }
}
