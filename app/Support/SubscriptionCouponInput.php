<?php

namespace App\Support;

use App\Models\SubscriptionCoupon;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SubscriptionCouponInput
{
    public static function validate(Request $request, ?SubscriptionCoupon $coupon = null): array
    {
        $request->merge(['code' => is_string($request->input('code'))
            ? strtoupper(trim($request->input('code')))
            : $request->input('code')]);

        $codeRule = Rule::unique('subscription_coupons', 'code');
        if ($coupon) {
            $codeRule->ignore($coupon);
        }

        $data = $request->validate([
            'scope' => ['sometimes', Rule::in(['subscription', 'marketplace'])],
            'code' => ['required', 'string', 'max:80', $codeRule],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['percent', 'fixed'])],
            'value_cents' => ['nullable', 'integer', 'min:0'],
            'percent_off' => ['nullable', 'integer', 'min:1', 'max:100'],
            'max_redemptions' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'is_active' => ['boolean'],
        ]);

        foreach (['starts_at', 'ends_at'] as $field) {
            if (isset($data[$field])) {
                $date = Carbon::parse($data[$field])->setTimezone(config('app.timezone'));
                if ($field === 'ends_at' && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $data[$field])) {
                    $date->endOfDay();
                }
                $data[$field] = $date;
            }
        }

        $start = array_key_exists('starts_at', $data) ? $data['starts_at'] : $coupon?->starts_at;
        $end = array_key_exists('ends_at', $data) ? $data['ends_at'] : $coupon?->ends_at;
        if ($start && $end && $end->lt($start)) {
            throw ValidationException::withMessages([
                'ends_at' => __('validation.after_or_equal', ['attribute' => 'ends_at', 'date' => 'starts_at']),
            ]);
        }

        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        return $data;
    }
}
