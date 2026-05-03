<?php

use App\Http\Controllers\BlogPostController;
use App\Http\Controllers\KontaktController;
use App\Http\Controllers\LegalPageController;
use App\Http\Controllers\OrganizationJobController;
use App\Http\Controllers\PricingController;
use App\Http\Controllers\PublicClubController;
use App\Models\BlogPost;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;


Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
})->name('welcome');

Route::get('/robots.txt', function () {
    $baseUrl = url('/');

    return response("User-agent: *\nAllow: /\nDisallow: /admin\nDisallow: /dashboard\nDisallow: /settings\nDisallow: /conversations\nDisallow: /messages\nSitemap: {$baseUrl}/sitemap.xml\n", 200, [
        'Content-Type' => 'text/plain',
    ]);
})->name('robots');

Route::get('/sitemap.xml', function () {
    $staticRoutes = [
        ['loc' => route('welcome'), 'priority' => '1.0', 'changefreq' => 'weekly'],
        ['loc' => route('guest.vereine'), 'priority' => '0.8', 'changefreq' => 'daily'],
        ['loc' => route('guest.pricing'), 'priority' => '0.8', 'changefreq' => 'monthly'],
        ['loc' => route('guest.blog.index'), 'priority' => '0.8', 'changefreq' => 'weekly'],
        ['loc' => route('guest.jobs'), 'priority' => '0.7', 'changefreq' => 'weekly'],
        ['loc' => route('guest.e-learning'), 'priority' => '0.6', 'changefreq' => 'monthly'],
        ['loc' => route('guest.gamification'), 'priority' => '0.6', 'changefreq' => 'monthly'],
        ['loc' => route('guest.top-inhalte'), 'priority' => '0.6', 'changefreq' => 'monthly'],
        ['loc' => route('legal.imprint'), 'priority' => '0.3', 'changefreq' => 'yearly'],
        ['loc' => route('policy.show'), 'priority' => '0.3', 'changefreq' => 'yearly'],
        ['loc' => route('terms.show'), 'priority' => '0.3', 'changefreq' => 'yearly'],
        ['loc' => route('legal.community'), 'priority' => '0.3', 'changefreq' => 'yearly'],
        ['loc' => route('legal.minors'), 'priority' => '0.3', 'changefreq' => 'yearly'],
    ];

    $blogRoutes = BlogPost::query()
        ->published()
        ->whereNotNull('slug')
        ->latest('published_at')
        ->get(['slug', 'updated_at', 'published_at'])
        ->map(fn (BlogPost $post) => [
            'loc' => route('guest.blog.show', $post->slug),
            'lastmod' => optional($post->updated_at ?? $post->published_at)->toAtomString(),
            'priority' => '0.7',
            'changefreq' => 'monthly',
        ]);

    $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

    foreach (collect($staticRoutes)->merge($blogRoutes) as $url) {
        $xml .= "  <url>\n";
        $xml .= '    <loc>'.e($url['loc'])."</loc>\n";
        if (! empty($url['lastmod'])) {
            $xml .= '    <lastmod>'.e($url['lastmod'])."</lastmod>\n";
        }
        $xml .= '    <changefreq>'.e($url['changefreq'])."</changefreq>\n";
        $xml .= '    <priority>'.e($url['priority'])."</priority>\n";
        $xml .= "  </url>\n";
    }

    $xml .= '</urlset>';

    return response($xml, 200, [
        'Content-Type' => 'application/xml',
    ]);
})->name('sitemap');

Route::get('/top-inhalte', fn () => Inertia::render('Guest/Top-Inhalte', [
    'canLogin' => Route::has('login'),
    'canRegister' => Route::has('register'),
]))->name('guest.top-inhalte');

Route::get('/preise', [PricingController::class, 'index'])->name('guest.pricing');

Route::get('/jobs', [OrganizationJobController::class, 'publicIndex'])->name('guest.jobs');

Route::get('/e-learning', fn () => Inertia::render('Guest/E-Learning', [
    'canLogin' => Route::has('login'),
    'canRegister' => Route::has('register'),
]))->name('guest.e-learning');

Route::get('/gamification', fn () => Inertia::render('Guest/Gamification', [
    'canLogin' => Route::has('login'),
    'canRegister' => Route::has('register'),
]))->name('guest.gamification');

Route::get('/vereine', [PublicClubController::class, 'index'])->name('guest.vereine');

Route::get('/blog', [BlogPostController::class, 'publicIndex'])->name('guest.blog.index');
Route::get('/blog/{blogPost:slug}', [BlogPostController::class, 'publicShow'])->name('guest.blog.show');

Route::get('/impressum', [LegalPageController::class, 'imprint'])->name('legal.imprint');
Route::get('/datenschutz', [LegalPageController::class, 'privacy'])->name('policy.show');
Route::get('/agb', [LegalPageController::class, 'terms'])->name('terms.show');
Route::get('/community-richtlinien', [LegalPageController::class, 'community'])->name('legal.community');
Route::get('/jugendschutz', [LegalPageController::class, 'minors'])->name('legal.minors');
Route::get('/cookies', [LegalPageController::class, 'cookies'])->name('legal.cookies');
Route::get('/widerruf', [LegalPageController::class, 'withdrawal'])->name('legal.withdrawal');
Route::get('/kontakt-und-melden', [LegalPageController::class, 'reporting'])->name('legal.reporting');


Route::post('/user/language', function (Request $request) {

    $request->validate([
        'language' => 'required|in:de,en,fr,ar',
    ]);

    $lang = $request->language;

    // ✅ Nur wenn User eingeloggt ist
    if ($request->user()) {
        $request->user()->update([
            'language' => $lang,
        ]);
    }

    // ✅ IMMER setzen (auch für Gäste)
    session()->put('locale', $lang);

    // ✅ Direkt anwenden
    app()->setLocale($lang);

    return back()->with('message', 'Sprache erfolgreich aktualisiert!');

})->name('user.language.update');






Route::post('/standort/anlegen', [KontaktController::class, 'store'])->name('contact.store');
