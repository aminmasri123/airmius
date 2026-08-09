<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Support\LocalizedPublicUrl;
use App\Support\SupportedLocale;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class PublicBlogFeedController extends Controller
{
    private const MAX_ITEMS = 30;

    private const CACHE_MINUTES = 10;

    public function __invoke(Request $request): Response
    {
        $locale = SupportedLocale::normalize(app()->getLocale()) ?? SupportedLocale::DEFAULT;
        $cacheKey = 'public:blog-rss:v5:'.$locale.':'.sha1(url('/'));

        /** @var array{xml: string, etag: string, last_modified: string|null} $feed */
        $feed = Cache::remember(
            $cacheKey,
            now()->addMinutes(self::CACHE_MINUTES),
            fn (): array => $this->buildFeed($locale),
        );

        $response = response($feed['xml'], 200, [
            'Content-Type' => 'application/rss+xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=300, s-maxage=600',
        ]);
        $response->setEtag($feed['etag']);

        if ($feed['last_modified']) {
            $response->setLastModified(Carbon::parse($feed['last_modified']));
        }

        $response->isNotModified($request);

        return $response;
    }

    /** @return array{xml: string, etag: string, last_modified: string|null} */
    private function buildFeed(string $locale): array
    {
        $posts = BlogPost::query()
            ->published()
            ->preferredForLocale($locale)
            ->with(['author:id,name', 'blogCategory:id,name,slug'])
            ->latest('published_at')
            ->take(self::MAX_ITEMS)
            ->get([
                'id',
                'author_id',
                'blog_category_id',
                'title',
                'slug',
                'content_locale',
                'excerpt',
                'content',
                'category',
                'published_at',
                'updated_at',
            ]);

        $feedUrl = LocalizedPublicUrl::forLocale(route('guest.blog.rss'), $locale);
        $blogUrl = LocalizedPublicUrl::forLocale(route('guest.blog.index'), $locale);
        $lastModified = $posts
            ->map(fn (BlogPost $post) => $post->updated_at ?? $post->published_at)
            ->filter()
            ->sortDesc()
            ->first();
        if ($lastModified?->isFuture()) {
            $lastModified = now();
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:dc="http://purl.org/dc/elements/1.1/">'."\n";
        $xml .= "  <channel>\n";
        $xml .= '    <title>'.$this->xmlText(__('guest_seo.pages.blog.title'))."</title>\n";
        $xml .= '    <link>'.$this->xmlText($blogUrl)."</link>\n";
        $xml .= '    <description>'.$this->xmlText(__('guest_seo.pages.blog.description'))."</description>\n";
        $xml .= '    <language>'.$this->rssLanguage($locale)."</language>\n";
        $xml .= '    <atom:link href="'.$this->xmlText($feedUrl).'" rel="self" type="application/rss+xml" />'."\n";

        foreach (SupportedLocale::ALL as $alternateLocale) {
            $alternateUrl = LocalizedPublicUrl::forLocale(route('guest.blog.rss'), $alternateLocale);
            $xml .= '    <atom:link href="'.$this->xmlText($alternateUrl).'" rel="alternate" type="application/rss+xml" hreflang="'.$alternateLocale.'" />'."\n";
        }

        if ($lastModified) {
            $xml .= '    <lastBuildDate>'.$lastModified->toRfc2822String()."</lastBuildDate>\n";
        }

        foreach ($posts as $post) {
            $localizedPostUrl = LocalizedPublicUrl::forLocale(
                route('guest.blog.show', $post->slug),
                $post->content_locale,
            );
            $description = $post->excerpt
                ?: str($post->content)->stripTags()->squish()->limit(240)->toString();

            $xml .= "    <item>\n";
            $xml .= '      <title>'.$this->xmlText($post->title)."</title>\n";
            $xml .= '      <link>'.$this->xmlText($localizedPostUrl)."</link>\n";
            $xml .= '      <guid isPermaLink="true">'.$this->xmlText(route('guest.blog.show', $post->slug))."</guid>\n";
            $xml .= '      <description>'.$this->xmlText($description)."</description>\n";
            $xml .= '      <dc:language>'.$this->xmlText($post->content_locale)."</dc:language>\n";
            if ($post->author?->name) {
                $xml .= '      <dc:creator>'.$this->xmlText($post->author->name)."</dc:creator>\n";
            }
            if ($post->blogCategory?->name || $post->category) {
                $xml .= '      <category>'.$this->xmlText($post->blogCategory?->name ?: $post->category)."</category>\n";
            }
            if ($post->published_at) {
                $xml .= '      <pubDate>'.$post->published_at->toRfc2822String()."</pubDate>\n";
            }
            $xml .= "    </item>\n";
        }

        $xml .= "  </channel>\n";
        $xml .= '</rss>';

        return [
            'xml' => $xml,
            'etag' => sha1($xml),
            'last_modified' => $lastModified?->toAtomString(),
        ];
    }

    private function rssLanguage(string $locale): string
    {
        return match ($locale) {
            'en' => 'en-US',
            'fr' => 'fr-FR',
            'ar' => 'ar',
            default => 'de-DE',
        };
    }

    private function xmlText(mixed $value): string
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', '', (string) $value) ?? '';

        return e($value);
    }
}
