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

        $meta['title'] = self::fullTitle($meta['title'] ?? self::SITE_NAME);
        $meta['description'] = self::cleanText($meta['description'] ?? self::defaultDescription(), 180);
        $meta['canonical'] = ($meta['canonical'] ?? null) === false
            ? null
            : self::absoluteUrl($meta['canonical'] ?? $request->url(), $request);
        $meta['image'] = self::absoluteUrl($meta['image'] ?? self::DEFAULT_IMAGE, $request);
        $meta['type'] = $meta['type'] ?? 'website';
        $meta['site_name'] = self::SITE_NAME;
        $meta['robots'] = ! empty($meta['noindex']) ? 'noindex,nofollow' : 'index,follow';
        $meta['locale'] = self::openGraphLocale(app()->getLocale());
        $meta['schema_json'] = self::schemaJson($meta['schema'] ?? null);

        return $meta;
    }

    private static function defaultMeta(string $component): array
    {
        return match ($component) {
            'Welcome' => [
                'title' => 'Airmius Sport Plattform',
                'description' => 'Airmius verbindet Sportler, Trainer, Teams und Vereine mit Kommunikation, Events, Training, Marketplace und digitaler Vereinsorganisation.',
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
                'title' => 'Airmius Preise für Sportler, Trainer, Vereine, Partner und Werbeagentur',
                'description' => 'Faire Airmius Pläne für Sportler, Trainer, Vereine, Eltern, Sponsoren, Anbieter, Verbände und Website-Services für Vereine.',
                'canonical' => route('guest.pricing'),
            ],
            'Guest/Blog/Index' => [
                'title' => 'Airmius Blog',
                'description' => 'Praxiswissen, Updates und Ideen für digitale Sportorganisation, Vereine, Trainer, Teams und Sportler.',
                'canonical' => route('guest.blog.index'),
            ],
            'Guest/Jobs' => [
                'title' => 'Jobs im Sport',
                'description' => 'Finde Jobs, Ehrenamtsrollen und Vereinsaufgaben im Sportumfeld auf Airmius.',
                'canonical' => route('guest.jobs'),
            ],
            'Guest/Sponsors' => [
                'title' => 'Airmius Sponsoren',
                'description' => 'Entdecke Sponsoren und Partner, die Sport, Vereine und Airmius unterstützen.',
                'canonical' => route('guest.sponsors'),
            ],
            'Guest/Werbeagentur' => [
                'title' => 'Werbeagentur für Vereine',
                'description' => 'Airmius unterstützt Vereine mit Websites, digitalen Kampagnen, Sponsoring-Flächen und klaren Online-Prozessen.',
                'canonical' => route('guest.werbeagentur'),
            ],
            'Guest/E-Learning' => [
                'title' => 'E-Learning für Sportorganisation',
                'description' => 'Lerne moderne Sportorganisation mit Airmius: Kommunikation, Trainingsplanung, Datenschutz und digitale Vereinsprozesse einfach erklärt.',
                'canonical' => route('guest.e-learning'),
            ],
            'Guest/Gamification' => [
                'title' => 'Gamification für Sport, Teams und Vereine',
                'description' => 'Motiviere Sportler, Teams und Vereine mit Badges, Fortschritt, Herausforderungen und fairer Gamification in Airmius.',
                'canonical' => route('guest.gamification'),
            ],
            'Guest/Top-Inhalte' => [
                'title' => 'Top Inhalte',
                'description' => 'Entdecke beliebte Inhalte, Themen und Updates rund um Airmius, Sport, Teams und digitale Vereinsarbeit.',
                'canonical' => route('guest.top-inhalte'),
            ],
            'Guest/Marketplace' => [
                'title' => 'Airmius Sport Marketplace',
                'description' => 'Sportfokussierter Marketplace für Produkte, Kurse, Camps und Services. Gäste können direkt ohne Konto bestellen.',
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
                'title' => 'Zertifikat prüfen',
                'description' => 'Öffentliche Prüfung eines Airmius E-Learning Zertifikats.',
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
            'image' => $seo['image'] ?? null,
            'type' => $seo['type'] ?? null,
            'noindex' => $seo['noindex'] ?? null,
            'schema' => $seo['schema'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    private static function blogPostMeta(array $post, bool $isPreview): array
    {
        $title = $post['meta_title'] ?? $post['title'] ?? 'Airmius Blog';
        $slug = $post['slug'] ?? null;

        return [
            'title' => $isPreview ? '[Vorschau] '.$title : $title,
            'description' => $post['meta_description']
                ?? $post['excerpt']
                ?? 'Artikel aus dem Airmius Blog zu Sport, Training, Vereinen und digitaler Organisation.',
            'canonical' => $slug ? route('guest.blog.show', $slug) : null,
            'image' => $post['cover_image'] ?? null,
            'type' => 'article',
            'noindex' => $isPreview,
            'schema' => [
                '@context' => 'https://schema.org',
                '@type' => 'BlogPosting',
                'headline' => $title,
                'description' => self::cleanText($post['meta_description'] ?? $post['excerpt'] ?? ''),
                'image' => ! empty($post['cover_image']) ? [$post['cover_image']] : null,
                'datePublished' => $post['published_at'] ?? null,
                'dateModified' => $post['updated_at'] ?? $post['published_at'] ?? null,
                'author' => ! empty($post['author']['name'])
                    ? ['@type' => 'Person', 'name' => $post['author']['name']]
                    : null,
                'publisher' => ['@type' => 'Organization', 'name' => self::SITE_NAME],
            ],
        ];
    }

    private static function legalMeta(array $props): array
    {
        $title = (string) ($props['title'] ?? 'Rechtliches');

        return [
            'title' => $title,
            'description' => "{$title} von Airmius: rechtliche Informationen, Datenschutz, Nutzungsbedingungen und Hinweise für Nutzer, Vereine und Erziehungsberechtigte.",
        ];
    }

    private static function marketplaceProductMeta(array $product): array
    {
        $title = (string) ($product['title'] ?? 'Marketplace-Angebot');
        $image = $product['image_url'] ?? ($product['gallery_images'][0] ?? null);
        $price = $product['price'] ?? [];
        $grossCents = $price['item_gross_cents'] ?? $price['gross_cents'] ?? $product['price_cents'] ?? null;

        return [
            'title' => "{$title} kaufen",
            'description' => $product['description'] ?? 'Marketplace-Angebot auf Airmius ansehen und als Gast bestellen.',
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
                    'name' => $product['provider_name'] ?? 'Airmius Marketplace',
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
        $name = (string) ($provider['name'] ?? 'Airmius Anbieter');

        return [
            'title' => "{$name} im Airmius Marketplace",
            'description' => $provider['description'] ?? "{$name} Angebote im Airmius Marketplace ansehen.",
            'canonical' => $provider['url'] ?? null,
            'image' => $provider['logo_url'] ?? $provider['cover_url'] ?? null,
        ];
    }

    private static function learningCourseMeta(array $course): array
    {
        return [
            'title' => $course['title'] ?? 'Airmius Sportschule',
            'description' => $course['subtitle'] ?? $course['description'] ?? 'Airmius Sportschule Kurs ansehen.',
            'canonical' => $course['show_url'] ?? null,
            'image' => $course['cover_image'] ?? null,
            'type' => 'article',
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

    private static function openGraphLocale(?string $locale): string
    {
        return match (substr((string) $locale, 0, 2)) {
            'en' => 'en_US',
            'fr' => 'fr_FR',
            'ar' => 'ar_AR',
            default => 'de_DE',
        };
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
        return 'Airmius verbindet Sportler, Teams und Vereine in einer digitalen Sportplattform.';
    }

    private static function arrayValue(array $props, string $key): array
    {
        return is_array($props[$key] ?? null) ? $props[$key] : [];
    }
}
