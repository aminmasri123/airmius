<?php

namespace App\Support;

use App\Models\Club;
use App\Models\ClubPersonProfile;
use App\Models\User;

class ClubWorkforceDirectory
{
    public static function assertSameClub(ClubPersonProfile $person, Club $club): void
    {
        abort_unless((int) $person->club_id === (int) $club->id, 404);
    }

    public static function canViewSensitiveData(User $actor, Club $club): bool
    {
        return ClubPermissions::allows($club, $actor, ClubPermissions::MEMBERS_MANAGE)
            || ClubPermissions::allows($club, $actor, ClubPermissions::MEMBERS_EDIT)
            || ClubPermissions::allows($club, $actor, ClubPermissions::MEMBERS_VIEW);
    }

    public static function publicPayload(ClubPersonProfile $person, User $actor): array
    {
        $person->loadMissing('engagements');
        $canViewSensitive = self::canViewSensitiveData($actor, $person->club);

        $payload = [
            'id' => $person->id,
            'club_id' => $person->club_id,
            'display_name' => $person->display_name,
            'privacy_level' => $person->privacy_level,
            'user_id' => $person->user_id,
            'membership_user_id' => $person->membership_user_id,
            'has_separate_membership_link' => $person->hasSeparateMembershipLink(),
            'engagements' => $person->engagements->map(fn ($engagement) => [
                'id' => $engagement->id,
                'engagement_type' => $engagement->engagement_type,
                'role_key' => $engagement->role_key,
                'status' => $engagement->status,
                'starts_on' => $engagement->starts_on?->toDateString(),
                'ends_on' => $engagement->ends_on?->toDateString(),
                'qualification_requirements' => $engagement->qualification_requirements ?? [],
            ])->values()->all(),
        ];

        if ($canViewSensitive && $person->privacy_level !== ClubPersonProfile::PRIVACY_CONFIDENTIAL) {
            $payload['email'] = $person->email;
            $payload['phone'] = $person->phone;
            $payload['date_of_birth'] = $person->date_of_birth?->toDateString();
            $payload['data_processing_flags'] = $person->data_processing_flags ?? [];
        }

        return $payload;
    }
}
