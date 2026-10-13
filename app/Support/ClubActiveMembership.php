<?php

namespace App\Support;

use App\Models\Club;
use App\Models\ClubExternalMember;
use App\Models\User;

class ClubActiveMembership
{
    public static function allows(Club $club, int|User $user): bool
    {
        $userId = $user instanceof User ? (int) $user->id : $user;

        if ((int) $club->owner_id === $userId) {
            return true;
        }

        $member = $club->users()->where('users.id', $userId)->first();
        if ($member && self::directMemberIsActive($club, $member)) {
            return true;
        }

        if ($club->externalMembers()
            ->where('linked_user_id', $userId)
            ->whereRaw("LOWER(TRIM(membership_status)) = 'active'")
            ->exists()) {
            return true;
        }

        return $club->teams()
            ->whereHas('users', fn ($query) => $query->where('users.id', $userId))
            ->exists();
    }

    public static function externalMember(Club $club, int|User $user): ?ClubExternalMember
    {
        $userId = $user instanceof User ? (int) $user->id : $user;

        return $club->externalMembers()
            ->where('linked_user_id', $userId)
            ->whereRaw("LOWER(TRIM(membership_status)) = 'active'")
            ->first();
    }

    public static function directMemberIsActive(Club $club, User $member): bool
    {
        $roles = ClubRoles::normalize($member->pivot?->role, $member->pivot?->roles ?? []);
        $status = strtolower(trim((string) ($member->pivot?->membership_status ?? '')));

        return $status === 'active'
            || ($status === '' && (int) $club->owner_id === (int) $member->id)
            || ($status === '' && array_intersect($roles, ClubRoles::ELEVATED) !== []);
    }
}
