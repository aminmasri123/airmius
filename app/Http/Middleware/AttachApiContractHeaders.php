<?php

namespace App\Http\Middleware;

use App\Support\Api\V1\ApiContract;
use Closure;
use Illuminate\Http\Request;

class AttachApiContractHeaders
{
    public function handle(Request $request, Closure $next)
    {
        if (ApiContract::matches($request)) {
            ApiContract::requestId($request);
        }

        $response = $next($request);

        if (ApiContract::matches($request)) {
            foreach (ApiContract::headers($request) as $header => $value) {
                $response->headers->set($header, $value);
            }
        }

        return $response;
    }
}
