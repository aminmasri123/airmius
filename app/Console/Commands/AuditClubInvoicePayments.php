<?php

namespace App\Console\Commands;

use App\Models\Club;
use App\Services\ClubInvoiceReconciliationAudit;
use Illuminate\Console\Command;

final class AuditClubInvoicePayments extends Command
{
    protected $signature = 'airmius:audit-club-invoice-payments
        {club : Positive ID of the club to inspect}
        {--json : Print the report as JSON}
        {--details : Include invoice IDs and amounts, without personal or bank data}
        {--limit=50 : Maximum detail rows (1–200); all invoices are counted}
        {--after-id=0 : Show detail rows after this invoice ID; summary still covers the whole club}
        {--strict : Return exit code 1 when review findings exist}';

    protected $description = 'Read-only audit of club invoice status against recorded payments; never repairs data';

    public function handle(ClubInvoiceReconciliationAudit $auditor): int
    {
        $id = (string) $this->argument('club');
        $limit = (string) $this->option('limit');
        $afterId = (string) $this->option('after-id');
        if (! ctype_digit($id) || (int) $id < 1 || ! ctype_digit($limit) || (int) $limit < 1 || (int) $limit > 200 || ! ctype_digit($afterId)) {
            $this->error('Vereins-ID muss positiv sein; Detailgrenze muss zwischen 1 und 200 liegen; Start-ID darf nicht negativ sein.');

            return self::INVALID;
        }
        $club = Club::find($id);
        if (! $club) {
            $this->error('Verein nicht gefunden.');

            return self::INVALID;
        }
        $report = $auditor->report($club, (bool) $this->option('details'), (int) $limit, (int) $afterId);
        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->info("Rechnungen geprüft: {$report['invoices_scanned']}; mit Prüfhinweis: {$report['invoices_flagged']}");
            $this->line($report['notice']);
            $this->table(['Prüfhinweis', 'Anzahl'], collect($report['reason_counts'])->map(fn ($count, $reason) => [ClubInvoiceReconciliationAudit::REASONS[$reason], $count])->values()->all());
            if ($this->option('details')) {
                $this->table(['Rechnungs-ID', 'Status', 'Betrag (Cent)', 'Eingänge (Cent)', 'Prüfhinweise'], array_map(fn ($row) => [
                    $row['invoice_id'], $row['status'], $row['invoice_amount_cents'], $row['settled_amount_cents'], implode(', ', $row['reasons']),
                ], $report['findings']));
                $this->line("Weitere Rechnungen mit Prüfhinweis: {$report['details_omitted']}");
                if ($report['next_after_id'] !== null) {
                    $this->line("Nächste Detailseite: --after-id={$report['next_after_id']} ({$report['details_remaining']} weitere)");
                }
            }
        }

        return $this->option('strict') && $report['requires_human_review'] ? self::FAILURE : self::SUCCESS;
    }
}
