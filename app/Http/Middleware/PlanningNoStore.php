<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Throwable;

class PlanningNoStore
{
    public function handle(Request $request, Closure $next)
    {
        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $handler = app(ExceptionHandler::class);
            $handler->report($exception);
            $response = $handler->render($request, $exception);
        }
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
}
