<?php

namespace App\Services;

use App\Support\ClubMembershipInput;
use Illuminate\Validation\ValidationException;

class BankCsvMapper
{
    private const ALIASES = [
        'date' => ['datum', 'date', 'booking_date', 'bookingdate', 'buchungsdatum', 'buchungstag', 'umsatzdatum', 'transaction_date', 'value_date', 'valuta'],
        'amount' => ['betrag', 'amount', 'amount_eur', 'betrag_eur', 'umsatz', 'wert', 'credit', 'gutschrift', 'credit_amount', 'eingang', 'einnahme'],
        'purpose' => ['verwendungszweck', 'purpose', 'referenz', 'reference', 'payment_reference', 'paymentreference', 'buchungstext', 'beschreibung', 'description', 'memo', 'mitteilung'],
        'debtor_name' => ['auftraggeber', 'zahler', 'name', 'debtor_name', 'partner_name', 'partner', 'payee', 'counterparty', 'gegenpartei'],
        'debtor_iban' => ['iban', 'debtor_iban', 'partner_iban', 'counterparty_iban', 'konto', 'gegenkonto'],
        'currency' => ['waehrung', 'currency', 'currency_code'],
    ];

    public function decodeMapping(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }
        $mapping = json_decode($json, true);
        if (! is_array($mapping) || ($mapping !== [] && array_is_list($mapping))) {
            throw ValidationException::withMessages(['mapping' => 'Ungültige Spaltenzuordnung.']);
        }

        return $mapping;
    }

    /** @return array{columns: array<int, string>, mapping: array<string, int>, needs_mapping: bool, rows: array<int, array<string, mixed>>} */
    public function inspect(string $path, array $requestedMapping = []): array
    {
        $handle = fopen($path, 'r');
        if (! $handle) {
            return ['columns' => [], 'mapping' => [], 'needs_mapping' => true, 'rows' => []];
        }

        $firstLine = fgets($handle) ?: '';
        rewind($handle);
        $delimiter = collect([',', ';', "\t"])
            ->sortByDesc(fn (string $candidate) => count(str_getcsv($firstLine, $candidate, '"', '')))
            ->first();
        $table = [];
        while (($values = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            if ($values !== [null]) {
                $table[] = $values;
            }
        }
        fclose($handle);

        $headerIndex = null;
        $columns = [];
        $suggested = [];
        foreach ($table as $index => $values) {
            $candidate = array_map(fn ($value) => $this->key($value), $values);
            $candidateMapping = $this->suggestedMapping($candidate);
            if (isset($candidateMapping['amount'])) {
                $headerIndex = $index;
                $columns = $candidate;
                $suggested = $candidateMapping;
                break;
            }
        }

        if ($headerIndex === null) {
            foreach ($table as $index => $values) {
                $candidate = array_map(fn ($value) => $this->key($value), $values);
                if (count(array_filter($candidate)) >= 2) {
                    $headerIndex = $index;
                    $columns = $candidate;
                    $suggested = $this->suggestedMapping($candidate);
                    break;
                }
            }
        }

        if ($headerIndex === null) {
            return ['columns' => [], 'mapping' => [], 'needs_mapping' => true, 'rows' => []];
        }

        foreach ($requestedMapping as $field => $index) {
            if (! array_key_exists($field, self::ALIASES) || filter_var($index, FILTER_VALIDATE_INT) === false
                || (int) $index < 0 || (int) $index >= count($columns)) {
                throw ValidationException::withMessages(['mapping' => 'Ungültige Spaltenzuordnung.']);
            }
            $suggested[$field] = (int) $index;
        }

        if (isset($suggested['date'], $suggested['amount']) && $suggested['date'] === $suggested['amount']) {
            throw ValidationException::withMessages(['mapping' => 'Datum und Betrag müssen verschiedenen Spalten zugeordnet sein.']);
        }

        $needsMapping = ! isset($suggested['date'], $suggested['amount'])
            || ($requestedMapping === [] && count($columns) > 2
                && ! isset($suggested['purpose']) && ! isset($suggested['debtor_name']));
        $rows = [];
        if (! $needsMapping) {
            foreach (array_slice($table, $headerIndex + 1) as $values) {
                $row = [];
                foreach ($columns as $index => $header) {
                    if ($header !== '') {
                        $row[$header] = $values[$index] ?? null;
                    }
                }
                if (! array_filter($row, fn ($value) => filled($value))) {
                    continue;
                }
                foreach ($suggested as $field => $index) {
                    $row[match ($field) {
                        'date' => 'booking_date',
                        'amount' => 'amount',
                        'purpose' => 'purpose',
                        'debtor_name' => 'debtor_name',
                        'debtor_iban' => 'debtor_iban',
                        'currency' => 'currency',
                    }] = $values[$index] ?? null;
                }
                $rows[] = $row;
            }
        }

        return [
            'columns' => array_map(
                fn ($value) => trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $value)),
                $table[$headerIndex],
            ),
            'mapping' => $suggested,
            'needs_mapping' => $needsMapping,
            'rows' => $rows,
        ];
    }

    private function key(mixed $value): string
    {
        return ClubMembershipInput::normalizeKey(preg_replace('/^\xEF\xBB\xBF/', '', (string) $value));
    }

    private function suggestedMapping(array $columns): array
    {
        $mapping = [];
        foreach ($columns as $index => $column) {
            foreach (self::ALIASES as $field => $aliases) {
                if (! isset($mapping[$field]) && in_array($column, $aliases, true)) {
                    $mapping[$field] = $index;
                }
            }
        }

        return $mapping;
    }
}
