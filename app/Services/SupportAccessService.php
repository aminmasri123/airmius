<?php

namespace App\Services;

use App\Models\Club;
use App\Models\User;
use App\Support\ClubPermissions;
use Illuminate\Support\Collection;

class SupportAccessService
{
    /** @return array{global: bool, club_ids: array<int, int>} */
    public function operatorScope(User $user): array
    {
        $global = $user->can('support.tickets') || $user->can('system.manage');

        return [
            'global' => $global,
            'club_ids' => $global
                ? []
                : $this->supportClubs($user)->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
        ];
    }

    public function canOperate(User $user): bool
    {
        $scope = $this->operatorScope($user);

        return $scope['global'] || $scope['club_ids'] !== [];
    }

    /** @return Collection<int, Club> */
    public function linkedClubs(User $user): Collection
    {
        return Club::query()
            ->linkedToUser($user)
            ->select(['clubs.id', 'clubs.name'])
            ->orderBy('clubs.name')
            ->get();
    }

    /** @return Collection<int, Club> */
    public function supportClubs(User $user): Collection
    {
        return Club::query()
            ->where(function ($query) use ($user): void {
                $query->where('owner_id', $user->id)
                    ->orWhereHas('users', fn ($members) => $members->where('users.id', $user->id));
            })
            ->select(['clubs.id', 'clubs.name', 'clubs.owner_id'])
            ->orderBy('clubs.name')
            ->get()
            ->filter(fn (Club $club) => ClubPermissions::allows($club, $user, ClubPermissions::SUPPORT_MANAGE))
            ->values();
    }

    public function requesterClubId(User $user, mixed $requestedClubId, string $category): ?int
    {
        $linkedClubIds = $this->linkedClubs($user)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($requestedClubId !== null) {
            $clubId = (int) $requestedClubId;
            abort_unless($linkedClubIds->contains($clubId), 403);

            return $clubId;
        }

        return in_array($category, ['club', 'membership'], true) && $linkedClubIds->count() === 1
            ? $linkedClubIds->first()
            : null;
    }
}
