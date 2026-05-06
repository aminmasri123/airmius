<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

class MediaGuidelineController extends Controller
{
    public function index()
    {
        return Inertia::render('Auth/Dashboard/MediaGuidelines/Index', [
            'guidelines' => [
                [
                    'category' => 'Profil',
                    'name' => 'Profilbild',
                    'dimensions' => '800 x 800 px',
                    'ratio' => '1:1',
                    'formats' => 'JPG, PNG, WebP',
                    'max_size' => 'bis 8 MB',
                    'note' => 'Quadratisch hochladen. Das Bild wird rund oder quadratisch zugeschnitten angezeigt.',
                ],
                [
                    'category' => 'Profil',
                    'name' => 'Profil-/Vereins-Cover',
                    'dimensions' => '1600 x 500 px',
                    'ratio' => '3.2:1',
                    'formats' => 'JPG, PNG, WebP',
                    'max_size' => 'bis 8 MB',
                    'note' => 'Wichtige Inhalte mittig platzieren, weil mobile Ansichten seitlich beschneiden koennen.',
                ],
                [
                    'category' => 'Verein & Team',
                    'name' => 'Vereinslogo / Teamlogo',
                    'dimensions' => '800 x 800 px',
                    'ratio' => '1:1',
                    'formats' => 'PNG, WebP, JPG',
                    'max_size' => 'bis 8 MB',
                    'note' => 'Am besten quadratisch mit ruhigem Hintergrund oder transparentem PNG.',
                ],
                [
                    'category' => 'Blog',
                    'name' => 'Blog-Cover',
                    'dimensions' => '1600 x 900 px',
                    'ratio' => '16:9',
                    'formats' => 'JPG, PNG, WebP',
                    'max_size' => 'bis 8 MB',
                    'note' => 'Querformat fuer Blogliste, Detailseite und Social-Sharing-Vorschau.',
                ],
                [
                    'category' => 'Feed',
                    'name' => 'Post-Bild',
                    'dimensions' => '1200 x 1200 px',
                    'ratio' => '1:1',
                    'formats' => 'JPG, PNG, WebP',
                    'max_size' => 'bis 8 MB',
                    'note' => 'Quadratisch funktioniert im Feed am stabilsten. Alternativ 1600 x 900 px fuer Querformat.',
                ],
                [
                    'category' => 'Video',
                    'name' => 'Video-Thumbnail',
                    'dimensions' => '1280 x 720 px',
                    'ratio' => '16:9',
                    'formats' => 'JPG, PNG, WebP',
                    'max_size' => 'bis 8 MB',
                    'note' => 'Klarer Titelbereich und Motiv in der Mitte. Ideal fuer Vorschauen und externe Shares.',
                ],
                [
                    'category' => 'Marketplace',
                    'name' => 'Produktbild Kachel',
                    'dimensions' => '1200 x 1200 px',
                    'ratio' => '1:1',
                    'formats' => 'JPG, PNG, WebP',
                    'max_size' => 'bis 8 MB',
                    'note' => 'Produkt gut ausleuchten, wenig Text im Bild, einheitlicher Hintergrund.',
                ],
                [
                    'category' => 'Marketplace',
                    'name' => 'Produktbild Detailseite',
                    'dimensions' => '1600 x 1000 px',
                    'ratio' => '16:10',
                    'formats' => 'JPG, PNG, WebP',
                    'max_size' => 'bis 8 MB',
                    'note' => 'Funktioniert fuer grosse Detailansichten. Wichtiges Motiv mittig halten.',
                ],
                [
                    'category' => 'Marketplace',
                    'name' => 'Marketplace Hero / Featured Deal',
                    'dimensions' => '1600 x 900 px',
                    'ratio' => '16:9',
                    'formats' => 'JPG, PNG, WebP',
                    'max_size' => 'bis 8 MB',
                    'note' => 'Ideal fuer hervorgehobene Angebote, Banner und Social-Vorschauen.',
                ],
                [
                    'category' => 'Sponsoren',
                    'name' => 'Sponsorenlogo',
                    'dimensions' => '1000 x 500 px',
                    'ratio' => '2:1',
                    'formats' => 'PNG, WebP, SVG falls unterstuetzt',
                    'max_size' => 'bis 4 MB',
                    'note' => 'Logo mit ausreichend Rand exportieren, damit es in Listen nicht abgeschnitten wirkt.',
                ],
                [
                    'category' => 'Gamification',
                    'name' => 'Badge-Icon',
                    'dimensions' => '512 x 512 px',
                    'ratio' => '1:1',
                    'formats' => 'PNG, WebP',
                    'max_size' => 'bis 2 MB',
                    'note' => 'Einfache Formen und hoher Kontrast, damit das Icon auch klein lesbar bleibt.',
                ],
            ],
        ]);
    }
}
