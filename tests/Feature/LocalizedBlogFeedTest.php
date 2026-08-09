<?php

namespace Tests\Feature;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\User;
use App\Support\LocalizedPublicUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class LocalizedBlogFeedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_public_blog_feed_is_locale_isolated_cacheable_and_valid_xml(): void
    {
        $author = User::factory()->create(['name' => 'Airmius Redaktion']);
        $post = BlogPost::factory()->published()->create([
            'author_id' => $author->id,
            'published_by' => $author->id,
            'title' => 'Training & Team <2030>',
            'slug' => 'training-team-2030',
            'excerpt' => 'Ein öffentlicher Beitrag für Sportler, Teams und Vereine.',
            'category' => 'Training',
            'published_at' => now()->subHour(),
        ]);

        $expectations = [
            'de' => ['de-DE', 'Airmius Blog'],
            'en' => ['en-US', 'Practical knowledge, updates and ideas'],
            'fr' => ['fr-FR', 'Blog Airmius'],
            'ar' => ['ar', 'مدونة Airmius'],
        ];
        $etags = [];

        foreach ($expectations as $locale => [$rssLanguage, $localizedCopy]) {
            $url = LocalizedPublicUrl::forLocale(route('guest.blog.rss'), $locale);
            $localizedPostUrl = LocalizedPublicUrl::forLocale(route('guest.blog.show', $post), 'de');
            $response = $this->get($url);

            $response->assertOk()
                ->assertHeader('Content-Language', $locale)
                ->assertHeader('X-Airmius-Text-Direction', $locale === 'ar' ? 'rtl' : 'ltr')
                ->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8')
                ->assertHeaderMissing('Set-Cookie')
                ->assertSee("<language>{$rssLanguage}</language>", false)
                ->assertSee($localizedCopy, false)
                ->assertSee('<link>'.e($localizedPostUrl).'</link>', false)
                ->assertSee('<guid isPermaLink="true">'.e(route('guest.blog.show', $post)).'</guid>', false)
                ->assertSee('<dc:creator>Airmius Redaktion</dc:creator>', false)
                ->assertSee('<dc:language>de</dc:language>', false)
                ->assertSee('<title>Training &amp; Team &lt;2030&gt;</title>', false)
                ->assertDontSee('<author>Airmius Redaktion</author>', false);

            $this->assertNotFalse(simplexml_load_string((string) $response->getContent()));
            $this->assertNotEmpty($response->headers->get('Last-Modified'));
            $etags[$locale] = (string) $response->headers->get('ETag');

            $this->withHeader('If-None-Match', $etags[$locale])
                ->get($url)
                ->assertNotModified();
        }

        $this->assertCount(4, array_unique($etags), 'Each localized feed must have its own representation and ETag.');
    }

    public function test_feed_is_bounded_and_exposes_reciprocal_locale_links(): void
    {
        $author = User::factory()->create();

        foreach (range(1, 35) as $position) {
            BlogPost::factory()->published()->create([
                'author_id' => $author->id,
                'published_by' => $author->id,
                'title' => "Feed Beitrag {$position}",
                'slug' => "feed-beitrag-{$position}",
                'published_at' => now()->subMinutes($position),
            ]);
        }

        $response = $this->get(LocalizedPublicUrl::forLocale(route('guest.blog.rss'), 'fr'));
        $content = (string) $response->getContent();

        $response->assertOk();
        $this->assertSame(30, substr_count($content, '<item>'));

        foreach (['de', 'en', 'fr', 'ar'] as $locale) {
            $response->assertSee(
                'href="'.e(LocalizedPublicUrl::forLocale(route('guest.blog.rss'), $locale)).'" rel="alternate" type="application/rss+xml" hreflang="'.$locale.'"',
                false,
            );
        }
    }

    public function test_blog_html_advertises_the_matching_feed_and_keeps_client_seo_localized(): void
    {
        $category = BlogCategory::query()->create([
            'name' => 'Laufen',
            'slug' => 'laufen',
            'description' => null,
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $post = BlogPost::factory()->published()->create([
            'blog_category_id' => $category->id,
            'category' => $category->name,
        ]);

        $arabicFeed = LocalizedPublicUrl::forLocale(route('guest.blog.rss'), 'ar');
        $this->get(route('guest.blog.index', ['locale' => 'ar']))
            ->assertOk()
            ->assertSee(
                '<link rel="alternate" type="application/rss+xml" href="'.e($arabicFeed).'" title="مدونة Airmius"',
                false,
            )
            ->assertInertia(fn ($page) => $page
                ->where('seo.title', 'مدونة Airmius')
                ->where('seo.description', __('guest_seo.pages.blog.description', locale: 'ar')));

        $this->get(route('guest.blog.category', ['blogCategory' => $category->slug, 'locale' => 'en']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('seo.title', 'Laufen in the Airmius Blog')
                ->where('seo.description', __('guest_seo.pages.blog.description', locale: 'en')));

        $frenchFeed = LocalizedPublicUrl::forLocale(route('guest.blog.rss'), 'fr');
        $this->get(route('guest.blog.show', ['blogPost' => $post->slug, 'locale' => 'fr']))
            ->assertOk()
            ->assertSee(
                '<link rel="alternate" type="application/rss+xml" href="'.e($frenchFeed).'" title="Blog Airmius"',
                false,
            );
    }

    public function test_blog_feed_contract_stays_lazy_stateless_and_client_discoverable(): void
    {
        $controller = File::get(app_path('Http/Controllers/PublicBlogFeedController.php'));
        $middleware = File::get(app_path('Http/Middleware/SetLocale.php'));
        $seoHead = File::get(resource_path('js/Components/Guest/SeoHead.vue'));

        $this->assertStringContainsString('->take(self::MAX_ITEMS)', $controller);
        $this->assertStringContainsString("'public:blog-rss:v5:'.\$locale", $controller);
        $this->assertStringContainsString("routeIs('robots', 'sitemap', 'guest.blog.rss', 'site.webmanifest')", $middleware);
        $this->assertStringContainsString('const resolvedFeedUrl = computed', $seoHead);
        $this->assertStringContainsString('type="application/rss+xml"', $seoHead);
        $this->assertStringContainsString(':feed="route(\'guest.blog.rss\')"', File::get(resource_path('js/Pages/Guest/Blog/Index.vue')));
        $this->assertStringContainsString(':feed="route(\'guest.blog.rss\')"', File::get(resource_path('js/Pages/Guest/Blog/Show.vue')));
    }
}
