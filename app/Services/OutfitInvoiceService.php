<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\OutfitSubscription;

class OutfitInvoiceService
{
    public function createPaidInvoice(OutfitSubscription $subscription): Invoice
    {
        $subscription->loadMissing(['plan', 'sponsor']);

        $number = $subscription->payment_reference ?: $this->invoiceNumber($subscription);
        $periodStart = now()->toDateString();
        $periodEnd = ($subscription->current_period_ends_at ?: now()->addMonthNoOverflow())->toDateString();

        return Invoice::query()->updateOrCreate(
            [
                'source' => 'outfit_subscription',
                'number' => $number,
            ],
            [
                'club_id' => null,
                'user_id' => $subscription->user_id,
                'title' => 'Outfit-Abo: '.($subscription->plan?->name ?: 'Airmius Outfit-Abo'),
                'description' => trim(collect([
                    'Automatisch erzeugter Beleg für Outfit-Abo #'.$subscription->id.'.',
                    $subscription->sponsor?->name ? 'Sponsor: '.$subscription->sponsor->name.'.' : null,
                    'Zahlungsart: '.$this->paymentMethodLabel($subscription->payment_provider).'.',
                ])->filter()->implode(' ')),
                'amount' => number_format(max(0, (int) $subscription->monthly_price_cents) / 100, 2, '.', ''),
                'status' => 'paid',
                'billing_period_start' => $periodStart,
                'billing_period_end' => $periodEnd,
                'due_date' => $subscription->payment_due_at ?: now(),
                'issued_at' => now(),
                'paid_at' => now(),
            ],
        );
    }

    private function invoiceNumber(OutfitSubscription $subscription): string
    {
        return 'AIR-OUT-'.$subscription->created_at?->format('Y').'-'.str_pad((string) $subscription->id, 6, '0', STR_PAD_LEFT);
    }

    private function paymentMethodLabel(?string $provider): string
    {
        return match ($provider) {
            'paypal' => 'PayPal',
            'bank_transfer' => 'Überweisung',
            default => $provider ?: 'Unbekannt',
        };
    }
}
