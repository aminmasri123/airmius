<?php

namespace App\Services;

use App\Models\Club;
use App\Models\ClubPaymentAllocation;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class ClubInvoiceCreditService
{
    public function apply(Invoice $invoice, ?User $actor = null): void
    {
        if (! Schema::hasTable('club_payment_allocations')) {
            abort_if(Payment::query()->where('club_id', $invoice->club_id)->whereNull('invoice_id')
                ->where('purpose', 'prepayment')->where('status', 'paid')->exists(),
                422, __('organization.club.payment_ledger_migration_required'));

            return;
        }
        DB::transaction(function () use ($invoice, $actor) {
            self::lockClub((int) $invoice->club_id);
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            $this->allocate($locked, $actor);
            $invoice->refresh();
        });
    }

    private function allocate(Invoice $invoice, ?User $actor): void
    {
        if (in_array($invoice->status, ['paid', 'cancelled', 'waived'], true)) {
            return;
        }
        $credits = Payment::query()->where('club_id', $invoice->club_id)
            ->whereNull('invoice_id')->where('purpose', 'prepayment')->where('status', 'paid')
            ->orderBy('id')->lockForUpdate()->get();
        foreach ($credits as $payment) {
            // Match the beneficiary, not just a shared family payer.
            $origin = $payment->bookingReceipts()->where('action', 'credited')->latest('id')->first();
            $beneficiary = $origin?->payload['beneficiary'] ?? null;
            if (! $beneficiary) {
                $legacy = Invoice::query()->where('club_id', $invoice->club_id)->where('status', 'cancelled')
                    ->where(fn ($q) => $q->whereJsonContains('contribution_snapshot->cancellation->payment_ids', $payment->id)
                        ->orWhereJsonContains('contribution_snapshot->cancellation_payment_ids', $payment->id))
                    ->orderBy('id')->first();
                if ($legacy) {
                    $beneficiary = self::beneficiary($legacy);
                }
            }
            if ($beneficiary !== self::beneficiary($invoice)) {
                continue;
            }
            $allocated = ClubPaymentAllocation::query()->where('payment_id', $payment->id)
                ->whereNull('released_at')->sum('amount_cents');
            $available = max(0, (int) round((float) $payment->amount * 100) - $allocated);
            $amount = min($available, $invoice->receivedCents() < (int) round((float) $invoice->amount * 100)
                ? (int) round((float) $invoice->amount * 100) - $invoice->receivedCents() : 0);
            if ($amount <= 0) {
                continue;
            }
            ClubPaymentAllocation::create([
                'club_id' => $invoice->club_id, 'invoice_id' => $invoice->id,
                'payment_id' => $payment->id, 'amount_cents' => $amount,
            ]);
            $before = $invoice->claim_status ?? $invoice->status;
            $after = app(ClubInvoicePaymentService::class)->synchronize($invoice);
            app(PaymentBookingReceiptService::class)->record($payment, $invoice, 'credit_applied', $before, $after, $actor, [
                'amount_cents' => $amount, 'beneficiary' => $beneficiary,
            ]);
        }
    }

    public static function beneficiary(Invoice $invoice): string
    {
        return $invoice->club_external_member_id ? 'external:'.$invoice->club_external_member_id
            : 'member:'.($invoice->membership_user_id ?: $invoice->user_id);
    }

    public static function lockClub(int $clubId): void
    {
        Club::query()->lockForUpdate()->findOrFail($clubId);
    }

    public static function assertNoDuplicate(int $clubId, ?int $memberId, ?int $externalId, array $data): void
    {
        if (empty($data['billing_period_start']) || empty($data['billing_period_end'])) {
            return;
        }
        $query = Invoice::query()->where('club_id', $clubId)->where('status', '!=', 'cancelled')
            ->where(fn ($q) => $q->whereNull('source')->orWhere('source', '!=', 'sepa_fee_recharge'))
            ->whereDate('billing_period_start', '<=', $data['billing_period_end'])
            ->whereDate('billing_period_end', '>=', $data['billing_period_start']);
        if ($externalId) {
            $query->where('club_external_member_id', $externalId);
        } else {
            $query->whereNull('club_external_member_id')->where(fn ($q) => $q
                ->where('membership_user_id', $memberId)
                ->orWhere(fn ($legacy) => $legacy->whereNull('membership_user_id')->where('user_id', $memberId)));
        }
        if ($query->exists()) {
            throw ValidationException::withMessages([
                'billing_period_start' => __('organization.club.invoice_period_already_billed'),
            ]);
        }
    }
}
