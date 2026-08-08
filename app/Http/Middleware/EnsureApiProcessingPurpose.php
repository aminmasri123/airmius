<?php

namespace App\Http\Middleware;

use App\Support\Authorization\AuthorizationContext;
use App\Support\PlatformModuleRegistry;
use App\Support\Privacy\ProcessingPurpose;
use Closure;
use Illuminate\Http\Request;

class EnsureApiProcessingPurpose
{
    public function handle(Request $request, Closure $next)
    {
        $resolvedModule = PlatformModuleRegistry::forApiPath($request->path());
        $request->attributes->set(
            AuthorizationContext::REQUEST_PURPOSE_ATTRIBUTE,
            $resolvedModule['module']['processing_purpose'] ?? ProcessingPurpose::ProductOperation,
        );

        $response = $next($request);
        $purpose = $request->attributes->get(AuthorizationContext::REQUEST_PURPOSE_ATTRIBUTE);

        $response->headers->set(
            'X-Airmius-Data-Purpose',
            $purpose instanceof ProcessingPurpose ? $purpose->value : ProcessingPurpose::ProductOperation->value,
        );
        $response->headers->set('X-Airmius-Module', $resolvedModule['key'] ?? 'platform');

        return $response;
    }
}
