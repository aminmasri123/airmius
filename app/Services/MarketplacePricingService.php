<?php

namespace App\Services;

use App\Models\CommerceShippingRate;
use App\Models\CommerceTaxRate;
use App\Models\MarketplaceProduct;
use App\Models\Setting;
use App\Support\VisitorCountry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class MarketplacePricingService
{
    public function __construct(private VisitorCountry $visitorCountry) {}

    public function quoteForRequest(MarketplaceProduct $product, Request $request, ?string $country = null, ?array $address = null): array
    {
        $address = $this->normalizeAddress($address ?: $request->only([
            'shipping_country',
            'shipping_state',
            'shipping_postal_code',
            'shipping_city',
            'shipping_street',
            'shipping_house_number',
            'country',
        ]));
        $customer = $this->normalizeCustomer($request->only(['customer_type', 'customer_company', 'customer_vat_id']));
        $resolved = $this->visitorCountry->resolve($request, $country ?: $address['country'] ?: $request->input('country'));

        return $this->quote($product, $resolved['country'], $resolved['source'], $address, $customer);
    }

    public function quote(MarketplaceProduct $product, ?string $country = null, ?string $source = null, array $address = [], array $customer = []): array
    {
        $address = $this->normalizeAddress($address);
        $customer = $this->normalizeCustomer($customer);
        $shippingCountry = $address['country'] ?: null;
        $profile = $this->taxProfile($address['country'] ?: $country, $address['state'] ?? null, $product->tax_class ?: 'standard', $customer);
        $grossCents = $this->convertCents(
            (int) $product->price_cents,
            $product->currency ?: 'EUR',
            $profile['currency'],
        );
        $shipping = $this->shippingProfile($product, $shippingCountry, $address['postal_code'] ?? null, $grossCents, $profile['currency']);
        $shippingGrossCents = $this->convertCents(
            (int) $shipping['amount_cents'],
            $shipping['currency'],
            $profile['currency'],
        );
        $totalGrossCents = $grossCents + $shippingGrossCents;
        $taxRate = (float) $profile['tax_rate'];
        $netCents = $taxRate > 0
            ? (int) round($grossCents / (1 + ($taxRate / 100)))
            : $grossCents;
        $shippingNetCents = $taxRate > 0
            ? (int) round($shippingGrossCents / (1 + ($taxRate / 100)))
            : $shippingGrossCents;

        return [
            'country' => $profile['country'],
            'country_source' => $source ?: 'fallback',
            'currency' => $profile['currency'],
            'item_gross_cents' => $grossCents,
            'item_net_cents' => $netCents,
            'item_tax_cents' => max(0, $grossCents - $netCents),
            'shipping_gross_cents' => $shippingGrossCents,
            'shipping_net_cents' => $shippingNetCents,
            'shipping_tax_cents' => max(0, $shippingGrossCents - $shippingNetCents),
            'shipping_label' => $shipping['name'],
            'gross_cents' => $totalGrossCents,
            'net_cents' => $netCents + $shippingNetCents,
            'tax_cents' => max(0, ($grossCents - $netCents) + ($shippingGrossCents - $shippingNetCents)),
            'tax_rate' => $taxRate,
            'tax_label' => $profile['tax_label'],
            'tax_rule' => $profile['tax_rule'],
            'reverse_charge' => $profile['reverse_charge'],
            'base_currency' => $product->currency ?: 'EUR',
            'base_gross_cents' => (int) $product->price_cents,
            'address' => $address,
            'customer' => $customer,
            'is_estimate' => $profile['is_estimate'],
        ];
    }

    public function taxProfiles(): array
    {
        if (Schema::hasTable('commerce_tax_rates')) {
            $profiles = CommerceTaxRate::query()
                ->where('is_active', true)
                ->orderBy('priority')
                ->orderByDesc('is_default')
                ->get()
                ->where('tax_class', 'standard')
                ->unique('country_code')
                ->mapWithKeys(fn (CommerceTaxRate $rate) => [
                    strtoupper($rate->country_code) => [
                        'currency' => strtoupper($rate->currency ?: 'EUR'),
                        'tax_rate' => (float) $rate->rate_percent,
                        'tax_label' => $rate->tax_label ?: 'Tax',
                        'is_estimate' => false,
                    ],
                ])
                ->all();

            if ($profiles !== []) {
                return $profiles;
            }
        }

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

    public function shippingRates(): array
    {
        if (! Schema::hasTable('commerce_shipping_rates')) {
            return [];
        }

        return CommerceShippingRate::query()
            ->where('is_active', true)
            ->orderBy('priority')
            ->get()
            ->map(fn (CommerceShippingRate $rate) => [
                'id' => $rate->id,
                'name' => $rate->name,
                'country_code' => $rate->country_code ? strtoupper($rate->country_code) : null,
                'postal_code_prefix' => $rate->postal_code_prefix,
                'amount_cents' => (int) $rate->amount_cents,
                'currency' => strtoupper($rate->currency ?: 'EUR'),
                'free_from_cents' => $rate->free_from_cents,
            ])
            ->all();
    }

    public function commissionPercentFor(MarketplaceProduct $product): int
    {
        $category = trim((string) $product->category);
        $commissions = $this->categoryCommissionSettings();

        if ($category !== '' && array_key_exists($category, $commissions)) {
            return $this->clampPercent((int) $commissions[$category]);
        }

        if ($product->commission_percent !== null) {
            return $this->clampPercent((int) $product->commission_percent);
        }

        return $this->clampPercent((int) Setting::valueFor('marketplace_default_commission_percent', 10));
    }

    public function commissionCents(MarketplaceProduct $product, int $itemGrossCents): int
    {
        return (int) floor(max(0, $itemGrossCents) * ($this->commissionPercentFor($product) / 100));
    }

    private function taxProfile(?string $country, ?string $region = null, string $taxClass = 'standard', array $customer = []): array
    {
        $country = strtoupper((string) $country);
        $region = trim((string) $region);
        $companyCountry = strtoupper((string) Setting::valueFor('commerce_company_country', 'DE'));
        $isEuCountry = $this->isEuCountry($country);
        $isCompanyCountry = $country === $companyCountry;
        $customer = $this->normalizeCustomer($customer);

        if ($country !== '' && ! $isEuCountry && Setting::valueFor('commerce_export_vat_mode', 'zero') === 'zero') {
            return [
                'country' => $country,
                'currency' => strtoupper((string) Setting::valueFor('commerce_company_currency', 'EUR')),
                'tax_rate' => 0.0,
                'tax_label' => 'Export',
                'tax_rule' => 'export_outside_eu',
                'reverse_charge' => false,
                'is_estimate' => false,
            ];
        }

        if (
            Setting::boolFor('commerce_reverse_charge_enabled', true)
            && $customer['type'] === 'business'
            && filled($customer['vat_id'])
            && $isEuCountry
            && ! $isCompanyCountry
        ) {
            return [
                'country' => $country,
                'currency' => strtoupper((string) Setting::valueFor('commerce_company_currency', 'EUR')),
                'tax_rate' => 0.0,
                'tax_label' => 'Reverse Charge',
                'tax_rule' => 'eu_b2b_reverse_charge',
                'reverse_charge' => true,
                'is_estimate' => false,
            ];
        }

        if (Schema::hasTable('commerce_tax_rates')) {
            $query = CommerceTaxRate::query()
                ->where('is_active', true)
                ->where('country_code', $country ?: 'DE')
                ->where('tax_class', $taxClass)
                ->orderBy('priority')
                ->orderByDesc('is_default');

            $rate = $region !== ''
                ? (clone $query)->where('region', $region)->first()
                : null;

            $rate ??= $query->whereNull('region')->first();
            $rate ??= CommerceTaxRate::query()->where('is_active', true)->where('is_default', true)->orderBy('priority')->first();

            if ($rate) {
                return [
                    'country' => strtoupper($rate->country_code),
                    'currency' => strtoupper($rate->currency ?: 'EUR'),
                    'tax_rate' => (float) $rate->rate_percent,
                    'tax_label' => $rate->tax_label ?: 'Tax',
                    'tax_rule' => $isCompanyCountry ? 'domestic' : 'eu_b2c_destination',
                    'reverse_charge' => false,
                    'is_estimate' => false,
                ];
            }
        }

        $profiles = $this->taxProfiles();
        $profile = $profiles[$country] ?? ['currency' => 'EUR', 'tax_rate' => 19.0, 'tax_label' => 'Tax', 'is_estimate' => true];

        return ['country' => $country ?: 'DE', 'tax_rule' => 'fallback', 'reverse_charge' => false, ...$profile];
    }

    private function shippingProfile(MarketplaceProduct $product, ?string $country, ?string $postalCode, int $itemGrossCents, string $currency): array
    {
        if (! $this->requiresShipping($product) || blank($country)) {
            return ['name' => 'Keine Versandkosten', 'amount_cents' => 0, 'currency' => $currency];
        }

        $country = strtoupper((string) ($country ?: 'DE'));
        $postalCode = preg_replace('/\s+/', '', (string) $postalCode);

        if (Schema::hasTable('commerce_shipping_rates')) {
            $rates = CommerceShippingRate::query()
                ->where('is_active', true)
                ->where(function ($query) use ($country) {
                    $query->where('country_code', $country)->orWhereNull('country_code');
                })
                ->orderBy('priority')
                ->get();

            $rate = $rates->first(function (CommerceShippingRate $rate) use ($postalCode) {
                return blank($rate->postal_code_prefix)
                    || ($postalCode !== '' && str_starts_with($postalCode, (string) $rate->postal_code_prefix));
            });

            if ($rate) {
                $amount = (int) $rate->amount_cents;
                if ($rate->free_from_cents !== null && $itemGrossCents >= (int) $rate->free_from_cents) {
                    $amount = 0;
                }

                return [
                    'name' => $amount > 0 ? $rate->name : $rate->name.' (kostenfrei)',
                    'amount_cents' => $amount,
                    'currency' => strtoupper($rate->currency ?: $currency),
                ];
            }
        }

        return ['name' => 'Standardversand', 'amount_cents' => 0, 'currency' => $currency];
    }

    private function requiresShipping(MarketplaceProduct $product): bool
    {
        return (bool) ($product->is_shippable ?? in_array($product->category, ['product', 'outfit_subscription'], true));
    }

    private function normalizeAddress(array $address): array
    {
        return [
            'country' => strtoupper((string) ($address['shipping_country'] ?? $address['country'] ?? '')),
            'state' => trim((string) ($address['shipping_state'] ?? $address['state'] ?? '')),
            'postal_code' => trim((string) ($address['shipping_postal_code'] ?? $address['postal_code'] ?? '')),
            'city' => trim((string) ($address['shipping_city'] ?? $address['city'] ?? '')),
            'street' => trim((string) ($address['shipping_street'] ?? $address['street'] ?? '')),
            'house_number' => trim((string) ($address['shipping_house_number'] ?? $address['house_number'] ?? '')),
        ];
    }

    private function normalizeCustomer(array $customer): array
    {
        return [
            'type' => ($customer['customer_type'] ?? $customer['type'] ?? 'consumer') === 'business' ? 'business' : 'consumer',
            'company' => trim((string) ($customer['customer_company'] ?? $customer['company'] ?? '')),
            'vat_id' => strtoupper(preg_replace('/\s+/', '', (string) ($customer['customer_vat_id'] ?? $customer['vat_id'] ?? ''))),
        ];
    }

    private function isEuCountry(?string $country): bool
    {
        return in_array(strtoupper((string) $country), [
            'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'EL', 'ES', 'FI', 'FR', 'HR', 'HU', 'IE', 'IT',
            'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK',
        ], true);
    }

    private function categoryCommissionSettings(): array
    {
        $raw = Setting::valueFor('marketplace_category_commissions', '{}');
        $decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function clampPercent(int $percent): int
    {
        return max(0, min(100, $percent));
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
