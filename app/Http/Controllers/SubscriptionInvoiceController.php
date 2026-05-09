<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\SubscriptionInvoice;
use App\Support\AppNotification;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SubscriptionInvoiceController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->can('subscriptions.manage') || $request->user()->can('billing.manage'), 403);

        return Inertia::render('Auth/Dashboard/Admin/SubscriptionInvoices/Index', [
            'invoices' => SubscriptionInvoice::query()
                ->with(['user:id,name,email', 'club:id,name', 'plan:id,name', 'checkout:id,status,provider_checkout_id,payment_reference'])
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

    public function markPaid(Request $request, SubscriptionInvoice $subscriptionInvoice)
    {
        abort_unless($request->user()->can('subscriptions.manage') || $request->user()->can('billing.manage'), 403);

        $invoice = $subscriptionInvoice->load(['checkout.user', 'checkout.club', 'checkout.plan', 'user', 'club', 'plan']);
        $checkout = $invoice->checkout;
        $periodEndsAt = $invoice->billing_period_end
            ?: ($checkout?->billing_interval === 'yearly' ? now()->addYear() : now()->addMonth());

        if ($checkout) {
            if ($checkout->club_id) {
                $checkout->club->currentSubscription()->updateOrCreate(
                    ['club_id' => $checkout->club_id],
                    [
                        'subscription_plan_id' => $checkout->subscription_plan_id,
                        'status' => 'active',
                        'payment_provider' => $checkout->provider,
                        'provider_subscription_id' => $checkout->provider_subscription_id,
                        'provider_customer_id' => $checkout->provider_customer_id,
                        'trial_ends_at' => null,
                        'current_period_ends_at' => $periodEndsAt,
                    ],
                );
            } else {
                $checkout->user->userSubscriptions()->updateOrCreate(
                    ['subscription_plan_id' => $checkout->subscription_plan_id],
                    [
                        'status' => 'active',
                        'payment_provider' => $checkout->provider,
                        'provider_subscription_id' => $checkout->provider_subscription_id,
                        'provider_customer_id' => $checkout->provider_customer_id,
                        'trial_ends_at' => null,
                        'current_period_ends_at' => $periodEndsAt,
                    ],
                );
            }

            $checkout->forceFill([
                'status' => 'completed',
                'completed_at' => now(),
                'payload' => array_merge($checkout->payload ?? [], [
                    'manually_marked_paid_at' => now()->toIso8601String(),
                    'manually_marked_paid_by' => $request->user()->id,
                ]),
            ])->save();
        }

        $invoice->forceFill([
            'status' => 'paid',
            'paid_at' => now(),
            'payment_reference' => $invoice->payment_reference ?: $checkout?->payment_reference ?: $checkout?->provider_checkout_id,
            'meta' => array_merge($invoice->meta ?? [], [
                'manually_marked_paid_at' => now()->toIso8601String(),
                'manually_marked_paid_by' => $request->user()->id,
            ]),
        ])->save();

        if ($invoice->user_id) {
            AppNotification::send($invoice->user_id, 'subscription.invoice.paid', [
                'title' => 'Airmius Rechnung bezahlt',
                'body' => $invoice->number.' wurde als bezahlt markiert.',
                'subscription_invoice_id' => $invoice->id,
            ]);
        }

        return back()->with('success', 'Rechnung wurde als bezahlt markiert und das Abo wurde aktiviert.');
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
            'checkout_id' => $invoice->payment_checkout_id,
            'checkout_status' => $invoice->checkout?->status,
            'provider_checkout_id' => $invoice->checkout?->provider_checkout_id,
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
