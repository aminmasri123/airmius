<?php

namespace App\Services;

use App\Models\Club;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class WorkspaceContextService
{
    /**
     * Return only the minimum club data required by the global application shell.
     */
    public function clubsFor(User $user): Collection
    {
        return Club::query()
            ->linkedToUser($user)
            ->orderBy('name')
            ->get(['clubs.id', 'clubs.name']);
    }

    public function canSelectClub(User $user, Club $club): bool
    {
        return Club::query()
            ->linkedToUser($user)
            ->whereKey($club->getKey())
            ->exists();
    }

    public function selectClub(Request $request, Club $club): void
    {
        abort_unless($this->canSelectClub($request->user(), $club), 403);

        $request->session()->put('club_id', $club->getKey());
    }

    public function clear(Request $request): void
    {
        $request->session()->forget('club_id');
    }

    public function payload(Request $request): array
    {
        $user = $request->user();

        if (! $user) {
            return [
                'current' => null,
                'clubs' => [],
            ];
        }

        $clubs = $this->clubsFor($user);
        $currentId = $request->session()->get('club_id');
        $current = $currentId
            ? $clubs->firstWhere('id', (int) $currentId)
            : null;

        // Never keep a stale tenant selection after membership access was removed.
        if ($currentId && ! $current) {
            $request->session()->forget('club_id');
        }

        return [
            'current' => $current ? [
                'type' => 'club',
                'id' => $current->id,
                'name' => $current->name,
            ] : null,
            'clubs' => $clubs
                ->map(fn (Club $club) => [
                    'id' => $club->id,
                    'name' => $club->name,
                ])
                ->values()
                ->all(),
        ];
    }
}
