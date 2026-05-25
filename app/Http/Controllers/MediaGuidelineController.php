<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\MediaOptimizer;
use App\Support\UploadStorage;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MediaGuidelineController extends Controller
{
    public function __construct(private MediaOptimizer $mediaOptimizer) {}

    public function index()
    {
        return Inertia::render('Auth/Dashboard/MediaGuidelines/Index', [
            'guidelines' => $this->guidelines(),
            'loginSlider' => $this->loginSliderPayload(),
            'visuals' => $this->visualsForAdmin(),
        ]);
    }

    public function updateVisuals(Request $request)
    {
        $definitions = $this->visualDefinitions();

        $request->validate([
            'login_slider_sources' => ['nullable', 'array'],
            'login_slider_sources.*' => ['nullable', 'string', 'max:2048'],
            'login_slider_uploads' => ['nullable', 'array'],
            'login_slider_uploads.*' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'sources' => ['nullable', 'array'],
            'sources.*' => ['nullable', 'string', 'max:2048'],
            'uploads' => ['nullable', 'array'],
            'uploads.*' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ]);

        $loginSliderSources = collect($request->input('login_slider_sources', []))
            ->map(fn ($source) => trim((string) $source))
            ->filter()
            ->values();

        foreach ($request->file('login_slider_uploads', []) as $file) {
            if ($file) {
                $loginSliderSources->push($this->mediaOptimizer->store($file, 'login/visuals')['path']);
            }
        }

        Setting::setValue(
            'login_visual_slider',
            $loginSliderSources->isNotEmpty()
                ? $loginSliderSources->values()->toJson(JSON_UNESCAPED_SLASHES)
                : json_encode($this->defaultLoginSliderSources(), JSON_UNESCAPED_SLASHES)
        );

        foreach ($definitions as $key => $definition) {
            $request->validate([
                "sources.{$key}" => ['nullable', 'string', 'max:2048'],
                "uploads.{$key}" => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            ]);

            $source = trim((string) $request->input("sources.{$key}", ''));

            if ($request->hasFile("uploads.{$key}")) {
                $source = $this->mediaOptimizer->store($request->file("uploads.{$key}"), $definition['upload_dir'])['path'];
            }

            Setting::setValue($definition['setting_key'], $source ?: $definition['default']);
        }

        return back()->with('success', 'Bildquellen wurden gespeichert.');
    }

    private function guidelines(): array
    {
        return [
                [
                    'category' => 'Profil',
                    'name' => 'Profilbild',
                    'dimensions' => '800 x 800 px',
                    'ratio' => '1:1',
                    'formats' => 'JPG, PNG, WebP',
                    'max_size' => 'bis 8 MB',
                    'note' => 'Quadratisch hochladen. Das Bild wird rund oder quadratisch zugeschnitten angezeigt.',
                    'edit_hint' => 'Bearbeitung im jeweiligen Nutzerprofil.',
                ],
                [
                    'category' => 'Profil',
                    'name' => 'Profil-/Vereins-Cover',
                    'dimensions' => '1600 x 500 px',
                    'ratio' => '3.2:1',
                    'formats' => 'JPG, PNG, WebP',
                    'max_size' => 'bis 8 MB',
                    'note' => 'Wichtige Inhalte mittig platzieren, weil mobile Ansichten seitlich beschneiden können.',
                    'edit_hint' => 'Bearbeitung im Profil, Verein oder Team.',
                ],
                [
                    'category' => 'Login',
                    'name' => 'Login-Slider rechts',
                    'dimensions' => '1080 x 1920 px',
                    'ratio' => '9:16',
                    'formats' => 'PNG, WebP, JPG',
                    'max_size' => 'bis 3 MB',
                    'note' => 'Hochformat für die rechte Login-Seite. Wichtige Texte und Logos mittig platzieren; der Code zeigt die Bilder in einem 9:16-Frame.',
                    'visual_keys' => ['login_slider'],
                ],
                [
                    'category' => 'Verein & Team',
                    'name' => 'Vereinslogo / Teamlogo',
                    'dimensions' => '800 x 800 px',
                    'ratio' => '1:1',
                    'formats' => 'PNG, WebP, JPG',
                    'max_size' => 'bis 8 MB',
                    'note' => 'Am besten quadratisch mit ruhigem Hintergrund oder transparentem PNG.',
                    'edit_hint' => 'Bearbeitung direkt auf der Verein- oder Teamseite.',
                ],
                [
                    'category' => 'Blog',
                    'name' => 'Blog-Cover',
                    'dimensions' => '1600 x 900 px',
                    'ratio' => '16:9',
                    'formats' => 'JPG, PNG, WebP',
                    'max_size' => 'bis 8 MB',
                    'note' => 'Querformat für Blogliste, Detailseite und Social-Sharing-Vorschau.',
                    'edit_hint' => 'Bearbeitung beim jeweiligen Blogbeitrag.',
                ],
                [
                    'category' => 'Feed',
                    'name' => 'Post-Bild',
                    'dimensions' => '1200 x 1200 px',
                    'ratio' => '1:1',
                    'formats' => 'JPG, PNG, WebP',
                    'max_size' => 'bis 8 MB',
                    'note' => 'Quadratisch funktioniert im Feed am stabilsten. Alternativ 1600 x 900 px für Querformat.',
                    'edit_hint' => 'Wird beim Erstellen oder Bearbeiten eines Beitrags gesetzt.',
                ],
                [
                    'category' => 'Video',
                    'name' => 'Video-Thumbnail',
                    'dimensions' => '1280 x 720 px',
                    'ratio' => '16:9',
                    'formats' => 'JPG, PNG, WebP',
                    'max_size' => 'bis 8 MB',
                    'note' => 'Klarer Titelbereich und Motiv in der Mitte. Ideal für Vorschauen und externe Shares.',
                    'edit_hint' => 'Bearbeitung beim jeweiligen Video/Inhalt.',
                ],
                [
                    'category' => 'Marketplace',
                    'name' => 'Produktbild Kachel',
                    'dimensions' => '1200 x 1200 px',
                    'ratio' => '1:1',
                    'formats' => 'JPG, PNG, WebP',
                    'max_size' => 'bis 8 MB',
                    'note' => 'Produkt gut ausleuchten, wenig Text im Bild, einheitlicher Hintergrund.',
                    'edit_hint' => 'Bearbeitung beim jeweiligen Marketplace-Produkt.',
                ],
                [
                    'category' => 'Marketplace',
                    'name' => 'Produktbild Detailseite',
                    'dimensions' => '1600 x 1000 px',
                    'ratio' => '16:10',
                    'formats' => 'JPG, PNG, WebP',
                    'max_size' => 'bis 8 MB',
                    'note' => 'Funktioniert für große Detailansichten. Wichtiges Motiv mittig halten.',
                    'edit_hint' => 'Bearbeitung beim jeweiligen Marketplace-Produkt.',
                ],
                [
                    'category' => 'Marketplace',
                    'name' => 'Marketplace Hero / Featured Deal',
                    'dimensions' => '1600 x 900 px',
                    'ratio' => '16:9',
                    'formats' => 'JPG, PNG, WebP',
                    'max_size' => 'bis 8 MB',
                    'note' => 'Ideal für hervorgehobene Angebote, Banner und Social-Vorschauen.',
                    'visual_keys' => ['marketplace_hero_banner', 'marketplace_sale_banner'],
                ],
                [
                    'category' => 'Outfit-Abo',
                    'name' => 'Dashboard Hero',
                    'dimensions' => '1920 x 1080 px',
                    'ratio' => '16:9',
                    'formats' => 'WebP, JPG, PNG',
                    'max_size' => 'bis 8 MB',
                    'note' => 'Empfohlenes neues Format für die Dashboard-Ansicht. Querformat nutzen; wichtige Texte, Logo und Hauptmotiv mittig platzieren.',
                    'visual_keys' => ['outfit_subscription_hero'],
                ],
                [
                    'category' => 'Outfit-Abo',
                    'name' => 'Social / Story Motiv',
                    'dimensions' => '1080 x 1920 px',
                    'ratio' => '9:16',
                    'formats' => 'WebP, JPG, PNG',
                    'max_size' => 'bis 8 MB',
                    'note' => 'Geeignet für Stories oder mobile Kampagnen. Für das Dashboard wird daraus nicht das beste Ergebnis, weil dort Querformat stabiler ist.',
                    'edit_hint' => 'Als Kampagnenmotiv nutzbar; globales Dashboard-Bild ist der Outfit-Abo Hero.',
                ],
                [
                    'category' => 'Sponsoren',
                    'name' => 'Sponsorenlogo',
                    'dimensions' => '1000 x 500 px',
                    'ratio' => '2:1',
                    'formats' => 'PNG, WebP, SVG falls unterstuetzt',
                    'max_size' => 'bis 4 MB',
                    'note' => 'Logo mit ausreichend Rand exportieren, damit es in Listen nicht abgeschnitten wirkt.',
                    'edit_hint' => 'Bearbeitung beim jeweiligen Sponsor.',
                ],
                [
                    'category' => 'Gamification',
                    'name' => 'Badge-Icon',
                    'dimensions' => '512 x 512 px',
                    'ratio' => '1:1',
                    'formats' => 'PNG, WebP',
                    'max_size' => 'bis 2 MB',
                    'note' => 'Einfache Formen und hoher Kontrast, damit das Icon auch klein lesbar bleibt.',
                    'edit_hint' => 'Bearbeitung im Badge-/Gamification-Adminbereich.',
                ],
            ];
    }

    private function visualsForAdmin(): array
    {
        return collect($this->visualDefinitions())
            ->map(fn (array $definition, string $key) => [
                'key' => $key,
                'category' => $definition['category'],
                'label' => $definition['label'],
                'description' => $definition['description'],
                'recommended_size' => $definition['recommended_size'],
                'ratio' => $definition['ratio'],
                'source' => Setting::valueFor($definition['setting_key'], $definition['default']),
                'url' => UploadStorage::url(Setting::valueFor($definition['setting_key'], $definition['default'])),
            ])
            ->values()
            ->all();
    }

    private function loginSliderPayload(): array
    {
        return collect($this->loginSliderSources())
            ->map(fn (string $source, int $index) => [
                'key' => 'login_slide_'.($index + 1),
                'source' => $source,
                'url' => UploadStorage::url($source),
                'label' => 'Login-Slider Bild '.($index + 1),
            ])
            ->values()
            ->all();
    }

    private function loginSliderSources(): array
    {
        $stored = Setting::valueFor('login_visual_slider');
        $decoded = is_string($stored) ? json_decode($stored, true) : null;

        if (is_array($decoded) && count(array_filter($decoded))) {
            return collect($decoded)
                ->map(fn ($source) => trim((string) $source))
                ->filter()
                ->values()
                ->all();
        }

        return collect($this->defaultLoginSliderSources())
            ->map(fn (string $source, int $index) => Setting::valueFor('login_visual_slide_'.($index + 1), $source))
            ->filter()
            ->values()
            ->all();
    }

    private function defaultLoginSliderSources(): array
    {
        return [
            '/img/login/bild1.png',
            '/img/login/bild2.png',
            '/img/login/bild3.png',
            '/img/login/bild4.png',
        ];
    }

    private function visualDefinitions(): array
    {
        return [
            'marketplace_side_banner' => [
                'setting_key' => 'marketplace_visual_side_banner',
                'category' => 'Marketplace',
                'label' => 'Marketplace Seitenbanner',
                'description' => 'Schmaler, dezenter Hintergrundbanner links/rechts im Marketplace.',
                'recommended_size' => '192 x 1080 px oder 384 x 2160 px',
                'ratio' => '8:45',
                'default' => '/images/marketplace/airmius-marketplace-side-banner.png',
                'upload_dir' => 'marketplace/visuals',
            ],
            'marketplace_hero_banner' => [
                'setting_key' => 'marketplace_visual_hero_banner',
                'category' => 'Marketplace',
                'label' => 'Marketplace Hero-Banner',
                'description' => 'Optionales Hauptbild im Marketplace-Kopfbereich.',
                'recommended_size' => '1600 x 900 px',
                'ratio' => '16:9',
                'default' => '',
                'upload_dir' => 'marketplace/visuals',
            ],
            'marketplace_sale_banner' => [
                'setting_key' => 'marketplace_visual_sale_banner',
                'category' => 'Marketplace',
                'label' => 'Marketplace Sale-Kachel',
                'description' => 'Optionales Aktionsbild für die Sale-Kachel.',
                'recommended_size' => '800 x 1000 px',
                'ratio' => '4:5',
                'default' => '',
                'upload_dir' => 'marketplace/visuals',
            ],
            'outfit_subscription_hero' => [
                'setting_key' => 'outfit_subscription_hero_image',
                'category' => 'Outfit-Abo',
                'label' => 'Outfit-Abo Dashboard Hero',
                'description' => 'Großes Hero-Bild auf der Outfit-Abo Dashboardseite.',
                'recommended_size' => '1920 x 1080 px',
                'ratio' => '16:9',
                'default' => '/images/marketplace/airmius_outfit_abo.webp',
                'upload_dir' => 'outfit-subscriptions/visuals',
            ],
        ];
    }
}
