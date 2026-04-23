<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $locale = null;

        // 1. PRIORITÄT: eingeloggter User
        if ($request->user()?->language) {
            $locale = $request->user()->language;
        }

        // 2. FALLBACK: Session (Gast User)
        if (!$locale && Session::has('locale')) {
            $locale = Session::get('locale');
        }

        // 3. FALLBACK: Browser Language
        if (!$locale) {
            $locale = substr($request->server('HTTP_ACCEPT_LANGUAGE'), 0, 2);
        }

        // 4. FINAL FALLBACK
        if (!in_array($locale, ['de', 'en', 'fr'])) {
            $locale = 'de';
        }

        App::setLocale($locale);

        return $next($request);
    }
}


