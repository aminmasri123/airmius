<?php

namespace App\Support\Validation;

class ClubProfileRules
{
    public static function store(): array
    {
        return [
            ...self::profile(),
            'is_official' => ['boolean'],
        ];
    }

    public static function update(): array
    {
        return [
            ...self::profile(),
            'logo' => ['nullable', 'string', 'max:255'],
        ];
    }

    private static function profile(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'sport_type' => ['nullable', 'string', 'max:120'],
            'official_club_number' => ['nullable', 'string', 'max:120'],
            'country' => ['required', 'string', 'size:2'],
            'street' => ['nullable', 'string', 'max:255'],
            'house_number' => ['nullable', 'string', 'max:40'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'sepa_account_holder' => ['nullable', 'string', 'max:120'],
            'sepa_iban' => ['nullable', 'string', 'max:40'],
            'sepa_bic' => ['nullable', 'string', 'max:20'],
            'is_listed' => ['boolean'],
            'teams_are_listed' => ['boolean'],
            'members_can_post_to_club' => ['boolean'],
            'members_can_post_to_teams' => ['boolean'],
        ];
    }
}
