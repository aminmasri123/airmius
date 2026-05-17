<?php

namespace App\Services;

use App\Models\CommerceOrder;

class CommerceDocumentService
{
    public function __construct(private AirmiusLegalProfile $legalProfile) {}

    public function pdf(CommerceOrder $order, string $type = 'invoice'): string
    {
        $order->loadMissing(['items', 'user']);

        $pdf = new AirmiusPdfDocument();
        $profile = $this->legalProfile->data();
        $number = $type === 'credit_note'
            ? ($order->credit_note_number ?: 'Gutschrift-'.$order->id)
            : ($order->invoice_number ?: 'Rechnung-'.$order->id);
        $title = $type === 'credit_note' ? 'GUTSCHRIFT' : 'RECHNUNG';
        $customerName = $order->customer_company ?: ($order->user?->name ?: $order->guest_name ?: '-');
        $customerEmail = $order->user?->email ?: $order->guest_email ?: '-';

        $pdf->header(
            $title,
            $number,
            'Sport. Vereine. Wachstum.',
            $profile['brand_name'] ?? null,
            $type === 'credit_note' ? 'Gutschriftnummer' : 'Rechnungsnummer',
            $type
        );
        $pdf->card(48, 632, 150, 54, 'Datum', now()->format('d.m.Y'));
        $pdf->card(222, 632, 150, 54, 'Bestellung', '#'.$order->id);
        $pdf->card(396, 632, 150, 54, $type === 'credit_note' ? 'Erstattung' : 'Betrag', $this->money($type === 'credit_note' ? ($order->refunded_cents ?: $order->amount_cents) : $order->amount_cents, $order->currency), true);

        $pdf->sectionTitle('Leistungsempfaenger', 48, 585);
        $pdf->text($customerName, 48, 562, 13, true);
        $pdf->text($customerEmail, 48, 544, 10, false, AirmiusPdfDocument::SLATE, 80);
        if (filled($order->customer_vat_id)) {
            $pdf->text('USt-IdNr.: '.$order->customer_vat_id, 48, 528, 9, false, AirmiusPdfDocument::SLATE, 80);
        }
        $pdf->issuerBlock($profile, 48, 497);

        $pdf->sectionTitle('Steuer', 396, 585);
        $pdf->labelValue('Land', $order->tax_country ?: '-', 396, 562);
        $pdf->labelValue('Satz', number_format((float) $order->tax_rate_percent, 2, ',', '.').' %', 396, 538);
        $pdf->labelValue('Status', $order->status ?: '-', 396, 514);

        $pdf->sectionTitle('Positionen', 48, 382);
        $pdf->fillColor(241, 245, 249)->rect(48, 339, 498, 32, true);
        $pdf->text('Beschreibung', 64, 350, 10, true, [51, 65, 85]);
        $pdf->text('Menge', 330, 350, 10, true, [51, 65, 85]);
        $pdf->text('Summe', 488, 350, 10, true, [51, 65, 85]);
        $pdf->strokeColor(...AirmiusPdfDocument::BORDER)->line(48, 339, 546, 339);

        $y = 315;
        foreach ($order->items->take(5) as $item) {
            $pdf->text($item->title, 64, $y, 10, true, AirmiusPdfDocument::NAVY, 48);
            $pdf->text((string) $item->quantity, 330, $y, 9, false, AirmiusPdfDocument::SLATE);
            $pdf->text($this->money($item->total_cents, $item->currency), 487, $y, 10, true);
            $y -= 22;
        }

        if ($order->items->count() > 5) {
            $pdf->text('+ '.($order->items->count() - 5).' weitere Positionen', 64, $y, 8, false, AirmiusPdfDocument::MUTED);
        }

        $pdf->strokeColor(...AirmiusPdfDocument::BORDER)->line(48, 190, 546, 190);
        $pdf->text('Netto', 365, 162, 10, false, AirmiusPdfDocument::SLATE);
        $pdf->text($this->money($order->net_cents, $order->currency), 488, 162, 10);
        $pdf->text('Steuer', 365, 140, 10, false, AirmiusPdfDocument::SLATE);
        $pdf->text($this->money($order->tax_cents, $order->currency), 488, 140, 10);
        $pdf->text('Versand', 365, 118, 10, false, AirmiusPdfDocument::SLATE);
        $pdf->text($this->money($order->shipping_cents, $order->currency), 488, 118, 10);
        $pdf->text($type === 'credit_note' ? 'Erstattung' : 'Gesamtbetrag', 365, 92, 13, true);
        $pdf->text($this->money($type === 'credit_note' ? ($order->refunded_cents ?: $order->amount_cents) : $order->amount_cents, $order->currency), 474, 92, 13, true, AirmiusPdfDocument::BLUE);

        if (filled($profile['small_business_notice'])) {
            $pdf->text($profile['small_business_notice'], 48, 132, 8, false, AirmiusPdfDocument::SLATE, 95);
        }
        if (filled($profile['invoice_note'])) {
            $pdf->text($profile['invoice_note'], 48, 116, 8, false, AirmiusPdfDocument::SLATE, 95);
        }

        return $pdf->legalFooter($profile, $type === 'credit_note' ? 'Gutschrift wurde erstellt.' : 'Danke fuer deine Bestellung.')->render();
    }

    private function money(int $cents, string $currency): string
    {
        return number_format($cents / 100, 2, ',', '.').' '.$currency;
    }
}
