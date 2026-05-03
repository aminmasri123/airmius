<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\SubscriptionInvoice;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SubscriptionInvoiceController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->can('subscriptions.manage') || $request->user()->can('billing.manage'), 403);

        return Inertia::render('Auth/Dashboard/Admin/SubscriptionInvoices/Index', [
            'invoices' => SubscriptionInvoice::query()
                ->with(['user:id,name,email', 'club:id,name', 'plan:id,name'])
                ->latest('id')
                ->paginate(50)
                ->through(fn (SubscriptionInvoice $invoice) => $this->resource($invoice)),
            'summary' => [
                'open' => SubscriptionInvoice::query()->whereIn('status', ['open', 'awaiting_transfer'])->count(),
                'paid' => SubscriptionInvoice::query()->where('status', 'paid')->count(),
                'overdue' => SubscriptionInvoice::query()->where('status', 'overdue')->count(),
                'revenue_cents' => SubscriptionInvoice::query()->where('status', 'paid')->sum('amount_cents'),
            ],
        ]);
    }

    public function download(Request $request, SubscriptionInvoice $subscriptionInvoice)
    {
        $canManage = $request->user()?->can('subscriptions.manage') || $request->user()?->can('billing.manage');
        abort_unless($canManage || $subscriptionInvoice->user_id === $request->user()?->id, 403);

        $pdf = $this->buildSimplePdf($subscriptionInvoice->load(['user', 'club', 'plan']));

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$subscriptionInvoice->number.'.pdf"',
        ]);
    }

    private function resource(SubscriptionInvoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'number' => $invoice->number,
            'title' => $invoice->title,
            'amount' => number_format($invoice->amount_cents / 100, 2, ',', '.').' '.$invoice->currency,
            'amount_cents' => $invoice->amount_cents,
            'currency' => $invoice->currency,
            'status' => $invoice->status,
            'payment_method' => $invoice->payment_method,
            'payment_reference' => $invoice->payment_reference,
            'issued_at' => $invoice->issued_at?->toDateString(),
            'due_at' => $invoice->due_at?->toDateString(),
            'paid_at' => $invoice->paid_at?->toDateString(),
            'user' => $invoice->user,
            'club' => $invoice->club,
            'plan' => $invoice->plan,
        ];
    }

    private function buildSimplePdf(SubscriptionInvoice $invoice): string
    {
        $lines = [
            'Airmius Rechnung',
            'Rechnung: '.$invoice->number,
            'Datum: '.($invoice->issued_at?->format('d.m.Y') ?? now()->format('d.m.Y')),
            '',
            'Leistungsempfaenger:',
            $invoice->club?->name ?: ($invoice->user?->name ?: $invoice->user?->email),
            $invoice->user?->email ?: '',
            '',
            'Leistung:',
            $invoice->title,
            $invoice->description ?: '',
            'Plan: '.($invoice->plan?->name ?: '-'),
            'Zeitraum: '.($invoice->billing_period_start?->format('d.m.Y') ?? '-').' - '.($invoice->billing_period_end?->format('d.m.Y') ?? '-'),
            '',
            'Betrag: '.number_format($invoice->amount_cents / 100, 2, ',', '.').' '.$invoice->currency,
            'Status: '.$invoice->status,
            'Zahlungsart: '.($invoice->payment_method ?: '-'),
            'Verwendungszweck: '.($invoice->payment_reference ?: '-'),
            '',
            'Zahlungsempfaenger:',
            Setting::valueFor('billing_bank_account_holder', 'Airmius'),
            Setting::valueFor('billing_bank_name', ''),
            'IBAN: '.Setting::valueFor('billing_iban', ''),
            'BIC: '.Setting::valueFor('billing_bic', ''),
        ];

        return $this->plainTextPdf($lines);
    }

    private function plainTextPdf(array $lines): string
    {
        $content = "BT\n/F1 12 Tf\n50 790 Td\n14 TL\n";

        foreach ($lines as $line) {
            $content .= '('.$this->escapePdfText($line).") Tj\nT*\n";
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
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= str_pad((string) $offsets[$i], 10, '0', STR_PAD_LEFT)." 00000 n \n";
        }

        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\n";
        $pdf .= "startxref\n".$xref."\n%%EOF";

        return $pdf;
    }

    private function escapePdfText(?string $text): string
    {
        $text = iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', (string) $text);

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }
}
