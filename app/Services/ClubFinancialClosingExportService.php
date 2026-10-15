<?php

namespace App\Services;

use App\Models\Club;
use App\Support\UploadStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

class ClubFinancialClosingExportService
{
    private const TABLES = [
        'invoices', 'payments', 'bank_transactions', 'club_finance_entries',
        'club_sepa_batches', 'club_sepa_settlements', 'club_sepa_fee_corrections',
        'club_sepa_fee_recharges', 'club_sepa_fee_recharge_voids',
        'club_sepa_fee_recharge_credits', 'club_dunning_rules', 'club_dunning_events',
        'club_finance_assignments', 'club_payment_allocations',
        'payment_booking_receipts', 'club_procurement_receipts',
        'club_receipt_uploads', 'club_contribution_rules',
        'club_business_partners', 'club_accounting_accounts', 'club_cost_centers',
        'club_projects', 'club_year_periods', 'club_budgets', 'club_money_accounts',
        'club_procurement_requests', 'club_subscriptions',
    ];

    public function hasFinancialRecords(Club $club): bool
    {
        foreach (self::TABLES as $table) {
            if (! in_array($table, ['club_subscriptions', 'club_year_periods'], true)
                && DB::table($table)->where('club_id', $club->id)->exists()) {
                return true;
            }
        }

        return false;
    }

    public function fingerprint(Club $club): string
    {
        $hash = hash_init('sha256');
        foreach (self::TABLES as $table) {
            $this->hashRows($hash, $table, DB::table($table)->where('club_id', $club->id)->orderBy('id')->cursor());
        }
        foreach ($this->childQueries($club) as $table => $query) {
            $this->hashRows($hash, $table, $query->orderBy('id')->cursor());
        }
        foreach ($this->receiptIds($club) as $id) {
            $file = DB::table('files')->where('club_id', $club->id)->where('id', $id)->first();
            if (! $file || ! is_string($file->path) || str_starts_with($file->path, '/')
                || str_contains($file->path, '://') || in_array('..', explode('/', $file->path), true)) {
                throw new RuntimeException('A linked financial receipt is unavailable.');
            }
            $stream = Storage::disk(UploadStorage::disk($file->path))->readStream($file->path);
            if (! is_resource($stream)) {
                throw new RuntimeException('A linked financial receipt is unavailable.');
            }
            hash_update($hash, json_encode($file, JSON_THROW_ON_ERROR));
            hash_update_stream($hash, $stream);
            fclose($stream);
        }

        return hash_final($hash);
    }

    private function hashRows($hash, string $table, iterable $rows): void
    {
        hash_update($hash, $table."\n");
        foreach ($rows as $row) {
            hash_update($hash, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
        }
    }

    private function receiptIds(Club $club)
    {
        return DB::table('club_finance_entries')->where('club_id', $club->id)
            ->whereNotNull('receipt_file_id')->pluck('receipt_file_id')
            ->merge(DB::table('club_receipt_uploads')->where('club_id', $club->id)->pluck('file_id'))
            ->unique()->sort()->values();
    }

    private function childQueries(Club $club): array
    {
        $batchIds = DB::table('club_sepa_batches')->where('club_id', $club->id)->select('id');

        return [
            'club_sepa_batch_items' => DB::table('club_sepa_batch_items')->whereIn('club_sepa_batch_id', $batchIds),
            'club_sepa_notices' => DB::table('club_sepa_notices')->whereIn('club_sepa_batch_id', $batchIds),
            'club_sepa_notice_provider_events' => DB::table('club_sepa_notice_provider_events')
                ->whereIn('club_sepa_notice_id', DB::table('club_sepa_notices')
                    ->whereIn('club_sepa_batch_id', $batchIds)->select('id')),
            'club_procurement_items' => DB::table('club_procurement_items')
                ->whereIn('club_procurement_request_id', DB::table('club_procurement_requests')
                    ->where('club_id', $club->id)->select('id')),
        ];
    }

    public function create(Club $club): string
    {
        $path = tempnam(sys_get_temp_dir(), 'airmius-club-export-');
        if ($path === false) {
            throw new RuntimeException('Could not create temporary export file.');
        }

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($path);
            throw new RuntimeException('Could not open financial export.');
        }

        $temporary = [];
        $counts = [];
        $missing = [];

        try {
            foreach (self::TABLES as $table) {
                [$file, $count] = $this->writeJsonLines(
                    DB::table($table)->where('club_id', $club->id)->orderBy('id')->cursor(),
                    $temporary,
                );
                if (! $zip->addFile($file, "data/{$table}.jsonl")) {
                    throw new RuntimeException('Could not add financial table to export.');
                }
                $counts[$table] = $count;
            }

            foreach ($this->childQueries($club) as $table => $query) {
                [$file, $count] = $this->writeJsonLines($query->orderBy('id')->cursor(), $temporary);
                if (! $zip->addFile($file, "data/{$table}.jsonl")) {
                    throw new RuntimeException("Could not add {$table} to export.");
                }
                $counts[$table] = $count;
            }

            $receiptIds = $this->receiptIds($club);
            foreach ($receiptIds->chunk(100) as $ids) {
                $found = [];
                foreach (DB::table('files')->where('club_id', $club->id)->whereIn('id', $ids)->cursor() as $file) {
                    $found[] = $file->id;
                    if (! is_string($file->path) || $file->path === '' || str_starts_with($file->path, '/')
                        || str_contains($file->path, '://') || in_array('..', explode('/', $file->path), true)) {
                        $missing[] = $file->id;

                        continue;
                    }
                    $disk = Storage::disk(UploadStorage::disk($file->path));
                    if (! $disk->exists($file->path) || ! is_resource($stream = $disk->readStream($file->path))) {
                        $missing[] = $file->id;

                        continue;
                    }
                    $copy = tempnam(sys_get_temp_dir(), 'airmius-receipt-');
                    if ($copy === false) {
                        fclose($stream);
                        throw new RuntimeException('Could not create receipt export file.');
                    }
                    $temporary[] = $copy;
                    $target = fopen($copy, 'wb');
                    if ($target === false) {
                        fclose($stream);
                        throw new RuntimeException('Could not write receipt export file.');
                    }
                    if (stream_copy_to_stream($stream, $target) === false) {
                        fclose($target);
                        fclose($stream);
                        throw new RuntimeException('Could not copy receipt into export.');
                    }
                    fclose($target);
                    fclose($stream);
                    if (! $zip->addFile($copy, 'receipts/'.$file->id.'-'.basename($file->path))) {
                        throw new RuntimeException('Could not add receipt to export.');
                    }
                }
                foreach ($ids->diff($found) as $id) {
                    $missing[] = $id;
                }
            }

            $zip->addFromString('manifest.json', json_encode([
                'format' => 'airmius-club-financial-closing.v1',
                'club_id' => $club->id,
                'club_name' => $club->name,
                'exported_at' => now()->toIso8601String(),
                'record_counts' => $counts,
                'missing_receipt_file_ids' => $missing,
                'scope' => 'Club finance source records, SEPA histories and linked receipts only; not a complete club export or a legally compliant long-term archive.',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            if (! $zip->close()) {
                throw new RuntimeException('Could not finish financial export.');
            }

            return $path;
        } catch (\Throwable $exception) {
            $zip->close();
            @unlink($path);
            throw $exception;
        } finally {
            foreach ($temporary as $file) {
                @unlink($file);
            }
        }
    }

    private function writeJsonLines(iterable $rows, array &$temporary): array
    {
        $path = tempnam(sys_get_temp_dir(), 'airmius-rows-');
        if ($path === false) {
            throw new RuntimeException('Could not create export data file.');
        }
        $temporary[] = $path;
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new RuntimeException('Could not write export data file.');
        }
        $count = 0;
        try {
            foreach ($rows as $row) {
                fwrite($handle, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
                $count++;
            }
        } finally {
            fclose($handle);
        }

        return [$path, $count];
    }
}
