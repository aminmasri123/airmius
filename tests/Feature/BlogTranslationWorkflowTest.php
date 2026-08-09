<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\User;
use App\Support\LocalizedPublicUrl;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BlogTranslationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_posts_have_stable_translation_groups_and_database_enforces_one_variant_per_locale(): void
    {
        $post = BlogPost::factory()->create();

        $this->assertSame('de', $post->content_locale);
        $this->assertTrue(Str::isUuid($post->translation_group));

        BlogPost::factory()->create([
            'translation_group' => $post->translation_group,
            'content_locale' => 'en',
        ]);

        $this->expectException(UniqueConstraintViolationException::class);
        BlogPost::factory()->create([
            'translation_group' => $post->translation_group,
            'content_locale' => 'en',
        ]);
    }

    public function test_web_editor_creates_linked_translation_records_revision_metadata_and_rejects_duplicates(): void
    {
        $editor = User::factory()->create();
        $editor->givePermissionTo(['blog.view', 'blog.create', 'blog.update']);
        $source = BlogPost::factory()->create([
            'title' => 'Deutsche Quelle',
            'slug' => 'deutsche-quelle',
            'content_locale' => 'de',
        ]);
        $this->actingAs($editor);

        $this->post(route('blogs.store'), $this->webPayload([
            'title' => 'English translation',
            'slug' => 'english-translation',
            'content_locale' => 'en',
            'translation_of_id' => $source->id,
        ]))->assertRedirect();

        $translation = BlogPost::query()->where('slug', 'english-translation')->firstOrFail();
        $this->assertSame($source->translation_group, $translation->translation_group);
        $this->assertSame('en', $translation->content_locale);
        $this->assertDatabaseHas('blog_post_revisions', [
            'blog_post_id' => $translation->id,
            'content_locale' => 'en',
            'translation_group' => $source->translation_group,
        ]);

        $this->post(route('blogs.store'), $this->webPayload([
            'title' => 'Duplicate English translation',
            'slug' => 'duplicate-english-translation',
            'content_locale' => 'en',
            'translation_of_id' => $source->id,
        ]))->assertSessionHasErrors('content_locale');

        $this->get(route('blogs.index', ['content_locale' => 'en']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.content_locale', 'en')
                ->has('supportedLocales', 4)
                ->has('posts.data', 1)
                ->where('posts.data.0.id', $translation->id)
                ->has('posts.data.0.translation_variants', 2));
    }

    public function test_guest_blog_prefers_exact_translation_uses_german_fallback_and_redirects_old_variant(): void
    {
        [$german, $english] = $this->publishedPair();
        $fallback = BlogPost::factory()->published()->create([
            'title' => 'Nur auf Deutsch',
            'slug' => 'nur-auf-deutsch',
            'content_locale' => 'de',
            'published_at' => now()->subMinute(),
        ]);

        $response = $this->get(route('guest.blog.index', ['locale' => 'en']))->assertOk();
        $posts = collect($response->viewData('page')['props']['posts']['data']);

        $this->assertEqualsCanonicalizing([$english->id, $fallback->id], $posts->pluck('id')->all());
        $this->assertNotContains($german->id, $posts->pluck('id')->all());
        $this->assertFalse((bool) $posts->firstWhere('id', $english->id)['is_locale_fallback']);
        $this->assertTrue((bool) $posts->firstWhere('id', $fallback->id)['is_locale_fallback']);
        $this->assertSame('de', $posts->firstWhere('id', $fallback->id)['content_locale']);

        $this->get(route('guest.blog.show', [
            'blogPost' => $german->slug,
            'locale' => 'en',
        ]))
            ->assertStatus(301)
            ->assertRedirect(LocalizedPublicUrl::forLocale(
                route('guest.blog.show', $english->slug),
                'en',
            ));

        $this->get(route('guest.blog.show', [
            'blogPost' => $fallback->slug,
            'locale' => 'fr',
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('post.content_locale', 'de')
                ->where('post.content_direction', 'ltr')
                ->where('post.is_locale_fallback', true)
                ->has('seo.alternates', 2));
    }

    public function test_translated_article_has_content_canonical_actual_hreflang_and_rtl_metadata(): void
    {
        [$german, $english] = $this->publishedPair();
        $arabic = BlogPost::factory()->published()->create([
            'title' => 'التدريب الذكي',
            'slug' => 'smart-training-ar',
            'content_locale' => 'ar',
            'translation_group' => $german->translation_group,
        ]);
        $canonical = LocalizedPublicUrl::forLocale(route('guest.blog.show', $arabic->slug), 'ar');

        $response = $this->get($canonical)
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.e($canonical).'"', false)
            ->assertSee('hreflang="de"', false)
            ->assertSee('hreflang="en"', false)
            ->assertSee('hreflang="ar"', false)
            ->assertDontSee('hreflang="fr"', false)
            ->assertInertia(fn (Assert $page) => $page
                ->where('post.content_locale', 'ar')
                ->where('post.content_direction', 'rtl')
                ->where('post.is_locale_fallback', false)
                ->has('translations', 3)
                ->has('seo.alternates', 4));

        $this->assertStringContainsString('"inLanguage":"ar"', (string) $response->getContent());
        $this->assertSame($german->translation_group, $english->translation_group);
    }

    public function test_rss_api_and_sitemap_publish_only_the_preferred_real_language_variants(): void
    {
        [$german, $english] = $this->publishedPair();
        $fallback = BlogPost::factory()->published()->create([
            'title' => 'German fallback item',
            'slug' => 'german-fallback-item',
            'content_locale' => 'de',
        ]);

        $feed = $this->get(LocalizedPublicUrl::forLocale(route('guest.blog.rss'), 'en'))
            ->assertOk()
            ->assertSee($english->title)
            ->assertDontSee($german->title)
            ->assertSee($fallback->title)
            ->assertSee('<dc:language>en</dc:language>', false)
            ->assertSee('<dc:language>de</dc:language>', false);
        $this->assertNotFalse(simplexml_load_string((string) $feed->getContent()));

        $api = $this->getJson('/api/v1/public/blog?locale=en')
            ->assertOk()
            ->assertJsonCount(2, 'data');
        $cards = collect($api->json('data'));
        $this->assertEqualsCanonicalizing([$english->id, $fallback->id], $cards->pluck('id')->all());
        $this->assertTrue((bool) $cards->firstWhere('id', $fallback->id)['is_locale_fallback']);
        $this->assertArrayNotHasKey('translation_group', $cards->first());

        $englishUrl = LocalizedPublicUrl::forLocale(route('guest.blog.show', $english->slug), 'en');
        $sitemap = (string) $this->get(route('sitemap'))->assertOk()->getContent();
        $this->assertSame(1, substr_count($sitemap, '<loc>'.e(route('guest.blog.show', $german->slug)).'</loc>'));
        $this->assertSame(1, substr_count($sitemap, '<loc>'.e($englishUrl).'</loc>'));
        $this->assertStringNotContainsString(
            e(LocalizedPublicUrl::forLocale(route('guest.blog.show', $german->slug), 'fr')),
            $sitemap,
        );
    }

    public function test_mobile_editorial_api_uses_same_translation_contract(): void
    {
        $editor = User::factory()->create();
        $editor->givePermissionTo(['blog.view', 'blog.create']);
        $source = BlogPost::factory()->create(['content_locale' => 'de']);
        Sanctum::actingAs($editor);

        $created = $this->postJson('/api/v1/editorial/posts', [
            'title' => 'Version française',
            'slug' => 'version-francaise',
            'content' => 'Contenu français sûr.',
            'content_locale' => 'fr',
            'translation_of_id' => $source->id,
            'status' => 'draft',
        ])
            ->assertCreated()
            ->assertJsonPath('data.content_locale', 'fr')
            ->assertJsonCount(2, 'data.translations');

        $this->assertDatabaseHas('blog_posts', [
            'id' => $created->json('data.id'),
            'translation_group' => $source->translation_group,
            'content_locale' => 'fr',
        ]);

        $this->postJson('/api/v1/editorial/posts', [
            'title' => 'Deuxième version française',
            'slug' => 'deuxieme-version-francaise',
            'content' => 'Doublon.',
            'content_locale' => 'fr',
            'translation_of_id' => $source->id,
            'status' => 'draft',
        ])->assertUnprocessable()->assertJsonValidationErrors('content_locale');
    }

    public function test_editorial_translation_coverage_is_eager_loaded_without_per_post_queries(): void
    {
        $editor = User::factory()->create();
        $editor->givePermissionTo('blog.view');
        BlogPost::factory()->count(12)->create();
        Sanctum::actingAs($editor);
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->getJson('/api/v1/editorial/posts')
            ->assertOk()
            ->assertJsonCount(12, 'data');

        $translationQueries = collect(DB::getQueryLog())
            ->pluck('query')
            ->filter(fn (string $query): bool => str_contains(strtolower($query), 'translation_group'))
            ->filter(fn (string $query): bool => str_starts_with(strtolower(ltrim($query)), 'select'));

        $this->assertCount(1, $translationQueries, 'Translation coverage must be loaded in one bounded query.');
    }

    public function test_page_local_copy_is_complete_rtl_ready_and_does_not_expand_core_catalogs(): void
    {
        $copy = json_decode(File::get(resource_path('js/Pages/Blog/blogLocalizationCopy.json')), true, 512, JSON_THROW_ON_ERROR);
        $referenceKeys = array_keys($copy['de']);

        foreach (['en', 'fr', 'ar'] as $locale) {
            $this->assertSame($referenceKeys, array_keys($copy[$locale]));
            $this->assertSame(array_keys($copy['de']['language_names']), array_keys($copy[$locale]['language_names']));
        }

        $this->assertMatchesRegularExpression('/\p{Arabic}/u', $copy['ar']['fallback_notice']);
        $this->assertStringContainsString('blogLocalizationCopy.json', File::get(resource_path('js/Pages/Guest/Blog/Index.vue')));
        $this->assertStringContainsString('blogLocalizationCopy.json', File::get(resource_path('js/Pages/Guest/Blog/Show.vue')));
        $this->assertStringContainsString('blogLocalizationCopy.json', File::get(resource_path('js/Pages/Auth/Dashboard/Blogs/Index.vue')));
        $this->assertStringNotContainsString('fallback_notice', File::get(resource_path('js/lang/ar.json')));
    }

    /** @return array{BlogPost, BlogPost} */
    private function publishedPair(): array
    {
        $german = BlogPost::factory()->published()->create([
            'title' => 'Deutscher Hauptartikel',
            'slug' => 'deutscher-hauptartikel',
            'content_locale' => 'de',
            'published_at' => now()->subMinutes(3),
        ]);
        $english = BlogPost::factory()->published()->create([
            'title' => 'English main article',
            'slug' => 'english-main-article',
            'content_locale' => 'en',
            'translation_group' => $german->translation_group,
            'published_at' => now()->subMinutes(2),
        ]);

        return [$german, $english];
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function webPayload(array $overrides = []): array
    {
        return array_replace([
            'title' => 'Blog translation',
            'slug' => 'blog-translation',
            'excerpt' => 'Safe editorial excerpt.',
            'content' => '<p>Safe editorial content.</p>',
            'content_locale' => 'de',
            'tags' => '',
            'status' => 'draft',
        ], $overrides);
    }
}
