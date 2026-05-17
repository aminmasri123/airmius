<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;

class SetLocale
{
    private const SUPPORTED_LOCALES = ['de', 'en', 'fr', 'ar'];

    public function handle(Request $request, Closure $next)
    {
        App::setLocale($this->resolveLocale($request));

        return $next($request);
    }

    private function resolveLocale(Request $request): string
    {
        $candidates = [
            $request->header('X-Locale'),
            $request->header('X-App-Locale'),
            $request->query('locale'),
            $request->user()?->language,
            $request->hasSession() && Session::has('locale') ? Session::get('locale') : null,
            $request->server('HTTP_ACCEPT_LANGUAGE'),
        ];

        foreach ($candidates as $candidate) {
            $locale = $this->normalizeLocale($candidate);

            if ($locale) {
                return $locale;
            }
        }

        return 'de';
    }

    private function normalizeLocale(mixed $locale): ?string
    {
        if (! is_string($locale) || trim($locale) === '') {
            return null;
        }

        $locale = strtolower(trim(explode(',', $locale)[0] ?? ''));
        $locale = strtolower(trim(explode(';', $locale)[0] ?? ''));
        $locale = str_replace('_', '-', $locale);
        $locale = substr($locale, 0, 2);

        return in_array($locale, self::SUPPORTED_LOCALES, true) ? $locale : null;
    }
}
