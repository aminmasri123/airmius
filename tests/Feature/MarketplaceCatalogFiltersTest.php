<?php

namespace Tests\Feature;

use App\Models\MarketplaceProduct;
use App\Models\MarketplaceProductReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MarketplaceCatalogFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_filters_combine_before_pagination_and_ignore_unpublished_ratings_and_products(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $match = $this->product('equipment', 2500);
        $this->review($match, 4);
        $this->review($match, 5);
        $this->review($match, 1, 'hidden');
        $low = $this->product('equipment', 3000);
        $this->review($low, 2);
        $this->review($low, 5, 'hidden');
        $this->product('equipment', 2200); // No reviews must not pass a minimum rating.
        $expensive = $this->product('equipment', 9000);
        $this->review($expensive, 5);
        $other = $this->product('course', 2000);
        $this->review($other, 5);
        $hidden = $this->product('secret-category', 2000, ['status' => 'draft']);
        $this->review($hidden, 5);

        $this->getJson('/api/v1/commerce/products?category=equipment&min_price_cents=2000&max_price_cents=5000&min_rating=4.5&sort=price_asc&per_page=1')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $match->id)
            ->assertJsonPath('data.0.review_summary.rating_avg', 4.5)
            ->assertJsonPath('meta.total', 1)->assertJsonPath('meta.filters.categories', ['course', 'equipment']);
    }

    public function test_price_sorting_is_global_stable_and_preserved_on_following_pages(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $high = $this->product('equipment', 9000);
        $low = $this->product('equipment', 1000);
        $tie1 = $this->product('equipment', 4000);
        $tie2 = $this->product('equipment', 4000);
        $this->getJson('/api/v1/commerce/products?sort=price_asc&per_page=2')
            ->assertOk()->assertJsonPath('data.0.id', $low->id)->assertJsonPath('data.1.id', $tie1->id)
            ->assertJsonPath('meta.total', 4)->assertJsonPath('meta.last_page', 2);
        $this->getJson('/api/v1/commerce/products?sort=price_asc&per_page=2&page=2')
            ->assertOk()->assertJsonPath('data.0.id', $tie2->id)->assertJsonPath('data.1.id', $high->id);
        $this->getJson('/api/v1/commerce/products?sort=price_desc&per_page=2')
            ->assertOk()->assertJsonPath('data.0.id', $high->id)->assertJsonPath('data.1.id', $tie1->id);
    }

    public function test_zero_price_search_and_validation_are_applied_on_the_server(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $free = $this->product('course', 0, ['title' => 'Sprint Workshop']);
        $this->product('course', 1000, ['title' => 'Sprint Workshop']);
        $this->product('equipment', 0, ['title' => 'Ball']);
        $this->getJson('/api/v1/commerce/products?search=Sprint&max_price_cents=0')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $free->id);
        foreach ([
            ['min_price_cents' => -1], ['max_price_cents' => 100, 'min_price_cents' => 200],
            ['min_rating' => 6], ['min_rating' => 0], ['sort' => 'unsupported'],
        ] as $filters) {
            $this->getJson('/api/v1/commerce/products?'.http_build_query($filters))->assertUnprocessable();
        }
    }

    public function test_taxonomy_filters_keep_legacy_products_and_hide_unpublished_subcategories(): void
    {
        $legacy = $this->product('product', 2000, ['manages_stock' => true, 'stock_quantity' => 5]);
        $shorts = $this->product('product', 2500, [
            'manages_stock' => true, 'stock_quantity' => 5,
            'catalog_group' => 'apparel', 'catalog_subcategory' => 'shorts',
            'sports' => ['running', 'fitness'],
        ]);
        $this->product('product', 2000, [
            'manages_stock' => true, 'stock_quantity' => 5,
            'catalog_group' => 'apparel', 'catalog_subcategory' => 'pants_leggings',
            'sports' => ['running'], 'status' => 'draft',
        ]);

        $this->getJson('/api/v1/public/marketplace')
            ->assertOk()->assertJsonPath('meta.total', 2)->assertJsonFragment(['id' => $legacy->id])
            ->assertJsonPath('meta.taxonomy.groups.apparel.children', ['shorts' => 'Shorts']);

        Sanctum::actingAs(User::factory()->create());
        foreach (['public/marketplace', 'commerce/products'] as $endpoint) {
            foreach (['running', 'fitness'] as $sport) {
                $this->getJson("/api/v1/{$endpoint}?catalog_group=apparel&catalog_subcategory=shorts&sport={$sport}")
                    ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $shorts->id);
            }
            $this->getJson("/api/v1/{$endpoint}?catalog_group=shoes&catalog_subcategory=shorts")
                ->assertUnprocessable()->assertJsonValidationErrors('catalog_subcategory');
        }
    }

    private function product(string $category, int $price, array $overrides = []): MarketplaceProduct
    {
        return MarketplaceProduct::query()->create(array_merge([
            'user_id' => User::factory()->create()->id, 'title' => 'Training equipment', 'description' => 'For training',
            'category' => $category, 'price_cents' => $price, 'currency' => 'EUR',
            'status' => 'published', 'moderation_status' => 'approved',
        ], $overrides));
    }

    private function review(MarketplaceProduct $product, int $rating, string $status = 'published'): void
    {
        MarketplaceProductReview::query()->create([
            'marketplace_product_id' => $product->id, 'user_id' => User::factory()->create()->id,
            'rating' => $rating, 'status' => $status, 'verified_purchase' => true,
        ]);
    }
}
