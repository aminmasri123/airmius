<?php

namespace App\Http\Middleware;

use App\Support\AdminTwoFactor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformAdminTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        if (AdminTwoFactor::missingFor($request->user())) {
            return response()->json([
                'message' => AdminTwoFactor::MESSAGE,
                'code' => AdminTwoFactor::ERROR_CODE,
            ], 403);
        }

        return $next($request);
    }
}
