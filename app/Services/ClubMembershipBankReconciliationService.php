<?php

namespace App\Services;

use App\Models\Club;
use App\Models\BankTransaction;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\AppNotification;
use App\Support\ClubMembershipInput;
use Illuminate\Support\Facades\DB;

class ClubMembershipBankReconciliationService
{
    public function readRows(string $path): array
    {
        $handle = fopen($path, 'r');

        if (! $handle) {
            return [];
        }

        $firstLine = fgets($handle) ?: '';
        rewind($handle);
        $delimiter = str_contains($firstLine, ';') ? ';' : (str_contains($firstLine, "\t") ? "\t" : ',');
        $tableRows = [];

        while (($values = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($values !== [null] && $values !== false) {
                $tableRows[] = $values;
            }
        }

        fclose($handle);

        return $this->normalizeTableRows($tableRows);
    }

    public function transactionFromRow(array $row): ?array
    {
        $amount = ClubMembershipInput::normalizeMoney(
            $row['betrag'] ?? $row['amount'] ?? $row['umsatz'] ?? $row['wert'] ?? null
        );

        if ($amount === null) {
            return null;
        }

        $purpose = trim((string) (
            $row['verwendungszweck']
            ?? $row['purpose']
            ?? $row['referenz']
            ?? $row['reference']
            ?? $row['buchungstext']
            ?? ''
        ));
        $debtorName = trim((string) (
            $row['auftraggeber']
            ?? $row['zahler']
            ?? $row['name']
            ?? $row['debtor_name']
            ?? ''
        ));
        $debtorIban = ClubMembershipInput::normalizeIban($row['iban'] ?? $row['debtor_iban'] ?? $row['konto'] ?? null);
        $bookingDate = ClubMembershipInput::normalizeDate($row['datum'] ?? $row['date'] ?? $row['buchungstag'] ?? $row['booking_date'] ?? null);
        $currency = strtoupper(trim((string) ($row['Währung'] ?? $row['currency'] ?? 'EUR'))) ?: 'EUR';

        $hashPayload = implode('|', [
            $bookingDate,
            number_format((float) $amount, 2, '.', ''),
            $currency,
            $debtorName,
            $debtorIban,
            $purpose,
        ]);

        return [
            'transaction_hash' => hash('sha256', $hashPayload),
            'booking_date' => $bookingDate,
            'amount' => $amount,
            'currency' => $currency,
            'debtor_name' => $debtorName ?: null,
            'debtor_iban' => $debtorIban,
            'purpose' => $purpose ?: null,
            'raw_data' => $row,
        ];
    }

    public function matchInvoice(Club $club, array $transaction): array
    {
        $openInvoices = Invoice::query()
            ->where('club_id', $club->id)
            ->whereIn('status', ['open', 'overdue'])
            ->whereNotNull('user_id')
            ->with('user:id,name,email')
            ->withSum('settledPayments', 'amount')
            ->get();

        $purpose = strtoupper((string) ($transaction['purpose'] ?? ''));
        $amount = round((float) $transaction['amount'], 2);

        $numberMatch = $openInvoices->first(fn (Invoice $invoice) => str_contains($purpose, strtoupper($invoice->number))
            && $invoice->outstandingCents() === (int) round($amount * 100));

        if ($numberMatch) {
            return [
                'invoice' => $numberMatch,
                'status' => 'matched',
                'confidence' => 100,
                'reason' => 'Rechnungsnummer und Betrag stimmen überein.',
            ];
        }

        $sameAmountInvoices = $openInvoices
            ->filter(fn (Invoice $invoice) => $invoice->outstandingCents() === (int) round($amount * 100))
            ->values();

        if ($sameAmountInvoices->count() === 1) {
            $invoice = $sameAmountInvoices->first();
            $membership = DB::table('club_user')
                ->where('club_id', $club->id)
                ->where('user_id', $invoice->user_id)
                ->first();

            if ($membership && $transaction['debtor_iban'] && ClubMembershipInput::normalizeIban($membership->sepa_iban ?? null) === $transaction['debtor_iban']) {
                return [
                    'invoice' => $invoice,
                    'status' => 'matched',
                    'confidence' => 95,
                    'reason' => 'Betrag und IBAN stimmen überein.',
                ];
            }

            if ($invoice->user && $this->nameLooksSimilar($invoice->user->name, $transaction['debtor_name'] ?? '')) {
                return [
                    'invoice' => $invoice,
                    'status' => 'suggested',
                    'confidence' => 75,
                    'reason' => 'Betrag stimmt, Name wirkt passend.',
                ];
            }
        }

        return [
            'invoice' => null,
            'status' => 'unmatched',
            'confidence' => 0,
            'reason' => 'Keine eindeutige offene Rechnung gefunden.',
        ];
    }

    public function recordMatchedPayment(Invoice $invoice, array $transaction): Payment
    {
        $payment = app(ClubInvoicePaymentService::class)->record($invoice, [
            'amount' => $transaction['amount'] ?? null,
            'method' => 'bank_import',
            'reference' => $transaction['purpose'] ?? null,
            'paid_at' => $transaction['booking_date'] ?? now(),
            'notes' => 'Automatisch per Bankabgleich zugeordnet.',
            'idempotency_key' => 'bank:'.($transaction['transaction_hash'] ?? hash('sha256', json_encode($transaction))).':invoice:'.$invoice->id,
        ], request()->user());

        if ($invoice->user_id) {
            AppNotification::send((int) $invoice->user_id, 'invoice.paid', [
                'title' => 'Zahlung eingegangen',
                'body' => 'Deine Zahlung für '.$invoice->number.' wurde per Bankabgleich erkannt.',
                'url' => route('auth.settings'),
                'invoice_id' => $invoice->id,
            ]);
        }

        return $payment;
    }

    public function previewAllocations(Club $club, array $transaction, ?array $manualAllocations = null): array
    {
        $amountCents = (int) round((float) ($transaction['amount'] ?? 0) * 100);
        if ($amountCents <= 0 || blank($transaction['booking_date'] ?? null)) {
            return [
                'status' => 'invalid',
                'confidence' => 0,
                'reason' => 'Ungueltiger Bankumsatz.',
                'allocations' => [],
                'allocated_cents' => 0,
                'unallocated_cents' => max(0, $amountCents),
                'overpaid_cents' => 0,
                'conflicts' => ['invalid_transaction'],
            ];
        }

        if ($manualAllocations !== null) {
            return $this->manualAllocationPreview($club, $transaction, $manualAllocations);
        }

        $invoices = Invoice::query()
            ->where('club_id', $club->id)
            ->whereIn('status', ['open', 'overdue'])
            ->whereNotNull('user_id')
            ->with('user:id,name,email')
            ->withSum('settledPayments', 'amount')
            ->orderBy('due_date')
            ->orderBy('id')
            ->get()
            ->filter(fn (Invoice $invoice) => $invoice->outstandingCents() > 0)
            ->values();

        $purpose = strtoupper((string) ($transaction['purpose'] ?? ''));
        $referenced = $invoices
            ->filter(fn (Invoice $invoice) => str_contains($purpose, strtoupper($invoice->number)))
            ->values();

        if ($referenced->isEmpty()) {
            $single = $this->matchInvoice($club, $transaction);

            return [
                'status' => $single['status'],
                'confidence' => $single['confidence'],
                'reason' => $single['reason'],
                'allocations' => $single['invoice'] ? [[
                    'invoice' => $single['invoice'],
                    'amount_cents' => $amountCents,
                    'outstanding_cents' => $single['invoice']->outstandingCents(),
                    'overpaid_cents' => max(0, $amountCents - $single['invoice']->outstandingCents()),
                    'mode' => $amountCents >= $single['invoice']->outstandingCents() ? 'full' : 'partial',
                ]] : [],
                'allocated_cents' => $single['invoice'] ? $amountCents : 0,
                'unallocated_cents' => $single['invoice'] ? 0 : $amountCents,
                'overpaid_cents' => $single['invoice'] ? max(0, $amountCents - $single['invoice']->outstandingCents()) : 0,
                'conflicts' => $single['invoice'] ? [] : ['no_unique_invoice'],
            ];
        }

        $remaining = $amountCents;
        $allocations = [];
        $overpaid = 0;

        foreach ($referenced as $index => $invoice) {
            if ($remaining <= 0) {
                break;
            }

            $outstanding = $invoice->outstandingCents();
            $isLast = $index === $referenced->count() - 1;
            $amount = $isLast ? $remaining : min($remaining, $outstanding);
            $remaining -= $amount;
            $over = max(0, $amount - $outstanding);
            $overpaid += $over;
            $allocations[] = [
                'invoice' => $invoice,
                'amount_cents' => $amount,
                'outstanding_cents' => $outstanding,
                'overpaid_cents' => $over,
                'mode' => $amount >= $outstanding ? ($over > 0 ? 'overpayment' : 'full') : 'partial',
            ];
        }

        $conflicts = [];
        if ($remaining > 0) {
            $conflicts[] = 'unallocated_amount';
        }

        return [
            'status' => $conflicts === [] ? 'matched' : 'suggested',
            'confidence' => $conflicts === [] ? 100 : 85,
            'reason' => $referenced->count() > 1 ? 'Sammelzahlung anhand mehrerer Rechnungsnummern erkannt.' : 'Rechnungsnummer erkannt.',
            'allocations' => $allocations,
            'allocated_cents' => array_sum(array_column($allocations, 'amount_cents')),
            'unallocated_cents' => $remaining,
            'overpaid_cents' => $overpaid,
            'conflicts' => $conflicts,
        ];
    }

    public function recordTransactionAllocations(Club $club, array $transaction, ?array $manualAllocations = null): array
    {
        return DB::transaction(function () use ($club, $transaction, $manualAllocations) {
            $existing = BankTransaction::query()
                ->where('club_id', $club->id)
                ->where('transaction_hash', $transaction['transaction_hash'])
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return ['transaction' => $existing, 'payments' => [], 'duplicate' => true, 'preview' => null];
            }

            $preview = $this->previewAllocations($club, $transaction, $manualAllocations);
            if ($preview['allocations'] === [] || $preview['conflicts'] !== []) {
                $bankTransaction = BankTransaction::query()->create($this->bankTransactionAttributes($club, $transaction, null, null, 'unmatched', $preview));

                return ['transaction' => $bankTransaction, 'payments' => [], 'duplicate' => false, 'preview' => $preview];
            }

            $bankTransaction = BankTransaction::query()->create($this->bankTransactionAttributes($club, $transaction, $preview['allocations'][0]['invoice'], null, 'matched', $preview));
            $payments = [];

            foreach ($preview['allocations'] as $allocation) {
                /** @var Invoice $lockedInvoice */
                $lockedInvoice = Invoice::query()->lockForUpdate()->findOrFail($allocation['invoice']->id);
                abort_unless((int) $lockedInvoice->club_id === (int) $club->id, 422, 'Die Zuordnung enthaelt eine fremde Rechnung.');
                abort_if($lockedInvoice->status === 'cancelled', 422, __('organization.club.cancelled_invoice_payment_forbidden'));
                $payments[] = app(ClubInvoicePaymentService::class)->record($lockedInvoice, [
                    'amount' => number_format($allocation['amount_cents'] / 100, 2, '.', ''),
                    'method' => 'bank_import',
                    'reference' => $transaction['purpose'] ?? null,
                    'paid_at' => $transaction['booking_date'] ?? now(),
                    'notes' => 'Per Sammelzahlung/Bankabgleich zugeordnet.',
                    'source' => 'bank_transaction_allocation',
                ], request()->user(), $allocation['overpaid_cents'] > 0);
            }

            $firstPayment = $payments[0] ?? null;
            $bankTransaction->update([
                'payment_id' => $firstPayment?->id,
                'raw_data' => [
                    ...($bankTransaction->raw_data ?? []),
                    'allocation_payment_ids' => collect($payments)->pluck('id')->all(),
                ],
            ]);

            return ['transaction' => $bankTransaction->fresh(), 'payments' => $payments, 'duplicate' => false, 'preview' => $preview];
        });
    }

    private function manualAllocationPreview(Club $club, array $transaction, array $manualAllocations): array
    {
        $amountCents = (int) round((float) ($transaction['amount'] ?? 0) * 100);
        $invoiceIds = collect($manualAllocations)->pluck('invoice_id')->filter()->map(fn ($id) => (int) $id)->all();
        $invoices = Invoice::query()
            ->where('club_id', $club->id)
            ->whereIn('id', $invoiceIds)
            ->withSum('settledPayments', 'amount')
            ->get()
            ->keyBy('id');
        $allocations = [];
        $conflicts = [];

        foreach ($manualAllocations as $allocation) {
            $invoice = $invoices->get((int) ($allocation['invoice_id'] ?? 0));
            $cents = (int) round((float) ($allocation['amount'] ?? 0) * 100);
            if (! $invoice || $cents <= 0 || in_array($invoice->status, ['paid', 'cancelled'], true)) {
                $conflicts[] = 'invalid_manual_allocation';
                continue;
            }
            $outstanding = $invoice->outstandingCents();
            $allocations[] = [
                'invoice' => $invoice,
                'amount_cents' => $cents,
                'outstanding_cents' => $outstanding,
                'overpaid_cents' => max(0, $cents - $outstanding),
                'mode' => $cents >= $outstanding ? ($cents > $outstanding ? 'overpayment' : 'full') : 'partial',
            ];
        }

        $allocated = array_sum(array_column($allocations, 'amount_cents'));
        if ($allocated !== $amountCents) {
            $conflicts[] = 'allocation_total_mismatch';
        }

        return [
            'status' => $conflicts === [] ? 'matched' : 'conflict',
            'confidence' => $conflicts === [] ? 100 : 0,
            'reason' => $conflicts === [] ? 'Manuelle Zuordnung bestätigt.' : 'Manuelle Zuordnung enthält Konflikte.',
            'allocations' => $allocations,
            'allocated_cents' => $allocated,
            'unallocated_cents' => max(0, $amountCents - $allocated),
            'overpaid_cents' => array_sum(array_column($allocations, 'overpaid_cents')),
            'conflicts' => array_values(array_unique($conflicts)),
        ];
    }

    private function bankTransactionAttributes(Club $club, array $transaction, ?Invoice $invoice, ?Payment $payment, string $status, array $preview): array
    {
        return [
            'club_id' => $club->id,
            'invoice_id' => $invoice?->id,
            'payment_id' => $payment?->id,
            'imported_by' => request()->user()?->id,
            'transaction_hash' => $transaction['transaction_hash'],
            'booking_date' => $transaction['booking_date'],
            'amount' => $transaction['amount'],
            'currency' => $transaction['currency'] ?? 'EUR',
            'debtor_name' => $transaction['debtor_name'] ?? null,
            'debtor_iban' => $transaction['debtor_iban'] ?? null,
            'purpose' => $transaction['purpose'] ?? null,
            'status' => $status,
            'match_confidence' => $preview['confidence'],
            'match_reason' => $preview['reason'],
            'raw_data' => [
                'source' => $transaction['raw_data'] ?? [],
                'allocations' => collect($preview['allocations'])->map(fn (array $allocation) => [
                    'invoice_id' => $allocation['invoice']->id,
                    'amount_cents' => $allocation['amount_cents'],
                    'mode' => $allocation['mode'],
                ])->values()->all(),
                'conflicts' => $preview['conflicts'],
            ],
        ];
    }

    private function normalizeTableRows(array $tableRows): array
    {
        $headerIndex = null;
        $headers = [];

        foreach ($tableRows as $index => $values) {
            $candidate = array_map(fn ($value) => ClubMembershipInput::normalizeKey($value), $values);

            if (in_array('betrag', $candidate, true) || in_array('amount', $candidate, true)) {
                $headerIndex = $index;
                $headers = $candidate;
                break;
            }
        }

        if ($headerIndex === null) {
            return [];
        }

        $rows = [];

        foreach (array_slice($tableRows, $headerIndex + 1) as $values) {
            $row = [];
            foreach ($headers as $index => $header) {
                if ($header !== '') {
                    $row[$header] = $values[$index] ?? null;
                }
            }

            if (array_filter($row, fn ($value) => filled($value))) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    private function nameLooksSimilar(?string $expected, ?string $actual): bool
    {
        $expected = strtolower(preg_replace('/[^a-z0-9]+/i', '', (string) $expected));
        $actual = strtolower(preg_replace('/[^a-z0-9]+/i', '', (string) $actual));

        return $expected !== '' && $actual !== '' && (str_contains($actual, $expected) || str_contains($expected, $actual));
    }
}
