<?php

namespace App\Support;

use App\Models\Club;
use App\Models\ClubExternalMember;
use App\Models\User;

class ClubMemberDuplicates
{
    public static function candidate(Club $club, ClubExternalMember $externalMember): ?array
    {
        $normalizedEmail = strtolower(trim((string) $externalMember->email));
        $memberNumber = trim((string) $externalMember->member_number);

        $matches = $club->users->filter(function (User $user) use ($normalizedEmail, $memberNumber): bool {
            $sameEmail = $normalizedEmail !== ''
                && strtolower(trim((string) $user->email)) === $normalizedEmail;
            $sameMemberNumber = $memberNumber !== ''
                && trim((string) $user->pivot?->member_number) === $memberNumber;

            return $sameEmail || $sameMemberNumber;
        });

        if ($matches->isEmpty()) {
            return null;
        }

        if ($matches->count() > 1) {
            return [
                'ambiguous' => true,
                'candidates' => $matches->map(fn (User $member) => [
                    'user_id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                ])->values()->all(),
            ];
        }

        $member = $matches->first();

        $reasons = [];
        if ($normalizedEmail !== '' && strtolower(trim((string) $member->email)) === $normalizedEmail) {
            $reasons[] = 'email';
        }
        if ($memberNumber !== '' && trim((string) $member->pivot?->member_number) === $memberNumber) {
            $reasons[] = 'member_number';
        }

        return [
            'ambiguous' => false,
            'user_id' => $member->id,
            'name' => $member->name,
            'email' => $member->email,
            'reasons' => $reasons,
        ];
    }
}
