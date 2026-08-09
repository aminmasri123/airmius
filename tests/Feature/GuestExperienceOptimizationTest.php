<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\CommerceOrder;
use App\Models\LearningCourse;
use App\Models\MarketplaceProduct;
use App\Models\Setting;
use App\Models\User;
use App\Services\CommercePaymentGatewayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class GuestExperienceOptimizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_order_pages_are_private_non_indexable_and_do_not_publish_token_canonicals(): void
    {
        Setting::setValue('billing_iban', 'DE89370400440532013000');
        Setting::setValue('billing_bank_account_holder', 'Airmius GmbH');

        foreach (['success', 'bank-transfer.show', 'cancel'] as $routeSuffix) {
            $order = $this->createGuestOrder($routeSuffix === 'cancel' ? ['status' => 'pending'] : []);
            $url = $routeSuffix === 'cancel'
                ? app(CommercePaymentGatewayService::class)->orderRoute($order, 'cancel')
                : route('commerce-checkout.guest.'.$routeSuffix, [$order, $order->access_token]);

            $response = $this->get($url);

            $response->assertOk()
                ->assertHeader('Pragma', 'no-cache')
                ->assertHeader('Expires', '0')
                ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
                ->assertHeader('Referrer-Policy', 'no-referrer')
                ->assertSee('<meta name="robots" content="noindex,nofollow"', false)
                ->assertDontSee('<link rel="canonical"', false)
                ->assertDontSee('<meta property="og:url"', false);

            foreach (['private', 'no-store', 'no-cache', 'must-revalidate', 'max-age=0'] as $directive) {
                $this->assertStringContainsString($directive, (string) $response->headers->get('Cache-Control'));
            }
        }
    }

    public function test_marketplace_filter_partial_reload_omits_curated_and_static_payloads(): void
    {
        $this->createPublishedProduct(['title' => 'Schneller Gastfilter']);
        $assetVersion = app(HandleInertiaRequests::class)->version(request());

        $this->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $assetVersion,
            'X-Inertia-Partial-Component' => 'Guest/Marketplace',
            'X-Inertia-Partial-Data' => 'products,commerceCatalog,filters',
        ])
            ->get(route('guest.marketplace', ['search' => 'Schneller']))
            ->assertOk()
            ->assertJsonPath('component', 'Guest/Marketplace')
            ->assertJsonPath('props.products.data.0.title', 'Schneller Gastfilter')
            ->assertJsonPath('props.commerceCatalog.meta.contract', 'commerce-card.v1')
            ->assertJsonPath('props.filters.search', 'Schneller')
            ->assertJsonMissingPath('props.featuredProducts')
            ->assertJsonMissingPath('props.flashDeals')
            ->assertJsonMissingPath('props.essentialDeals')
            ->assertJsonMissingPath('props.serviceDeals')
            ->assertJsonMissingPath('props.sportCategories')
            ->assertJsonMissingPath('props.officialStores')
            ->assertJsonMissingPath('props.providerLocations')
            ->assertJsonMissingPath('props.authUser')
            ->assertJsonMissingPath('props.cart');
    }

    public function test_public_learning_catalog_is_paginated_and_partial_filters_are_bounded(): void
    {
        $tutor = User::factory()->create();
        foreach (range(1, 30) as $position) {
            LearningCourse::query()->create([
                'user_id' => $tutor->id,
                'title' => "Gastkurs {$position}",
                'slug' => "gastkurs-{$position}",
                'description' => 'Ressourcenschonender Gastkurs.',
                'category' => $position === 30 ? 'recovery' : 'training',
                'level' => 'beginner',
                'status' => 'published',
                'is_public' => true,
                'published_at' => now()->subMinutes($position),
            ]);
        }

        $this->get(route('guest.e-learning'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Guest/E-Learning')
                ->has('learningCourses.data', 24)
                ->where('learningCourses.total', 30)
                ->where('learningCourses.per_page', 24));

        $assetVersion = app(HandleInertiaRequests::class)->version(request());
        $this->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $assetVersion,
            'X-Inertia-Partial-Component' => 'Guest/E-Learning',
            'X-Inertia-Partial-Data' => 'learningCourses,filters',
        ])
            ->get(route('guest.e-learning', ['category' => 'recovery']))
            ->assertOk()
            ->assertJsonPath('component', 'Guest/E-Learning')
            ->assertJsonCount(1, 'props.learningCourses.data')
            ->assertJsonPath('props.learningCourses.data.0.title', 'Gastkurs 30')
            ->assertJsonPath('props.filters.category', 'recovery')
            ->assertJsonMissingPath('props.facets')
            ->assertJsonMissingPath('props.learningProducts')
            ->assertJsonMissingPath('props.canLogin')
            ->assertJsonMissingPath('props.canRegister');
    }

    public function test_guest_catalogs_have_bounded_filter_and_sort_indexes(): void
    {
        $this->assertIndex('marketplace_products', 'marketplace_public_catalog_idx', ['status', 'moderation_status', 'category', 'id']);
        $this->assertIndex('marketplace_products', 'marketplace_public_price_idx', ['status', 'moderation_status', 'price_cents', 'id']);
        $this->assertIndex('learning_courses', 'learning_public_catalog_idx', ['status', 'is_public', 'featured_at', 'published_at', 'id']);
        $this->assertIndex('learning_courses', 'learning_public_filter_idx', ['status', 'is_public', 'category', 'level', 'is_free']);
    }

    public function test_public_machine_readable_pages_support_shared_caching_and_conditional_requests(): void
    {
        foreach (['robots', 'sitemap', 'guest.blog.rss', 'site.webmanifest'] as $routeName) {
            $response = $this->get(route($routeName));
            $etag = (string) $response->headers->get('ETag');

            $response->assertOk();
            $response->assertHeaderMissing('Set-Cookie');
            $this->assertNotSame('', $etag, $routeName.' must expose an ETag.');
            $this->assertStringContainsString('public', (string) $response->headers->get('Cache-Control'));
            $this->assertStringContainsString('max-age=', (string) $response->headers->get('Cache-Control'));

            $this->withHeader('If-None-Match', $etag)
                ->get(route($routeName))
                ->assertNotModified();
        }
    }

    public function test_every_guest_page_exposes_a_focusable_main_landmark(): void
    {
        $pages = File::allFiles(resource_path('js/Pages/Guest'));

        $this->assertNotEmpty($pages);

        foreach ($pages as $page) {
            if ($page->getExtension() !== 'vue') {
                continue;
            }

            $source = File::get($page->getPathname());

            $this->assertStringContainsString('<main', $source, $page->getRelativePathname());
            $this->assertSame(1, substr_count($source, 'id="main-content"'), $page->getRelativePathname());
            $this->assertStringContainsString('tabindex="-1"', $source, $page->getRelativePathname());
        }
    }

    public function test_guest_navigation_and_custom_marketplace_shells_expose_skip_navigation(): void
    {
        $nav = File::get(resource_path('js/Components/Guest/Nav.vue'));

        $this->assertStringContainsString("import SkipLink from '@/Components/Guest/SkipLink.vue'", $nav);
        $this->assertStringContainsString('<SkipLink />', $nav);
        $this->assertStringContainsString("event.key === 'Escape'", $nav);
        $this->assertStringContainsString("event.key !== 'Tab'", $nav);
        $this->assertStringContainsString('closeButton.value?.focus()', $nav);

        foreach (['Marketplace.vue', 'MarketplaceProductShow.vue', 'MarketplaceProviderShow.vue', 'MarketplaceWishlist.vue'] as $page) {
            $source = File::get(resource_path('js/Pages/Guest/'.$page));

            $this->assertStringContainsString("import SkipLink from '@/Components/Guest/SkipLink.vue'", $source, $page);
            $this->assertStringContainsString('<SkipLink />', $source, $page);
        }
    }

    public function test_guest_images_defer_decoding_and_identify_critical_images(): void
    {
        $files = File::allFiles(resource_path('js/Pages/Guest'));
        $images = [];

        foreach ($files as $file) {
            if ($file->getExtension() !== 'vue') {
                continue;
            }

            preg_match_all('/<img\b[\s\S]*?>/', File::get($file->getPathname()), $matches);

            foreach ($matches[0] as $image) {
                $images[] = [$file->getRelativePathname(), $image];
            }
        }

        $this->assertNotEmpty($images);

        foreach ($images as [$path, $image]) {
            $this->assertStringContainsString('decoding="async"', $image, $path.' has an image without async decoding.');
        }

        $criticalImages = collect($images)->filter(
            fn (array $entry) => str_contains($entry[1], 'fetchpriority="high"')
                || str_contains($entry[1], ":fetchpriority=\"slideIndex === 0 ? 'high' : 'low'\""),
        );

        $this->assertGreaterThanOrEqual(4, $criticalImages->count());
    }

    private function createGuestOrder(array $overrides = []): CommerceOrder
    {
        return CommerceOrder::create(array_merge([
            'guest_name' => 'Gast Person',
            'guest_email' => 'gast@example.test',
            'access_token' => Str::random(64),
            'type' => 'marketplace_product',
            'provider' => 'bank_transfer',
            'amount_cents' => 4900,
            'currency' => 'EUR',
            'status' => 'awaiting_transfer',
            'payment_reference' => 'AIR-GUEST-TEST',
            'due_at' => now()->addDays(14),
            'payload' => [],
        ], $overrides));
    }

    private function createPublishedProduct(array $overrides = []): MarketplaceProduct
    {
        return MarketplaceProduct::create(array_merge([
            'title' => 'Gast Produkt',
            'description' => 'Ressourcenschonendes öffentliches Angebot.',
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

    /** @param array<int, string> $columns */
    private function assertIndex(string $table, string $name, array $columns): void
    {
        $index = collect(Schema::getIndexes($table))->firstWhere('name', $name);

        $this->assertNotNull($index, "Missing index {$name} on {$table}.");
        $this->assertSame($columns, $index['columns']);
    }
}
