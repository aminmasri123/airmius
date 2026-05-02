<?php

use App\Http\Controllers\BlogPostController;
use App\Http\Controllers\KontaktController;
use App\Http\Controllers\LegalPageController;
use App\Http\Controllers\OrganizationJobController;
use App\Http\Controllers\PublicClubController;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;



Route::get('/test-r2', function () {
    $result = Storage::disk('r2')->put('test.txt', 'Hallo Welt');
dd($result);

});








Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
})->name('welcome');

Route::get('/top-inhalte', fn () => Inertia::render('Guest/Top-Inhalte', [
    'canLogin' => Route::has('login'),
    'canRegister' => Route::has('register'),
]))->name('guest.top-inhalte');

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
