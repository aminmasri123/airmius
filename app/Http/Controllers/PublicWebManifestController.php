<?php

namespace App\Http\Controllers;

use App\Support\LocalizedPublicUrl;
use App\Support\SupportedLocale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class PublicWebManifestController extends Controller
{
    private const CACHE_HOURS = 24;

    public function __invoke(Request $request): Response
    {
        $locale = SupportedLocale::normalize(app()->getLocale()) ?? SupportedLocale::DEFAULT;
        $cacheKey = 'public:webmanifest:v2:'.$locale.':'.sha1(url('/'));

        /** @var array{json: string, etag: string} $manifest */
        $manifest = Cache::remember(
            $cacheKey,
            now()->addHours(self::CACHE_HOURS),
            fn (): array => $this->buildManifest($locale),
        );

        $response = response($manifest['json'], 200, [
            'Content-Type' => 'application/manifest+json; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600, s-maxage=86400, stale-while-revalidate=86400',
        ]);
        $response->setEtag($manifest['etag']);
        $response->isNotModified($request);

        return $response;
    }

    /** @return array{json: string, etag: string} */
    private function buildManifest(string $locale): array
    {
        $icon = '/img/logo/Airmius-PWA-192.png';
        $manifest = [
            'name' => __('guest_manifest.name'),
            'short_name' => 'Airmius',
            'description' => __('guest_manifest.description'),
            'lang' => $locale,
            'dir' => SupportedLocale::direction($locale),
            'id' => '/',
            'start_url' => $this->localizedPath('welcome', $locale),
            'scope' => '/',
            'display' => 'standalone',
            'display_override' => ['window-controls-overlay', 'standalone', 'minimal-ui'],
            'orientation' => 'any',
            'background_color' => '#07101D',
            'theme_color' => '#07101D',
            'categories' => ['sports', 'fitness', 'social', 'productivity'],
            'icons' => [
                $this->icon('/img/logo/Airmius-PWA-192.png', 192, 'any'),
                $this->icon('/img/logo/Airmius-PWA-512.png', 512, 'any'),
                $this->icon('/img/logo/Airmius-PWA-Maskable-192.png', 192, 'maskable'),
                $this->icon('/img/logo/Airmius-PWA-Maskable-512.png', 512, 'maskable'),
            ],
            'shortcuts' => [
                $this->shortcut('clubs', 'guest.vereine', $locale, $icon),
                $this->shortcut('marketplace', 'guest.marketplace', $locale, $icon),
                $this->shortcut('learning', 'guest.e-learning', $locale, $icon),
            ],
            'prefer_related_applications' => false,
            'launch_handler' => [
                'client_mode' => 'navigate-existing',
            ],
        ];
        $json = json_encode(
            $manifest,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        return [
            'json' => $json,
            'etag' => sha1($json),
        ];
    }

    /** @return array{src: string, sizes: string, type: string, purpose: string} */
    private function icon(string $src, int $size, string $purpose): array
    {
        return [
            'src' => $src,
            'sizes' => "{$size}x{$size}",
            'type' => 'image/png',
            'purpose' => $purpose,
        ];
    }

    /** @return array{name: string, short_name: string, description: string, url: string, icons: array<int, array{src: string, sizes: string, type: string}>} */
    private function shortcut(string $key, string $routeName, string $locale, string $icon): array
    {
        return [
            'name' => __("guest_manifest.shortcuts.{$key}.name"),
            'short_name' => __("guest_manifest.shortcuts.{$key}.short_name"),
            'description' => __("guest_manifest.shortcuts.{$key}.description"),
            'url' => $this->localizedPath($routeName, $locale),
            'icons' => [[
                'src' => $icon,
                'sizes' => '192x192',
                'type' => 'image/png',
            ]],
        ];
    }

    private function localizedPath(string $routeName, string $locale): string
    {
        return LocalizedPublicUrl::forLocale(route($routeName, absolute: false), $locale);
    }
}
