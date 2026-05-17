<?php

namespace Tests\Feature;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BlogPublicTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_public_blog_index_only_lists_published_posts(): void
    {
        BlogPost::factory()->published()->create([
            'title' => 'Sichtbarer Trainingsartikel',
            'slug' => 'sichtbarer-trainingsartikel',
        ]);
        BlogPost::factory()->create([
            'title' => 'Interner Entwurf',
            'slug' => 'interner-entwurf',
            'status' => 'draft',
        ]);

        $response = $this->get(route('guest.blog.index'));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Guest/Blog/Index'))
            ->assertSee('Sichtbarer Trainingsartikel')
            ->assertDontSee('Interner Entwurf');
    }

    public function test_public_blog_show_rejects_unpublished_posts(): void
    {
        $post = BlogPost::factory()->create([
            'slug' => 'noch-nicht-veroeffentlicht',
            'status' => 'review',
            'published_at' => null,
        ]);

        $this->get(route('guest.blog.show', $post))->assertNotFound();
    }

    public function test_admin_preview_can_render_unpublished_posts(): void
    {
        $author = User::factory()->create();
        $this->actingAs($author);

        app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $author->givePermissionTo('blog.view');

        $post = BlogPost::factory()->create([
            'title' => 'Entwurf fuer Vorschau',
            'slug' => 'entwurf-fuer-vorschau',
            'status' => 'draft',
            'published_at' => null,
        ]);

        $response = $this->get(route('blogs.preview', $post));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Guest/Blog/Show')
                ->where('isPreview', true))
            ->assertSee('Entwurf fuer Vorschau')
            ->assertSee('entwurf-fuer-vorschau');
    }

    public function test_publishing_requires_editorial_quality_score(): void
    {
        $author = User::factory()->create();
        $this->actingAs($author);

        app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $author->givePermissionTo(['blog.create', 'blog.publish']);

        $category = BlogCategory::query()->updateOrCreate(['slug' => 'training'], ['name' => 'Training', 'is_active' => true]);

        $this->post(route('blogs.store'), [
            'title' => 'Zu kurz',
            'slug' => 'zu-kurz',
            'excerpt' => 'Kurz',
            'content' => '<p>Kurz.</p>',
            'blog_category_id' => $category->id,
            'status' => 'published',
        ])->assertSessionHasErrors('status');

        $this->assertDatabaseMissing('blog_posts', ['slug' => 'zu-kurz']);
    }

    public function test_high_quality_published_post_creates_revision(): void
    {
        $author = User::factory()->create();
        $this->actingAs($author);

        app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $author->givePermissionTo(['blog.create', 'blog.publish']);

        $category = BlogCategory::query()->updateOrCreate(['slug' => 'training'], ['name' => 'Training', 'is_active' => true]);
        $content = '<p>'.str_repeat('Training ', 470).'</p>';

        $this->post(route('blogs.store'), [
            'title' => 'Trainingsplanung fuer moderne Vereine digital verbessern',
            'slug' => 'trainingsplanung-moderne-vereine',
            'excerpt' => 'Ein praktischer Leitfaden fuer Vereine, Trainer und Teams, die Training, Organisation und Kommunikation digital besser verbinden wollen.',
            'content' => $content,
            'cover_image' => 'https://example.com/blog-cover.jpg',
            'blog_category_id' => $category->id,
            'tags' => 'Training, Digitalisierung',
            'meta_title' => 'Trainingsplanung fuer moderne Vereine verbessern',
            'meta_description' => 'Praxisnaher Leitfaden fuer Vereine und Trainer, um Training, Organisation und Kommunikation mit digitalen Prozessen besser zu verbinden.',
            'status' => 'published',
        ])->assertRedirect();

        $post = BlogPost::query()->where('slug', 'trainingsplanung-moderne-vereine')->firstOrFail();

        $this->assertSame('published', $post->status);
        $this->assertSame($author->id, $post->published_by);
        $this->assertGreaterThanOrEqual(85, $post->seo_score);
        $this->assertDatabaseHas('blog_post_revisions', [
            'blog_post_id' => $post->id,
            'user_id' => $author->id,
            'seo_score' => $post->seo_score,
        ]);
    }

    public function test_updating_blog_post_records_changed_revision_fields(): void
    {
        $author = User::factory()->create();
        $this->actingAs($author);

        app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $author->givePermissionTo('blog.update');

        $category = BlogCategory::query()->updateOrCreate(['slug' => 'training'], ['name' => 'Training', 'is_active' => true]);
        $post = BlogPost::factory()->create([
            'title' => 'Alter Titel',
            'slug' => 'alter-titel',
            'category' => 'Training',
            'blog_category_id' => $category->id,
            'tags' => ['Training'],
        ]);

        $this->put(route('blogs.update', $post), [
            'title' => 'Neuer Titel fuer die Redaktion',
            'slug' => 'alter-titel',
            'excerpt' => $post->excerpt,
            'content' => $post->content,
            'cover_image' => $post->cover_image,
            'blog_category_id' => $category->id,
            'tags' => 'Training',
            'status' => 'draft',
        ])->assertRedirect();

        $revision = $post->fresh()->revisions()->latest('id')->firstOrFail();

        $this->assertContains('title', $revision->changed_fields);
        $this->assertSame('Neuer Titel fuer die Redaktion', $revision->title);
    }

    public function test_public_blog_index_can_filter_by_category(): void
    {
        BlogCategory::query()->updateOrCreate(['slug' => 'training'], ['name' => 'Training', 'is_active' => true]);
        BlogCategory::query()->updateOrCreate(['slug' => 'sponsoring'], ['name' => 'Sponsoring', 'is_active' => true]);

        BlogPost::factory()->published()->create([
            'title' => 'Trainingsplanung im Verein',
            'category' => 'Training',
        ]);
        BlogPost::factory()->published()->create([
            'title' => 'Sponsorendeck vorbereiten',
            'category' => 'Sponsoring',
        ]);

        $response = $this->get(route('guest.blog.index', ['category' => 'Training']));

        $response->assertOk()
            ->assertSee('Trainingsplanung im Verein')
            ->assertDontSee('Sponsorendeck vorbereiten');
    }

    public function test_public_blog_category_route_lists_matching_posts(): void
    {
        $category = BlogCategory::query()->updateOrCreate(['slug' => 'training'], ['name' => 'Training', 'is_active' => true]);

        BlogPost::factory()->published()->create([
            'title' => 'Athletik richtig planen',
            'category' => 'Training',
        ]);
        BlogPost::factory()->published()->create([
            'title' => 'Digitale Vereinsprozesse',
            'category' => 'Digitalisierung',
        ]);

        $response = $this->get(route('guest.blog.category', $category));

        $response->assertOk()
            ->assertSee('Athletik richtig planen')
            ->assertDontSee('Digitale Vereinsprozesse');
    }

    public function test_public_blog_show_includes_related_posts_and_reading_time(): void
    {
        $content = '<p>'.str_repeat('Training ', 260).'</p>';
        $post = BlogPost::factory()->published()->create([
            'title' => 'Hauptartikel',
            'slug' => 'hauptartikel',
            'category' => 'Training',
            'content' => $content,
        ]);
        BlogPost::factory()->published()->create([
            'title' => 'Passender Folgeartikel',
            'slug' => 'passender-folgeartikel',
            'category' => 'Training',
        ]);
        BlogPost::factory()->published()->create([
            'title' => 'Andere Kategorie',
            'slug' => 'andere-kategorie',
            'category' => 'Sponsoring',
        ]);

        $response = $this->get(route('guest.blog.show', $post));

        $response->assertOk()
            ->assertSee('Hauptartikel')
            ->assertSee('Passender Folgeartikel')
            ->assertDontSee('Andere Kategorie');

        $this->assertSame(2, $post->fresh()->reading_time_minutes);
    }

    public function test_sitemap_includes_blog_categories_with_published_posts(): void
    {
        $category = BlogCategory::query()->updateOrCreate(['slug' => 'training'], ['name' => 'Training', 'is_active' => true]);
        BlogPost::factory()->published()->create([
            'title' => 'Sitemap Artikel',
            'slug' => 'sitemap-artikel',
            'category' => 'Training',
            'blog_category_id' => $category->id,
        ]);

        $response = $this->get(route('sitemap'));

        $response->assertOk()
            ->assertSee(route('guest.blog.category', $category->slug), false)
            ->assertSee(route('guest.blog.show', 'sitemap-artikel'), false);
    }

    public function test_rss_feed_lists_only_published_blog_posts(): void
    {
        BlogPost::factory()->published()->create([
            'title' => 'RSS Sichtbar',
            'slug' => 'rss-sichtbar',
            'excerpt' => 'RSS Kurztext',
        ]);
        BlogPost::factory()->create([
            'title' => 'RSS Entwurf',
            'slug' => 'rss-entwurf',
            'status' => 'draft',
        ]);

        $response = $this->get(route('guest.blog.rss'));

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8')
            ->assertSee('<rss version="2.0"', false)
            ->assertSee('RSS Sichtbar')
            ->assertSee(route('guest.blog.show', 'rss-sichtbar'), false)
            ->assertDontSee('RSS Entwurf');
    }

    public function test_blog_content_is_sanitized_when_created(): void
    {
        $author = User::factory()->create();
        $this->actingAs($author);

        app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $author->givePermissionTo('blog.create');

        $category = BlogCategory::query()->updateOrCreate(['slug' => 'training'], ['name' => 'Training', 'is_active' => true]);

        $this->post(route('blogs.store'), [
            'title' => 'Sicherer Inhalt',
            'slug' => 'sicherer-inhalt',
            'excerpt' => 'Kurztext',
            'content' => '<p onclick="alert(1)">Text</p><a href=javascript:alert(1)>Link</a><img src="data:text/html;base64,abc" onerror="alert(1)"><script>alert(1)</script><span class="blog-mark unknown">Markiert</span>',
            'blog_category_id' => $category->id,
            'tags' => 'Security, Blog',
            'status' => 'draft',
        ])->assertRedirect();

        $post = BlogPost::query()->where('slug', 'sicherer-inhalt')->firstOrFail();
        $content = $post->content;

        $this->assertSame($category->id, $post->blog_category_id);
        $this->assertSame('Training', $post->category);
        $this->assertStringNotContainsString('onclick', $content);
        $this->assertStringNotContainsString('javascript:', $content);
        $this->assertStringNotContainsString('data:text/html', $content);
        $this->assertStringNotContainsString('<script', $content);
        $this->assertStringContainsString('class="blog-mark"', $content);
        $this->assertStringNotContainsString('unknown', $content);
    }
}
