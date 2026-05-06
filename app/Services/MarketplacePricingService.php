<?php

namespace App\Services;

use App\Models\MarketplaceProduct;
use App\Support\VisitorCountry;
use Illuminate\Http\Request;

class MarketplacePricingService
{
    public function __construct(private VisitorCountry $visitorCountry) {}

    public function quoteForRequest(MarketplaceProduct $product, Request $request, ?string $country = null): array
    {
        $resolved = $this->visitorCountry->resolve($request, $country ?: $request->input('country'));

        return $this->quote($product, $resolved['country'], $resolved['source']);
    }

    public function quote(MarketplaceProduct $product, ?string $country = null, ?string $source = null): array
    {
        $profile = $this->taxProfile($country);
        $grossCents = $this->convertCents(
            (int) $product->price_cents,
            $product->currency ?: 'EUR',
            $profile['currency'],
        );
        $taxRate = (float) $profile['tax_rate'];
        $netCents = $taxRate > 0
            ? (int) round($grossCents / (1 + ($taxRate / 100)))
            : $grossCents;

        return [
            'country' => $profile['country'],
            'country_source' => $source ?: 'fallback',
            'currency' => $profile['currency'],
            'gross_cents' => $grossCents,
            'net_cents' => $netCents,
            'tax_cents' => max(0, $grossCents - $netCents),
            'tax_rate' => $taxRate,
            'tax_label' => $profile['tax_label'],
            'base_currency' => $product->currency ?: 'EUR',
            'base_gross_cents' => (int) $product->price_cents,
            'is_estimate' => $profile['is_estimate'],
        ];
    }

    public function taxProfiles(): array
    {
        return [
            'DE' => ['currency' => 'EUR', 'tax_rate' => 19.0, 'tax_label' => 'MwSt.', 'is_estimate' => false],
            'AT' => ['currency' => 'EUR', 'tax_rate' => 20.0, 'tax_label' => 'USt.', 'is_estimate' => false],
            'FR' => ['currency' => 'EUR', 'tax_rate' => 20.0, 'tax_label' => 'TVA', 'is_estimate' => false],
            'ES' => ['currency' => 'EUR', 'tax_rate' => 21.0, 'tax_label' => 'IVA', 'is_estimate' => false],
            'IT' => ['currency' => 'EUR', 'tax_rate' => 22.0, 'tax_label' => 'IVA', 'is_estimate' => false],
            'NL' => ['currency' => 'EUR', 'tax_rate' => 21.0, 'tax_label' => 'BTW', 'is_estimate' => false],
            'BE' => ['currency' => 'EUR', 'tax_rate' => 21.0, 'tax_label' => 'TVA/BTW', 'is_estimate' => false],
            'CH' => ['currency' => 'CHF', 'tax_rate' => 8.1, 'tax_label' => 'MwSt.', 'is_estimate' => false],
            'GB' => ['currency' => 'GBP', 'tax_rate' => 20.0, 'tax_label' => 'VAT', 'is_estimate' => false],
            'MA' => ['currency' => 'MAD', 'tax_rate' => 20.0, 'tax_label' => 'TVA', 'is_estimate' => false],
            'US' => ['currency' => 'USD', 'tax_rate' => 0.0, 'tax_label' => 'Tax', 'is_estimate' => true],
            'CA' => ['currency' => 'CAD', 'tax_rate' => 0.0, 'tax_label' => 'Tax', 'is_estimate' => true],
        ];
    }

    private function taxProfile(?string $country): array
    {
        $country = strtoupper((string) $country);
        $profiles = $this->taxProfiles();
        $profile = $profiles[$country] ?? ['currency' => 'EUR', 'tax_rate' => 19.0, 'tax_label' => 'Tax', 'is_estimate' => true];

        return ['country' => $country ?: 'DE', ...$profile];
    }

    private function convertCents(int $amountCents, string $fromCurrency, string $toCurrency): int
    {
        $fromCurrency = strtoupper($fromCurrency);
        $toCurrency = strtoupper($toCurrency);

        if ($fromCurrency === $toCurrency) {
            return $amountCents;
        }

        $rates = [
            'EUR' => 1.0,
            'USD' => 1.08,
            'CAD' => 1.48,
            'CHF' => 0.97,
            'GBP' => 0.86,
            'MAD' => 10.85,
        ];

        $eur = $amountCents / ($rates[$fromCurrency] ?? 1.0);

        return (int) round($eur * ($rates[$toCurrency] ?? 1.0));
    }
}
