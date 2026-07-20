<?php

namespace App\Http\Resources\Api\V1;

use App\Support\MarketplaceProviderSummary;
use App\Support\MarketplacePurchaseConfidence;
use App\Support\MarketplaceReviewSummary;
use App\Support\MarketplaceTrustPresentation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MarketplaceProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $product = $this->resource;
        $country = strtoupper((string) $request->query('country', ''));
        $product->loadMissing(['user.approvedSellerApplications', 'club', 'inventories']);

        $providerProfile = MarketplaceProviderSummary::forProduct($product, $country ?: null);
        $fulfillment = MarketplaceProviderSummary::fulfillmentForProduct($product, $country ?: null, $providerProfile);
        $reviewSummary = MarketplaceReviewSummary::forProduct($product);
        $returnPolicy = $this->returnPolicy();
        $availability = $this->availability($country, $fulfillment);
        $sellerTrust = [
            'seller_verified' => (bool) ($providerProfile['verified'] ?? false),
        ];
        $purchaseConfidence = MarketplacePurchaseConfidence::forProduct($product, [
            'review_summary' => $reviewSummary,
            'return_policy' => $returnPolicy,
            'seller_verified' => $sellerTrust['seller_verified'],
            'country' => $country,
            'sellable_stock' => $fulfillment['sellable_stock'] ?? null,
            'is_available' => $availability['is_available'],
            'has_pickup' => (bool) ($providerProfile['has_pickup'] ?? false),
        ]);
        $wishlistCount = $product->relationLoaded('wishlists')
            ? $product->wishlists->count()
            : $product->wishlists()->count();
        $isWishlisted = $request->user()
            ? $product->wishlists()->where('user_id', $request->user()->id)->exists()
            : false;

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'club_id' => $this->club_id,
            'title' => $this->title,
            'description' => $this->description,
            'features' => $this->features,
            'product_attributes' => $this->product_attributes,
            'attribute_options' => $this->attribute_options,
            'variants' => $this->variants,
            'image_url' => $this->image_url,
            'gallery_images' => $this->gallery_images,
            'category' => $this->category,
            'offer_type' => $this->offer_type,
            'product_type' => $this->product_type,
            'sku' => $this->sku,
            'is_shippable' => $this->is_shippable,
            'manages_stock' => $this->manages_stock,
            'stock_quantity' => $this->stock_quantity,
            'low_stock_threshold' => $this->low_stock_threshold,
            'price_cents' => $this->price_cents,
            'currency' => $this->currency,
            'available_countries' => $this->available_countries,
            'status' => $this->status,
            'moderation_status' => $this->moderation_status,
            'commission_percent' => $this->commission_percent,
            'seller' => new UserResource($this->whenLoaded('user')),
            'club' => new ClubResource($this->whenLoaded('club')),
            'review_summary' => $reviewSummary,
            'return_policy' => $returnPolicy,
            'availability' => $availability,
            'seller_trust' => $sellerTrust,
            'purchase_confidence' => $purchaseConfidence,
            'provider_profile' => $providerProfile,
            'fulfillment' => $fulfillment,
            'trust_presentation' => MarketplaceTrustPresentation::forProduct($product, $providerProfile, $fulfillment, $returnPolicy, $reviewSummary, $purchaseConfidence),
            'wishlist_summary' => [
                'count' => $wishlistCount,
            ],
            'viewer' => [
                'is_wishlisted' => $isWishlisted,
            ],
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }

    private function returnPolicy(): array
    {
        $type = (string) ($this->return_policy_type ?: ($this->isDigitalDelivery() ? 'digital' : 'standard'));
        $windowDays = (int) ($this->return_window_days ?? ($this->isDigitalDelivery() ? 0 : 14));

        return [
            'type' => $type,
            'window_days' => $windowDays,
            'returns_available' => $windowDays > 0 && ! in_array($type, ['digital', 'service'], true),
        ];
    }

    private function availability(string $country, array $fulfillment): array
    {
        return [
            'is_available' => $this->isAvailableForCountry($country ?: null),
            'sellable_stock' => $fulfillment['sellable_stock'] ?? null,
            'delivery_mode' => $fulfillment['delivery_mode'] ?? null,
            'pickup_available' => (bool) ($fulfillment['pickup_available'] ?? false),
            'returns_dropoff_available' => (bool) ($fulfillment['returns_dropoff_available'] ?? false),
            'lead_time_days' => $fulfillment['lead_time_days'] ?? ['min' => null, 'max' => null],
            'country' => $country ?: null,
        ];
    }
}