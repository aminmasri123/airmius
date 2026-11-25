<?php

namespace App\Support;

use App\Models\Club;
use App\Models\ClubGovernanceAssignment;
use App\Models\ClubRoleAssignment;
use App\Models\File;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

final class ClubFileAudience
{
    public const MEMBERS = 'club_members';

    public const BOARD = 'board';

    public const ALL = [self::MEMBERS, self::BOARD];

    public static function allows(File $file, User $user): bool
    {
        if (! $file->club_id || ! $file->club) {
            return true;
        }

        if (! ClubActiveMembership::allows($file->club, $user)) {
            return false;
        }

        return ($file->audience ?: self::MEMBERS) !== self::BOARD
            || self::isBoardMember($file->club, $user);
    }

    public static function isBoardMember(Club $club, User $user): bool
    {
        if ((int) $club->owner_id === (int) $user->id) {
            return true;
        }

        if (! ClubActiveMembership::allows($club, $user)) {
            return false;
        }

        $member = $club->users()->whereKey($user->id)->first();
        $roles = $member
            ? ClubRoles::normalize($member->pivot?->role, $member->pivot?->roles ?? [])
            : [];

        if (array_intersect($roles, ['owner', 'admin', 'manager', 'financial_controller']) !== []) {
            return true;
        }

        if (Schema::hasTable('club_role_assignments') && Schema::hasTable('club_role_definitions')) {
            $configured = ClubRoleAssignment::query()
                ->where('club_role_assignments.club_id', $club->id)
                ->where('club_role_assignments.user_id', $user->id)
                ->join('club_role_definitions', 'club_role_definitions.id', '=', 'club_role_assignments.club_role_definition_id')
                ->where('club_role_definitions.club_id', $club->id)
                ->where('club_role_definitions.key', 'board')
                ->where('club_role_definitions.is_active', true)
                ->exists();
            if ($configured) {
                return true;
            }
        }

        return Schema::hasTable('club_governance_assignments')
            && ClubGovernanceAssignment::query()
                ->where('club_id', $club->id)
                ->where('user_id', $user->id)
                ->whereHas('body', fn ($query) => $query->where('type', 'board'))
                ->where(fn ($query) => $query->whereNull('starts_on')->orWhere('starts_on', '<=', today()))
                ->where(fn ($query) => $query->whereNull('ends_on')->orWhere('ends_on', '>=', today()))
                ->exists();
    }
}
