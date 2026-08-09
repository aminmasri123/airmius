<?php

namespace Tests\Feature;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Club;
use App\Models\LearningCourse;
use App\Models\MarketplaceProduct;
use App\Models\Sponsor;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicContentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_catalog_routes_use_the_shared_rate_limiter(): void
    {
        foreach ([
            'api.v1.public.blog.index',
            'api.v1.public.blog.show',
            'api.v1.public.sponsors.index',
            'api.v1.public.recruiting.jobs.index',
            'api.v1.public.clubs.index',
            'api.v1.public.marketplace.index',
            'api.v1.public.commerce.catalog',
            'api.v1.public.learning.certificates.verify',
            'api.v1.public.learning.courses.index',
        ] as $name) {
            $route = app('router')->getRoutes()->getByName($name);

            self::assertNotNull($route, $name);
            self::assertContains('throttle:public-content', $route->gatherMiddleware(), $name);
        }
    }

    public function test_blog_api_returns_only_published_content_and_plain_safe_detail_text(): void
    {
        $author = User::factory()->create(['name' => 'Mina Redaktion']);
        $category = BlogCategory::query()->where('is_active', true)->firstOrFail();
        $published = BlogPost::factory()->published()->create([
            'author_id' => $author->id,
            'blog_category_id' => $category->id,
            'category' => $category->name,
            'title' => 'Sicher im Vereinsalltag',
            'slug' => 'sicher-im-vereinsalltag',
            'excerpt' => 'Praktische Hinweise für sichere Vereinsarbeit.',
            'content' => '<h2>Datenschutz</h2><p>Nur notwendige Daten.</p><script>alert(1)</script>',
            'published_at' => now()->subMinute(),
        ]);
        $draft = BlogPost::factory()->create([
            'author_id' => $author->id,
            'title' => 'Interner Entwurf',
            'slug' => 'interner-entwurf',
            'status' => 'draft',
        ]);
        BlogPost::factory()->create([
            'author_id' => $author->id,
            'title' => 'Spaeter',
            'slug' => 'spaeter',
            'status' => 'published',
            'published_at' => now()->addDay(),
        ]);

        $this->getJson('/api/v1/public/blog')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $published->id)
            ->assertJsonPath('data.0.author.name', 'Mina Redaktion')
            ->assertJsonPath('data.0.category.slug', $category->slug);

        $detail = $this->getJson('/api/v1/public/blog/sicher-im-vereinsalltag')
            ->assertOk()
            ->assertJsonPath('data.title', 'Sicher im Vereinsalltag');

        $this->assertStringNotContainsString('<script', $detail->json('data.content_text'));
        $this->assertStringNotContainsString('<h2', $detail->json('data.content_text'));
        $this->assertStringContainsString('Nur notwendige Daten.', $detail->json('data.content_text'));

        $this->getJson("/api/v1/public/blog/{$draft->slug}")->assertNotFound();
    }

    public function test_sponsor_api_exposes_active_public_profile_but_not_internal_contract_data(): void
    {
        $active = Sponsor::query()->create([
            'scope' => 'platform',
            'name' => 'Sport Partner',
            'contact_name' => 'Interne Person',
            'email' => 'intern@example.test',
            'website' => 'https://partner.example.test',
            'amount' => 25000,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);
        Sponsor::query()->create([
            'scope' => 'platform',
            'name' => 'Abgelaufen',
            'ends_at' => now()->subDay(),
        ]);
        Sponsor::query()->create([
            'scope' => 'platform',
            'name' => 'Unsicherer Link',
            'website' => 'javascript:alert(1)',
        ]);

        $response = $this->getJson('/api/v1/public/sponsors')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $active->id)
            ->assertJsonPath('data.0.website', 'https://partner.example.test')
            ->assertJsonPath('data.1.website', null)
            ->assertJsonPath('stats.total', 2);

        foreach ($response->json('data') as $sponsor) {
            $this->assertArrayNotHasKey('email', $sponsor);
            $this->assertArrayNotHasKey('contact_name', $sponsor);
            $this->assertArrayNotHasKey('amount', $sponsor);
        }
    }

    public function test_public_marketplace_api_returns_only_published_safe_cards(): void
    {
        $seller = User::factory()->create(['name' => 'Öffentlicher Anbieter']);
        $published = MarketplaceProduct::query()->create([
            'user_id' => $seller->id,
            'title' => 'Vereinsausrüstung',
            'description' => 'Sicheres Trainingszubehör.',
            'category' => 'service',
            'offer_type' => 'service',
            'product_type' => 'digital',
            'is_shippable' => false,
            'manages_stock' => false,
            'price_cents' => 3900,
            'currency' => 'EUR',
            'tax_class' => 'standard',
            'status' => 'published',
            'moderation_status' => 'approved',
            'commission_percent' => 10,
            'payout_status' => 'pending_sales',
        ]);
        MarketplaceProduct::query()->create([
            'user_id' => $seller->id,
            'title' => 'Interner Entwurf',
            'description' => 'Nicht öffentlich.',
            'category' => 'service',
            'price_cents' => 1000,
            'currency' => 'EUR',
            'status' => 'draft',
            'moderation_status' => 'pending',
        ]);

        $response = $this->getJson('/api/v1/public/marketplace')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $published->id)
            ->assertJsonPath('data.0.title', 'Vereinsausrüstung')
            ->assertJsonPath('data.0.provider_name', 'Öffentlicher Anbieter')
            ->assertJsonPath('meta.total', 1);

        $this->assertArrayNotHasKey('sku', $response->json('data.0'));
        $this->assertArrayNotHasKey('commission_percent', $response->json('data.0'));
    }

    public function test_public_learning_api_returns_only_published_public_course_cards(): void
    {
        $tutor = User::factory()->create(['name' => 'Öffentliche Kursleitung']);
        $published = LearningCourse::query()->create([
            'user_id' => $tutor->id,
            'title' => 'Sicher im Verein',
            'slug' => 'sicher-im-verein',
            'subtitle' => 'Grundlagen für Teams',
            'description' => 'Datenschutz und Rollen verständlich erklärt.',
            'category' => 'Vereine',
            'level' => 'beginner',
            'status' => 'published',
            'is_public' => true,
            'is_free' => true,
            'published_at' => now()->subMinute(),
        ]);
        LearningCourse::query()->create([
            'user_id' => $tutor->id,
            'title' => 'Interner Kurs',
            'slug' => 'interner-kurs',
            'category' => 'Vereine',
            'level' => 'beginner',
            'status' => 'draft',
            'is_public' => false,
        ]);

        $response = $this->getJson('/api/v1/public/learning/courses')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $published->id)
            ->assertJsonPath('data.0.title', 'Sicher im Verein')
            ->assertJsonPath('data.0.tutor.name', 'Öffentliche Kursleitung')
            ->assertJsonPath('meta.total', 1);

        $this->assertArrayNotHasKey('user_id', $response->json('data.0'));
        $this->assertArrayNotHasKey('certificate_signature_name', $response->json('data.0'));
    }

    public function test_public_club_api_returns_only_verified_listed_safe_cards(): void
    {
        $owner = User::factory()->create();
        $public = Club::factory()->create([
            'owner_id' => $owner->id,
            'name' => 'Airmius Running Club',
            'sport_type' => 'Running',
            'city' => 'Berlin',
            'country' => 'DE',
            'verification_status' => 'verified',
            'is_listed' => true,
            'is_official' => true,
            'membership_requests_enabled' => true,
        ]);
        Team::factory()->create(['club_id' => $public->id, 'name' => 'Airmius 10K']);
        Club::factory()->create([
            'owner_id' => User::factory()->create()->id,
            'name' => 'Nicht gelistet',
            'verification_status' => 'verified',
            'is_listed' => false,
        ]);
        Club::factory()->create([
            'owner_id' => User::factory()->create()->id,
            'name' => 'Noch nicht verifiziert',
            'verification_status' => 'pending',
            'is_listed' => true,
        ]);

        $response = $this->getJson('/api/v1/public/clubs?q=Airmius&location=Berlin')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $public->id)
            ->assertJsonPath('data.0.name', 'Airmius Running Club')
            ->assertJsonPath('data.0.teams_count', 1)
            ->assertJsonPath('data.0.is_official', true)
            ->assertJsonPath('meta.total', 1);

        $card = $response->json('data.0');
        $this->assertArrayNotHasKey('owner_id', $card);
        $this->assertArrayNotHasKey('sepa_iban', $card);
        $this->assertArrayNotHasKey('members', $card);
    }
}
