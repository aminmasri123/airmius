<?php

namespace App\Support;

use App\Models\Club;
use App\Models\User;

class ClubGovernanceMeetingAccess
{
    public static function canOpen(Club $club, User $user): bool
    {
        if (ClubPermissions::allows($club, $user, ClubPermissions::GOVERNANCE_EDIT)) {
            return true;
        }

        return ClubPermissions::allows($club, $user, ClubPermissions::GOVERNANCE_VIEW)
            && $club->users()->where('users.id', $user->id)->wherePivot('membership_status', 'active')->exists()
            && $club->governanceMeetings()->whereHas('recipients', fn ($query) => $query
                ->where('user_id', $user->id)->where('attendance_eligible', true))->exists();
    }
}
