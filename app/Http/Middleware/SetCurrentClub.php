<?php

namespace App\Http\Middleware;

use App\Models\Club;
use Closure;
use Illuminate\Http\Request;

class SetCurrentClub
{
    public function handle(Request $request, Closure $next)
    {
        $clubId = $request->route('club')
            ?? $request->header('X-Club-ID')
            ?? session('club_id');

        if (!$clubId) {
            abort(403, 'Kein Club ausgewählt');
        }

        // Club aus DB laden
        $club = Club::findOrFail($clubId);

        // 🔐 Prüfen ob User im Club ist
        if (!auth()->user()->clubs->contains($club->id)) {
            abort(403, 'Kein Zugriff auf diesen Club');
        }

        // 👉 GLOBAL setzen (wichtig!)
        app()->instance('currentClub', $club);

        return $next($request);
    }
}
