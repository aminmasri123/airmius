<?php

namespace App\Services;

use App\Models\Club;
use App\Models\ClubExternalMember;
use App\Models\ClubNumberAllocation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ClubMemberNumberService
{
    public function __construct(private ClubNumberRangeService $ranges) {}

    public function linkAllocation(ClubExternalMember $external, User $user): void
    {
        if (blank($external->member_number) || ! DB::table('club_user')
            ->where('club_id', $external->club_id)->where('user_id', $user->id)
            ->where('member_number', $external->member_number)->exists()) {
            return;
        }

        ClubNumberAllocation::query()->where('club_id', $external->club_id)
            ->where('assigned_subject_type', 'external_member')->where('assigned_subject_id', $external->id)
            ->where('formatted_number', $external->member_number)
            ->update(['assigned_subject_type' => 'member', 'assigned_subject_id' => $user->id]);
    }

    public function generate(Club $club, User|ClubExternalMember $member, User $actor): string
    {
        return DB::transaction(function () use ($club, $member, $actor) {
            // Serialize allocations across both membership representations, including fallback numbers.
            $club = Club::query()->whereKey($club->id)->lockForUpdate()->firstOrFail();
            $external = $member instanceof ClubExternalMember;
            if ($external) {
                abort_unless((int) $member->club_id === (int) $club->id, 404);
                $member = $club->externalMembers()->whereKey($member->id)->lockForUpdate()->firstOrFail();
            } else {
                abort_unless($club->users()->where('users.id', $member->id)->exists(), 404);
            }

            $available = fn (string $number): bool => ! DB::table('club_user')
                ->where('club_id', $club->id)->where('member_number', $number)->exists()
                && ! $club->externalMembers()->where('member_number', $number)->exists();
            $allocation = $this->ranges->allocateDefault($club, 'member', $actor, (string) Str::uuid(), $available);
            $number = $allocation?->formatted_number;
            if ($number === null) {
                $next = DB::table('club_user')->where('club_id', $club->id)->whereNotNull('member_number')->count()
                    + $club->externalMembers()->whereNotNull('member_number')->count() + 1;
                do {
                    $number = 'M-'.$club->id.'-'.str_pad((string) $next++, 5, '0', STR_PAD_LEFT);
                } while (! $available($number));
            }

            if ($external) {
                $member->update(['member_number' => $number]);
            } else {
                $club->users()->updateExistingPivot($member->id, ['member_number' => $number]);
            }
            if ($allocation) {
                $this->ranges->assignTo($allocation, $external ? 'external_member' : 'member', $member->id);
            }

            return $number;
        }, 3);
    }
}
