<?php

namespace App\Support;

use App\Models\Club;
use App\Models\MarketplaceProduct;
use App\Models\MarketplaceProductInventory;
use App\Models\MarketplaceProviderLocation;
use App\Models\MarketplaceProviderProfile;
use App\Models\User;
use Illuminate\Support\Str;

class MarketplaceProviderSummary
{
    public static function forProduct(MarketplaceProduct $product, ?string $country = null): array
    {
        $club = $product->relationLoaded('club') ? $product->club : $product->club()->first();
        $user = $product->relationLoaded('user') ? $product->user : $product->user()->first();
        $profile = self::profileFor($club, $user);
        $name = $profile?->publicName() ?: ($club?->name ?: ($user?->name ?: 'Airmius Anbieter'));
        $locations = $profile ? self::publicLocations($profile, $country) : [];
        $verified = self::sellerVerified($product, $club, $user);
        $primaryLocation = collect($locations)->firstWhere('pickup_enabled', true) ?: collect($locations)->first();

        return [
            'name' => $name,
            'type' => $profile ? self::providerTypeLabel($profile, $club) : self::fallbackProviderType($product, $verified),
            'logo_url' => $profile?->logo_url ?: ($club?->logo ? UploadStorage::url($club->logo) : null),
            'initials' => Str::upper(Str::substr($name, 0, 2)),
            'location' => $profile?->publicAddressSummary() ?: self::fallbackLocation($club, $user),
            'verified' => $verified,
            'description' => $profile?->public_description,
            'support_email' => $profile?->show_support_email ? $profile->support_email : null,
            'phone' => $profile?->show_phone ? $profile->phone : null,
            'website' => $profile?->website,
            'locations' => $locations,
            'has_pickup' => collect($locations)->contains(fn (array $location) => (bool) $location['pickup_enabled']),
            'has_returns_location' => collect($locations)->contains(fn (array $location) => (bool) $location['returns_enabled']),
            'primary_location' => $primaryLocation,
            'url' => $club
                ? route('guest.marketplace.providers.show', ['type' => 'club', 'id' => $club->id])
                : ($user ? route('guest.marketplace.providers.show', ['type' => 'user', 'id' => $user->id]) : null),
        ];
    }

    public static function fulfillmentForProduct(MarketplaceProduct $product, ?string $country = null, ?array $provider = null): array
    {
        $country = strtoupper((string) $country);
        $provider ??= self::forProduct($product, $country ?: null);
        $locations = collect($provider['locations'] ?? []);
        $pickupLocations = $locations->where('pickup_enabled', true)->values();
        $returnLocations = $locations->where('returns_enabled', true)->values();
        $inventories = self::activeInventories($product, $country ?: null);
        $leadTimes = $inventories
            ->pluck('lead_time_days')
            ->filter(fn ($value) => $value !== null)
            ->map(fn ($value) => max(0, (int) $value))
            ->values();
        $sellableStock = (bool) $product->manages_stock
            ? $inventories->sum(fn (MarketplaceProductInventory $inventory) => $inventory->availableQuantity()) ?: $product->sellableStockForCountry($country ?: null)
            : null;

        return [
            'delivery_mode' => $product->isDigitalDelivery()
                ? 'digital'
                : (((bool) $product->is_shippable && $pickupLocations->isNotEmpty()) ? 'shipping_pickup' : ((bool) $product->is_shippable ? 'shipping' : 'provider_arranged')),
            'digital_delivery' => $product->isDigitalDelivery(),
            'shipping_available' => (bool) $product->is_shippable,
            'pickup_available' => $pickupLocations->isNotEmpty(),
            'returns_dropoff_available' => $returnLocations->isNotEmpty(),
            'pickup_locations_count' => $pickupLocations->count(),
            'return_locations_count' => $returnLocations->count(),
            'pickup_locations' => $pickupLocations->take(3)->values()->all(),
            'return_locations' => $returnLocations->take(3)->values()->all(),
            'lead_time_days' => [
                'min' => $leadTimes->isNotEmpty() ? $leadTimes->min() : null,
                'max' => $leadTimes->isNotEmpty() ? $leadTimes->max() : null,
            ],
            'sellable_stock' => $sellableStock === PHP_INT_MAX ? null : $sellableStock,
            'country' => $country ?: null,
        ];
    }

    public static function publicLocations(MarketplaceProviderProfile $profile, ?string $country = null): array
    {
        $country = strtoupper((string) $country);

        return $profile->locations()
            ->where('is_public', true)
            ->when($country !== '', fn ($query) => $query->orderByRaw('CASE WHEN UPPER(country) = ? THEN 0 ELSE 1 END', [$country]))
            ->orderByDesc('pickup_enabled')
            ->orderByDesc('returns_enabled')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit(6)
            ->get()
            ->map(fn (MarketplaceProviderLocation $location) => [
                'id' => $location->id,
                'name' => $location->name,
                'type' => $location->type,
                'address' => $location->addressSummary(),
                'city' => $location->city,
                'country' => strtoupper((string) $location->country),
                'opening_hours' => $location->opening_hours,
                'note' => $location->note,
                'phone' => $location->phone,
                'email' => $location->email,
                'image_url' => $location->image_url,
                'latitude' => $location->latitude !== null ? (float) $location->latitude : null,
                'longitude' => $location->longitude !== null ? (float) $location->longitude : null,
                'pickup_enabled' => (bool) $location->pickup_enabled,
                'returns_enabled' => (bool) $location->returns_enabled,
            ])
            ->all();
    }

    public static function profileFor(?Club $club, ?User $user): ?MarketplaceProviderProfile
    {
        return MarketplaceProviderProfile::query()
            ->with('locations')
            ->when($club, fn ($query) => $query->where('club_id', $club->id))
            ->when(! $club && $user, fn ($query) => $query->where('user_id', $user->id))
            ->first();
    }

    private static function activeInventories(MarketplaceProduct $product, ?string $country = null)
    {
        $country = strtoupper((string) $country);

        if ($product->relationLoaded('inventories')) {
            return $product->inventories
                ->where('is_active', true)
                ->when($country !== '', fn ($items) => $items->where('country_code', $country))
                ->values();
        }

        return $product->inventories()
            ->where('is_active', true)
            ->when($country !== '', fn ($query) => $query->where('country_code', $country))
            ->get();
    }

    private static function sellerVerified(MarketplaceProduct $product, ?Club $club, ?User $user): bool
    {
        if ($club?->verification_status === 'verified') {
            return true;
        }

        if (! $user) {
            return false;
        }

        return $user->relationLoaded('approvedSellerApplications')
            ? $user->approvedSellerApplications->isNotEmpty()
            : $user->approvedSellerApplications()->exists();
    }

    private static function providerTypeLabel(MarketplaceProviderProfile $profile, ?Club $club): string
    {
        if ($club?->verification_status === 'verified') {
            return 'Verifizierter Verein';
        }

        return match ($profile->provider_type) {
            'club' => 'Verein / Anbieter',
            'business' => 'Shop / Fachhändler',
            'trainer' => 'Trainer / Coach',
            default => 'Airmius Anbieter',
        };
    }

    private static function fallbackProviderType(MarketplaceProduct $product, bool $verified): string
    {
        if ($product->club_id) {
            return $verified ? 'Verifizierter Verein' : 'Verein / Anbieter';
        }

        return $verified ? 'Verifizierter Anbieter' : 'Airmius Anbieter';
    }

    private static function fallbackLocation(?Club $club, ?User $user): string
    {
        $location = trim(implode(', ', array_filter([
            $club?->city ?: $user?->city,
            strtoupper((string) ($club?->country ?: $user?->country ?: '')),
        ])));

        return $location ?: 'Online';
    }
}
