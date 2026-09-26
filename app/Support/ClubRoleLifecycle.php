<?php

namespace App\Support;

use App\Models\Club;
use App\Models\ClubPermissionDelegation;
use App\Models\ClubRoleAssignment;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

final class ClubRoleLifecycle
{
    public function clearForMembership(Club $club, User $member, ?User $actor = null): array
    {
        $assignments = Schema::hasTable('club_role_assignments')
            ? ClubRoleAssignment::query()->where('club_id', $club->id)->where('user_id', $member->id)->delete()
            : 0;
        $delegations = Schema::hasTable('club_permission_delegations')
            ? ClubPermissionDelegation::query()
                ->where('club_id', $club->id)
                ->whereNull('revoked_at')
                ->where(fn ($query) => $query->where('grantor_user_id', $member->id)->orWhere('grantee_user_id', $member->id))
                ->update(['revoked_at' => now(), 'revoked_by' => $actor?->id, 'updated_at' => now()])
            : 0;

        if ($assignments > 0 || $delegations > 0) {
            ClubAuditLog::record($club, $actor, 'club.role_access.ended', $member, [
                'role_assignments_removed' => $assignments,
                'delegations_revoked' => $delegations,
            ]);
        }

        return ['role_assignments_removed' => $assignments, 'delegations_revoked' => $delegations];
    }
}
