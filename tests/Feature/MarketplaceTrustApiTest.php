<?php

namespace Tests\Feature;

use App\Models\CommerceOrder;
use App\Models\CommerceWarehouse;
use App\Models\MarketplaceProduct;
use App\Models\MarketplaceProductInventory;
use App\Models\MarketplaceProductReview;
use App\Models\MarketplaceProviderProfile;
use App\Models\MarketplaceSellerApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MarketplaceTrustApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_buyer_can_review_product_and_product_summary_updates(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $product = $this->publishedProduct($seller, [
            'return_policy_type' => 'standard',
            'return_window_days' => 30,
            'manages_stock' => true,
            'stock_quantity' => 7,
        ]);

        MarketplaceSellerApplication::query()->create([
            'user_id' => $seller->id,
            'applicant_type' => 'private',
            'accepted_rules' => ['seller_terms'],
            'status' => 'approved',
        ]);

        $order = CommerceOrder::query()->create([
            'user_id' => $buyer->id,
            'orderable_type' => MarketplaceProduct::class,
            'orderable_id' => $product->id,
            'type' => 'marketplace_product',
            'provider' => 'bank_transfer',
            'amount_cents' => 4900,
            'currency' => 'EUR',
            'status' => 'completed',
            'completed_at' => now(),
        ]);
        $order->items()->create([
            'orderable_type' => MarketplaceProduct::class,
            'orderable_id' => $product->id,
            'title' => $product->title,
            'quantity' => 1,
            'unit_gross_cents' => 4900,
            'total_cents' => 4900,
            'currency' => 'EUR',
            'is_shippable' => false,
        ]);

        Sanctum::actingAs($buyer);

        $this->postJson("/api/v1/commerce/products/{$product->id}/reviews", [
            'rating' => 5,
            'title' => 'Sehr sauberer Kurs',
            'body' => 'Der Inhalt war direkt nach der Zahlung verfuegbar.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.rating', 5)
            ->assertJsonPath('data.verified_purchase', true);

        $this->getJson("/api/v1/commerce/products/{$product->id}?country=DE")
            ->assertOk()
            ->assertJsonPath('data.review_summary.rating_avg', 5)
            ->assertJsonPath('data.review_summary.rating_count', 1)
            ->assertJsonPath('data.review_summary.verified_purchase_count', 1)
            ->assertJsonPath('data.review_summary.rating_distribution.0.rating', 5)
            ->assertJsonPath('data.review_summary.rating_distribution.0.count', 1)
            ->assertJsonPath('data.review_summary.rating_distribution.1.rating', 4)
            ->assertJsonPath('data.review_summary.rating_distribution.1.count', 0)
            ->assertJsonPath('data.return_policy.window_days', 30)
            ->assertJsonPath('data.availability.is_available', true)
            ->assertJsonPath('data.availability.sellable_stock', 7)
            ->assertJsonPath('data.seller_trust.seller_verified', true)
            ->assertJsonPath('data.purchase_confidence.score', 100)
            ->assertJsonPath('data.purchase_confidence.level', 'excellent')
            ->assertJsonPath('data.purchase_confidence.signals.0.key', 'seller')
            ->assertJsonPath('data.purchase_confidence.signals.0.state', 'strong')
            ->assertJsonPath('data.purchase_confidence.signals.5.key', 'reviews')
            ->assertJsonPath('data.purchase_confidence.signals.5.meta.verified_purchase_count', 1);
    }

    public function test_non_buyer_cannot_review_product(): void
    {
        $seller = User::factory()->create();
        $visitor = User::factory()->create();
        $product = $this->publishedProduct($seller);

        Sanctum::actingAs($visitor);

        $this->postJson("/api/v1/commerce/products/{$product->id}/reviews", [
            'rating' => 4,
        ])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'forbidden');
    }

    public function test_review_listing_only_returns_published_reviews(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create(['name' => 'Review Buyer']);
        $product = $this->publishedProduct($seller);

        MarketplaceProductReview::query()->create([
            'marketplace_product_id' => $product->id,
            'user_id' => $buyer->id,
            'rating' => 4,
            'title' => 'Hilfreich',
            'status' => 'published',
            'verified_purchase' => true,
        ]);
        MarketplaceProductReview::query()->create([
            'marketplace_product_id' => $product->id,
            'user_id' => User::factory()->create()->id,
            'rating' => 1,
            'title' => 'Moderiert',
            'status' => 'hidden',
            'verified_purchase' => false,
        ]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v1/commerce/products/{$product->id}/reviews")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.rating', 4)
            ->assertJsonPath('data.0.author.user_card.display_name', 'Review Buyer');
    }

    public function test_product_review_summary_exposes_rating_distribution(): void
    {
        $seller = User::factory()->create();
        $product = $this->publishedProduct($seller);

        foreach ([5, 5, 4, 2] as $rating) {
            MarketplaceProductReview::query()->create([
                'marketplace_product_id' => $product->id,
                'user_id' => User::factory()->create()->id,
                'rating' => $rating,
                'status' => 'published',
                'verified_purchase' => true,
            ]);
        }

        MarketplaceProductReview::query()->create([
            'marketplace_product_id' => $product->id,
            'user_id' => User::factory()->create()->id,
            'rating' => 1,
            'status' => 'hidden',
            'verified_purchase' => false,
        ]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v1/commerce/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.review_summary.rating_avg', 4)
            ->assertJsonPath('data.review_summary.rating_count', 4)
            ->assertJsonPath('data.review_summary.rating_distribution.0.rating', 5)
            ->assertJsonPath('data.review_summary.rating_distribution.0.count', 2)
            ->assertJsonPath('data.review_summary.rating_distribution.1.rating', 4)
            ->assertJsonPath('data.review_summary.rating_distribution.1.count', 1)
            ->assertJsonPath('data.review_summary.rating_distribution.2.rating', 3)
            ->assertJsonPath('data.review_summary.rating_distribution.2.count', 0)
            ->assertJsonPath('data.review_summary.rating_distribution.3.rating', 2)
            ->assertJsonPath('data.review_summary.rating_distribution.3.count', 1)
            ->assertJsonPath('data.review_summary.rating_distribution.4.rating', 1)
            ->assertJsonPath('data.review_summary.rating_distribution.4.count', 0);
    }

    public function test_user_can_manage_product_wishlist(): void
    {
        $seller = User::factory()->create();
        $user = User::factory()->create();
        $product = $this->publishedProduct($seller);

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/commerce/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.viewer.is_wishlisted', false);

        $this->postJson("/api/v1/commerce/products/{$product->id}/wishlist")
            ->assertCreated()
            ->assertJsonPath('data.marketplace_product_id', $product->id)
            ->assertJsonPath('data.is_wishlisted', true)
            ->assertJsonPath('data.wishlist_count', 1);

        $this->assertDatabaseHas('marketplace_product_wishlists', [
            'user_id' => $user->id,
            'marketplace_product_id' => $product->id,
        ]);

        $this->getJson("/api/v1/commerce/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.viewer.is_wishlisted', true)
            ->assertJsonPath('data.wishlist_summary.count', 1);

        $this->getJson('/api/v1/commerce/wishlist')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $product->id)
            ->assertJsonPath('data.0.viewer.is_wishlisted', true);

        $this->deleteJson("/api/v1/commerce/products/{$product->id}/wishlist")
            ->assertOk()
            ->assertJsonPath('data.is_wishlisted', false)
            ->assertJsonPath('data.wishlist_count', 0);

        $this->assertDatabaseMissing('marketplace_product_wishlists', [
            'user_id' => $user->id,
            'marketplace_product_id' => $product->id,
        ]);
    }

    public function test_user_cannot_wishlist_unapproved_product(): void
    {
        $seller = User::factory()->create();
        $user = User::factory()->create();
        $product = $this->publishedProduct($seller, [
            'moderation_status' => 'pending',
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/commerce/products/{$product->id}/wishlist")
            ->assertNotFound();

        $this->assertDatabaseMissing('marketplace_product_wishlists', [
            'user_id' => $user->id,
            'marketplace_product_id' => $product->id,
        ]);
    }

    public function test_mobile_product_contract_exposes_provider_locations_pickup_returns_and_fulfillment(): void
    {
        $seller = User::factory()->create(['name' => 'Run Shop Owner']);
        $buyer = User::factory()->create();
        MarketplaceSellerApplication::query()->create([
            'user_id' => $seller->id,
            'applicant_type' => 'business',
            'accepted_rules' => ['seller_terms'],
            'status' => 'approved',
        ]);
        $profile = MarketplaceProviderProfile::query()->create([
            'user_id' => $seller->id,
            'display_name' => 'Airmius Run Shop',
            'provider_type' => 'business',
            'support_email' => 'support@example.test',
            'show_support_email' => true,
            'legal_country' => 'DE',
            'legal_city' => 'Berlin',
            'show_public_address' => false,
            'status' => 'active',
        ]);
        $profile->locations()->create([
            'name' => 'Berlin Mitte Store',
            'type' => 'store',
            'country' => 'DE',
            'city' => 'Berlin',
            'postal_code' => '10115',
            'street' => 'Invalidenstrasse',
            'house_number' => '1',
            'opening_hours' => 'Mo-Fr 10-19',
            'pickup_enabled' => true,
            'returns_enabled' => true,
            'is_public' => true,
        ]);
        $profile->locations()->create([
            'name' => 'Backoffice',
            'type' => 'office',
            'country' => 'DE',
            'city' => 'Berlin',
            'pickup_enabled' => true,
            'returns_enabled' => true,
            'is_public' => false,
        ]);

        $product = $this->publishedProduct($seller, [
            'title' => 'Mobile Pickup Shoe',
            'category' => 'product',
            'offer_type' => 'physical_product',
            'product_type' => 'physical',
            'is_shippable' => true,
            'manages_stock' => true,
            'stock_quantity' => 0,
            'return_policy_type' => 'standard',
            'return_window_days' => 30,
        ]);
        $warehouse = CommerceWarehouse::query()->create([
            'user_id' => $seller->id,
            'name' => 'Berlin Warehouse',
            'country_code' => 'DE',
            'city' => 'Berlin',
            'is_active' => true,
        ]);
        MarketplaceProductInventory::query()->create([
            'marketplace_product_id' => $product->id,
            'commerce_warehouse_id' => $warehouse->id,
            'country_code' => 'DE',
            'stock_quantity' => 8,
            'reserved_quantity' => 2,
            'low_stock_threshold' => 2,
            'lead_time_days' => 2,
            'is_active' => true,
        ]);

        Sanctum::actingAs($buyer);

        $this->getJson("/api/v1/commerce/products/{$product->id}?country=DE")
            ->assertOk()
            ->assertJsonPath('data.provider_profile.name', 'Airmius Run Shop')
            ->assertJsonPath('data.provider_profile.type', 'Shop / Fachhaendler')
            ->assertJsonPath('data.provider_profile.verified', true)
            ->assertJsonPath('data.provider_profile.support_email', 'support@example.test')
            ->assertJsonPath('data.provider_profile.has_pickup', true)
            ->assertJsonPath('data.provider_profile.has_returns_location', true)
            ->assertJsonPath('data.provider_profile.locations.0.name', 'Berlin Mitte Store')
            ->assertJsonPath('data.provider_profile.locations.0.pickup_enabled', true)
            ->assertJsonMissingPath('data.provider_profile.locations.1.name')
            ->assertJsonPath('data.fulfillment.delivery_mode', 'shipping_pickup')
            ->assertJsonPath('data.fulfillment.pickup_available', true)
            ->assertJsonPath('data.fulfillment.returns_dropoff_available', true)
            ->assertJsonPath('data.fulfillment.pickup_locations_count', 1)
            ->assertJsonPath('data.fulfillment.return_locations_count', 1)
            ->assertJsonPath('data.fulfillment.lead_time_days.min', 2)
            ->assertJsonPath('data.fulfillment.sellable_stock', 6)
            ->assertJsonPath('data.availability.delivery_mode', 'shipping_pickup')
            ->assertJsonPath('data.availability.pickup_available', true)
            ->assertJsonPath('data.availability.returns_dropoff_available', true)
            ->assertJsonPath('data.availability.lead_time_days.min', 2)
            ->assertJsonPath('data.purchase_confidence.signals.3.key', 'delivery')
            ->assertJsonPath('data.purchase_confidence.signals.3.state', 'strong')
            ->assertJsonPath('data.purchase_confidence.signals.3.meta.mode', 'shipping_pickup');
    }

    public function test_product_trust_presentation_makes_seller_size_delivery_returns_and_reviews_visible(): void
    {
        $seller = User::factory()->create(['name' => 'Trust Shop Owner']);
        $buyer = User::factory()->create();
        MarketplaceSellerApplication::query()->create([
            'user_id' => $seller->id,
            'applicant_type' => 'business',
            'accepted_rules' => ['seller_terms'],
            'status' => 'approved',
        ]);
        $profile = MarketplaceProviderProfile::query()->create([
            'user_id' => $seller->id,
            'display_name' => 'Airmius Trust Shop',
            'provider_type' => 'business',
            'support_email' => 'help@example.test',
            'show_support_email' => true,
            'status' => 'active',
        ]);
        $profile->locations()->create([
            'name' => 'Trust Store',
            'type' => 'store',
            'country' => 'DE',
            'city' => 'Koeln',
            'pickup_enabled' => true,
            'returns_enabled' => true,
            'is_public' => true,
        ]);

        $product = $this->publishedProduct($seller, [
            'title' => 'Airmius Match Shirt',
            'category' => 'product',
            'offer_type' => 'physical_product',
            'product_type' => 'physical',
            'is_shippable' => true,
            'manages_stock' => true,
            'stock_quantity' => 0,
            'return_policy_type' => 'standard',
            'return_window_days' => 30,
            'product_attributes' => [
                'sizes' => ['S', 'M', 'L'],
                'fit' => 'regular',
                'size_chart' => 'S=46, M=50, L=54',
            ],
            'attribute_options' => [
                'color' => ['black', 'green'],
            ],
        ]);
        $warehouse = CommerceWarehouse::query()->create([
            'user_id' => $seller->id,
            'name' => 'Trust Warehouse',
            'country_code' => 'DE',
            'city' => 'Koeln',
            'is_active' => true,
        ]);
        MarketplaceProductInventory::query()->create([
            'marketplace_product_id' => $product->id,
            'commerce_warehouse_id' => $warehouse->id,
            'country_code' => 'DE',
            'stock_quantity' => 5,
            'reserved_quantity' => 1,
            'lead_time_days' => 3,
            'is_active' => true,
        ]);
        MarketplaceProductReview::query()->create([
            'marketplace_product_id' => $product->id,
            'user_id' => $buyer->id,
            'rating' => 5,
            'status' => 'published',
            'verified_purchase' => true,
        ]);

        Sanctum::actingAs($buyer);

        $this->getJson("/api/v1/commerce/products/{$product->id}?country=DE")
            ->assertOk()
            ->assertJsonPath('data.trust_presentation.version', '2026-06-03.marketplace_trust.v1')
            ->assertJsonPath('data.trust_presentation.headline_state.level', 'excellent')
            ->assertJsonPath('data.trust_presentation.trust_highlights.0.key', 'seller_verified')
            ->assertJsonPath('data.trust_presentation.trust_highlights.0.state', 'strong')
            ->assertJsonPath('data.trust_presentation.seller_profile_card.name', 'Airmius Trust Shop')
            ->assertJsonPath('data.trust_presentation.seller_profile_card.verified', true)
            ->assertJsonPath('data.trust_presentation.seller_profile_card.support_email_visible', true)
            ->assertJsonPath('data.trust_presentation.seller_profile_card.pickup_locations_count', 1)
            ->assertJsonPath('data.trust_presentation.size_guidance.available', true)
            ->assertJsonPath('data.trust_presentation.size_guidance.size_options.1', 'M')
            ->assertJsonPath('data.trust_presentation.size_guidance.fit_note', 'regular')
            ->assertJsonPath('data.trust_presentation.delivery_promise.mode', 'shipping_pickup')
            ->assertJsonPath('data.trust_presentation.delivery_promise.lead_time_days.min', 3)
            ->assertJsonPath('data.trust_presentation.returns_visibility.returns_available', true)
            ->assertJsonPath('data.trust_presentation.returns_visibility.dropoff_available', true)
            ->assertJsonPath('data.trust_presentation.review_visibility.verified_purchase_count', 1)
            ->assertJsonPath('data.trust_presentation.mobile_sections.1', 'size_guidance');
    }

    private function publishedProduct(User $seller, array $overrides = []): MarketplaceProduct
    {
        return MarketplaceProduct::query()->create(array_merge([
            'user_id' => $seller->id,
            'title' => 'Airmius Sprintkurs',
            'description' => 'Ein Kurs fuer bessere Beschleunigung.',
            'category' => 'course',
            'offer_type' => 'online_course',
            'product_type' => 'digital',
            'is_shippable' => false,
            'manages_stock' => false,
            'price_cents' => 4900,
            'currency' => 'EUR',
            'status' => 'published',
            'moderation_status' => 'approved',
            'commission_percent' => 10,
            'payout_status' => 'pending_sales',
        ], $overrides));
    }
}
