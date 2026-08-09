<?php

namespace Tests\Feature;

use App\Models\LearningCourse;
use App\Models\MarketplaceProduct;
use App\Models\OutfitSubscriptionPlan;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicCommerceCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_catalog_unifies_three_offer_kinds_without_private_fields(): void
    {
        [$product, $course, $plan] = $this->createCatalogFixtures();

        $response = $this->getJson(route('api.v1.public.commerce.catalog'))
            ->assertOk()
            ->assertJsonPath('meta.contract', 'commerce-card.v1')
            ->assertJsonPath('meta.returned', 3)
            ->assertJsonPath('data.0.key', "product:{$product->id}")
            ->assertJsonPath('data.1.key', "course:{$course->id}")
            ->assertJsonPath('data.2.key', "outfit_subscription:{$plan->id}")
            ->assertJsonPath('data.2.requires_auth', true)
            ->assertJsonPath('data.2.price.billing_interval', 'month');

        $this->assertStringContainsString('public', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('stale-while-revalidate=60', (string) $response->headers->get('Cache-Control'));
        $this->assertNotEmpty($response->headers->get('ETag'));

        foreach ($response->json('data') as $item) {
            foreach (['email', 'user_id', 'club_id', 'sponsor_id', 'commission_percent', 'contract_terms'] as $privateField) {
                $this->assertArrayNotHasKey($privateField, $item);
                $this->assertArrayNotHasKey($privateField, $item['metadata']);
            }
            $this->assertArrayHasKey('target_url', $item);
            $this->assertArrayHasKey('delivery_type', $item);
        }

        $this->assertStringNotContainsString('<script', (string) $response->json('data.0.summary'));
        $this->assertStringNotContainsString('alert(', (string) $response->json('data.0.summary'));

        $etag = (string) $response->headers->get('ETag');
        $this->withHeader('If-None-Match', $etag)
            ->getJson(route('api.v1.public.commerce.catalog'))
            ->assertNotModified();
    }

    public function test_catalog_filters_are_bounded_and_hide_non_public_records(): void
    {
        [, $course] = $this->createCatalogFixtures();
        LearningCourse::query()->create([
            'user_id' => $course->user_id,
            'title' => 'Interner Kurs',
            'slug' => 'interner-kurs',
            'status' => 'draft',
            'is_public' => false,
        ]);
        MarketplaceProduct::query()->create([
            'title' => 'Nicht freigegeben',
            'description' => 'Darf nicht erscheinen.',
            'category' => 'service',
            'offer_type' => 'service',
            'product_type' => 'digital',
            'manages_stock' => false,
            'price_cents' => 100,
            'currency' => 'EUR',
            'status' => 'published',
            'moderation_status' => 'pending',
        ]);

        $this->getJson(route('api.v1.public.commerce.catalog', [
            'kind' => 'course',
            'q' => 'Sprint',
            'limit' => 1,
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Sprinttechnik')
            ->assertJsonPath('meta.limit_per_kind', 1)
            ->assertJsonPath('meta.kinds.0', 'course');

        $this->getJson(route('api.v1.public.commerce.catalog', ['kind' => 'invalid']))
            ->assertUnprocessable();
        $this->getJson(route('api.v1.public.commerce.catalog', ['limit' => 13]))
            ->assertUnprocessable();
    }

    public function test_full_catalog_uses_at_most_one_select_per_offer_kind(): void
    {
        $this->createCatalogFixtures();
        $catalogSelects = [];

        DB::listen(function (QueryExecuted $query) use (&$catalogSelects): void {
            $sql = strtolower($query->sql);
            if (str_starts_with(ltrim($sql), 'select')
                && (str_contains($sql, 'marketplace_products')
                    || str_contains($sql, 'learning_courses')
                    || str_contains($sql, 'outfit_subscription_plans'))) {
                $catalogSelects[] = $sql;
            }
        });

        $this->getJson(route('api.v1.public.commerce.catalog'))->assertOk();

        $this->assertCount(3, $catalogSelects, 'The unified catalogue must execute exactly one bounded SELECT per kind.');
    }

    public function test_catalog_copy_is_available_in_all_supported_locales(): void
    {
        $expectedTitles = [
            'de' => 'Produkte, Kurse und Outfit-Abos direkt vergleichen',
            'en' => 'Compare products, courses and outfit subscriptions',
            'fr' => 'Comparez produits, cours et abonnements tenue',
            'ar' => 'قارن المنتجات والدورات واشتراكات الملابس',
        ];

        foreach ($expectedTitles as $locale => $title) {
            $this->assertSame($title, __('commerce.catalog.title', locale: $locale));
            $this->assertNotSame('commerce.catalog.kinds.product', __('commerce.catalog.kinds.product', locale: $locale));
        }
    }

    private function createCatalogFixtures(): array
    {
        $author = User::factory()->create();
        $product = MarketplaceProduct::query()->create([
            'user_id' => $author->id,
            'title' => 'Recovery Analyse',
            'description' => '<b>Sichere Auswertung</b><script>alert(1)</script>',
            'category' => 'service',
            'offer_type' => 'service',
            'product_type' => 'digital',
            'is_shippable' => false,
            'manages_stock' => false,
            'price_cents' => 3900,
            'currency' => 'EUR',
            'status' => 'published',
            'moderation_status' => 'approved',
        ]);
        $course = LearningCourse::query()->create([
            'user_id' => $author->id,
            'title' => 'Sprinttechnik',
            'slug' => 'sprinttechnik',
            'subtitle' => 'Schneller und sauberer laufen',
            'description' => 'Ein öffentlicher Kurs.',
            'category' => 'training',
            'sport_type' => 'Running',
            'level' => 'advanced',
            'language' => 'de',
            'status' => 'published',
            'is_public' => true,
            'is_free' => true,
            'price_cents' => 0,
            'currency' => 'EUR',
            'published_at' => now()->subMinute(),
        ]);
        $plan = OutfitSubscriptionPlan::query()->create([
            'name' => 'Teamwear Box',
            'slug' => 'teamwear-box',
            'description' => 'Monatliche Sportkleidung.',
            'monthly_price_cents' => 4990,
            'sponsor_discount_cents' => 1000,
            'currency' => 'EUR',
            'sports' => ['Running'],
            'items_per_box' => 3,
            'sort_order' => 1,
            'is_public' => true,
            'is_active' => true,
        ]);

        return [$product, $course, $plan];
    }
}
