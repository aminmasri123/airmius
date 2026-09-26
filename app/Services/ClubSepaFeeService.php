<?php

namespace App\Services;

use App\Models\Club;
use App\Models\ClubFinanceEntry;
use App\Models\ClubSepaBatch;
use App\Models\ClubSepaBatchItem;
use App\Models\ClubSepaFeeCorrection;
use App\Models\ClubSepaFeeRecharge;
use App\Models\ClubSepaFeeRechargeCredit;
use App\Models\ClubSepaSettlement;
use App\Models\User;
use App\Support\ClubAuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

final class ClubSepaFeeService
{
    public function record(ClubSepaBatchItem $item, User $actor, array $data): ClubSepaSettlement
    {
        $data['amount_cents'] = (int) $data['amount_cents'];

        return DB::transaction(function () use ($item, $actor, $data) {
            $batch = ClubSepaBatch::findOrFail($item->club_sepa_batch_id);
            $club = Club::lockForUpdate()->findOrFail($batch->club_id);
            $batch = ClubSepaBatch::lockForUpdate()->findOrFail($batch->id);
            abort_unless($batch->status === 'exported', 422, __('sepa.export_required'));
            $item = ClubSepaBatchItem::lockForUpdate()->findOrFail($item->id);
            $result = $item->settlement()->lockForUpdate()->first();
            abort_unless($result?->status === 'returned' && $result->club_id === $club->id, 422, __('sepa.return_required'));
            $reference = mb_strtoupper(trim($data['reference']));
            Validator::make(['reference' => $reference], ['reference' => ['required', 'string', 'max:180']])->validate();
            abort_unless($data['booked_on'] >= $result->returned_on->toDateString()
                && $data['booked_on'] <= today()->toDateString(), 422, __('sepa.bank_date'));
            $matches = fn ($entry) => $entry && $entry->club_id === $club->id && $entry->type === 'expense'
                && $entry->account === 'bank' && (int) round((float) $entry->amount * 100) === $data['amount_cents']
                && $entry->booked_on->toDateString() === $data['booked_on'] && mb_strtoupper(trim((string) $entry->reference)) === $reference;
            if ($result->fee_finance_entry_id) {
                $entry = ClubFinanceEntry::lockForUpdate()->find($result->fee_finance_entry_id);
                abort_unless($matches($entry) && (isset($data['finance_entry_id'])
                    ? (int) $data['finance_entry_id'] === $entry->id : $result->fee_created_entry), 422, __('sepa.fee_recorded'));

                return $result->load('feeEntry');
            }
            if (isset($data['finance_entry_id'])) {
                $entry = ClubFinanceEntry::lockForUpdate()->find($data['finance_entry_id']);
                abort_unless($matches($entry), 422, __('sepa.fee_mismatch'));
                if (Schema::hasTable('club_sepa_fee_corrections')) {
                    abort_if(ClubSepaFeeCorrection::where('finance_entry_id', $entry->id)->exists(), 422, __('sepa.fee_controlled'));
                }
                abort_if(ClubSepaSettlement::where('fee_finance_entry_id', $entry->id)->exists(), 422, __('sepa.fee_recorded'));
            } else {
                // Existing bank expenses must be explicitly linked, never copied.
                abort_if(ClubFinanceEntry::where('club_id', $club->id)->where('account', 'bank')
                    ->select(['id', 'reference'])->lazyById(500)
                    ->contains(fn ($candidate) => mb_strtoupper(trim((string) $candidate->reference)) === $reference), 422, __('sepa.fee_existing'));
                $entry = ClubFinanceEntry::create([
                    'club_id' => $club->id, 'user_id' => $actor->id, 'type' => 'expense', 'account' => 'bank',
                    'category' => 'sepa_return_fee', 'title' => __('sepa.fee_title'),
                    'amount' => $data['amount_cents'] / 100, 'booked_on' => $data['booked_on'], 'reference' => $reference,
                ]);
            }
            $result->update(['fee_finance_entry_id' => $entry->id, 'fee_created_entry' => ! isset($data['finance_entry_id'])]);
            ClubAuditLog::record($club, $actor, 'club.sepa.fee_recorded', $batch, [
                'item_id' => $item->id, 'settlement_id' => $result->id, 'finance_entry_id' => $entry->id,
                'amount_cents' => $data['amount_cents'], 'created_entry' => $result->fee_created_entry,
            ]);

            return $result->load('feeEntry');
        });
    }

    public function correct(ClubSepaBatchItem $item, User $actor, array $data): ClubSepaFeeCorrection
    {
        return DB::transaction(function () use ($item, $actor, $data) {
            $batch = ClubSepaBatch::findOrFail($item->club_sepa_batch_id);
            $club = Club::lockForUpdate()->findOrFail($batch->club_id);
            $batch = ClubSepaBatch::lockForUpdate()->findOrFail($batch->id);
            abort_unless($batch->status === 'exported', 422, __('sepa.export_required'));
            $item = ClubSepaBatchItem::lockForUpdate()->findOrFail($item->id);
            $result = $item->settlement()->lockForUpdate()->first();
            abort_unless($result?->status === 'returned' && $result->club_id === $club->id && $result->fee_finance_entry_id, 422, __('sepa.fee_correction_state'));
            $original = ClubFinanceEntry::lockForUpdate()->findOrFail($result->fee_finance_entry_id);
            abort_unless($original->club_id === $club->id && $original->type === 'expense' && $original->account === 'bank', 422, __('sepa.fee_mismatch'));
            $reference = mb_strtoupper(trim($data['reference']));
            $reason = trim($data['reason']);
            Validator::make(['reference' => $reference], ['reference' => ['required', 'string', 'max:180']])->validate();
            $previousRequest = ClubSepaFeeCorrection::where('request_id', $data['request_id'])->first();
            if ($previousRequest) {
                abort_unless($previousRequest->settlement_id === $result->id && $previousRequest->user_id === $actor->id
                    && $previousRequest->revision === (int) $data['expected_revision'] + 1
                    && $previousRequest->amount_cents === (int) $data['amount_cents']
                    && $previousRequest->booked_on->toDateString() === $data['booked_on']
                    && $previousRequest->reference === $reference && $previousRequest->reason === $reason, 422, __('sepa.fee_correction_state'));

                return $previousRequest;
            }
            $last = ClubSepaFeeCorrection::where('settlement_id', $result->id)->orderByDesc('revision')->first();
            $revision = $last?->revision ?? 0;
            $previousAmount = $last?->amount_cents ?? (int) round((float) $original->amount * 100);
            $amount = (int) $data['amount_cents'];
            abort_unless($revision === (int) $data['expected_revision'] && $amount !== $previousAmount, 422, __('sepa.fee_correction_state'));
            abort_unless($data['booked_on'] >= ($last?->booked_on ?? $original->booked_on)->toDateString()
                && $data['booked_on'] <= today()->toDateString(), 422, __('sepa.bank_date'));
            abort_if(ClubFinanceEntry::where('club_id', $club->id)->where('account', 'bank')->select(['id', 'reference'])->lazyById(500)
                ->contains(fn ($candidate) => mb_strtoupper(trim((string) $candidate->reference)) === $reference), 422, __('sepa.bank_reference_used'));
            // A signed expense adjusts the original expense total without manufacturing income.
            $entryAttributes = [
                'club_id' => $club->id, 'user_id' => $actor->id, 'type' => 'expense', 'account' => 'bank',
                'category' => 'sepa_return_fee_correction', 'title' => __('sepa.fee_correction_title'),
                'amount' => ($amount - $previousAmount) / 100, 'booked_on' => $data['booked_on'], 'reference' => $reference,
                'description' => $reason,
            ];
            if (Schema::hasColumn('club_finance_entries', 'reversal_of_id')) {
                $entryAttributes['reversal_of_id'] = $original->id;
            }
            if (Schema::hasColumn('club_finance_entries', 'correction_snapshot')) {
                $entryAttributes['correction_snapshot'] = [
                    'source' => 'club_sepa_fee_correction',
                    'settlement_id' => $result->id,
                    'revision' => $revision + 1,
                    'previous_amount_cents' => $previousAmount,
                    'amount_cents' => $amount,
                    'delta_cents' => $amount - $previousAmount,
                    'previous_finance_entry_id' => $last?->finance_entry_id,
                    'original_finance_entry_id' => $original->id,
                ];
            }
            $entry = ClubFinanceEntry::create($entryAttributes);
            $correction = ClubSepaFeeCorrection::create([
                'club_id' => $club->id, 'settlement_id' => $result->id, 'finance_entry_id' => $entry->id, 'user_id' => $actor->id,
                'request_id' => $data['request_id'], 'revision' => $revision + 1, 'previous_amount_cents' => $previousAmount,
                'amount_cents' => $amount, 'booked_on' => $data['booked_on'], 'reference' => $reference, 'reason' => $reason,
            ]);
            if (Schema::hasColumn('club_sepa_fee_recharges', 'review_required')) {
                ClubSepaFeeRecharge::where('settlement_id', $result->id)->where('status', 'approved')->update(['review_required' => true]);
            }
            ClubAuditLog::record($club, $actor, 'club.sepa.fee_corrected', $batch, [
                'item_id' => $item->id, 'correction_id' => $correction->id, 'finance_entry_id' => $entry->id,
                'previous_amount_cents' => $previousAmount, 'amount_cents' => $amount,
            ]);

            return $correction;
        });
    }

    public function updateFinanceEntry(ClubFinanceEntry $entry, array $attributes): void
    {
        DB::transaction(function () use ($entry, $attributes) {
            Club::lockForUpdate()->findOrFail($entry->club_id);
            $locked = ClubFinanceEntry::lockForUpdate()->findOrFail($entry->id);
            if (Schema::hasColumn('club_sepa_settlements', 'fee_finance_entry_id')) {
                abort_if(ClubSepaSettlement::where('fee_finance_entry_id', $locked->id)->exists(), 422, __('sepa.fee_controlled'));
            }
            if (Schema::hasTable('club_sepa_fee_corrections')) {
                abort_if(ClubSepaFeeCorrection::where('finance_entry_id', $locked->id)->exists(), 422, __('sepa.fee_controlled'));
            }
            if (Schema::hasTable('club_sepa_fee_recharge_credits')) {
                abort_if(ClubSepaFeeRechargeCredit::where('refund_finance_entry_id', $locked->id)->exists(), 422, __('sepa.fee_controlled'));
            }
            if (Schema::hasColumn('club_finance_entries', 'reversal_of_id')) {
                abort_if(ClubFinanceEntry::where('reversal_of_id', $locked->id)->exists() || $locked->reversal_of_id, 422, __('sepa.fee_controlled'));
            }
            $locked->update($attributes);
        });
    }
}
