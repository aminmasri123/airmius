<?php

namespace App\Services;

use App\Models\Club;
use App\Models\ClubFinanceEntry;
use App\Models\ClubSepaBatch;
use App\Models\ClubSepaBatchItem;
use App\Models\ClubSepaFeeCorrection;
use App\Models\ClubSepaFeeRecharge;
use App\Models\ClubSepaFeeRechargeCredit;
use App\Models\ClubSepaFeeRechargeVoid;
use App\Models\ClubSepaSettlement;
use App\Models\Invoice;
use App\Models\User;
use App\Support\ClubAuditLog;
use Illuminate\Support\Facades\DB;

final class ClubSepaFeeRechargeService
{
    public function propose(ClubSepaBatchItem $item, User $actor, array $data): ClubSepaFeeRecharge
    {
        return DB::transaction(function () use ($item, $actor, $data) {
            [$club, $batch, $result] = $this->lockContext($item);
            $existing = ClubSepaFeeRecharge::where('request_id', $data['request_id'])->first();
            if ($existing) {
                abort_unless($existing->settlement_id === $result->id && $existing->proposed_by === $actor->id
                    && $existing->fee_revision === (int) $data['expected_revision']
                    && $existing->amount_cents === (int) $data['amount_cents']
                    && $existing->due_date->toDateString() === $data['due_date']
                    && $existing->basis === trim($data['basis']) && $existing->reason === trim($data['reason']), 422, __('sepa.recharge_changed'));

                return $existing;
            }
            abort_unless($data['due_date'] >= today()->toDateString(), 422, __('sepa.recharge_changed'));
            abort_if(ClubSepaFeeRecharge::where('active_settlement_id', $result->id)->exists(), 422, __('sepa.recharge_active'));
            $fee = ClubFinanceEntry::lockForUpdate()->findOrFail($result->fee_finance_entry_id);
            abort_unless($fee->club_id === $club->id && $fee->type === 'expense' && $fee->account === 'bank', 422, __('sepa.fee_mismatch'));
            $last = ClubSepaFeeCorrection::where('settlement_id', $result->id)->orderByDesc('revision')->first();
            $revision = $last?->revision ?? 0;
            $amount = $last?->amount_cents ?? (int) round((float) $fee->amount * 100);
            abort_unless($revision === (int) $data['expected_revision'] && (int) $data['amount_cents'] > 0
                && (int) $data['amount_cents'] <= $amount, 422, __('sepa.recharge_changed'));
            $invoice = Invoice::lockForUpdate()->findOrFail($item->invoice_id);
            abort_unless($invoice->club_id === $club->id && $invoice->user_id && $invoice->user()->exists(), 422, __('sepa.recharge_changed'));
            $proposal = ClubSepaFeeRecharge::create([
                'club_id' => $club->id, 'settlement_id' => $result->id, 'active_settlement_id' => $result->id,
                'source_invoice_id' => $invoice->id, 'member_id' => $invoice->user_id, 'proposed_by' => $actor->id,
                'request_id' => $data['request_id'], 'status' => 'draft', 'fee_revision' => $revision,
                'fee_amount_cents' => $amount, 'amount_cents' => (int) $data['amount_cents'],
                'due_date' => $data['due_date'], 'basis' => trim($data['basis']), 'reason' => trim($data['reason']),
            ]);
            ClubAuditLog::record($club, $actor, 'club.sepa.fee_recharge_proposed', $batch, [
                'proposal_id' => $proposal->id, 'item_id' => $item->id, 'amount_cents' => $proposal->amount_cents,
            ]);

            return $proposal;
        });
    }

    public function approve(ClubSepaBatchItem $item, ClubSepaFeeRecharge $proposal, User $actor, string $revenueAccount): ClubSepaFeeRecharge
    {
        return DB::transaction(function () use ($item, $proposal, $actor, $revenueAccount) {
            [$club, $batch, $result] = $this->lockContext($item);
            $proposal = ClubSepaFeeRecharge::lockForUpdate()->findOrFail($proposal->id);
            abort_unless($proposal->club_id === $club->id && $proposal->settlement_id === $result->id, 404);
            abort_unless($proposal->proposed_by && $proposal->proposed_by !== $actor->id, 422, __('sepa.second_person'));
            if ($proposal->status === 'approved') {
                abort_unless($proposal->approved_by === $actor->id && $proposal->revenue_account === $revenueAccount && $proposal->invoice_id, 422, __('sepa.recharge_changed'));

                return $proposal;
            }
            abort_unless($proposal->status === 'draft' && $proposal->active_settlement_id === $result->id
                && $proposal->due_date->toDateString() >= today()->toDateString(), 422, __('sepa.recharge_changed'));
            abort_if(ltrim($revenueAccount, '0') === ltrim(trim($club->datev_bank_account ?: '1200'), '0'), 422, __('sepa.recharge_account'));
            $fee = ClubFinanceEntry::lockForUpdate()->findOrFail($result->fee_finance_entry_id);
            abort_unless($fee->club_id === $club->id && $fee->type === 'expense' && $fee->account === 'bank', 422, __('sepa.fee_mismatch'));
            $last = ClubSepaFeeCorrection::where('settlement_id', $result->id)->orderByDesc('revision')->first();
            $amount = $last?->amount_cents ?? (int) round((float) $fee->amount * 100);
            abort_unless(($last?->revision ?? 0) === $proposal->fee_revision && $amount === $proposal->fee_amount_cents
                && $proposal->amount_cents > 0 && $proposal->amount_cents <= $amount, 422, __('sepa.recharge_changed'));
            $source = Invoice::lockForUpdate()->findOrFail($item->invoice_id);
            abort_unless($source->id === $proposal->source_invoice_id && $source->club_id === $club->id
                && $proposal->member_id && $source->user_id === $proposal->member_id && $source->user()->exists(), 422, __('sepa.recharge_changed'));
            $number = 'AIR-FEE-'.$club->id.'-'.$proposal->id;
            abort_if(Invoice::where('number', $number)->exists(), 422, __('sepa.recharge_changed'));
            $invoice = Invoice::create([
                'club_id' => $club->id, 'user_id' => $proposal->member_id, 'number' => $number,
                'title' => __('sepa.recharge_title'), 'description' => $proposal->reason,
                'amount' => $proposal->amount_cents / 100, 'status' => 'open', 'source' => 'sepa_fee_recharge',
                'due_date' => $proposal->due_date->toDateString(), 'issued_at' => now(),
            ]);
            $proposal->update(['status' => 'approved', 'invoice_id' => $invoice->id, 'approved_by' => $actor->id,
                'approved_at' => now(), 'revenue_account' => $revenueAccount]);
            ClubAuditLog::record($club, $actor, 'club.sepa.fee_recharge_approved', $batch, [
                'proposal_id' => $proposal->id, 'invoice_id' => $invoice->id, 'amount_cents' => $proposal->amount_cents,
                'revenue_account' => $revenueAccount,
            ]);

            return $proposal;
        });
    }

    public function requestVoid(ClubSepaBatchItem $item, ClubSepaFeeRecharge $proposal, User $actor, array $data): ClubSepaFeeRechargeVoid
    {
        return DB::transaction(function () use ($item, $proposal, $actor, $data) {
            [$club, $batch, $result] = $this->lockContext($item);
            $proposal = ClubSepaFeeRecharge::lockForUpdate()->findOrFail($proposal->id);
            abort_unless($proposal->club_id === $club->id && $proposal->settlement_id === $result->id, 404);
            $existing = ClubSepaFeeRechargeVoid::where('request_id', $data['request_id'])->first();
            if ($existing) {
                abort_unless($existing->recharge_id === $proposal->id && $existing->requested_by === $actor->id
                    && $existing->reason === trim($data['reason']), 422, __('sepa.recharge_changed'));

                return $existing;
            }
            $this->lockVoidableInvoice($proposal);
            abort_if(ClubSepaFeeRechargeVoid::where('active_recharge_id', $proposal->id)->exists(), 422, __('sepa.recharge_changed'));
            $request = ClubSepaFeeRechargeVoid::create([
                'club_id' => $club->id, 'recharge_id' => $proposal->id, 'active_recharge_id' => $proposal->id,
                'request_id' => $data['request_id'], 'requested_by' => $actor->id, 'status' => 'pending', 'reason' => trim($data['reason']),
            ]);
            ClubAuditLog::record($club, $actor, 'club.sepa.fee_recharge_void_requested', $batch, ['proposal_id' => $proposal->id, 'void_request_id' => $request->id]);

            return $request;
        });
    }

    public function reviewVoid(ClubSepaBatchItem $item, ClubSepaFeeRecharge $proposal, ClubSepaFeeRechargeVoid $request, User $actor, bool $approve, ?string $reason = null): ClubSepaFeeRechargeVoid
    {
        return DB::transaction(function () use ($item, $proposal, $request, $actor, $approve, $reason) {
            [$club, $batch, $result] = $this->lockContext($item);
            $proposal = ClubSepaFeeRecharge::lockForUpdate()->findOrFail($proposal->id);
            $request = ClubSepaFeeRechargeVoid::lockForUpdate()->findOrFail($request->id);
            abort_unless($proposal->club_id === $club->id && $proposal->settlement_id === $result->id
                && $request->club_id === $club->id && $request->recharge_id === $proposal->id, 404);
            $status = $approve ? 'applied' : 'withdrawn';
            $reason = $approve ? null : trim((string) $reason);
            if ($request->status === $status) {
                abort_unless($request->reviewed_by === $actor->id && $request->review_reason === $reason, 422, __('sepa.recharge_changed'));

                return $request;
            }
            abort_unless($request->status === 'pending' && $request->active_recharge_id === $proposal->id, 422, __('sepa.recharge_changed'));
            if ($approve) {
                abort_unless($request->requested_by && $request->requested_by !== $actor->id, 422, __('sepa.second_person'));
                $invoice = $this->lockVoidableInvoice($proposal);
                // Deliberate controlled transition; generic model updates remain forbidden.
                Invoice::whereKey($invoice->id)->update(['status' => 'cancelled', 'paid_at' => null]);
                $proposal->update(['status' => 'voided', 'active_settlement_id' => null, 'review_required' => false]);
            }
            $request->update(['status' => $status, 'active_recharge_id' => null, 'reviewed_by' => $actor->id,
                'reviewed_at' => now(), 'review_reason' => $reason]);
            ClubAuditLog::record($club, $actor, $approve ? 'club.sepa.fee_recharge_voided' : 'club.sepa.fee_recharge_void_withdrawn', $batch,
                ['proposal_id' => $proposal->id, 'invoice_id' => $proposal->invoice_id, 'void_request_id' => $request->id]);

            return $request;
        });
    }

    private function lockVoidableInvoice(ClubSepaFeeRecharge $proposal): Invoice
    {
        abort_unless($proposal->status === 'approved' && $proposal->invoice_id, 422, __('sepa.recharge_changed'));
        $invoice = Invoice::lockForUpdate()->findOrFail($proposal->invoice_id);
        abort_unless($invoice->club_id === $proposal->club_id && $invoice->user_id === $proposal->member_id
            && $invoice->source === 'sepa_fee_recharge'
            && (int) round((float) $invoice->amount * 100) === $proposal->amount_cents
            && in_array($invoice->status, ['open', 'overdue'], true), 422, __('sepa.recharge_changed'));
        abort_if($invoice->payments()->exists() || $invoice->bankTransactions()->exists() || $invoice->sepa_exported_at
            || ClubSepaBatchItem::where('invoice_id', $invoice->id)->whereHas('batch', fn ($query) => $query->where('status', '!=', 'cancelled'))->exists(), 422, __('sepa.recharge_void_bank'));

        return $invoice;
    }

    public function requestCredit(ClubSepaBatchItem $item, ClubSepaFeeRecharge $proposal, User $actor, array $data): ClubSepaFeeRechargeCredit
    {
        return DB::transaction(function () use ($item, $proposal, $actor, $data) {
            [$club, $batch, $result] = $this->lockContext($item);
            $proposal = ClubSepaFeeRecharge::lockForUpdate()->findOrFail($proposal->id);
            abort_unless($proposal->club_id === $club->id && $proposal->settlement_id === $result->id, 404);
            $existing = ClubSepaFeeRechargeCredit::where('request_id', $data['request_id'])->first();
            if ($existing) {
                abort_unless($existing->recharge_id === $proposal->id && $existing->requested_by === $actor->id
                    && $existing->reason === trim($data['reason']), 422, __('sepa.recharge_changed'));

                return $existing;
            }
            [$invoice] = $this->lockCreditableInvoice($proposal);
            abort_if(ClubSepaFeeRechargeCredit::where('active_recharge_id', $proposal->id)->exists()
                || ClubSepaFeeRechargeVoid::where('active_recharge_id', $proposal->id)->exists(), 422, __('sepa.recharge_changed'));
            $request = ClubSepaFeeRechargeCredit::create([
                'club_id' => $club->id, 'recharge_id' => $proposal->id, 'active_recharge_id' => $proposal->id,
                'request_id' => $data['request_id'], 'requested_by' => $actor->id, 'status' => 'pending',
                'reason' => trim($data['reason']), 'amount_cents' => (int) round((float) $invoice->amount * 100),
            ]);
            ClubAuditLog::record($club, $actor, 'club.sepa.fee_recharge_credit_requested', $batch, [
                'proposal_id' => $proposal->id, 'credit_request_id' => $request->id, 'invoice_id' => $invoice->id,
                'amount_cents' => $request->amount_cents,
            ]);

            return $request;
        });
    }

    public function reviewCredit(ClubSepaBatchItem $item, ClubSepaFeeRecharge $proposal, ClubSepaFeeRechargeCredit $request, User $actor, bool $approve, ?string $reason = null): ClubSepaFeeRechargeCredit
    {
        return DB::transaction(function () use ($item, $proposal, $request, $actor, $approve, $reason) {
            [$club, $batch, $result] = $this->lockContext($item);
            $proposal = ClubSepaFeeRecharge::lockForUpdate()->findOrFail($proposal->id);
            $request = ClubSepaFeeRechargeCredit::lockForUpdate()->findOrFail($request->id);
            abort_unless($proposal->club_id === $club->id && $proposal->settlement_id === $result->id
                && $request->club_id === $club->id && $request->recharge_id === $proposal->id, 404);
            $status = $approve ? 'issued' : 'withdrawn';
            $reason = $approve ? null : trim((string) $reason);
            if (($approve && in_array($request->status, ['issued', 'completed', 'refunded'], true))
                || (! $approve && $request->status === $status)) {
                abort_unless($request->reviewed_by === $actor->id && $request->review_reason === $reason, 422, __('sepa.recharge_changed'));

                return $request;
            }
            abort_unless($request->status === 'pending' && $request->active_recharge_id === $proposal->id, 422, __('sepa.recharge_changed'));
            $updates = ['status' => $status, 'active_recharge_id' => null, 'reviewed_by' => $actor->id,
                'reviewed_at' => now(), 'review_reason' => $reason];
            if ($approve) {
                abort_unless($request->requested_by && $request->requested_by !== $actor->id, 422, __('sepa.second_person'));
                [$invoice, $payments] = $this->lockCreditableInvoice($proposal);
                abort_unless($request->amount_cents === (int) round((float) $invoice->amount * 100), 422, __('sepa.recharge_changed'));
                $refundDue = (int) round((float) $payments->where('status', 'paid')->sum('amount') * 100);
                $updates['status'] = $refundDue > 0 ? 'issued' : 'completed';
                $updates['refund_due_cents'] = $refundDue;
                $updates['credit_note_number'] = 'AIR-GS-FEE-'.$club->id.'-'.$request->id;
                Invoice::whereKey($invoice->id)->update(['status' => 'cancelled', 'paid_at' => null]);
                $proposal->update(['status' => 'credited', 'active_settlement_id' => null, 'review_required' => false]);
            }
            $request->update($updates);
            ClubAuditLog::record($club, $actor, $approve ? 'club.sepa.fee_recharge_credited' : 'club.sepa.fee_recharge_credit_withdrawn', $batch, [
                'proposal_id' => $proposal->id, 'credit_request_id' => $request->id, 'invoice_id' => $proposal->invoice_id,
                'amount_cents' => $request->amount_cents, 'refund_due_cents' => $updates['refund_due_cents'] ?? null,
            ]);

            return $request;
        });
    }

    public function recordRefund(ClubSepaBatchItem $item, ClubSepaFeeRecharge $proposal, ClubSepaFeeRechargeCredit $request, User $actor, array $data): ClubSepaFeeRechargeCredit
    {
        return DB::transaction(function () use ($item, $proposal, $request, $actor, $data) {
            [$club, $batch, $result] = $this->lockContext($item);
            $proposal = ClubSepaFeeRecharge::lockForUpdate()->findOrFail($proposal->id);
            $request = ClubSepaFeeRechargeCredit::lockForUpdate()->findOrFail($request->id);
            abort_unless($proposal->club_id === $club->id && $proposal->settlement_id === $result->id
                && $proposal->status === 'credited' && $request->club_id === $club->id
                && $request->recharge_id === $proposal->id && $request->refund_due_cents > 0, 422, __('sepa.recharge_changed'));
            $reference = mb_strtoupper(trim($data['reference']));
            $matches = fn ($entry) => $entry && $entry->club_id === $club->id && $entry->type === 'expense'
                && $entry->account === 'bank' && (int) round((float) $entry->amount * 100) === $request->refund_due_cents
                && $entry->booked_on->toDateString() === $data['booked_on']
                && mb_strtoupper(trim((string) $entry->reference)) === $reference;
            if ($request->status === 'refunded') {
                $entry = ClubFinanceEntry::lockForUpdate()->find($request->refund_finance_entry_id);
                abort_unless($request->refund_request_id === $data['request_id'] && $matches($entry)
                    && (isset($data['finance_entry_id']) ? (int) $data['finance_entry_id'] === $entry->id : $request->refund_created_entry),
                    422, __('sepa.recharge_changed'));

                return $request;
            }
            abort_unless($request->status === 'issued' && $request->reviewed_at
                && $data['booked_on'] >= $request->reviewed_at->toDateString()
                && $data['booked_on'] <= today()->toDateString(), 422, __('sepa.bank_date'));
            abort_if(ClubSepaFeeRechargeCredit::where('refund_request_id', $data['request_id'])->exists(), 422, __('sepa.recharge_changed'));
            if (isset($data['finance_entry_id'])) {
                $entry = ClubFinanceEntry::lockForUpdate()->find($data['finance_entry_id']);
                abort_unless($matches($entry), 422, __('sepa.fee_mismatch'));
                abort_if(ClubSepaFeeRechargeCredit::where('refund_finance_entry_id', $entry->id)->exists()
                    || ClubSepaFeeCorrection::where('finance_entry_id', $entry->id)->exists()
                    || ClubSepaSettlement::where('fee_finance_entry_id', $entry->id)->exists(),
                    422, __('sepa.fee_controlled'));
            } else {
                abort_if(ClubFinanceEntry::where('club_id', $club->id)->where('account', 'bank')->select(['id', 'reference'])->lazyById(500)
                    ->contains(fn ($candidate) => mb_strtoupper(trim((string) $candidate->reference)) === $reference), 422, __('sepa.bank_reference_used'));
                $entry = ClubFinanceEntry::create([
                    'club_id' => $club->id, 'user_id' => $actor->id, 'type' => 'expense', 'account' => 'bank',
                    'category' => 'sepa_fee_recharge_refund', 'title' => __('sepa.recharge_refund_title'),
                    'amount' => $request->refund_due_cents / 100, 'booked_on' => $data['booked_on'], 'reference' => $reference,
                    'description' => $request->reason,
                ]);
            }
            $request->update(['status' => 'refunded', 'refund_request_id' => $data['request_id'],
                'refund_finance_entry_id' => $entry->id, 'refund_recorded_by' => $actor->id,
                'refund_booked_on' => $data['booked_on'], 'refund_reference' => $reference,
                'refund_created_entry' => ! isset($data['finance_entry_id']), 'refund_recorded_at' => now()]);
            ClubAuditLog::record($club, $actor, 'club.sepa.fee_recharge_refunded', $batch, [
                'proposal_id' => $proposal->id, 'credit_request_id' => $request->id, 'invoice_id' => $proposal->invoice_id,
                'finance_entry_id' => $entry->id, 'amount_cents' => $request->refund_due_cents,
                'created_entry' => $request->refund_created_entry,
            ]);

            return $request;
        });
    }

    private function lockCreditableInvoice(ClubSepaFeeRecharge $proposal): array
    {
        abort_unless($proposal->status === 'approved' && $proposal->invoice_id, 422, __('sepa.recharge_changed'));
        $invoice = Invoice::lockForUpdate()->findOrFail($proposal->invoice_id);
        abort_unless($invoice->club_id === $proposal->club_id && $invoice->user_id === $proposal->member_id
            && $invoice->source === 'sepa_fee_recharge'
            && (int) round((float) $invoice->amount * 100) === $proposal->amount_cents
            && in_array($invoice->status, ['open', 'overdue', 'paid'], true), 422, __('sepa.recharge_changed'));
        $payments = $invoice->payments()->lockForUpdate()->get();
        $bankTransactions = $invoice->bankTransactions()->lockForUpdate()->get();
        $activeSepa = ClubSepaBatchItem::where('invoice_id', $invoice->id)
            ->whereHas('batch', fn ($query) => $query->where('status', '!=', 'cancelled'))->exists();
        abort_unless($payments->isNotEmpty() || $bankTransactions->isNotEmpty() || $invoice->sepa_exported_at || $activeSepa, 422, __('sepa.recharge_void_bank'));

        return [$invoice, $payments];
    }

    public function cancel(ClubSepaBatchItem $item, ClubSepaFeeRecharge $proposal, User $actor, string $reason): ClubSepaFeeRecharge
    {
        return DB::transaction(function () use ($item, $proposal, $actor, $reason) {
            [$club, $batch, $result] = $this->lockContext($item);
            $proposal = ClubSepaFeeRecharge::lockForUpdate()->findOrFail($proposal->id);
            abort_unless($proposal->club_id === $club->id && $proposal->settlement_id === $result->id, 404);
            if ($proposal->status === 'cancelled') {
                abort_unless($proposal->cancelled_by === $actor->id && $proposal->cancellation_reason === trim($reason), 422, __('sepa.recharge_changed'));

                return $proposal;
            }
            abort_unless($proposal->status === 'draft', 422, __('sepa.recharge_changed'));
            $proposal->update(['status' => 'cancelled', 'active_settlement_id' => null,
                'cancelled_by' => $actor->id, 'cancelled_at' => now(), 'cancellation_reason' => trim($reason)]);
            ClubAuditLog::record($club, $actor, 'club.sepa.fee_recharge_cancelled', $batch, ['proposal_id' => $proposal->id, 'item_id' => $item->id]);

            return $proposal;
        });
    }

    private function lockContext(ClubSepaBatchItem $item): array
    {
        $batch = ClubSepaBatch::findOrFail($item->club_sepa_batch_id);
        $club = Club::lockForUpdate()->findOrFail($batch->club_id);
        $batch = ClubSepaBatch::lockForUpdate()->findOrFail($batch->id);
        abort_unless($batch->status === 'exported', 422, __('sepa.export_required'));
        $lockedItem = ClubSepaBatchItem::lockForUpdate()->findOrFail($item->id);
        abort_unless($lockedItem->invoice_id === $item->invoice_id && $lockedItem->club_sepa_batch_id === $batch->id, 422, __('sepa.recharge_changed'));
        $result = $lockedItem->settlement()->lockForUpdate()->first();
        abort_unless($result?->status === 'returned' && $result->club_id === $club->id && $result->fee_finance_entry_id, 422, __('sepa.recharge_changed'));

        return [$club, $batch, $result];
    }
}
