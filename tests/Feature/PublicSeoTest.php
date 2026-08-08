<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\Event;
use App\Models\MarketplaceProduct;
use App\Models\Sport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_home_initial_html_contains_server_side_seo_tags(): void
    {
        $response = $this
            ->withHeader('Accept-Language', 'de-DE,de;q=0.9')
            ->get(route('welcome'));

        $response->assertOk()
            ->assertSee('lang="de"', false)
            ->assertSee('<title inertia>Airmius Sport Plattform</title>', false)
            ->assertSee('<meta name="description"', false)
            ->assertSee('Airmius verbindet Sportler, Trainer, Teams und Vereine', false)
            ->assertSee('<link rel="canonical" href="'.route('welcome').'"', false)
            ->assertSee('<meta property="og:title"', false)
            ->assertSee('<meta name="twitter:card" content="summary_large_image"', false)
            ->assertSee('<script type="application/ld+json"', false);
    }

    public function test_public_blog_article_initial_html_contains_article_seo_tags(): void
    {
        $post = BlogPost::factory()->published()->create([
            'title' => 'Trainingsplanung fuer Vereine',
            'slug' => 'trainingsplanung-fuer-vereine',
            'excerpt' => 'So planen Vereine moderne Trainingswochen digital und nachvollziehbar.',
            'meta_title' => 'Digitale Trainingsplanung fuer Vereine',
            'meta_description' => 'Praxisnaher SEO-Text fuer digitale Trainingsplanung in modernen Sportvereinen.',
            'cover_image' => 'https://example.com/cover.jpg',
        ]);

        $response = $this->get(route('guest.blog.show', $post));

        $response->assertOk()
            ->assertSee('<title inertia>Digitale Trainingsplanung fuer Vereine | Airmius</title>', false)
            ->assertSee('<meta name="description" content="Praxisnaher SEO-Text fuer digitale Trainingsplanung in modernen Sportvereinen."', false)
            ->assertSee('<link rel="canonical" href="'.route('guest.blog.show', $post->slug).'"', false)
            ->assertSee('<meta property="og:type" content="article"', false)
            ->assertSee('<meta property="og:image" content="https://example.com/cover.jpg"', false)
            ->assertSee('"@type":"BlogPosting"', false);
    }

    public function test_duplicate_public_marketing_urls_redirect_to_canonical_routes(): void
    {
        $this->get('/preise')
            ->assertStatus(301)
            ->assertRedirect('/abos');

        $this->get('/werbeagentur-für-vereine')
            ->assertStatus(301)
            ->assertRedirect(route('guest.werbeagentur'));

        $this->get(route('guest.werbeagentur'))
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.route('guest.werbeagentur').'"', false);
    }

    public function test_sitemap_uses_canonical_public_urls(): void
    {
        $response = $this->get(route('sitemap'));

        $response->assertOk()
            ->assertSee(route('guest.pricing'), false)
            ->assertSee(route('guest.werbeagentur'), false)
            ->assertDontSee(url('/preise'), false)
            ->assertDontSee(url('/werbeagentur-für-vereine'), false);
    }

    public function test_public_marketing_pages_initial_html_contains_route_specific_seo_tags(): void
    {
        $this->get(route('guest.pricing'))
            ->assertOk()
            ->assertSee('<title inertia>Airmius Preise für Sportler, Trainer, Vereine, Partner und Werbeagentur</title>', false)
            ->assertSee('<meta name="description" content="Faire Airmius Pläne für Sportler, Trainer, Vereine, Eltern, Sponsoren, Anbieter, Verbände und Website-Services für Vereine."', false)
            ->assertSee('<link rel="canonical" href="'.route('guest.pricing').'"', false)
            ->assertSee('<meta property="og:image"', false);

        $this->get(route('guest.vereine'))
            ->assertOk()
            ->assertSee('<title inertia>Vereine finden | Airmius</title>', false)
            ->assertSee('<meta name="description" content="Finde Sportvereine nach Sportart, Standort und Teamangeboten. Entdecke Vereine auf Airmius und vernetze dich digital."', false)
            ->assertSee('<link rel="canonical" href="'.route('guest.vereine').'"', false);

        $this->get(route('guest.marketplace'))
            ->assertOk()
            ->assertSee('<title inertia>Airmius Sport Marketplace</title>', false)
            ->assertSee('<meta name="description" content="Sportfokussierter Marketplace für Produkte, Kurse, Camps und Services. Gäste können direkt ohne Konto bestellen."', false)
            ->assertSee('<link rel="canonical" href="'.route('guest.marketplace').'"', false);
    }

    public function test_marketplace_product_initial_html_contains_product_seo_tags(): void
    {
        $product = $this->createPublishedProduct([
            'title' => 'Laufanalyse Sensor',
            'description' => 'Sensor fuer Laufanalyse, Training und Technikfeedback im Verein.',
            'image_url' => 'https://example.com/laufanalyse.jpg',
            'gallery_images' => ['https://example.com/laufanalyse.jpg'],
            'sku' => 'AIR-RUN-01',
        ]);

        $this->get(route('guest.marketplace.products.show', $product))
            ->assertOk()
            ->assertSee('<title inertia>Laufanalyse Sensor kaufen | Airmius</title>', false)
            ->assertSee('<meta name="description" content="Sensor fuer Laufanalyse, Training und Technikfeedback im Verein."', false)
            ->assertSee('<link rel="canonical" href="'.route('guest.marketplace.products.show', $product).'"', false)
            ->assertSee('<meta property="og:type" content="product"', false)
            ->assertSee('<meta property="og:image" content="https://example.com/laufanalyse.jpg"', false)
            ->assertSee('"@type":"Product"', false);
    }

    public function test_marketplace_provider_initial_html_contains_provider_seo_tags(): void
    {
        $provider = User::factory()->create([
            'name' => 'Airmius Coach Studio',
            'bio' => 'Digitaler Anbieter fuer Trainingsplaene und Lauftechnik.',
        ]);
        $this->createPublishedProduct(['user_id' => $provider->id]);

        $this->get(route('guest.marketplace.providers.show', ['type' => 'user', 'id' => $provider->id]))
            ->assertOk()
            ->assertSee('<title inertia>Airmius Coach Studio im Airmius Marketplace</title>', false)
            ->assertSee('<meta name="description" content="Digitaler Anbieter fuer Trainingsplaene und Lauftechnik."', false)
            ->assertSee('<link rel="canonical" href="'.route('guest.marketplace.providers.show', ['type' => 'user', 'id' => $provider->id]).'"', false);
    }

    public function test_sitemap_contains_public_marketplace_product_and_provider_urls(): void
    {
        $provider = User::factory()->create(['name' => 'Airmius Kurs Anbieter']);
        $product = $this->createPublishedProduct(['user_id' => $provider->id]);

        $this->get(route('sitemap'))
            ->assertOk()
            ->assertSee(route('guest.marketplace.products.show', $product), false)
            ->assertSee(route('guest.marketplace.providers.show', ['type' => 'user', 'id' => $provider->id]), false);
    }

    public function test_prioritized_public_event_and_sport_pages_render_with_seo(): void
    {
        Sport::create([
            'name' => 'Laufen',
            'slug' => 'laufen',
            'category' => 'Ausdauer',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        Event::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Public Lauftreff',
            'type' => 'training',
            'visibility' => 'public',
            'status' => 'scheduled',
            'start_time' => now()->addWeek(),
            'location' => 'Stadion',
            'location_city' => 'Berlin',
        ]);

        $this->get(route('guest.events'))
            ->assertOk()
            ->assertSee('<title inertia>Sportevents entdecken | Airmius</title>', false)
            ->assertSee('<meta name="description" content="Finde öffentliche Trainings, Spiele, Treffen und Sportveranstaltungen von Vereinen und Teams auf Airmius."', false)
            ->assertSee('<link rel="canonical" href="'.route('guest.events').'"', false)
            ->assertSee('Public Lauftreff');

        $this->get(route('guest.sports'))
            ->assertOk()
            ->assertSee('<title inertia>Sportarten auf Airmius</title>', false)
            ->assertSee('<meta name="description" content="Entdecke aktive Sportarten auf Airmius und finde passende Vereine, Teams, Events und digitale Sportangebote."', false)
            ->assertSee('<link rel="canonical" href="'.route('guest.sports').'"', false)
            ->assertSee('Laufen');
    }

    public function test_sitemap_contains_prioritized_public_event_and_sport_urls(): void
    {
        $this->get(route('sitemap'))
            ->assertOk()
            ->assertSee(route('guest.vereine'), false)
            ->assertSee(route('guest.events'), false)
            ->assertSee(route('guest.sports'), false)
            ->assertSee(route('guest.pricing'), false)
            ->assertSee(route('guest.blog.index'), false);
    }

    private function createPublishedProduct(array $overrides = []): MarketplaceProduct
    {
        return MarketplaceProduct::create(array_merge([
            'title' => 'Lauftechnik Kurs',
            'description' => 'Ein Kurs fuer bessere Lauftechnik.',
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
