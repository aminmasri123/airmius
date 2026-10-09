<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Support\ClubAuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ClubInvoiceCancellationService
{
    public const ACTION_CREDIT = 'credit';

    public const ACTION_PAYMENT_ERROR = 'payment_error';

    public const ACTIONS = [self::ACTION_CREDIT, self::ACTION_PAYMENT_ERROR];

    public function __construct(
        private readonly ClubInvoicePaymentService $payments,
        private readonly ClubNumberRangeService $numberRanges,
    ) {}

    public function cancel(Invoice $invoice, ?string $action, User $actor): Invoice
    {
        return DB::transaction(function () use ($invoice, $action, $actor) {
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            if ($locked->status === 'cancelled') {
                return $locked;
            }

            $settledPayments = $locked->settledPayments()->lockForUpdate()->get();
            if ($settledPayments->isNotEmpty() && ! in_array($action, self::ACTIONS, true)) {
                throw ValidationException::withMessages([
                    'cancellation_action' => __('organization.club.invoice_cancellation_action_required'),
                ]);
            }

            foreach ($settledPayments as $payment) {
                $this->payments->assertEditable($payment);

                if ($action === self::ACTION_PAYMENT_ERROR && $payment->bankTransactions()->exists()) {
                    throw ValidationException::withMessages([
                        'cancellation_action' => __('organization.club.invoice_payment_error_bank_forbidden'),
                    ]);
                }

                $note = trim(collect([
                    $payment->notes,
                    $action === self::ACTION_CREDIT
                        ? __('organization.club.invoice_cancelled_credit_note', ['invoice' => $locked->number])
                        : __('organization.club.invoice_cancelled_payment_error_note', ['invoice' => $locked->number]),
                ])->filter()->implode("\n"));

                if ($action === self::ACTION_CREDIT) {
                    $payment->update([
                        'invoice_id' => null,
                        'purpose' => 'prepayment',
                        'notes' => $note,
                    ]);
                    $payment->bankTransactions()->update(['invoice_id' => null]);
                } else {
                    $payment->update([
                        'status' => 'cancelled',
                        'notes' => $note,
                    ]);
                }
            }

            $snapshot = $locked->contribution_snapshot ?: [];
            $snapshot['cancellation'] = [
                'action' => $settledPayments->isEmpty() ? 'no_payment' : $action,
                'settled_amount' => number_format((float) $settledPayments->sum('amount'), 2, '.', ''),
                'payment_ids' => $settledPayments->pluck('id')->values()->all(),
                'cancelled_by_user_id' => $actor->id,
                'cancelled_at' => now()->toJSON(),
            ];
            $locked->update([
                'status' => 'cancelled',
                'claim_status' => 'cancelled',
                'paid_at' => null,
                'contribution_snapshot' => $snapshot,
            ]);

            ClubAuditLog::record($locked->club, $actor, 'club.invoice.cancelled', $locked, [
                'invoice_number' => $locked->number,
                'cancellation_action' => $snapshot['cancellation']['action'],
                'settled_amount' => $snapshot['cancellation']['settled_amount'],
                'payment_ids' => $settledPayments->pluck('id')->all(),
            ]);

            return $locked->fresh();
        });
    }

    public function replaceAccidentalCancellation(Invoice $invoice, User $actor): Invoice
    {
        return DB::transaction(function () use ($invoice, $actor) {
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            abort_unless($locked->status === 'cancelled', 422, __('organization.club.invoice_replacement_only_cancelled'));

            $snapshot = $locked->contribution_snapshot ?: [];
            if (filled($snapshot['replacement_invoice_id'] ?? null)) {
                return Invoice::query()->findOrFail((int) $snapshot['replacement_invoice_id']);
            }

            $allocation = $this->numberRanges->allocateDefault(
                $locked->club,
                'invoice',
                $actor,
                (string) Str::uuid(),
                fn (string $number) => ! Invoice::query()->where('number', $number)->exists(),
            );
            $replacement = Invoice::query()->create([
                'club_id' => $locked->club_id,
                'user_id' => $locked->user_id,
                'membership_user_id' => $locked->membership_user_id,
                'club_external_member_id' => $locked->club_external_member_id,
                'number' => $allocation?->formatted_number ?? $this->nextInvoiceNumber($locked),
                'title' => $locked->title,
                'description' => $locked->description,
                'amount' => $locked->amount,
                'status' => 'open',
                'claim_status' => 'open',
                'source' => 'replacement_invoice',
                'contribution_snapshot' => [
                    ...$snapshot,
                    'replacement_for_invoice_id' => $locked->id,
                    'replacement_for_invoice_number' => $locked->number,
                    'replacement_created_by_user_id' => $actor->id,
                    'replacement_created_at' => now()->toJSON(),
                ],
                'billing_period_start' => $locked->billing_period_start,
                'billing_period_end' => $locked->billing_period_end,
                'due_date' => $locked->due_date,
                'issued_at' => now(),
            ]);
            if ($allocation) {
                $this->numberRanges->assignTo($allocation, 'invoice', $replacement->id);
            }

            $creditPaymentIds = collect($snapshot['cancellation']['payment_ids'] ?? []);
            if ($creditPaymentIds->isEmpty()) {
                $creditPaymentIds = collect($snapshot['cancellation_payment_ids'] ?? []);
            }
            $transferablePayments = $creditPaymentIds->isNotEmpty()
                ? Payment::query()
                    ->whereIn('id', $creditPaymentIds)
                    ->where('club_id', $locked->club_id)
                    ->whereNull('invoice_id')
                    ->where('purpose', 'prepayment')
                    ->where('status', 'paid')
                    ->lockForUpdate()
                    ->get()
                : $locked->settledPayments()->lockForUpdate()->get();
            $creditPaymentIds = $transferablePayments->pluck('id');
            $transferablePayments->each(function (Payment $payment) use ($replacement) {
                $payment->update([
                    'invoice_id' => $replacement->id,
                    'purpose' => 'membership_invoice',
                    'notes' => trim(collect([
                        $payment->notes,
                        __('organization.club.invoice_replacement_credit_note', ['invoice' => $replacement->number]),
                    ])->filter()->implode("\n")),
                ]);
                $payment->bankTransactions()->update(['invoice_id' => $replacement->id]);
            });
            $this->payments->synchronize($replacement);

            $snapshot['replacement_invoice_id'] = $replacement->id;
            $snapshot['replacement_invoice_number'] = $replacement->number;
            $locked->update(['contribution_snapshot' => $snapshot]);

            ClubAuditLog::record($locked->club, $actor, 'club.invoice.replaced_after_accidental_cancellation', $replacement, [
                'cancelled_invoice_id' => $locked->id,
                'cancelled_invoice_number' => $locked->number,
                'replacement_invoice_id' => $replacement->id,
                'replacement_invoice_number' => $replacement->number,
                'transferred_payment_ids' => $creditPaymentIds->values()->all(),
            ]);

            return $replacement->refresh();
        });
    }

    private function nextInvoiceNumber(Invoice $invoice): string
    {
        $next = Invoice::query()
            ->where('club_id', $invoice->club_id)
            ->whereYear('created_at', now()->year)
            ->count() + 1;

        return 'AIR-'.$invoice->club_id.'-'.now()->format('Y').'-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
