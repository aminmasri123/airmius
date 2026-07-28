<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProfileIsComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $this->isComplete($user)) {
            return $next($request);
        }

        if ($request->routeIs(
            'auth.profile-completion.*',
            'profile.show',
            'user-profile-information.update',
            'current-user-photo.destroy',
            'logout',
            'verification.*',
            'password.*',
            'social-auth.*',
        )) {
            return $next($request);
        }

        return redirect()->route('auth.profile-completion.edit');
    }

    private function isComplete($user): bool
    {
        return filled($user->first_name)
            && filled($user->last_name)
            && filled($user->country)
            && filled($user->birth_date)
            && filled($user->gender);
    }
}
