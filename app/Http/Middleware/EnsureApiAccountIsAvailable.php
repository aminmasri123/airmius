<?php

namespace App\Http\Middleware;

use App\Support\AccountAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiAccountIsAvailable
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! $request->routeIs('api.v1.auth.logout')) {
            AccountAccess::ensureAllowed($request->user());
        }

        return $next($request);
    }
}
