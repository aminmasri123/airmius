<?php

namespace App\Services;

use App\Models\User;
use App\Support\Roles;

class NutritionPremiumFeatureService
{
    public function capabilities(?User $user): array
    {
        $available = $user ? $this->canViewMicronutrients($user) : false;

        return [
            'micronutrients' => [
                'available' => $available,
                'requires_premium' => ! $available,
                'access_reason' => $available
                    ? null
                    : 'Vitamine und Mineralstoffe sind ab dem ersten Premium-Modell für Sportler, Coaches und Vereine verfügbar.',
            ],
        ];
    }

    public function canViewMicronutrients(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->hasAnyRole(Roles::FULL_ACCESS)) {
            return true;
        }

        $hasPaidUserPlan = $user->subscriptions()
            ->grantingAccess()
            ->whereHas('plan', fn ($query) => $query
                ->where(function ($priceQuery): void {
                    $priceQuery
                        ->where('monthly_price_cents', '>', 0)
                        ->orWhere('yearly_price_cents', '>', 0);
                }))
            ->exists();

        if ($hasPaidUserPlan) {
            return true;
        }

        return $user->clubs()
            ->whereHas('currentSubscription', fn ($query) => $query
                ->grantingAccess()
                ->whereHas('plan', fn ($planQuery) => $planQuery
                    ->where(function ($priceQuery): void {
                        $priceQuery
                            ->where('monthly_price_cents', '>', 0)
                            ->orWhere('yearly_price_cents', '>', 0);
                    })))
            ->exists();
    }

    public function filterProduct(array $product, ?User $user): array
    {
        if ($this->canViewMicronutrients($user)) {
            return $product;
        }

        unset($product['micronutrients']);

        return $product;
    }

    public function filterItems(array $items, ?User $user): array
    {
        if ($this->canViewMicronutrients($user)) {
            return $items;
        }

        return collect($items)
            ->map(function ($item) {
                if (is_array($item)) {
                    unset($item['micronutrients']);
                }

                return $item;
            })
            ->values()
            ->all();
    }
}
