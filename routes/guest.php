<?php

use App\Http\Controllers\BlogPostController;
use App\Http\Controllers\CommerceCheckoutController;
use App\Http\Controllers\KontaktController;
use App\Http\Controllers\LegalPageController;
use App\Http\Controllers\OrganizationJobController;
use App\Http\Controllers\PricingController;
use App\Http\Controllers\PublicLearningController;
use App\Http\Controllers\PublicMarketplaceController;
use App\Http\Controllers\PublicClubController;
use App\Http\Controllers\PublicSponsorController;
use App\Models\BlogCategory;
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
        ['loc' => route('guest.sponsors'), 'priority' => '0.7', 'changefreq' => 'monthly'],
        ['loc' => route('guest.werbeagentur'), 'priority' => '0.7', 'changefreq' => 'monthly'],
        ['loc' => route('guest.e-learning'), 'priority' => '0.6', 'changefreq' => 'monthly'],
        ['loc' => route('guest.gamification'), 'priority' => '0.6', 'changefreq' => 'monthly'],
        ['loc' => route('guest.top-inhalte'), 'priority' => '0.6', 'changefreq' => 'monthly'],
        ['loc' => route('guest.marketplace'), 'priority' => '0.7', 'changefreq' => 'daily'],
        ['loc' => route('legal.imprint'), 'priority' => '0.3', 'changefreq' => 'yearly'],
        ['loc' => route('policy.show'), 'priority' => '0.3', 'changefreq' => 'yearly'],
        ['loc' => route('terms.show'), 'priority' => '0.3', 'changefreq' => 'yearly'],
        ['loc' => route('legal.community'), 'priority' => '0.3', 'changefreq' => 'yearly'],
        ['loc' => route('legal.minors'), 'priority' => '0.3', 'changefreq' => 'yearly'],
        ['loc' => route('legal.cookies'), 'priority' => '0.3', 'changefreq' => 'yearly'],
        ['loc' => route('legal.withdrawal'), 'priority' => '0.3', 'changefreq' => 'yearly'],
        ['loc' => route('legal.reporting'), 'priority' => '0.3', 'changefreq' => 'yearly'],
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

    $blogCategoryRoutes = BlogCategory::query()
        ->where('is_active', true)
        ->whereHas('posts', fn ($query) => $query->published())
        ->orderBy('sort_order')
        ->orderBy('name')
        ->get(['slug', 'updated_at'])
        ->map(fn (BlogCategory $category) => [
            'loc' => route('guest.blog.category', $category->slug),
            'lastmod' => optional($category->updated_at)->toAtomString(),
            'priority' => '0.6',
            'changefreq' => 'weekly',
        ]);

    $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

    foreach (collect($staticRoutes)->merge($blogCategoryRoutes)->merge($blogRoutes) as $url) {
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

Route::get('/blog/rss.xml', function () {
    $posts = BlogPost::query()
        ->published()
        ->with(['author:id,name', 'blogCategory:id,name,slug'])
        ->latest('published_at')
        ->take(30)
        ->get();

    $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
    $xml .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">'."\n";
    $xml .= "  <channel>\n";
    $xml .= '    <title>'.e('Airmius Blog')."</title>\n";
    $xml .= '    <link>'.e(route('guest.blog.index'))."</link>\n";
    $xml .= '    <description>'.e('Praxiswissen, Updates und Ideen fuer digitale Sportorganisation.')."</description>\n";
    $xml .= '    <language>de-DE</language>'."\n";
    $xml .= '    <atom:link href="'.e(route('guest.blog.rss')).'" rel="self" type="application/rss+xml" />'."\n";

    foreach ($posts as $post) {
        $description = $post->excerpt ?: str($post->content)->stripTags()->squish()->limit(240)->toString();

        $xml .= "    <item>\n";
        $xml .= '      <title>'.e($post->title)."</title>\n";
        $xml .= '      <link>'.e(route('guest.blog.show', $post->slug))."</link>\n";
        $xml .= '      <guid isPermaLink="true">'.e(route('guest.blog.show', $post->slug))."</guid>\n";
        $xml .= '      <description>'.e($description)."</description>\n";
        if ($post->author?->name) {
            $xml .= '      <author>'.e($post->author->name)."</author>\n";
        }
        if ($post->blogCategory?->name || $post->category) {
            $xml .= '      <category>'.e($post->blogCategory?->name ?: $post->category)."</category>\n";
        }
        if ($post->published_at) {
            $xml .= '      <pubDate>'.e($post->published_at->toRfc2822String())."</pubDate>\n";
        }
        $xml .= "    </item>\n";
    }

    $xml .= "  </channel>\n";
    $xml .= '</rss>';

    return response($xml, 200, [
        'Content-Type' => 'application/rss+xml; charset=UTF-8',
    ]);
})->name('guest.blog.rss');

Route::get('/top-inhalte', fn () => Inertia::render('Guest/Top-Inhalte', [
    'canLogin' => Route::has('login'),
    'canRegister' => Route::has('register'),
]))->name('guest.top-inhalte');

Route::get('/preise', [PricingController::class, 'index']);
Route::get('/abos', [PricingController::class, 'index'])->name('guest.pricing');

Route::get('/jobs', [OrganizationJobController::class, 'publicIndex'])->name('guest.jobs');
Route::post('/jobs/{organizationJob}/interest', [OrganizationJobController::class, 'submitInterest'])->name('guest.jobs.interest');
Route::get('/sponsoren', [PublicSponsorController::class, 'index'])->name('guest.sponsors');

Route::get('/werbeagentur-fuer-vereine', fn () => Inertia::render('Guest/Werbeagentur', [
    'canLogin' => Route::has('login'),
    'canRegister' => Route::has('register'),
]));

Route::get('/werbeagentur-für-vereine', fn () => Inertia::render('Guest/Werbeagentur', [
    'canLogin' => Route::has('login'),
    'canRegister' => Route::has('register'),
]))->name('guest.werbeagentur');

Route::post('/werbeagentur-fuer-vereine/anfrage', [CommerceCheckoutController::class, 'storePublicWebsiteRequest'])->name('guest.werbeagentur.request');

Route::get('/e-learning', [PublicLearningController::class, 'index'])->name('guest.e-learning');
Route::get('/e-learning/courses/{course}', [PublicLearningController::class, 'show'])->name('guest.learning.courses.show');
Route::get('/e-learning/certificates/{code}', [PublicLearningController::class, 'verifyCertificate'])->name('guest.learning.certificates.verify');

Route::get('/gamification', fn () => Inertia::render('Guest/Gamification', [
    'canLogin' => Route::has('login'),
    'canRegister' => Route::has('register'),
]))->name('guest.gamification');

Route::get('/vereine', [PublicClubController::class, 'index'])->name('guest.vereine');
Route::get('/marketplace', [PublicMarketplaceController::class, 'index'])->name('guest.marketplace');
Route::get('/marketplace/providers/{type}/{id}', [PublicMarketplaceController::class, 'provider'])->name('guest.marketplace.providers.show');
Route::get('/marketplace/products/{product}', [PublicMarketplaceController::class, 'show'])->name('guest.marketplace.products.show');
Route::post('/marketplace/products/{product}/checkout', [PublicMarketplaceController::class, 'checkout'])->name('guest.marketplace.products.checkout');
Route::post('/marketplace/orders/{order}/{token}/returns', [CommerceCheckoutController::class, 'guestReturn'])->name('commerce-checkout.guest.returns.store');

Route::get('/blog', [BlogPostController::class, 'publicIndex'])->name('guest.blog.index');
Route::get('/blog/kategorie/{blogCategory:slug}', [BlogPostController::class, 'publicCategory'])->name('guest.blog.category');
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
