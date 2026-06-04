<?php

namespace App\Support;

use App\Models\MarketplaceProduct;

class MarketplacePurchaseConfidence
{
    public static function forProduct(MarketplaceProduct $product, array $context = []): array
    {
        $reviewSummary = $context['review_summary'] ?? MarketplaceReviewSummary::forProduct($product);
        $returnPolicy = $context['return_policy'] ?? self::returnPolicy($product);
        $sellerVerified = (bool) ($context['seller_verified'] ?? self::sellerVerified($product));
        $country = strtoupper((string) ($context['country'] ?? ''));
        $stock = $context['sellable_stock'] ?? ((bool) $product->manages_stock ? $product->sellableStockForCountry($country ?: null) : null);
        $available = (bool) ($context['is_available'] ?? $product->isAvailableForCountry($country ?: null));
        $hasPickup = (bool) ($context['has_pickup'] ?? false);

        $signals = [
            self::signal(
                'seller',
                $sellerVerified ? 'strong' : 'watch',
                20,
                $sellerVerified ? 20 : 11,
                ['verified' => $sellerVerified],
            ),
            self::signal(
                'moderation',
                $product->moderation_status === 'approved' ? 'strong' : 'risk',
                15,
                $product->moderation_status === 'approved' ? 15 : 3,
                ['status' => $product->moderation_status],
            ),
            self::stockSignal($product, $available, $stock),
            self::deliverySignal($product, $hasPickup),
            self::signal(
                'returns',
                ($returnPolicy['returns_available'] ?? false) ? 'strong' : 'watch',
                15,
                ($returnPolicy['returns_available'] ?? false) ? 15 : 8,
                [
                    'returns_available' => (bool) ($returnPolicy['returns_available'] ?? false),
                    'window_days' => (int) ($returnPolicy['window_days'] ?? 0),
                    'type' => $returnPolicy['type'] ?? null,
                ],
            ),
            self::reviewSignal($reviewSummary),
        ];

        $score = (int) round(collect($signals)->sum('points'));

        return [
            'score' => max(0, min(100, $score)),
            'level' => self::level($score),
            'signals' => $signals,
            'blockers' => collect($signals)
                ->filter(fn (array $signal) => $signal['state'] === 'risk')
                ->pluck('key')
                ->values()
                ->all(),
        ];
    }

    private static function stockSignal(MarketplaceProduct $product, bool $available, ?int $stock): array
    {
        if ($product->isDigitalDelivery()) {
            return self::signal('stock', 'strong', 20, 20, ['digital_delivery' => true, 'sellable_stock' => null]);
        }

        if (! $available) {
            return self::signal('stock', 'risk', 20, 4, ['digital_delivery' => false, 'sellable_stock' => max(0, (int) $stock)]);
        }

        if (! (bool) $product->manages_stock) {
            return self::signal('stock', 'ready', 20, 16, ['digital_delivery' => false, 'sellable_stock' => null]);
        }

        $quantity = max(0, (int) $stock);

        return self::signal(
            'stock',
            $quantity >= 5 ? 'strong' : 'ready',
            20,
            $quantity >= 5 ? 20 : 16,
            ['digital_delivery' => false, 'sellable_stock' => $quantity],
        );
    }

    private static function deliverySignal(MarketplaceProduct $product, bool $hasPickup): array
    {
        if ($product->isDigitalDelivery()) {
            return self::signal('delivery', 'strong', 15, 15, ['mode' => 'digital']);
        }

        if ((bool) $product->is_shippable && $hasPickup) {
            return self::signal('delivery', 'strong', 15, 15, ['mode' => 'shipping_pickup']);
        }

        if ((bool) $product->is_shippable) {
            return self::signal('delivery', 'ready', 15, 12, ['mode' => 'shipping']);
        }

        return self::signal('delivery', 'watch', 15, 8, ['mode' => 'provider_arranged']);
    }

    private static function reviewSignal(array $reviewSummary): array
    {
        $ratingCount = (int) ($reviewSummary['rating_count'] ?? 0);
        $verifiedCount = (int) ($reviewSummary['verified_purchase_count'] ?? 0);

        if ($verifiedCount > 0) {
            return self::signal('reviews', 'strong', 15, 15, [
                'rating_count' => $ratingCount,
                'verified_purchase_count' => $verifiedCount,
                'rating_avg' => $reviewSummary['rating_avg'] ?? null,
            ]);
        }

        if ($ratingCount > 0) {
            return self::signal('reviews', 'ready', 15, 12, [
                'rating_count' => $ratingCount,
                'verified_purchase_count' => 0,
                'rating_avg' => $reviewSummary['rating_avg'] ?? null,
            ]);
        }

        return self::signal('reviews', 'watch', 15, 8, [
            'rating_count' => 0,
            'verified_purchase_count' => 0,
            'rating_avg' => null,
        ]);
    }

    private static function signal(string $key, string $state, int $weight, int $points, array $meta = []): array
    {
        return [
            'key' => $key,
            'state' => $state,
            'weight' => $weight,
            'points' => max(0, min($weight, $points)),
            'meta' => $meta,
        ];
    }

    private static function level(int $score): string
    {
        return match (true) {
            $score >= 85 => 'excellent',
            $score >= 70 => 'ready',
            $score >= 55 => 'watch',
            default => 'risk',
        };
    }

    private static function returnPolicy(MarketplaceProduct $product): array
    {
        $type = (string) ($product->return_policy_type ?: ($product->isDigitalDelivery() ? 'digital' : 'standard'));
        $windowDays = (int) ($product->return_window_days ?? ($product->isDigitalDelivery() ? 0 : 14));

        return [
            'type' => $type,
            'window_days' => $windowDays,
            'returns_available' => $windowDays > 0 && ! in_array($type, ['digital', 'service'], true),
        ];
    }

    private static function sellerVerified(MarketplaceProduct $product): bool
    {
        if ($product->relationLoaded('club') && $product->club?->verification_status === 'verified') {
            return true;
        }

        if (! $product->relationLoaded('user') || ! $product->user) {
            return false;
        }

        return $product->user->relationLoaded('approvedSellerApplications')
            ? $product->user->approvedSellerApplications->isNotEmpty()
            : $product->user->approvedSellerApplications()->exists();
    }
}
