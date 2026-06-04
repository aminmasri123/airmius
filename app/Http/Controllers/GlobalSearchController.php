<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Team;
use App\Models\User;
use App\Support\Roles;
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
            ->get(['id', 'name', 'email', 'profile_photo_path'])
            ->map(fn (User $match) => [
                'type' => 'user',
                'id' => $match->id,
                'title' => $match->name,
                'subtitle' => $match->email,
                'url' => route('auth.users.show', $match->id),
                'avatar_url' => $match->profile_photo_thumb ?: $match->profile_photo_url,
            ]);

        $clubs = Club::query()
            ->when(
                ! (
                    $user->hasAnyRole(Roles::FULL_ACCESS)
                    || $user->can('clubs.view')
                    || $user->can('teams.view')
                ),
                function ($query) use ($user) {
                    $query->where(function ($innerQuery) use ($user) {
                        $innerQuery->where(fn ($publicClubQuery) => $publicClubQuery->verified()->where('is_listed', true))
                            ->orWhereHas('users', fn ($memberQuery) => $memberQuery->where('users.id', $user->id));
                    });
                }
            )
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
