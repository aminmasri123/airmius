<?php

namespace App\Services;

use App\Models\Ride;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RidePricingService
{
    private static ?array $currencyData = null;

    public function catalog(?User $user): array
    {
        $data = self::$currencyData ??= json_decode(file_get_contents(resource_path('data/ride-currencies.json')), true, flags: JSON_THROW_ON_ERROR);

        return ['default_currency' => $data['countries'][strtoupper((string) $user?->country)] ?? null, 'currencies' => $data['currencies']];
    }

    public function validate(Request $request): array
    {
        if (! $request->boolean('pricing_details') && ! $request->exists('cost_mode')) {
            return []; // Older clients and existing rides retain unspecified terms.
        }
        $catalog = $this->catalog($request->user());
        $data = $request->validate([
            'cost_mode' => ['required', Rule::in(['free', 'contribution'])],
            'cost_currency' => ['nullable', 'required_if:cost_mode,contribution', Rule::in(array_keys($catalog['currencies']))],
            'cost_amount' => ['nullable', 'required_if:cost_mode,contribution', 'string', 'max:30'],
        ]);
        if ($data['cost_mode'] === 'free') {
            return ['cost_mode' => 'free', 'cost_amount_minor' => 0, 'cost_currency' => null];
        }
        $currency = $data['cost_currency'];
        $digits = $catalog['currencies'][$currency];
        $amount = strtr(trim($data['cost_amount']), array_combine(
            preg_split('//u', '٠١٢٣٤٥٦٧٨٩۰۱۲۳۴۵۶۷۸۹٫', -1, PREG_SPLIT_NO_EMPTY),
            str_split('01234567890123456789.'),
        ));
        $pattern = $digits === 0 ? '/^\d+$/' : '/^\d+(?:[.,]\d{1,'.$digits.'})?$/';
        if (! preg_match($pattern, $amount)) {
            throw ValidationException::withMessages(['cost_amount' => __('ride_pricing.invalid_amount', ['digits' => $digits])]);
        }
        [$whole, $fraction] = array_pad(explode('.', str_replace(',', '.', $amount)), 2, '');
        if (strlen(ltrim($whole, '0')) > 9) {
            throw ValidationException::withMessages(['cost_amount' => __('ride_pricing.invalid_amount', ['digits' => $digits])]);
        }
        $minor = (int) $whole * (10 ** $digits) + (int) str_pad($fraction, $digits, '0');
        if ($minor <= 0 || $minor > 999999999999) {
            throw ValidationException::withMessages(['cost_amount' => __('ride_pricing.invalid_amount', ['digits' => $digits])]);
        }

        return ['cost_mode' => 'contribution', 'cost_amount_minor' => $minor, 'cost_currency' => $currency];
    }

    public function terms(Ride $ride): array
    {
        return ['agreed_cost_mode' => $ride->cost_mode, 'agreed_cost_amount_minor' => $ride->cost_amount_minor,
            'agreed_cost_currency' => $ride->cost_currency, 'agreed_price_version' => (int) $ride->price_version,
            'price_confirmation_required' => false];
    }

    public function payload(Ride $ride, mixed $pivot = null): array
    {
        $currencies = $this->catalog(null)['currencies'];
        $terms = fn ($mode, $minor, $currency) => [
            'mode' => $mode, 'amount_minor' => $minor === null ? null : (int) $minor,
            'currency' => $currency, 'digits' => $currency ? ($currencies[$currency] ?? 2) : 2,
            'amount' => $currency && $minor !== null ? number_format($minor / (10 ** ($currencies[$currency] ?? 2)), $currencies[$currency] ?? 2, '.', '') : null,
        ];

        return [...$terms($ride->cost_mode, $ride->cost_amount_minor, $ride->cost_currency),
            'version' => (int) $ride->price_version,
            'requires_confirmation' => (bool) ($pivot?->price_confirmation_required ?? false),
            'agreed' => $pivot ? $terms($pivot->agreed_cost_mode, $pivot->agreed_cost_amount_minor, $pivot->agreed_cost_currency) : null,
        ];
    }
}
