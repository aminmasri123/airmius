<?php

namespace App\Services;

use App\Models\GuardianChildRelationship;
use App\Models\User;

class GuardianMembershipOverviewService
{
    /** @return array<int, array<string, mixed>> */
    public function forChild(?User $guardian, string $guardianEmail, User $child): array
    {
        $email = mb_strtolower(trim($guardianEmail));

        return GuardianChildRelationship::query()
            ->where('child_user_id', $child->id)
            ->where('status', GuardianChildRelationship::STATUS_ACCEPTED)
            ->whereNull('revoked_at')
            ->whereNull('declined_at')
            ->where(fn ($query) => $query->whereNull('valid_from')->orWhereDate('valid_from', '<=', now()->toDateString()))
            ->where(fn ($query) => $query->whereNull('valid_until')->orWhereDate('valid_until', '>=', now()->toDateString()))
            ->where(function ($query) use ($guardian, $email): void {
                if ($guardian) {
                    $query->where('guardian_user_id', $guardian->id);

                    if ($guardian->hasVerifiedEmail()) {
                        $query->orWhereRaw('LOWER(TRIM(guardian_email)) = ?', [$email]);
                    }

                    return;
                }

                $query->whereRaw('LOWER(TRIM(guardian_email)) = ?', [$email]);
            })
            ->with('club:id,name,logo')
            ->get()
            ->map(function (GuardianChildRelationship $relationship) use ($child): ?array {
                $club = $relationship->club;
                if (! $club) {
                    return null;
                }

                $clubMembership = $child->clubs()->whereKey($club->id)->first();
                $teams = $child->teams()
                    ->where('club_id', $club->id)
                    ->orderBy('name')
                    ->get(['teams.id', 'teams.name', 'teams.club_id']);
                $membershipStatus = $clubMembership?->pivot?->membership_status;
                $activeClubMembership = $clubMembership
                    && ($membershipStatus === null || $membershipStatus === 'active');

                if (! $activeClubMembership && $teams->isEmpty()) {
                    return null;
                }

                return [
                    'id' => $club->id,
                    'name' => $club->name,
                    'logo' => $club->logo,
                    'membership_status' => $activeClubMembership ? ($membershipStatus ?: 'active') : 'team_member',
                    'role' => $clubMembership?->pivot?->role,
                    'guardian_relationship' => [
                        'id' => $relationship->id,
                        'status' => $relationship->status,
                        'is_primary' => (bool) $relationship->is_primary,
                        'relationship_type' => $relationship->relationship_type,
                    ],
                    'teams' => $teams->map(fn ($team) => [
                        'id' => $team->id,
                        'name' => $team->name,
                        'role' => $team->pivot?->role,
                    ])->values()->all(),
                ];
            })
            ->filter()
            ->unique('id')
            ->values()
            ->all();
    }
}
