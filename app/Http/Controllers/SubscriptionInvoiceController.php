<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionInvoice;
use App\Services\AirmiusLegalProfile;
use App\Services\AirmiusPdfDocument;
use App\Services\UserSubscriptionActivationService;
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

        $pdf = $this->buildBrandedPdf($subscriptionInvoice->load(['user', 'club', 'plan']), app(AirmiusLegalProfile::class));

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
                $subscription = $checkout->club->currentSubscription()->updateOrCreate(
                    ['club_id' => $checkout->club_id],
                    [
                        'subscription_plan_id' => $checkout->subscription_plan_id,
                        'status' => 'active',
                        'payment_provider' => $checkout->provider,
                        'billing_interval' => $checkout->billing_interval,
                        'provider_subscription_id' => $checkout->provider_subscription_id,
                        'provider_customer_id' => $checkout->provider_customer_id,
                        'trial_ends_at' => null,
                        'current_period_ends_at' => $periodEndsAt,
                        'next_invoice_at' => $periodEndsAt,
                        'grace_period_ends_at' => null,
                        'access_restricted_at' => null,
                    ],
                );
            } else {
                $subscription = $checkout->user->subscriptions()->updateOrCreate(
                    ['subscription_plan_id' => $checkout->subscription_plan_id],
                    [
                        'status' => 'active',
                        'payment_provider' => $checkout->provider,
                        'billing_interval' => $checkout->billing_interval,
                        'provider_subscription_id' => $checkout->provider_subscription_id,
                        'provider_customer_id' => $checkout->provider_customer_id,
                        'trial_ends_at' => null,
                        'current_period_ends_at' => $periodEndsAt,
                        'next_invoice_at' => $periodEndsAt,
                        'grace_period_ends_at' => null,
                        'access_restricted_at' => null,
                    ],
                );

                app(UserSubscriptionActivationService::class)->retireOtherUserSubscriptions($subscription->fresh('plan'));
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

        if (! $checkout && $invoice->subscription_type && $invoice->subscription_id) {
            $subscription = match ($invoice->subscription_type) {
                'club' => \App\Models\ClubSubscription::query()->find($invoice->subscription_id),
                'user' => \App\Models\UserSubscription::query()->find($invoice->subscription_id),
                default => null,
            };

            if ($subscription) {
                $subscription->forceFill([
                    'status' => 'active',
                    'grace_period_ends_at' => null,
                    'access_restricted_at' => null,
                    'payment_issue_email_sent_at' => null,
                ])->save();

                if ($subscription instanceof \App\Models\UserSubscription) {
                    app(UserSubscriptionActivationService::class)->retireOtherUserSubscriptions($subscription->fresh('plan'));
                }
            }
        }

        $invoice->forceFill([
            'status' => 'paid',
            'paid_at' => now(),
            'payment_reference' => $invoice->payment_reference ?: $checkout?->payment_reference ?: $checkout?->provider_checkout_id,
            'subscription_type' => $invoice->subscription_type ?: ($checkout?->club_id ? 'club' : ($checkout ? 'user' : null)),
            'subscription_id' => $invoice->subscription_id ?: ($subscription->id ?? null),
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

    private function buildBrandedPdf(SubscriptionInvoice $invoice, AirmiusLegalProfile $legalProfile): string
    {
        $pdf = new AirmiusPdfDocument();
        $profile = $legalProfile->data();
        $bank = $legalProfile->bank();
        $recipientName = $invoice->club?->name ?: ($invoice->user?->name ?: $invoice->user?->email ?: '-');
        $recipientEmail = $invoice->user?->email ?: '';
        $amount = $pdf->money($invoice->amount_cents, $invoice->currency);
        $period = ($invoice->billing_period_start?->format('d.m.Y') ?? '-').' - '.($invoice->billing_period_end?->format('d.m.Y') ?? '-');
        $issuedAt = $invoice->issued_at?->format('d.m.Y') ?? now()->format('d.m.Y');
        $dueAt = $invoice->due_at?->format('d.m.Y') ?? '-';
        $paidAt = $invoice->paid_at?->format('d.m.Y') ?? '-';
        $statusLabel = $this->statusLabel($invoice->status);
        $paymentMethod = $this->paymentMethodLabel($invoice->payment_method);

        $pdf->header(
            'RECHNUNG',
            $invoice->number,
            'Sport. Vereine. Wachstum.',
            $profile['brand_name'] ?? null,
            'Rechnungsnummer',
            'invoice'
        );

        // Meta cards
        $pdf->card(48, 632, 150, 54, 'Datum', $issuedAt);
        $pdf->card(222, 632, 150, 54, 'Faellig bis', $dueAt);
        $pdf->card(396, 632, 150, 54, 'Betrag', $amount, true);

        // Recipient and payment summary
        $pdf->sectionTitle('Leistungsempfaenger', 48, 585);
        $pdf->text($recipientName, 48, 562, 13, true);
        if ($recipientEmail !== '') {
            $pdf->text($recipientEmail, 48, 544, 10, false, AirmiusPdfDocument::SLATE);
        }
        if ($invoice->club) {
            $pdf->text('Verein: '.$invoice->club->name, 48, 528, 10, false, AirmiusPdfDocument::SLATE);
        }
        $pdf->issuerBlock($profile, 48, 497);

        $pdf->sectionTitle('Status', 396, 585);
        $pdf->statusPill($statusLabel, $invoice->status, 396, 552);
        $pdf->labelValue('Zahlungsart', $paymentMethod, 396, 528);
        $pdf->labelValue('Bezahlt am', $paidAt, 396, 504);

        // Line item table
        $pdf->sectionTitle('Leistung', 48, 382);
        $pdf->invoiceTableHeader(48, 339, 498);
        $pdf->text($invoice->title ?: 'Airmius Abo', 64, 315, 11, true);
        $pdf->text($invoice->description ?: ($invoice->plan?->name ?: '-'), 64, 298, 9, false, AirmiusPdfDocument::SLATE, 250);
        $pdf->text($period, 330, 315, 9, false, AirmiusPdfDocument::SLATE);
        $pdf->text($amount, 487, 315, 11, true);
        $pdf->strokeColor(...AirmiusPdfDocument::BORDER)->line(48, 279, 546, 279);

        // Totals
        $pdf->text('Zwischensumme', 365, 244, 10, false, AirmiusPdfDocument::SLATE);
        $pdf->text($amount, 488, 244, 10);
        $pdf->text('Gesamtbetrag', 365, 216, 14, true);
        $pdf->text($amount, 474, 216, 14, true, AirmiusPdfDocument::BLUE);

        // Payment details
        $pdf->sectionTitle('Zahlungsinformationen', 48, 244);
        $pdf->labelValue('Verwendungszweck', $invoice->payment_reference ?: $invoice->number, 48, 216, 190);
        $pdf->labelValue('Kontoinhaber', $bank['holder'], 48, 192, 190);
        $pdf->labelValue('Bank', $bank['bank_name'], 48, 168, 190);
        $pdf->labelValue('IBAN', $bank['iban'], 48, 144, 190);
        $pdf->labelValue('BIC', $bank['bic'], 48, 120, 190);

        if (filled($profile['small_business_notice'])) {
            $pdf->text($profile['small_business_notice'], 48, 104, 8, false, AirmiusPdfDocument::SLATE, 95);
        }
        if (filled($profile['invoice_note'])) {
            $pdf->text($profile['invoice_note'], 48, 92, 8, false, AirmiusPdfDocument::SLATE, 95);
        }

        return $pdf->legalFooter($profile)->render();
    }

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            'paid' => 'Bezahlt',
            'open' => 'Offen',
            'awaiting_transfer' => 'Wartet auf Ueberweisung',
            'overdue' => 'Ueberfaellig',
            'cancelled' => 'Storniert',
            default => $status ?: '-',
        };
    }

    private function paymentMethodLabel(?string $method): string
    {
        return match ($method) {
            'stripe' => 'Stripe',
            'paypal' => 'PayPal',
            'bank_transfer' => 'Ueberweisung',
            default => $method ?: '-',
        };
    }

}
