<?php

namespace App\Actions\Fortify;

use App\Support\ProgressiveLoginLockout;
use Closure;
use Illuminate\Http\Request;

class EnsureProgressiveLoginAllowed
{
    public function handle(Request $request, Closure $next)
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        app(ProgressiveLoginLockout::class)->ensureAllowed($request->input('email'));

        return $next($request);
    }
}
