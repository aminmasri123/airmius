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
use App\Models\Club;
use App\Models\Event;
use App\Models\MarketplaceProduct;
use App\Models\Sport;
use App\Models\Team;
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
        ['loc' => route('guest.events'), 'priority' => '0.7', 'changefreq' => 'daily'],
        ['loc' => route('guest.sports'), 'priority' => '0.7', 'changefreq' => 'weekly'],
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
        ['loc' => route('legal.data-erasure'), 'priority' => '0.3', 'changefreq' => 'yearly'],
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

    $marketplaceProducts = MarketplaceProduct::query()
        ->where('status', 'published')
        ->where(function ($query) {
            $query
                ->whereIn('offer_type', ['online_course', 'training_plan', 'service'])
                ->orWhere('product_type', 'digital')
                ->orWhere(function ($query) {
                    $query
                        ->where('manages_stock', true)
                        ->where(function ($stockQuery) {
                            $stockQuery
                                ->whereHas('inventories', fn ($inventoryQuery) => $inventoryQuery->availableForCountry('DE'))
                                ->orWhere(function ($legacyQuery) {
                                    $legacyQuery
                                        ->whereDoesntHave('inventories')
                                        ->whereNotNull('stock_quantity')
                                        ->where('stock_quantity', '>', 0);
                                });
                        });
                })
                ->orWhere(function ($query) {
                    $query
                        ->where('manages_stock', false)
                        ->whereIn('category', ['camp', 'service']);
                });
        })
        ->where(function ($query) {
            $query
                ->whereIn('offer_type', ['online_course', 'training_plan', 'service'])
                ->orWhere('product_type', 'digital')
                ->orWhereNull('available_countries')
                ->orWhereJsonLength('available_countries', 0)
                ->orWhereJsonContains('available_countries', 'DE');
        })
        ->latest('updated_at')
        ->limit(500)
        ->get(['id', 'updated_at', 'club_id', 'user_id']);

    $marketplaceProductRoutes = $marketplaceProducts
        ->map(fn (MarketplaceProduct $product) => [
            'loc' => route('guest.marketplace.products.show', $product),
            'lastmod' => optional($product->updated_at)->toAtomString(),
            'priority' => '0.6',
            'changefreq' => 'weekly',
        ]);

    $marketplaceProviderRoutes = $marketplaceProducts
        ->flatMap(function (MarketplaceProduct $product) {
            if ($product->club_id) {
                return [[
                    'loc' => route('guest.marketplace.providers.show', ['type' => 'club', 'id' => $product->club_id]),
                    'lastmod' => optional($product->updated_at)->toAtomString(),
                    'priority' => '0.5',
                    'changefreq' => 'weekly',
                ]];
            }

            if ($product->user_id) {
                return [[
                    'loc' => route('guest.marketplace.providers.show', ['type' => 'user', 'id' => $product->user_id]),
                    'lastmod' => optional($product->updated_at)->toAtomString(),
                    'priority' => '0.5',
                    'changefreq' => 'weekly',
                ]];
            }

            return [];
        })
        ->unique('loc')
        ->values();

    $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

    foreach (collect($staticRoutes)->merge($blogCategoryRoutes)->merge($blogRoutes)->merge($marketplaceProductRoutes)->merge($marketplaceProviderRoutes) as $url) {
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
    $xml .= '    <description>'.e('Praxiswissen, Updates und Ideen für digitale Sportorganisation.')."</description>\n";
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

Route::redirect('/preise', '/abos', 301);
Route::get('/abos', [PricingController::class, 'index'])->name('guest.pricing');

Route::get('/jobs', [OrganizationJobController::class, 'publicIndex'])->name('guest.jobs');
Route::post('/jobs/{organizationJob}/interest', [OrganizationJobController::class, 'submitInterest'])->name('guest.jobs.interest');
Route::get('/sponsoren', [PublicSponsorController::class, 'index'])->name('guest.sponsors');

Route::get('/werbeagentur-fuer-vereine', fn () => Inertia::render('Guest/Werbeagentur', [
    'canLogin' => Route::has('login'),
    'canRegister' => Route::has('register'),
]))->name('guest.werbeagentur');

Route::get('/werbeagentur-für-vereine', fn () => redirect()->route('guest.werbeagentur', [], 301));

Route::post('/werbeagentur-fuer-vereine/anfrage', [CommerceCheckoutController::class, 'storePublicWebsiteRequest'])->name('guest.werbeagentur.request');

Route::get('/e-learning', [PublicLearningController::class, 'index'])->name('guest.e-learning');
Route::get('/e-learning/courses/{course}', [PublicLearningController::class, 'show'])->name('guest.learning.courses.show');
Route::get('/e-learning/certificates/{code}', [PublicLearningController::class, 'verifyCertificate'])->name('guest.learning.certificates.verify');

Route::get('/gamification', fn () => Inertia::render('Guest/Gamification', [
    'canLogin' => Route::has('login'),
    'canRegister' => Route::has('register'),
]))->name('guest.gamification');

Route::get('/vereine', [PublicClubController::class, 'index'])->name('guest.vereine');
Route::get('/veranstaltungen', function (Request $request) {
    $filters = $request->validate([
        'search' => ['nullable', 'string', 'max:120'],
        'type' => ['nullable', 'in:training,match,meeting,public'],
        'location' => ['nullable', 'string', 'max:120'],
    ]);

    $events = Event::query()
        ->with([
            'club:id,name,sport_type,city,country',
            'team:id,name,club_id,sport_type',
            'team.club:id,name,sport_type,city,country',
        ])
        ->where('visibility', 'public')
        ->where('status', 'scheduled')
        ->where('start_time', '>=', now()->startOfDay())
        ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(function ($query) use ($search) {
            $query
                ->where('title', 'like', '%'.$search.'%')
                ->orWhere('notes', 'like', '%'.$search.'%')
                ->orWhereHas('club', fn ($clubQuery) => $clubQuery->where('name', 'like', '%'.$search.'%'))
                ->orWhereHas('team', fn ($teamQuery) => $teamQuery->where('name', 'like', '%'.$search.'%'));
        }))
        ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
        ->when($filters['location'] ?? null, fn ($query, $location) => $query->where(function ($query) use ($location) {
            $query
                ->where('location', 'like', '%'.$location.'%')
                ->orWhere('location_name', 'like', '%'.$location.'%')
                ->orWhere('location_city', 'like', '%'.$location.'%')
                ->orWhereHas('club', fn ($clubQuery) => $clubQuery
                    ->where('city', 'like', '%'.$location.'%')
                    ->orWhere('country', 'like', '%'.$location.'%'))
                ->orWhereHas('team.club', fn ($clubQuery) => $clubQuery
                    ->where('city', 'like', '%'.$location.'%')
                    ->orWhere('country', 'like', '%'.$location.'%'));
        }))
        ->orderBy('start_time')
        ->paginate(24)
        ->withQueryString()
        ->through(fn (Event $event) => [
            'id' => $event->id,
            'title' => $event->title,
            'type' => $event->type,
            'start_time' => $event->start_time?->toIso8601String(),
            'location' => $event->location_name ?: $event->location,
            'location_city' => $event->location_city,
            'club' => $event->club ? [
                'id' => $event->club->id,
                'name' => $event->club->name,
                'sport_type' => $event->club->sport_type,
                'city' => $event->club->city,
                'country' => $event->club->country,
            ] : null,
            'team' => $event->team ? [
                'id' => $event->team->id,
                'name' => $event->team->name,
                'sport_type' => $event->team->sport_type,
                'club' => $event->team->club ? [
                    'id' => $event->team->club->id,
                    'name' => $event->team->club->name,
                    'city' => $event->team->club->city,
                    'country' => $event->team->club->country,
                ] : null,
            ] : null,
        ]);

    return Inertia::render('Guest/Events', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'events' => $events,
        'eventTypes' => Event::TYPES,
        'filters' => [
            'search' => $filters['search'] ?? '',
            'type' => $filters['type'] ?? '',
            'location' => $filters['location'] ?? '',
        ],
    ]);
})->name('guest.events');
Route::get('/sportarten', function (Request $request) {
    $filters = $request->validate([
        'category' => ['nullable', 'string', 'max:80'],
        'search' => ['nullable', 'string', 'max:120'],
    ]);
    $clubCounts = Club::query()
        ->verified()
        ->whereNotNull('sport_type')
        ->selectRaw('sport_type, COUNT(*) as aggregate')
        ->groupBy('sport_type')
        ->pluck('aggregate', 'sport_type');
    $teamCounts = Team::query()
        ->whereNotNull('sport_type')
        ->selectRaw('sport_type, COUNT(*) as aggregate')
        ->groupBy('sport_type')
        ->pluck('aggregate', 'sport_type');

    $sports = Sport::query()
        ->where('is_active', true)
        ->when($filters['category'] ?? null, fn ($query, $category) => $query->where('category', $category))
        ->when($filters['search'] ?? null, fn ($query, $search) => $query->where('name', 'like', '%'.$search.'%'))
        ->orderBy('sort_order')
        ->orderBy('name')
        ->get(['id', 'name', 'slug', 'category'])
        ->map(fn (Sport $sport) => [
            'id' => $sport->id,
            'name' => $sport->name,
            'slug' => $sport->slug,
            'category' => $sport->category,
            'clubs_count' => (int) ($clubCounts[$sport->slug] ?? $clubCounts[$sport->name] ?? 0),
            'teams_count' => (int) ($teamCounts[$sport->slug] ?? $teamCounts[$sport->name] ?? 0),
        ]);

    return Inertia::render('Guest/Sportarten', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'sports' => $sports,
        'categories' => Sport::query()
            ->where('is_active', true)
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->values(),
        'filters' => [
            'category' => $filters['category'] ?? '',
            'search' => $filters['search'] ?? '',
        ],
    ]);
})->name('guest.sports');
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
Route::get('/daten-loeschen', [LegalPageController::class, 'dataErasure'])->name('legal.data-erasure');
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

    return back();

})->name('user.language.update');






Route::post('/standort/anlegen', [KontaktController::class, 'store'])->name('contact.store');
