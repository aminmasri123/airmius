<?php

namespace App\Support;

use App\Models\Club;
use App\Models\User;

class ClubProfilePermissions
{
    public const PROFILE_FIELDS = [
        'name', 'sport_type', 'country', 'street', 'house_number', 'postal_code',
        'city', 'state', 'is_listed', 'teams_are_listed',
        'members_can_post_to_club', 'members_can_post_to_teams',
    ];

    public const LEGAL_FIELDS = [
        'official_club_number', 'registry_authority', 'registry_number',
        'federation_affiliations', 'tax_authority', 'tax_number', 'vat_id',
        'tax_status', 'tax_exemption_valid_until', 'sepa_account_holder',
        'sepa_iban', 'sepa_bic',
    ];

    public const CONTACT_FIELDS = [
        'contact_email', 'contact_phone', 'website_url',
        'contact_details_public', 'contact_persons',
    ];

    public const BRANDING_FIELDS = [
        'brand_primary_color', 'brand_secondary_color', 'brand_accent_color',
        'letterhead_settings', 'document_templates', 'logo', 'cover_image',
    ];

    public static function capabilities(Club $club, User $user): array
    {
        return [
            'profile' => ClubPermissions::allows($club, $user, ClubPermissions::CLUB_PROFILE_EDIT),
            'legal' => ClubPermissions::allows($club, $user, ClubPermissions::CLUB_LEGAL_EDIT),
            'contact' => ClubPermissions::allows($club, $user, ClubPermissions::CLUB_CONTACT_EDIT),
            'branding' => ClubPermissions::allows($club, $user, ClubPermissions::CLUB_BRANDING_EDIT),
        ];
    }

    public static function canEditAny(Club $club, User $user): bool
    {
        return in_array(true, self::capabilities($club, $user), true);
    }

    public static function authorizeUpdate(Club $club, User $user, array $submittedFields): void
    {
        $capabilities = self::capabilities($club, $user);
        $groups = [
            'profile' => self::PROFILE_FIELDS,
            'legal' => self::LEGAL_FIELDS,
            'contact' => self::CONTACT_FIELDS,
            'branding' => self::BRANDING_FIELDS,
        ];

        $recognized = false;
        foreach ($groups as $group => $fields) {
            if (array_intersect($fields, $submittedFields) === []) {
                continue;
            }

            $recognized = true;
            abort_unless($capabilities[$group], 403);
        }

        abort_unless($recognized && self::canEditAny($club, $user), 403);
    }

    public static function authorizeBranding(Club $club, User $user): void
    {
        abort_unless(
            ClubPermissions::allows($club, $user, ClubPermissions::CLUB_BRANDING_EDIT),
            403,
        );
    }
}
