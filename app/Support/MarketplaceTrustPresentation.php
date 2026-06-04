<?php

namespace App\Support;

use App\Models\MarketplaceProduct;

class MarketplaceTrustPresentation
{
    public static function forProduct(
        MarketplaceProduct $product,
        array $providerProfile,
        array $fulfillment,
        array $returnPolicy,
        array $reviewSummary,
        array $purchaseConfidence
    ): array {
        return [
            'version' => '2026-06-03.marketplace_trust.v1',
            'headline_state' => self::headlineState($purchaseConfidence),
            'trust_highlights' => self::trustHighlights($providerProfile, $fulfillment, $returnPolicy, $reviewSummary, $purchaseConfidence),
            'seller_profile_card' => self::sellerProfileCard($providerProfile),
            'size_guidance' => self::sizeGuidance($product),
            'delivery_promise' => self::deliveryPromise($product, $fulfillment),
            'returns_visibility' => self::returnsVisibility($returnPolicy, $fulfillment),
            'review_visibility' => self::reviewVisibility($reviewSummary),
            'mobile_sections' => ['seller', 'size_guidance', 'delivery', 'returns', 'reviews', 'buyer_protection'],
        ];
    }

    private static function headlineState(array $purchaseConfidence): array
    {
        $level = (string) ($purchaseConfidence['level'] ?? 'watch');

        return [
            'level' => $level,
            'score' => (int) ($purchaseConfidence['score'] ?? 0),
            'badge_key' => match ($level) {
                'excellent' => 'commerce.trust.badges.excellent',
                'ready' => 'commerce.trust.badges.ready',
                'risk' => 'commerce.trust.badges.risk',
                default => 'commerce.trust.badges.watch',
            },
        ];
    }

    private static function trustHighlights(array $providerProfile, array $fulfillment, array $returnPolicy, array $reviewSummary, array $purchaseConfidence): array
    {
        $signals = collect($purchaseConfidence['signals'] ?? [])->keyBy('key');

        return [
            [
                'key' => 'seller_verified',
                'state' => ($providerProfile['verified'] ?? false) ? 'strong' : 'watch',
                'label_key' => 'commerce.trust.highlights.seller_verified',
                'visible' => true,
            ],
            [
                'key' => 'delivery_time',
                'state' => $signals->get('delivery')['state'] ?? 'watch',
                'label_key' => 'commerce.trust.highlights.delivery_time',
                'visible' => true,
                'meta' => ['lead_time_days' => $fulfillment['lead_time_days'] ?? ['min' => null, 'max' => null]],
            ],
            [
                'key' => 'returns',
                'state' => ($returnPolicy['returns_available'] ?? false) ? 'strong' : 'watch',
                'label_key' => 'commerce.trust.highlights.returns',
                'visible' => true,
                'meta' => [
                    'window_days' => (int) ($returnPolicy['window_days'] ?? 0),
                    'dropoff_available' => (bool) ($fulfillment['returns_dropoff_available'] ?? false),
                ],
            ],
            [
                'key' => 'verified_reviews',
                'state' => ((int) ($reviewSummary['verified_purchase_count'] ?? 0)) > 0 ? 'strong' : 'watch',
                'label_key' => 'commerce.trust.highlights.verified_reviews',
                'visible' => true,
                'meta' => [
                    'rating_avg' => $reviewSummary['rating_avg'] ?? null,
                    'rating_count' => (int) ($reviewSummary['rating_count'] ?? 0),
                    'verified_purchase_count' => (int) ($reviewSummary['verified_purchase_count'] ?? 0),
                ],
            ],
        ];
    }

    private static function sellerProfileCard(array $providerProfile): array
    {
        return [
            'name' => $providerProfile['name'] ?? null,
            'type' => $providerProfile['type'] ?? null,
            'verified' => (bool) ($providerProfile['verified'] ?? false),
            'description' => $providerProfile['description'] ?? null,
            'support_email_visible' => filled($providerProfile['support_email'] ?? null),
            'phone_visible' => filled($providerProfile['phone'] ?? null),
            'website_visible' => filled($providerProfile['website'] ?? null),
            'primary_location' => $providerProfile['primary_location'] ?? null,
            'pickup_locations_count' => collect($providerProfile['locations'] ?? [])->where('pickup_enabled', true)->count(),
            'returns_locations_count' => collect($providerProfile['locations'] ?? [])->where('returns_enabled', true)->count(),
            'profile_url' => $providerProfile['url'] ?? null,
        ];
    }

    private static function sizeGuidance(MarketplaceProduct $product): array
    {
        $attributes = collect($product->product_attributes ?? []);
        $options = collect($product->attribute_options ?? []);
        $variants = collect($product->variants ?? []);
        $sizeOptions = collect([
            ...self::valuesForKeys($attributes, ['size', 'sizes', 'größe', 'größe']),
            ...self::valuesForKeys($options, ['size', 'sizes', 'größe', 'größe']),
            ...$variants->pluck('size')->filter()->all(),
            ...$variants->pluck('attributes.size')->filter()->all(),
        ])->flatten()->filter()->unique()->values();
        $sizeChart = $attributes->get('size_chart') ?? $attributes->get('sizes_chart') ?? $attributes->get('größentabelle');
        $fit = $attributes->get('fit') ?? $attributes->get('passform');

        return [
            'available' => $sizeOptions->isNotEmpty() || filled($sizeChart) || filled($fit),
            'size_options' => $sizeOptions->values()->all(),
            'size_chart' => $sizeChart,
            'fit_note' => $fit,
            'advice_key' => $sizeOptions->isNotEmpty() || filled($sizeChart)
                ? 'commerce.trust.size_guidance.available'
                : 'commerce.trust.size_guidance.ask_seller',
        ];
    }

    private static function deliveryPromise(MarketplaceProduct $product, array $fulfillment): array
    {
        $leadTime = $fulfillment['lead_time_days'] ?? ['min' => null, 'max' => null];

        return [
            'mode' => $fulfillment['delivery_mode'] ?? null,
            'digital_delivery' => (bool) ($fulfillment['digital_delivery'] ?? $product->isDigitalDelivery()),
            'shipping_available' => (bool) ($fulfillment['shipping_available'] ?? false),
            'pickup_available' => (bool) ($fulfillment['pickup_available'] ?? false),
            'lead_time_days' => $leadTime,
            'label_key' => self::deliveryLabelKey((string) ($fulfillment['delivery_mode'] ?? 'provider_arranged'), $leadTime),
            'pickup_locations' => $fulfillment['pickup_locations'] ?? [],
        ];
    }

    private static function returnsVisibility(array $returnPolicy, array $fulfillment): array
    {
        return [
            'returns_available' => (bool) ($returnPolicy['returns_available'] ?? false),
            'type' => $returnPolicy['type'] ?? null,
            'window_days' => (int) ($returnPolicy['window_days'] ?? 0),
            'dropoff_available' => (bool) ($fulfillment['returns_dropoff_available'] ?? false),
            'return_locations' => $fulfillment['return_locations'] ?? [],
            'label_key' => ($returnPolicy['returns_available'] ?? false)
                ? 'commerce.trust.returns.available'
                : 'commerce.trust.returns.limited',
        ];
    }

    private static function reviewVisibility(array $reviewSummary): array
    {
        return [
            'rating_avg' => $reviewSummary['rating_avg'] ?? null,
            'rating_count' => (int) ($reviewSummary['rating_count'] ?? 0),
            'verified_purchase_count' => (int) ($reviewSummary['verified_purchase_count'] ?? 0),
            'distribution' => $reviewSummary['rating_distribution'] ?? [],
            'label_key' => ((int) ($reviewSummary['verified_purchase_count'] ?? 0)) > 0
                ? 'commerce.trust.reviews.verified_available'
                : 'commerce.trust.reviews.needs_more_reviews',
        ];
    }

    private static function valuesForKeys($source, array $keys): array
    {
        return collect($keys)
            ->flatMap(fn (string $key) => collect([$source->get($key)]))
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();
    }

    private static function deliveryLabelKey(string $mode, array $leadTime): string
    {
        if ($mode === 'digital') {
            return 'commerce.trust.delivery.digital';
        }

        if (($leadTime['min'] ?? null) !== null || ($leadTime['max'] ?? null) !== null) {
            return 'commerce.trust.delivery.with_lead_time';
        }

        if ($mode === 'shipping_pickup') {
            return 'commerce.trust.delivery.shipping_pickup';
        }

        return 'commerce.trust.delivery.provider_arranged';
    }
}

