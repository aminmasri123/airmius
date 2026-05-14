<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StoreIntendedUrlFromQuery
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('get') && in_array($request->route()?->getName(), ['login', 'register'], true)) {
            $target = $request->query('redirect') ?: $request->query('return_to');

            if (is_string($target) && str_starts_with($target, '/') && ! str_starts_with($target, '//')) {
                $request->session()->put('url.intended', $target);
            }
        }

        return $next($request);
    }
}
