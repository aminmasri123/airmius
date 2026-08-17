<?php

namespace Tests\Feature;

use App\Support\LocalizedPublicUrl;
use App\Support\SupportedLocale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class LocalizedWebManifestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_manifest_is_locale_isolated_stateless_and_conditionally_cacheable(): void
    {
        $expectations = [
            'de' => ['ltr', 'Airmius – All-in-One-Sportplattform', '/'],
            'en' => ['ltr', 'Airmius – All-in-One Sports Platform', '/?locale=en'],
            'fr' => ['ltr', 'Airmius – Plateforme sportive tout-en-un', '/?locale=fr'],
            'ar' => ['rtl', 'Airmius – منصة رياضية متكاملة', '/?locale=ar'],
        ];
        $etags = [];

        foreach ($expectations as $locale => [$direction, $name, $startUrl]) {
            $url = LocalizedPublicUrl::forLocale(route('site.webmanifest'), $locale);
            $response = $this->get($url);

            $response->assertOk()
                ->assertHeader('Content-Type', 'application/manifest+json; charset=UTF-8')
                ->assertHeader('Content-Language', $locale)
                ->assertHeader('X-Airmius-Text-Direction', $direction)
                ->assertHeaderMissing('Set-Cookie')
                ->assertJsonPath('name', $name)
                ->assertJsonPath('short_name', 'Airmius')
                ->assertJsonPath('lang', $locale)
                ->assertJsonPath('dir', $direction)
                ->assertJsonPath('id', '/')
                ->assertJsonPath('start_url', $startUrl)
                ->assertJsonPath('scope', '/')
                ->assertJsonPath('orientation', 'any')
                ->assertJsonPath('display', 'standalone')
                ->assertJsonPath('launch_handler.client_mode', 'navigate-existing')
                ->assertJsonCount(4, 'icons')
                ->assertJsonCount(3, 'shortcuts');

            foreach (['public', 'max-age=3600', 's-maxage=86400', 'stale-while-revalidate=86400'] as $directive) {
                $this->assertStringContainsString($directive, (string) $response->headers->get('Cache-Control'));
            }

            foreach (['Accept-Language', 'X-Locale', 'X-App-Locale', 'X-Airmius-Locale'] as $vary) {
                $this->assertStringContainsString($vary, (string) $response->headers->get('Vary'));
            }

            $manifest = $response->json();
            $this->assertSame(
                [
                    $this->localizedPath('guest.vereine', $locale),
                    $this->localizedPath('guest.marketplace', $locale),
                    $this->localizedPath('guest.e-learning', $locale),
                ],
                array_column($manifest['shortcuts'], 'url'),
            );
            $this->assertFalse($manifest['prefer_related_applications']);

            $etags[$locale] = (string) $response->headers->get('ETag');
            $this->assertNotSame('', $etags[$locale]);
            $this->withHeader('If-None-Match', $etags[$locale])
                ->get($url)
                ->assertNotModified();
        }

        $this->assertCount(4, array_unique($etags), 'Every manifest language needs a distinct cached representation.');
    }

    public function test_manifest_icons_match_declared_dimensions_and_maskable_safe_zone(): void
    {
        $expected = [
            'Airmius-PWA-180.png' => [180, 180],
            'Airmius-PWA-192.png' => [192, 192],
            'Airmius-PWA-512.png' => [512, 512],
            'Airmius-PWA-Maskable-192.png' => [192, 192],
            'Airmius-PWA-Maskable-512.png' => [512, 512],
        ];

        foreach ($expected as $file => $dimensions) {
            $path = public_path('img/logo/'.$file);
            $this->assertFileExists($path);
            $image = getimagesize($path);
            $this->assertNotFalse($image);
            $this->assertSame($dimensions[0], $image[0], $file.' width mismatch.');
            $this->assertSame($dimensions[1], $image[1], $file.' height mismatch.');
            $this->assertSame('image/png', $image['mime'], $file.' MIME mismatch.');
        }

        $regular = imagecreatefrompng(public_path('img/logo/Airmius-PWA-512.png'));
        $maskable = imagecreatefrompng(public_path('img/logo/Airmius-PWA-Maskable-512.png'));
        $this->assertInstanceOf(\GdImage::class, $regular);
        $this->assertInstanceOf(\GdImage::class, $maskable);

        $regularCornerAlpha = (imagecolorat($regular, 0, 0) >> 24) & 0x7F;
        $maskableCorner = imagecolorsforindex($maskable, imagecolorat($maskable, 0, 0));
        $this->assertSame(127, $regularCornerAlpha, 'The regular icon must preserve transparent corners.');
        $this->assertSame([7, 16, 29, 0], array_values($maskableCorner), 'The maskable icon needs an opaque Airmius background.');

        $maximumDistance = 0.0;
        for ($y = 0; $y < 512; $y++) {
            for ($x = 0; $x < 512; $x++) {
                $color = imagecolorsforindex($maskable, imagecolorat($maskable, $x, $y));
                $difference = abs($color['red'] - 7) + abs($color['green'] - 16) + abs($color['blue'] - 29);

                if ($difference <= 8) {
                    continue;
                }

                $maximumDistance = max($maximumDistance, hypot($x - 255.5, $y - 255.5));
            }
        }

        $this->assertLessThanOrEqual(512 * 0.4, $maximumDistance, 'Maskable logo pixels must stay inside the specification safe circle.');
        imagedestroy($regular);
        imagedestroy($maskable);
    }

    public function test_initial_html_discovers_the_matching_manifest_and_exact_icons(): void
    {
        $manifestUrl = LocalizedPublicUrl::forLocale(route('site.webmanifest', ['v' => 10]), 'ar');

        $this->get(route('welcome', ['locale' => 'ar']))
            ->assertOk()
            ->assertSee('<link rel="manifest" href="'.e($manifestUrl).'">', false)
            ->assertSee('Airmius-PWA-192.png?v=10', false)
            ->assertSee('Airmius-PWA-512.png?v=10', false)
            ->assertSee('Airmius-PWA-180.png?v=10', false)
            ->assertDontSee('Airmius-Mark.png?v=8', false);
    }

    public function test_manifest_catalogs_and_generation_contract_have_locale_parity(): void
    {
        $catalogs = collect(SupportedLocale::ALL)->mapWithKeys(fn (string $locale): array => [
            $locale => Arr::dot(require lang_path("{$locale}/guest_manifest.php")),
        ]);
        $sourceKeys = array_keys($catalogs[SupportedLocale::DEFAULT]);

        foreach ($catalogs as $locale => $catalog) {
            $this->assertSame($sourceKeys, array_keys($catalog), "Manifest catalog key mismatch for {$locale}.");
            $this->assertNotContains('', array_values($catalog), "Manifest catalog contains an empty value for {$locale}.");
        }
        $this->assertMatchesRegularExpression('/\p{Arabic}/u', implode(' ', $catalogs['ar']));

        $controller = File::get(app_path('Http/Controllers/PublicWebManifestController.php'));
        $routes = File::get(base_path('routes/web.php'));
        $builder = File::get(base_path('scripts/build_pwa_icons.php'));

        $this->assertStringContainsString("'public:webmanifest:v2:'.\$locale", $controller);
        $this->assertStringContainsString("'orientation' => 'any'", $controller);
        $this->assertStringNotContainsString('Airmius-Mark.png', $controller);
        $this->assertStringContainsString('withoutMiddleware([StartSession::class, ShareErrorsFromSession::class, ValidateCsrfToken::class])', $routes);
        $this->assertStringContainsString('Airmius-PWA-Maskable-', $builder);
        $this->assertStringContainsString('0.58', $builder);
    }

    private function localizedPath(string $routeName, string $locale): string
    {
        return LocalizedPublicUrl::forLocale(route($routeName, absolute: false), $locale);
    }
}
