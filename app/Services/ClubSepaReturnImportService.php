<?php

namespace App\Services;

use App\Models\Club;
use App\Models\ClubSepaBatch;
use App\Models\ClubSepaSettlement;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

final class ClubSepaReturnImportService
{
    private const REQUIRED = ['end_to_end_id', 'booking_date', 'amount', 'currency', 'reference', 'reason'];

    public function columns(ClubSepaBatch $batch, string $path): array
    {
        abort_unless($batch->status === 'exported', 422, __('sepa.export_required'));
        [$headers, $rows, $format] = $this->readTable($path);

        return ['columns' => $headers, 'row_count' => count($rows), 'format' => $format];
    }

    public function preview(ClubSepaBatch $batch, User $actor, string $path, ?array $mapping = null, array $ignored = []): array
    {
        if ($mapping !== null) {
            $mapping = array_map(fn ($index) => (int) $index, $mapping);
            ksort($mapping);
        }
        $ignored = array_map(fn ($index) => (int) $index, $ignored);
        sort($ignored);
        $report = $this->inspect($batch->fresh(), $path, $mapping, $ignored);
        $report['preview_token'] = Crypt::encryptString(json_encode([
            'batch' => $batch->id, 'actor' => $actor->id, 'file' => hash_file('sha256', $path),
            'mapping' => $mapping, 'ignored' => $ignored,
            'state' => $this->fingerprint($report), 'expires' => now()->addMinutes(15)->timestamp,
        ], JSON_THROW_ON_ERROR));

        return $report;
    }

    public function import(ClubSepaBatch $batch, User $actor, string $path, string $token, bool $confirmUnlinked): array
    {
        try {
            $proof = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            abort(422, __('sepa.import_review'));
        }
        abort_unless(is_array($proof) && ($proof['batch'] ?? null) === $batch->id && ($proof['actor'] ?? null) === $actor->id
            && ($proof['file'] ?? null) === hash_file('sha256', $path) && ($proof['expires'] ?? 0) > now()->timestamp, 422, __('sepa.import_review'));

        return DB::transaction(function () use ($batch, $actor, $path, $proof, $confirmUnlinked) {
            // Same lock order as single-position bank results; all rows commit together.
            Club::lockForUpdate()->findOrFail($batch->club_id);
            $locked = ClubSepaBatch::lockForUpdate()->findOrFail($batch->id);
            $report = $this->inspect($locked, $path, $proof['mapping'] ?? null, $proof['ignored'] ?? []);
            abort_unless($report['can_import'], 422, __('sepa.import_rows'));
            $onlyDuplicates = $report['already_returned'] === count($report['rows']);
            abort_unless($onlyDuplicates || hash_equals($proof['state'], $this->fingerprint($report)), 422, __('sepa.import_review'));
            abort_if($report['unlinked_count'] > 0 && ! $confirmUnlinked, 422, __('sepa.import_unlinked'));
            $imported = 0;
            foreach ($report['rows'] as $row) {
                if ($row['status'] === 'already_returned') {
                    continue;
                }
                $item = $locked->items()->findOrFail($row['item_id']);
                app(ClubSepaSettlementService::class)->returnDebit($item, $actor, [
                    'booked_on' => $row['booking_date'], 'reference' => $row['reference'],
                    'reason' => $row['reason'], 'fee_cents' => 0,
                ]);
                $imported++;
            }

            return ['imported' => $imported, 'already_returned' => $report['already_returned']];
        });
    }

    private function inspect(ClubSepaBatch $batch, string $path, ?array $mapping = null, array $ignored = []): array
    {
        abort_unless($batch->status === 'exported', 422, __('sepa.export_required'));
        $items = $batch->items()->with('settlement.payment')->get()->groupBy(fn ($item) => (string) $item->debtor_snapshot['number']);
        $rows = [];
        $seenItems = [];
        $seenReferences = [];
        [$headers, $table, $format] = $this->readTable($path);
        if ($mapping === null) {
            abort_unless($ignored === [] && array_diff(self::REQUIRED, $headers) === []
                && array_diff($headers, [...self::REQUIRED, 'iban']) === [], 422, __('sepa.import_file'));
            $mapping = array_flip($headers);
        }
        $used = array_values($mapping);
        $covered = [...$used, ...$ignored];
        sort($covered);
        abort_unless(array_diff(self::REQUIRED, array_keys($mapping)) === []
            && array_diff(array_keys($mapping), [...self::REQUIRED, 'iban']) === []
            && count($covered) === count(array_unique($covered))
            && $covered === array_keys($headers), 422, __('sepa.import_mapping'));
        foreach ($table as $index => $values) {
            $raw = array_map(fn ($column) => $values[$column], $mapping);
            $errors = [];
            $id = trim($raw['end_to_end_id']);
            $matches = $items->get($id);
            $item = $matches?->count() === 1 ? $matches->first() : null;
            if (! $item) {
                $errors[] = 'unknown_or_ambiguous_end_to_end_id';
            }
            $amount = trim($raw['amount']);
            $cents = preg_match('/^-\d{1,6}(?:[.,]\d{1,2})?$/D', $amount)
                ? (int) round((float) str_replace(',', '.', $amount) * 100) : null;
            if ($cents === null || $cents >= 0 || ($item && -$cents !== $item->amount_cents)) {
                $errors[] = 'amount_mismatch';
            }
            $currency = strtoupper(trim($raw['currency']));
            if ($currency !== 'EUR') {
                $errors[] = 'currency_mismatch';
            }
            $date = $this->date(trim($raw['booking_date']));
            if (! $date || $date < $batch->collection_date->toDateString() || $date > today()->toDateString()
                || ($item?->settlement?->settled_on && $date < $item->settlement->settled_on->toDateString())) {
                $errors[] = 'invalid_booking_date';
            }
            $reference = mb_strtoupper(trim($raw['reference']));
            if ($reference === '' || mb_strlen($reference) > 180) {
                $errors[] = 'invalid_reference';
            }
            $reason = trim($raw['reason']);
            if ($reason === '' || mb_strlen($reason) > 2000) {
                $errors[] = 'invalid_reason';
            }
            if ($item && isset($seenItems[$item->id])) {
                $errors[] = 'duplicate_position';
            }
            if (isset($seenReferences[$reference])) {
                $errors[] = 'duplicate_reference';
            }
            if ($item) {
                $seenItems[$item->id] = true;
            }
            $seenReferences[$reference] = true;
            if (filled($raw['iban'] ?? null) && $item
                && strtoupper(preg_replace('/\s+/', '', $raw['iban'])) !== strtoupper($item->debtor_snapshot['sepa_iban'])) {
                $errors[] = 'iban_mismatch';
            }
            $result = $item?->settlement;
            if (ClubSepaSettlement::where('club_id', $batch->club_id)->where('return_reference', $reference)
                ->when($result, fn ($query) => $query->where('id', '!=', $result->id))->exists()) {
                $errors[] = 'reference_used';
            }
            $duplicate = $result?->status === 'returned' && $result->return_reference === $reference
                && $result->returned_on?->toDateString() === $date && $result->return_reason === $reason && $result->return_fee_cents === 0;
            if ($result?->status === 'returned' && ! $duplicate) {
                $errors[] = 'conflicting_result';
            }
            if ($result?->status === 'settled' && (! $result->payment || $result->payment->status !== 'paid'
                || $result->payment->invoice_id !== $item->invoice_id || $result->payment->club_id !== $batch->club_id
                || (int) round((float) $result->payment->amount * 100) !== $item->amount_cents)) {
                $errors[] = 'payment_changed';
            }
            $rows[] = [
                'row' => $index + 2, 'item_id' => $item?->id, 'end_to_end_id' => $id,
                'amount_cents' => $cents, 'currency' => $currency, 'booking_date' => $date,
                'reference' => $reference, 'reason' => $reason, 'errors' => $errors,
                'status' => $errors !== [] ? 'invalid' : ($duplicate ? 'already_returned' : 'ready'),
                'has_linked_receipt' => $result?->payment_id !== null,
                'receipt_id' => $result?->payment_id, 'result_status' => $result?->status,
            ];
        }

        return [
            'batch_id' => $batch->id, 'rows' => $rows,
            'format' => $format,
            'mapping' => $mapping, 'ignored_columns' => array_map(fn ($index) => $headers[$index], $ignored),
            'can_import' => $rows !== [] && collect($rows)->every(fn ($row) => $row['status'] !== 'invalid'),
            'unlinked_count' => collect($rows)->filter(fn ($row) => $row['status'] === 'ready' && ! $row['has_linked_receipt'])->count(),
            'already_returned' => collect($rows)->where('status', 'already_returned')->count(),
        ];
    }

    private function fingerprint(array $report): string
    {
        return hash('sha256', json_encode($report['rows'], JSON_THROW_ON_ERROR));
    }

    private function date(string $value): ?string
    {
        foreach (['Y-m-d', 'd.m.Y'] as $format) {
            $date = \DateTimeImmutable::createFromFormat('!'.$format, $value);
            if ($date && $date->format($format) === $value) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    private function readTable(string $path): array
    {
        abort_unless(is_file($path) && filesize($path) <= 2 * 1024 * 1024, 422, __('sepa.import_file'));
        $contents = file_get_contents($path);
        abort_unless(is_string($contents) && $contents !== '', 422, __('sepa.import_file'));
        if (str_starts_with(ltrim($contents), '<')) {
            [$format, $rows] = app(ClubSepaReturnXmlParser::class)->parse($contents);

            return [[...self::REQUIRED, 'iban'], $rows, $format];
        }
        $handle = fopen($path, 'r');
        abort_unless($handle, 422, __('sepa.import_file'));
        try {
            $first = fgets($handle) ?: '';
            $delimiter = str_contains($first, ';') ? ';' : (str_contains($first, "\t") ? "\t" : ',');
            rewind($handle);
            $headers = fgetcsv($handle, 0, $delimiter);
            abort_unless(is_array($headers), 422, __('sepa.import_file'));
            $headers = array_map(fn ($header) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', $header ?? ''))), $headers);
            abort_unless(count($headers) <= 50 && count($headers) === count(array_unique($headers)), 422, __('sepa.import_file'));
            foreach ($headers as $header) {
                abort_unless($header !== '' && mb_check_encoding($header, 'UTF-8') && mb_strlen($header) <= 180, 422, __('sepa.import_file'));
            }
            $rows = [];
            while (($values = fgetcsv($handle, 0, $delimiter)) !== false) {
                if ($values === [null]) {
                    continue;
                }
                abort_unless(count($values) === count($headers) && count($rows) < 200, 422, __('sepa.import_file'));
                foreach ($values as $value) {
                    abort_unless(mb_check_encoding($value ?? '', 'UTF-8'), 422, __('sepa.import_file'));
                }
                $rows[] = array_map(fn ($value) => $value ?? '', $values);
            }
            abort_unless($rows !== [], 422, __('sepa.import_file'));

            return [$headers, $rows, 'csv'];
        } finally {
            fclose($handle);
        }
    }
}
