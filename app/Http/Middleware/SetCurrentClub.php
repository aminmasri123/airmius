<?php

namespace App\Http\Middleware;

use App\Models\Club;
use Closure;
use Illuminate\Http\Request;

class SetCurrentClub
{
    public function handle(Request $request, Closure $next)
    {
        $routeClub = $request->route('club');

        $clubId = $routeClub instanceof Club
            ? $routeClub->id
            : $routeClub;

        $clubId = $clubId
            ?? $request->header('X-Club-ID')
            ?? session('club_id');

        if (! $clubId) {
            abort(403, 'Kein Club ausgewählt');
        }

        // Club aus DB laden
        $club = Club::findOrFail($clubId);

        // Direct club members and users linked through a team can use the context.
        if (! Club::query()->linkedToUser($request->user())->whereKey($club->id)->exists()) {
            abort(403, 'Kein Zugriff auf diesen Club');
        }

        // 👉 GLOBAL setzen (wichtig!)
        app()->instance('currentClub', $club);

        return $next($request);
    }
}
