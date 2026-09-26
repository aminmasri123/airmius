<?php

namespace App\Services;

use App\Models\ClubSepaFeeRechargeCredit;

final class ClubSepaFeeRechargeCreditDocumentService
{
    public function pdf(ClubSepaFeeRechargeCredit $credit): string
    {
        $credit->loadMissing(['recharge.invoice', 'recharge.member', 'recharge.invoice.club']);
        $recharge = $credit->recharge;
        $invoice = $recharge?->invoice;
        $club = $invoice?->club;
        abort_unless($recharge && $invoice && $club && $credit->credit_note_number
            && in_array($credit->status, ['issued', 'completed', 'refunded'], true), 404);

        $pdf = new AirmiusPdfDocument;
        $pdf->header('GUTSCHRIFT', $credit->credit_note_number, 'Korrektur einer Gebührenweiterbelastung',
            $club->name, 'Gutschriftnummer', 'credit_note');
        $pdf->card(48, 632, 150, 54, 'Datum', $credit->reviewed_at?->format('d.m.Y') ?: '-');
        $pdf->card(222, 632, 150, 54, 'Ursprungsrechnung', $invoice->number);
        $pdf->card(396, 632, 150, 54, 'Gutschrift', $pdf->money($credit->amount_cents, 'EUR'), true);

        $pdf->sectionTitle('Empfänger', 48, 585);
        $pdf->text($recharge->member?->name ?: 'Mitglied #'.$recharge->member_id, 48, 560, 13, true);
        $pdf->text('Mitgliedskonto #'.$recharge->member_id, 48, 542, 9, false, AirmiusPdfDocument::SLATE);

        $pdf->sectionTitle('Aussteller', 320, 585);
        $pdf->text($club->name, 320, 560, 13, true);
        $address = trim(implode(' ', array_filter([$club->street, $club->house_number])));
        $city = trim(implode(' ', array_filter([$club->postal_code, $club->city])));
        $pdf->text($address ?: '-', 320, 542, 9, false, AirmiusPdfDocument::SLATE, 50);
        $pdf->text($city ?: ($club->country ?: '-'), 320, 526, 9, false, AirmiusPdfDocument::SLATE, 50);

        $pdf->sectionTitle('Korrektur', 48, 458);
        $pdf->text('Gebührenweiterbelastung aus '.$invoice->number, 48, 430, 11, true, AirmiusPdfDocument::NAVY, 75);
        $pdf->text($credit->reason, 48, 405, 9, false, AirmiusPdfDocument::SLATE, 105);
        $pdf->labelValue('Rechnungsbetrag', $pdf->money((int) round((float) $invoice->amount * 100), 'EUR'), 48, 366);
        $pdf->labelValue('Gutschrift', $pdf->money($credit->amount_cents, 'EUR'), 48, 340);
        $pdf->labelValue('Erstattungsbedarf', $pdf->money($credit->refund_due_cents ?? 0, 'EUR'), 48, 314);
        $pdf->labelValue('Status', match ($credit->status) {
            'refunded' => 'Erstattung dokumentiert',
            'completed' => 'Keine Erstattung erforderlich',
            default => 'Erstattung ausstehend',
        }, 48, 288);
        if ($credit->refund_reference) {
            $pdf->labelValue('Bankreferenz', $credit->refund_reference, 48, 262);
        }

        $pdf->fillColor(...AirmiusPdfDocument::SURFACE_MUTED)->rect(0, 0, AirmiusPdfDocument::PAGE_WIDTH, 96, true);
        $pdf->fillColor(...AirmiusPdfDocument::ORANGE)->rect(0, 84, AirmiusPdfDocument::PAGE_WIDTH, 12, true);
        $pdf->text('Dieses Dokument korrigiert ausschließlich die bezeichnete Gebührenrechnung.', 48, 58, 9, true, AirmiusPdfDocument::NAVY, 90);
        $pdf->text('Zahlungs- und Bankbelege bleiben als Nachweis erhalten.', 48, 40, 8, false, AirmiusPdfDocument::MUTED, 90);
        $pdf->text('Erstellt durch '.$club->name, 48, 20, 8, false, AirmiusPdfDocument::MUTED, 90);

        return $pdf->render();
    }
}
