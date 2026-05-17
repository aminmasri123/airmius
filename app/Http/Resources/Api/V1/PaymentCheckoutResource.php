<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentCheckoutResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $payload = $this->payload ?: [];

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'club_id' => $this->club_id,
            'subscription_plan_id' => $this->subscription_plan_id,
            'subscription_coupon_id' => $this->subscription_coupon_id,
            'provider' => $this->provider,
            'billing_interval' => $this->billing_interval,
            'original_amount_cents' => $this->original_amount_cents,
            'discount_cents' => $this->discount_cents,
            'amount_cents' => $this->amount_cents,
            'currency' => $this->currency,
            'status' => $this->status,
            'provider_checkout_id' => $this->provider_checkout_id,
            'provider_subscription_id' => $this->provider_subscription_id,
            'payment_reference' => $this->payment_reference,
            'due_at' => $this->due_at?->toJSON(),
            'checkout_url' => $this->checkout_url,
            'completed_at' => $this->completed_at?->toJSON(),
            'payment_action' => $this->paymentAction($payload),
            'pricing' => [
                'country' => $payload['pricing_country'] ?? null,
                'country_source' => $payload['pricing_country_source'] ?? null,
                'localized_price' => (bool) ($payload['localized_price'] ?? false),
                'base_currency' => $payload['base_currency'] ?? null,
                'base_monthly_price_cents' => $payload['base_monthly_price_cents'] ?? null,
                'base_yearly_price_cents' => $payload['base_yearly_price_cents'] ?? null,
            ],
            'bank_transfer' => $this->when($this->provider === 'bank_transfer', fn () => [
                'account_holder' => $payload['bank_account_holder'] ?? null,
                'bank_name' => $payload['bank_name'] ?? null,
                'iban' => $payload['iban'] ?? null,
                'bic' => $payload['bic'] ?? null,
                'payment_terms_days' => $payload['payment_terms_days'] ?? null,
            ]),
            'plan' => new SubscriptionPlanResource($this->whenLoaded('plan')),
            'club' => new ClubResource($this->whenLoaded('club')),
            'invoice' => new SubscriptionInvoiceResource($this->whenLoaded('invoice')),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }

    private function paymentAction(array $payload): array
    {
        if ($this->provider === 'bank_transfer') {
            return [
                'type' => 'bank_transfer',
                'provider' => 'bank_transfer',
                'payment_reference' => $this->payment_reference,
                'due_at' => $this->due_at?->toJSON(),
            ];
        }

        if (in_array($this->provider, ['stripe', 'paypal'], true)) {
            return [
                'type' => 'redirect',
                'provider' => $this->provider,
                'checkout_url' => $this->checkout_url,
                'return_url' => $payload['provider_return_url'] ?? null,
                'cancel_url' => $payload['provider_cancel_url'] ?? null,
            ];
        }

        return [
            'type' => 'unknown',
            'provider' => $this->provider,
        ];
    }
}
