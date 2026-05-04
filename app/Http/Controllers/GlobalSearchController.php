<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;

class GlobalSearchController extends Controller
{
    public function __invoke(Request $request)
    {
        $term = trim((string) $request->input('q'));

        if (mb_strlen($term) < 2) {
            return response()->json(['results' => []]);
        }

        $like = '%'.$term.'%';
        $user = $request->user();

        $users = User::query()
            ->where('id', '!=', $user->id)
            ->where(function ($query) use ($like) {
                $query->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like);
            })
            ->orderBy('name')
            ->limit(5)
            ->get(['id', 'name', 'email'])
            ->map(fn (User $match) => [
                'type' => 'user',
                'id' => $match->id,
                'title' => $match->name,
                'subtitle' => $match->email,
                'url' => route('auth.users.show', $match->id),
            ]);

        $clubs = Club::query()
            ->visibleTo($user)
            ->where('name', 'like', $like)
            ->orderBy('name')
            ->limit(5)
            ->get(['id', 'name'])
            ->map(fn (Club $club) => [
                'type' => 'club',
                'id' => $club->id,
                'title' => $club->name,
                'subtitle' => 'Verein',
                'url' => route('auth.clubs.show', $club->id),
            ]);

        $teams = Team::query()
            ->with('club:id,name')
            ->where('name', 'like', $like)
            ->orderBy('name')
            ->limit(5)
            ->get(['id', 'club_id', 'name', 'sport_type'])
            ->map(fn (Team $team) => [
                'type' => 'team',
                'id' => $team->id,
                'title' => $team->name,
                'subtitle' => trim(($team->club?->name ?? 'Team').' · '.($team->sport_type ?? '')),
                'url' => route('auth.teams.show', $team->id),
                'join_url' => route('auth.teams.join-requests.store', $team->id),
            ]);

        return response()->json([
            'results' => $users->concat($clubs)->concat($teams)->take(12)->values(),
        ]);
    }
}
