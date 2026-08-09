<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SeoMeta
{
    private const SITE_NAME = 'Airmius';

    private const DEFAULT_IMAGE = '/img/logo/Airmius-Logo-Light.png';

    public static function fromInertiaPage(array $page, Request $request): array
    {
        $props = is_array($page['props'] ?? null) ? $page['props'] : [];
        $component = (string) ($page['component'] ?? '');

        $meta = array_replace(
            self::defaultMeta($component),
            self::dynamicMeta($component, $props),
            self::propMeta($props['seo'] ?? null),
        );
        $hasExplicitAlternates = array_key_exists('alternates', $meta);

        $meta['title'] = self::fullTitle($meta['title'] ?? self::SITE_NAME);
        $meta['description'] = self::cleanText($meta['description'] ?? self::defaultDescription(), 180);
        $meta['canonical'] = ($meta['canonical'] ?? null) === false
            ? null
            : self::absoluteUrl($meta['canonical'] ?? $request->url(), $request);
        if ($meta['canonical']) {
            $canonicalLocale = SupportedLocale::normalize($meta['canonical_locale'] ?? null)
                ?? app()->getLocale();
            $meta['canonical'] = LocalizedPublicUrl::forLocale($meta['canonical'], $canonicalLocale);
        }
        $meta['image'] = self::absoluteUrl($meta['image'] ?? self::DEFAULT_IMAGE, $request);
        $meta['type'] = $meta['type'] ?? 'website';
        $meta['site_name'] = self::SITE_NAME;
        $meta['robots'] = ! empty($meta['noindex']) ? 'noindex,nofollow' : 'index,follow';
        $meta['locale'] = LocalizedPublicUrl::openGraphLocale(app()->getLocale());
        $meta['alternates'] = $meta['canonical'] && empty($meta['noindex'])
            ? ($hasExplicitAlternates
                ? self::validAlternates($meta['alternates'] ?? [], $request)
                : LocalizedPublicUrl::alternates($meta['canonical']))
            : [];
        $meta['alternate_locales'] = empty($meta['noindex'])
            ? ($hasExplicitAlternates
                ? self::openGraphLocalesFromAlternates($meta['alternates'], $meta['canonical_locale'] ?? app()->getLocale())
                : LocalizedPublicUrl::openGraphAlternates(app()->getLocale()))
            : [];
        $meta['feed'] = ! empty($meta['feed']) && empty($meta['noindex'])
            ? LocalizedPublicUrl::forLocale(self::absoluteUrl($meta['feed'], $request), app()->getLocale())
            : null;
        $meta['schema_json'] = self::schemaJson($meta['schema'] ?? null);

        return $meta;
    }

    private static function defaultMeta(string $component): array
    {
        return match ($component) {
            'Welcome' => [
                'title' => __('guest_seo.pages.welcome.title'),
                'description' => __('guest_seo.pages.welcome.description'),
                'canonical' => route('welcome'),
                'schema' => self::organizationSchema(route('welcome')),
            ],
            'Guest/Vereine' => [
                'title' => __('public_discovery.clubs.meta_title'),
                'description' => __('public_discovery.clubs.meta_description'),
                'canonical' => route('guest.vereine'),
            ],
            'Guest/Events' => [
                'title' => __('public_discovery.events.meta_title'),
                'description' => __('public_discovery.events.meta_description'),
                'canonical' => route('guest.events'),
            ],
            'Guest/Sportarten' => [
                'title' => __('public_discovery.sports.meta_title'),
                'description' => __('public_discovery.sports.meta_description'),
                'canonical' => route('guest.sports'),
            ],
            'Guest/Pricing' => [
                'title' => __('guest_seo.pages.pricing.title'),
                'description' => __('guest_seo.pages.pricing.description'),
                'canonical' => route('guest.pricing'),
            ],
            'Guest/Blog/Index' => [
                'title' => __('guest_seo.pages.blog.title'),
                'description' => __('guest_seo.pages.blog.description'),
                'canonical' => route('guest.blog.index'),
                'feed' => route('guest.blog.rss'),
            ],
            'Guest/Jobs' => [
                'title' => __('guest_seo.pages.jobs.title'),
                'description' => __('guest_seo.pages.jobs.description'),
                'canonical' => route('guest.jobs'),
            ],
            'Guest/Sponsors' => [
                'title' => __('guest_seo.pages.sponsors.title'),
                'description' => __('guest_seo.pages.sponsors.description'),
                'canonical' => route('guest.sponsors'),
            ],
            'Guest/Werbeagentur' => [
                'title' => __('guest_seo.pages.agency.title'),
                'description' => __('guest_seo.pages.agency.description'),
                'canonical' => route('guest.werbeagentur'),
            ],
            'Guest/E-Learning' => [
                'title' => __('guest_seo.pages.learning.title'),
                'description' => __('guest_seo.pages.learning.description'),
                'canonical' => route('guest.e-learning'),
            ],
            'Guest/Gamification' => [
                'title' => __('guest_seo.pages.gamification.title'),
                'description' => __('guest_seo.pages.gamification.description'),
                'canonical' => route('guest.gamification'),
            ],
            'Guest/Top-Inhalte' => [
                'title' => __('guest_seo.pages.top_content.title'),
                'description' => __('guest_seo.pages.top_content.description'),
                'canonical' => route('guest.top-inhalte'),
            ],
            'Guest/Marketplace' => [
                'title' => __('guest_seo.pages.marketplace.title'),
                'description' => __('guest_seo.pages.marketplace.description'),
                'canonical' => route('guest.marketplace'),
            ],
            'Guest/MarketplaceOrderStatus' => [
                'title' => __('commerce.guest_order_pages.status_title'),
                'description' => __('commerce.guest_order_pages.status_description'),
                'canonical' => false,
                'noindex' => true,
            ],
            'Guest/MarketplaceBankTransfer' => [
                'title' => __('commerce.guest_order_pages.payment_title'),
                'description' => __('commerce.guest_order_pages.payment_description'),
                'canonical' => false,
                'noindex' => true,
            ],
            default => [
                'title' => self::SITE_NAME,
                'description' => self::defaultDescription(),
            ],
        };
    }

    private static function dynamicMeta(string $component, array $props): array
    {
        return match ($component) {
            'Guest/Blog/Show' => self::blogPostMeta(self::arrayValue($props, 'post'), (bool) ($props['isPreview'] ?? false)),
            'Legal/Show' => self::legalMeta($props),
            'Guest/MarketplaceProductShow' => self::marketplaceProductMeta(self::arrayValue($props, 'product')),
            'Guest/MarketplaceProviderShow' => self::marketplaceProviderMeta(self::arrayValue($props, 'provider')),
            'Guest/LearningCourseShow' => self::learningCourseMeta(self::arrayValue($props, 'course')),
            'Guest/LearningCertificateVerify' => [
                'title' => __('guest_seo.pages.certificate.title'),
                'description' => __('guest_seo.pages.certificate.description'),
                'noindex' => true,
            ],
            default => [],
        };
    }

    private static function propMeta(mixed $seo): array
    {
        if (! is_array($seo)) {
            return [];
        }

        return array_filter([
            'title' => $seo['title'] ?? null,
            'description' => $seo['description'] ?? null,
            'canonical' => $seo['canonical'] ?? null,
            'canonical_locale' => $seo['canonical_locale'] ?? null,
            'image' => $seo['image'] ?? null,
            'type' => $seo['type'] ?? null,
            'noindex' => $seo['noindex'] ?? null,
            'schema' => $seo['schema'] ?? null,
            'alternates' => $seo['alternates'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    /** @return array<int, array{hreflang: string, href: string}> */
    private static function validAlternates(mixed $alternates, Request $request): array
    {
        if (! is_array($alternates)) {
            return [];
        }

        return collect($alternates)
            ->filter(fn ($alternate): bool => is_array($alternate)
                && in_array($alternate['hreflang'] ?? null, [...SupportedLocale::ALL, 'x-default'], true)
                && filled($alternate['href'] ?? null))
            ->map(fn (array $alternate): array => [
                'hreflang' => $alternate['hreflang'],
                'href' => self::absoluteUrl($alternate['href'], $request),
            ])
            ->unique('hreflang')
            ->values()
            ->all();
    }

    /** @return array<int, string> */
    private static function openGraphLocalesFromAlternates(array $alternates, mixed $currentLocale): array
    {
        $currentLocale = SupportedLocale::normalize($currentLocale) ?? SupportedLocale::DEFAULT;

        return collect($alternates)
            ->pluck('hreflang')
            ->filter(fn (string $locale): bool => $locale !== 'x-default' && $locale !== $currentLocale)
            ->map(fn (string $locale): string => LocalizedPublicUrl::openGraphLocale($locale))
            ->unique()
            ->values()
            ->all();
    }

    private static function blogPostMeta(array $post, bool $isPreview): array
    {
        $title = $post['meta_title'] ?? $post['title'] ?? __('guest_seo.dynamic.blog_title');
        $slug = $post['slug'] ?? null;

        return [
            'title' => $isPreview ? '[Vorschau] '.$title : $title,
            'description' => $post['meta_description']
                ?? $post['excerpt']
                ?? __('guest_seo.dynamic.blog_description'),
            'canonical' => $slug ? route('guest.blog.show', $slug) : null,
            'image' => $post['cover_image'] ?? null,
            'type' => 'article',
            'noindex' => $isPreview,
            'feed' => route('guest.blog.rss'),
            'schema' => [
                '@context' => 'https://schema.org',
                '@type' => 'BlogPosting',
                'headline' => $title,
                'description' => self::cleanText($post['meta_description'] ?? $post['excerpt'] ?? ''),
                'image' => ! empty($post['cover_image']) ? [$post['cover_image']] : null,
                'datePublished' => $post['published_at'] ?? null,
                'dateModified' => $post['updated_at'] ?? $post['published_at'] ?? null,
                'inLanguage' => $post['content_locale'] ?? SupportedLocale::DEFAULT,
                'author' => ! empty($post['author']['name'])
                    ? ['@type' => 'Person', 'name' => $post['author']['name']]
                    : null,
                'publisher' => ['@type' => 'Organization', 'name' => self::SITE_NAME],
            ],
        ];
    }

    private static function legalMeta(array $props): array
    {
        $title = (string) ($props['title'] ?? __('guest_seo.dynamic.legal_title'));

        return [
            'title' => $title,
            'description' => __('guest_seo.dynamic.legal_description', ['title' => $title]),
        ];
    }

    private static function marketplaceProductMeta(array $product): array
    {
        $title = (string) ($product['title'] ?? __('guest_seo.dynamic.product_name'));
        $image = $product['image_url'] ?? ($product['gallery_images'][0] ?? null);
        $price = $product['price'] ?? [];
        $grossCents = $price['item_gross_cents'] ?? $price['gross_cents'] ?? $product['price_cents'] ?? null;

        return [
            'title' => __('guest_seo.dynamic.product_title', ['title' => $title]),
            'description' => $product['description'] ?? __('guest_seo.dynamic.product_description'),
            'canonical' => ! empty($product['id']) ? route('guest.marketplace.products.show', $product['id']) : null,
            'image' => $image,
            'type' => 'product',
            'schema' => [
                '@context' => 'https://schema.org',
                '@type' => 'Product',
                'name' => $title,
                'description' => self::cleanText($product['description'] ?? ''),
                'image' => array_values(array_filter($product['gallery_images'] ?? [$image])),
                'sku' => $product['sku'] ?? null,
                'brand' => [
                    '@type' => 'Brand',
                    'name' => $product['provider_name'] ?? __('guest_seo.dynamic.marketplace_provider'),
                ],
                'offers' => [
                    '@type' => 'Offer',
                    'url' => ! empty($product['id']) ? route('guest.marketplace.products.show', $product['id']) : null,
                    'priceCurrency' => $price['currency'] ?? $product['currency'] ?? 'EUR',
                    'price' => is_numeric($grossCents) ? number_format(((int) $grossCents) / 100, 2, '.', '') : null,
                    'availability' => ! empty($product['manages_stock']) && (int) ($product['stock_quantity'] ?? 0) <= 0
                        ? 'https://schema.org/OutOfStock'
                        : 'https://schema.org/InStock',
                    'itemCondition' => 'https://schema.org/NewCondition',
                ],
            ],
        ];
    }

    private static function marketplaceProviderMeta(array $provider): array
    {
        $name = (string) ($provider['name'] ?? __('guest_seo.dynamic.provider_name'));

        return [
            'title' => __('guest_seo.dynamic.provider_title', ['name' => $name]),
            'description' => $provider['description'] ?? __('guest_seo.dynamic.provider_description', ['name' => $name]),
            'canonical' => $provider['url'] ?? null,
            'image' => $provider['logo_url'] ?? $provider['cover_url'] ?? null,
        ];
    }

    private static function learningCourseMeta(array $course): array
    {
        $title = $course['title'] ?? __('guest_seo.dynamic.learning_title');

        return [
            'title' => $title,
            'description' => $course['subtitle'] ?? $course['description'] ?? __('guest_seo.dynamic.learning_description'),
            'canonical' => $course['show_url'] ?? null,
            'canonical_locale' => $course['language'] ?? SupportedLocale::DEFAULT,
            'image' => $course['cover_image'] ?? null,
            'type' => 'article',
            'schema' => [
                '@context' => 'https://schema.org',
                '@type' => 'Course',
                'name' => $title,
                'description' => self::cleanText($course['subtitle'] ?? $course['description'] ?? ''),
                'inLanguage' => $course['language'] ?? SupportedLocale::DEFAULT,
                'provider' => [
                    '@type' => 'Organization',
                    'name' => self::SITE_NAME,
                ],
            ],
        ];
    }

    private static function fullTitle(string $title): string
    {
        $title = trim($title);

        if ($title === '' || Str::contains($title, self::SITE_NAME, true)) {
            return $title ?: self::SITE_NAME;
        }

        return "{$title} | ".self::SITE_NAME;
    }

    private static function cleanText(?string $value, int $limit = 240): string
    {
        $text = html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        return Str::limit($text, $limit, '');
    }

    private static function absoluteUrl(?string $value, Request $request): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return url(self::DEFAULT_IMAGE);
        }

        if (Str::startsWith($value, ['http://', 'https://'])) {
            return $value;
        }

        if (Str::startsWith($value, '//')) {
            return $request->getScheme().':'.$value;
        }

        return url($value);
    }

    private static function schemaJson(mixed $schema): ?string
    {
        if (! is_array($schema) || $schema === []) {
            return null;
        }

        return json_encode(
            self::filterNullValues($schema),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
        );
    }

    private static function filterNullValues(array $value): array
    {
        $filtered = [];

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $item = self::filterNullValues($item);
            }

            if ($item === null || $item === [] || $item === '') {
                continue;
            }

            $filtered[$key] = $item;
        }

        return $filtered;
    }

    private static function organizationSchema(string $url): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => self::SITE_NAME,
            'url' => $url,
            'logo' => url(self::DEFAULT_IMAGE),
        ];
    }

    private static function defaultDescription(): string
    {
        return __('guest_seo.default_description');
    }

    private static function arrayValue(array $props, string $key): array
    {
        return is_array($props[$key] ?? null) ? $props[$key] : [];
    }
}
