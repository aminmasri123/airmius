<?php

namespace App\Services;

use App\Models\Club;
use App\Models\Invoice;

/** Read-only evidence for a human reconciliation, never a repair instruction. */
final class ClubInvoiceReconciliationAudit
{
    public const REASONS = [
        'paid_without_payment_records' => 'Bezahlt markiert, aber keine Zahlungsdatensätze vorhanden; Altbelege prüfen.',
        'paid_without_settled_receipts' => 'Bezahlt markiert, aber keine erfolgreich verbuchten Eingänge vorhanden.',
        'paid_below_invoice_amount' => 'Bezahlt markiert, obwohl die verbuchten Eingänge den Rechnungsbetrag nicht decken.',
        'open_with_covered_amount' => 'Offen oder überfällig, obwohl die verbuchten Eingänge den Betrag decken.',
        'overpaid' => 'Verbuchte Eingänge übersteigen den Rechnungsbetrag; Guthaben/Erstattung prüfen.',
        'cancelled_with_receipts' => 'Stornierte Rechnung mit verbuchten Eingängen; Abwicklung prüfen.',
        'payment_club_mismatch' => 'Mindestens eine zugeordnete Zahlung gehört nicht zum Rechnungsverein.',
        'invalid_invoice_amount' => 'Negativer Rechnungsbetrag; fachliche Einordnung prüfen.',
        'negative_settled_payment' => 'Negative erfolgreich verbuchte Zahlung; fachliche Einordnung prüfen.',
    ];

    public function report(Club $club, bool $details = false, int $limit = 50, int $afterId = 0): array
    {
        if ($limit < 1 || $limit > 200) {
            throw new \InvalidArgumentException('Detail limit must be between 1 and 200.');
        }
        if ($afterId < 0) {
            throw new \InvalidArgumentException('Detail cursor must not be negative.');
        }
        $startedAt = now()->toIso8601String();
        // Bound the scan so new invoices cannot extend a running audit indefinitely.
        $maxId = (int) Invoice::where('club_id', $club->id)->max('id');
        $counts = array_fill_keys(array_keys(self::REASONS), 0);
        $scanned = $affected = $remaining = 0;
        $findings = [];
        Invoice::query()->where('club_id', $club->id)->where('id', '<=', $maxId)
            ->select(['id', 'club_id', 'status', 'amount'])
            ->withSum('settledPayments', 'amount')
            ->withCount([
                'payments', 'settledPayments',
                'payments as mismatched_payments_count' => fn ($query) => $query->where(fn ($query) => $query
                    ->whereNull('payments.club_id')->orWhereColumn('payments.club_id', '!=', 'invoices.club_id')),
                'settledPayments as negative_payments_count' => fn ($query) => $query->where('amount', '<', 0),
            ])
            ->chunkById(250, function ($invoices) use (&$scanned, &$affected, &$counts, &$findings, &$remaining, $details, $limit, $afterId) {
                foreach ($invoices as $invoice) {
                    $scanned++;
                    $total = (int) round((float) $invoice->amount * 100);
                    $received = $invoice->receivedCents();
                    $reasons = [];
                    if ($invoice->status === 'paid' && $total > 0 && $received < $total) {
                        $reasons[] = $invoice->payments_count === 0 ? 'paid_without_payment_records'
                            : ($invoice->settled_payments_count === 0 ? 'paid_without_settled_receipts' : 'paid_below_invoice_amount');
                    }
                    if (in_array($invoice->status, ['open', 'overdue'], true) && $total > 0 && $received >= $total) {
                        $reasons[] = 'open_with_covered_amount';
                    }
                    if ($total >= 0 && $received > $total) {
                        $reasons[] = 'overpaid';
                    }
                    if ($invoice->status === 'cancelled' && $invoice->settled_payments_count > 0) {
                        $reasons[] = 'cancelled_with_receipts';
                    }
                    if ($invoice->mismatched_payments_count > 0) {
                        $reasons[] = 'payment_club_mismatch';
                    }
                    if ($total < 0) {
                        $reasons[] = 'invalid_invoice_amount';
                    }
                    if ($invoice->negative_payments_count > 0) {
                        $reasons[] = 'negative_settled_payment';
                    }
                    if ($reasons === []) {
                        continue;
                    }
                    $affected++;
                    foreach ($reasons as $reason) {
                        $counts[$reason]++;
                    }
                    if ($details && $invoice->id > $afterId && count($findings) >= $limit) {
                        $remaining++;
                    }
                    if ($details && $invoice->id > $afterId && count($findings) < $limit) {
                        $findings[] = [
                            'invoice_id' => $invoice->id, 'status' => $invoice->status,
                            'invoice_amount_cents' => $total, 'settled_amount_cents' => $received,
                            // Deliberately not outstandingCents(): legacy paid invoices
                            // must retain their operational status during this audit.
                            'uncovered_amount_cents' => max(0, $total - $received),
                            'overpaid_amount_cents' => max(0, $received - max(0, $total)),
                            'payment_count' => $invoice->payments_count, 'reasons' => $reasons,
                        ];
                    }
                }
            });

        return [
            'contract' => 'club-invoice-reconciliation.v1', 'club_id' => $club->id,
            'started_at' => $startedAt, 'finished_at' => now()->toIso8601String(),
            'read_only' => true, 'requires_human_review' => $affected > 0,
            'invoices_scanned' => $scanned, 'invoices_flagged' => $affected,
            'reason_counts' => $counts, 'reason_descriptions' => self::REASONS,
            'details_included' => $details, 'details_limit' => $limit,
            'details_omitted' => $affected - count($findings), 'findings' => $findings,
            'details_after_id' => $afterId, 'details_remaining' => $remaining,
            'next_after_id' => $remaining > 0 ? $findings[array_key_last($findings)]['invoice_id'] : null,
            'notice' => 'Prüfhinweise, keine bewiesenen Zahlungsfehler oder neuen Forderungen. Mit Bank- und Altbelegen abgleichen. Laufende Buchungen können den Bericht verändern; dies ist kein transaktionsübergreifender Datenbanksnapshot.',
        ];
    }
}
