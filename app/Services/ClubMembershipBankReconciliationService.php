<?php

namespace App\Services;

use App\Models\Club;
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
            ->where('status', 'open')
            ->whereNotNull('user_id')
            ->with('user:id,name,email')
            ->get();

        $purpose = strtoupper((string) ($transaction['purpose'] ?? ''));
        $amount = round((float) $transaction['amount'], 2);

        $numberMatch = $openInvoices->first(fn (Invoice $invoice) => str_contains($purpose, strtoupper($invoice->number))
            && round((float) $invoice->amount, 2) === $amount);

        if ($numberMatch) {
            return [
                'invoice' => $numberMatch,
                'status' => 'matched',
                'confidence' => 100,
                'reason' => 'Rechnungsnummer und Betrag stimmen überein.',
            ];
        }

        $sameAmountInvoices = $openInvoices
            ->filter(fn (Invoice $invoice) => round((float) $invoice->amount, 2) === $amount)
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
        $payment = Payment::create([
            'club_id' => $invoice->club_id,
            'user_id' => $invoice->user_id,
            'invoice_id' => $invoice->id,
            'amount' => $transaction['amount'] ?? $invoice->amount,
            'status' => 'paid',
            'method' => 'bank_import',
            'reference' => $transaction['purpose'] ?? null,
            'paid_at' => $transaction['booking_date'] ?? now(),
            'notes' => 'Automatisch per Bankabgleich zugeordnet.',
        ]);

        $invoice->update([
            'status' => 'paid',
            'paid_at' => $transaction['booking_date'] ?? now(),
        ]);

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
