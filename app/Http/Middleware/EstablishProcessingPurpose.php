<?php

namespace App\Http\Middleware;

use App\Support\Authorization\AuthorizationContext;
use App\Support\Privacy\ProcessingPurpose;
use Closure;
use Illuminate\Http\Request;

class EstablishProcessingPurpose
{
    public function handle(Request $request, Closure $next, string $purpose)
    {
        $resolved = ProcessingPurpose::tryFrom($purpose);
        abort_unless($resolved, 500, 'Unknown processing purpose configured for route.');

        $request->attributes->set(AuthorizationContext::REQUEST_PURPOSE_ATTRIBUTE, $resolved);
        $response = $next($request);

        if ($request->is('api/v1') || $request->is('api/v1/*')) {
            $response->headers->set('X-Airmius-Data-Purpose', $resolved->value);
        }

        return $response;
    }
}
