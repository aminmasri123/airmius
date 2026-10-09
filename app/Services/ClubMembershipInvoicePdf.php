<?php

namespace App\Services;

use App\Models\Invoice;

class ClubMembershipInvoicePdf
{
    public function render(Invoice $invoice): string
    {
        $invoice->loadMissing(['club', 'user', 'membershipUser', 'externalMember', 'settledPayments']);
        $club = $invoice->club;
        $balance = $invoice->balancePayload();
        $pdf = new AirmiusPdfDocument;
        $pdf->fillColor(...AirmiusPdfDocument::NAVY)->rect(0, 700, 595, 142, true)
            ->text('Rechnung', 48, 790, 26, true, [255, 255, 255])
            ->text($club?->name ?? 'Verein', 48, 754, 14, true, [255, 255, 255], 58)
            ->text($invoice->number, 48, 722, 11, false, [255, 255, 255], 76)
            ->sectionTitle('Rechnungsempfaenger', 48, 663)
            ->text($invoice->user?->name ?: $invoice->externalMember?->name ?: $invoice->membershipUser?->name ?: '-', 48, 635, 12)
            ->text($invoice->user?->email ?: $invoice->externalMember?->email ?: '', 48, 615, 10)
            ->sectionTitle('Rechnungsdaten', 48, 580)
            ->labelValue('Ausgestellt am', ($invoice->issued_at ?? $invoice->created_at)?->format('d.m.Y') ?? '-', 48, 554)
            ->labelValue('Faellig am', $invoice->due_date?->format('d.m.Y') ?? '-', 48, 534)
            ->labelValue('Status', $invoice->statusLabel(), 48, 514)
            ->labelValue('Zeitraum', ($invoice->billing_period_start?->format('d.m.Y') ?? '-').' - '.($invoice->billing_period_end?->format('d.m.Y') ?? '-'), 48, 494)
            ->text($invoice->title ?: 'Mitgliedsbeitrag', 48, 455, 12, true, AirmiusPdfDocument::NAVY, 100)
            ->card(48, 365, 158, 65, 'Rechnungsbetrag', $pdf->money((int) round((float) $invoice->amount * 100), 'EUR'))
            ->card(218, 365, 158, 65, 'Erhalten', $pdf->money((int) round((float) $balance['received_amount'] * 100), 'EUR'))
            ->card(388, 365, 158, 65, 'Restbetrag', $pdf->money((int) round((float) $balance['outstanding_amount'] * 100), 'EUR'), true)
            ->sectionTitle('Zahlungseingaenge', 48, 336);
        foreach ($invoice->settledPayments->sortBy('paid_at')->take(5)->values() as $index => $payment) {
            $pdf->text(($payment->paid_at?->format('d.m.Y') ?? '-').'  '.$pdf->money((int) round((float) $payment->amount * 100), 'EUR'), 48, 312 - $index * 20, 10);
        }
        if ($invoice->settledPayments->count() > 5) {
            $pdf->text('Auszug: 5 von '.$invoice->settledPayments->count().' Zahlungseingaengen.', 48, 190, 9);
        }
        $pdf->text($club?->contact_email ?? '', 48, 140, 10)
            ->text('Rechnungsnummer bei Rueckfragen angeben: '.$invoice->number, 48, 120, 9, false, AirmiusPdfDocument::SLATE, 110);

        return $pdf->render();
    }
}
