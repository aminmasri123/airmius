<?php

namespace Tests\Feature;

use App\Models\BlogPost;
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
}
