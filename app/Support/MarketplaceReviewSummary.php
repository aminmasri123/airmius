<?php

namespace App\Support;

use App\Models\MarketplaceProduct;

class MarketplaceReviewSummary
{
    public static function forProduct(MarketplaceProduct $product): array
    {
        $ratingAverage = $product->reviews_avg_rating ?? null;
        $ratingCount = $product->reviews_count ?? null;
        $verifiedCount = $product->verified_purchase_reviews_count ?? null;

        if ($ratingAverage === null) {
            $ratingAverage = $product->relationLoaded('publishedReviews')
                ? $product->publishedReviews->avg('rating')
                : $product->publishedReviews()->avg('rating');
        }

        if ($ratingCount === null) {
            $ratingCount = $product->relationLoaded('publishedReviews')
                ? $product->publishedReviews->count()
                : $product->publishedReviews()->count();
        }

        if ($verifiedCount === null) {
            $verifiedCount = $product->relationLoaded('publishedReviews')
                ? $product->publishedReviews->where('verified_purchase', true)->count()
                : $product->publishedReviews()->where('verified_purchase', true)->count();
        }

        return [
            'rating_avg' => $ratingAverage !== null ? round((float) $ratingAverage, 1) : null,
            'rating_count' => (int) $ratingCount,
            'verified_purchase_count' => (int) $verifiedCount,
            'rating_distribution' => self::ratingDistribution($product),
        ];
    }

    private static function ratingDistribution(MarketplaceProduct $product): array
    {
        return collect([5, 4, 3, 2, 1])
            ->map(function (int $rating) use ($product) {
                $attribute = "rating_{$rating}_count";

                return [
                    'rating' => $rating,
                    'count' => (int) (
                        $product->{$attribute}
                        ?? ($product->relationLoaded('publishedReviews')
                            ? $product->publishedReviews->where('rating', $rating)->count()
                            : $product->publishedReviews()->where('rating', $rating)->count())
                    ),
                ];
            })
            ->all();
    }
}
