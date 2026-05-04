<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackUserActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->privacy_status !== 'anonymized') {
            $lastSeenAt = $user->last_seen_at;

            if (! $lastSeenAt || $lastSeenAt->lt(now()->subMinutes(15))) {
                $user->forceFill([
                    'last_seen_at' => now(),
                    'privacy_status' => 'active',
                    'inactivity_first_warning_sent_at' => null,
                    'inactivity_second_warning_sent_at' => null,
                    'deletion_scheduled_at' => null,
                ])->save();
            }
        }

        return $next($request);
    }
}
