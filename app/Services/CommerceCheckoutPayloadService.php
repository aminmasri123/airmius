<?php

namespace App\Services;

use App\Models\CommerceShippingAddress;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Support\EuVatId;
use App\Support\VisitorCountry;
use Illuminate\Http\Request;

class CommerceCheckoutPayloadService
{
    public function __construct(
        private CommerceCartService $cartService,
        private MarketplacePricingService $pricing,
        private VisitorCountry $visitorCountry,
    ) {}

    public function accountPlansFor(Request $request): array
    {
        $resolvedCountry = $this->visitorCountry->resolve($request, $request->user()?->country);
        $activePlanIds = $request->user()
            ? $request->user()
                ->subscriptions()
                ->grantingAccess()
                ->pluck('subscription_plan_id')
                ->all()
            : [];

        return SubscriptionPlan::query()
            ->with('countryPrices')
            ->where('target_actor', 'sportler')
            ->where('is_public', true)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(function (SubscriptionPlan $plan) use ($resolvedCountry, $activePlanIds) {
                $price = $plan->priceForCountry($resolvedCountry['country']);

                if (! $price['available']) {
                    return null;
                }

                return [
                    'id' => $plan->id,
                    'slug' => $plan->slug,
                    'name' => $plan->name,
                    'description' => $plan->description,
                    'monthly_price_cents' => $price['monthly_price_cents'],
                    'yearly_price_cents' => $price['yearly_price_cents'],
                    'currency' => $price['currency'],
                    'pricing_country' => $price['country_code'],
                    'localized_price' => $price['localized'],
                    'storage_gb' => $plan->storage_gb,
                    'features' => $plan->features ?? [],
                    'cta_label' => $plan->cta_label,
                    'badge' => $plan->badge,
                    'is_owned' => in_array($plan->id, $activePlanIds, true),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    public function shippingAddressForAuthenticatedUser(Request $request, array $data): array
    {
        $user = $request->user();

        return [
            'country' => strtoupper((string) ($data['shipping_country'] ?? $user?->country ?? 'DE')),
            'state' => trim((string) ($data['shipping_state'] ?? $user?->state ?? '')),
            'postal_code' => trim((string) ($data['shipping_postal_code'] ?? $user?->postal_code ?? '')),
            'city' => trim((string) ($data['shipping_city'] ?? $user?->city ?? '')),
            'street' => trim((string) ($data['shipping_street'] ?? $user?->street ?? '')),
            'house_number' => trim((string) ($data['shipping_house_number'] ?? $user?->house_number ?? '')),
        ];
    }

    public function profileAddressFor(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        return $this->addressResource([
            'id' => 'profile',
            'label' => 'Meine Adresse',
            'country' => $user->country ?: 'DE',
            'state' => $user->state,
            'postal_code' => $user->postal_code,
            'city' => $user->city,
            'street' => $user->street,
            'house_number' => $user->house_number,
            'is_default' => false,
        ]);
    }

    public function shippingAddressesFor(?User $user): array
    {
        if (! $user) {
            return [];
        }

        return CommerceShippingAddress::query()
            ->where('user_id', $user->id)
            ->orderByDesc('is_default')
            ->latest('id')
            ->get()
            ->map(fn (CommerceShippingAddress $address) => $this->addressResource($address->toArray()))
            ->all();
    }

    public function addressResource(array $address): array
    {
        $lineOne = trim(implode(' ', array_filter([
            $address['street'] ?? '',
            $address['house_number'] ?? '',
        ])));
        $lineTwo = trim(implode(' ', array_filter([
            $address['postal_code'] ?? '',
            $address['city'] ?? '',
        ])));

        return [
            'id' => $address['id'] ?? null,
            'label' => $address['label'] ?: ($lineOne ?: 'Lieferadresse'),
            'country' => strtoupper((string) ($address['country'] ?? 'DE')),
            'state' => trim((string) ($address['state'] ?? '')),
            'postal_code' => trim((string) ($address['postal_code'] ?? '')),
            'city' => trim((string) ($address['city'] ?? '')),
            'street' => trim((string) ($address['street'] ?? '')),
            'house_number' => trim((string) ($address['house_number'] ?? '')),
            'is_default' => (bool) ($address['is_default'] ?? false),
            'summary' => trim(implode(', ', array_filter([$lineOne, $lineTwo, strtoupper((string) ($address['country'] ?? 'DE'))]))),
        ];
    }

    public function customerFromData(array $data): array
    {
        return [
            'type' => ($data['customer_type'] ?? 'consumer') === 'business' ? 'business' : 'consumer',
            'company' => trim((string) ($data['customer_company'] ?? '')),
            'vat_id' => EuVatId::normalize((string) ($data['customer_vat_id'] ?? '')),
            'vat_id_is_valid' => EuVatId::looksValid((string) ($data['customer_vat_id'] ?? '')),
        ];
    }

    public function orderAmountsFromQuote(array $quote): array
    {
        return [
            'item_gross_cents' => (int) ($quote['item_gross_cents'] ?? $quote['gross_cents']),
            'shipping_cents' => (int) ($quote['shipping_gross_cents'] ?? 0),
            'net_cents' => (int) ($quote['net_cents'] ?? 0),
            'tax_cents' => (int) ($quote['tax_cents'] ?? 0),
            'amount_cents' => (int) ($quote['gross_cents'] ?? 0),
        ];
    }

    public function cartResource(Request $request): array
    {
        return $this->cartService->resourceFor(
            $request->user(),
            $this->shippingAddressForAuthenticatedUser($request, []),
        );
    }

    public function pricingCountries(): array
    {
        return collect($this->pricing->taxProfiles())
            ->map(fn (array $profile, string $country) => [
                'country' => $country,
                'currency' => $profile['currency'],
                'tax_rate' => $profile['tax_rate'],
                'label' => $country.' - '.$profile['currency'].' - '.$profile['tax_label'].' '.$profile['tax_rate'].'%',
            ])
            ->values()
            ->all();
    }

    public function looksLikeEuVatId(?string $vatId): bool
    {
        return EuVatId::looksValid($vatId);
    }
}
