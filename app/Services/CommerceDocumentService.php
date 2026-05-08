<?php

namespace App\Services;

use App\Models\CommerceOrder;
use App\Models\CommerceOrderItem;

class CommerceDocumentService
{
    public function pdf(CommerceOrder $order, string $type = 'invoice'): string
    {
        $order->loadMissing(['items', 'user']);
        $number = $type === 'credit_note'
            ? ($order->credit_note_number ?: 'Gutschrift-'.$order->id)
            : ($order->invoice_number ?: 'Rechnung-'.$order->id);

        $lines = [
            'Airmius',
            $type === 'credit_note' ? 'Gutschrift' : 'Rechnung',
            'Nummer: '.$number,
            'Bestellung: #'.$order->id,
            'Datum: '.now()->format('d.m.Y'),
            'Kunde: '.($order->user?->name ?: $order->guest_name ?: '-'),
            'E-Mail: '.($order->user?->email ?: $order->guest_email ?: '-'),
            '',
            'Positionen:',
        ];

        foreach ($order->items as $item) {
            $lines[] = $item->quantity.' x '.$item->title.' - '.$this->money($item->total_cents, $item->currency);
        }

        $lines = array_merge($lines, [
            '',
            'Netto: '.$this->money($order->net_cents, $order->currency),
            'Steuer: '.$this->money($order->tax_cents, $order->currency).' ('.number_format((float) $order->tax_rate_percent, 2, ',', '.').' %)',
            'Versand: '.$this->money($order->shipping_cents, $order->currency),
            'Brutto: '.$this->money($order->amount_cents, $order->currency),
        ]);

        if ($type === 'credit_note') {
            $lines[] = 'Erstattung: '.$this->money($order->refunded_cents ?: $order->amount_cents, $order->currency);
        }

        return $this->simplePdf($lines);
    }

    private function money(int $cents, string $currency): string
    {
        return number_format($cents / 100, 2, ',', '.').' '.$currency;
    }

    private function simplePdf(array $lines): string
    {
        $content = "BT\n/F1 11 Tf\n50 790 Td\n";
        foreach ($lines as $line) {
            $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line);
            $content .= '('.$escaped.") Tj\n0 -16 Td\n";
        }
        $content .= "ET";

        $objects = [
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
            "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
            "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n",
            "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n",
            "5 0 obj\n<< /Length ".strlen($content)." >>\nstream\n".$content."\nendstream\nendobj\n",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object;
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= str_pad((string) $offsets[$i], 10, '0', STR_PAD_LEFT)." 00000 n \n";
        }
        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";

        return $pdf;
    }
}
