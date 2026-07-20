<?php

namespace Tests\Unit;

use App\Support\Validation\ClubProfileRules;
use PHPUnit\Framework\TestCase;

class ClubProfileRulesTest extends TestCase
{
    public function test_store_and_update_rules_share_profile_fields(): void
    {
        $storeRules = ClubProfileRules::store();
        $updateRules = ClubProfileRules::update();
        $profileFields = [
            'name',
            'sport_type',
            'official_club_number',
            'country',
            'street',
            'house_number',
            'postal_code',
            'city',
            'state',
            'sepa_account_holder',
            'sepa_iban',
            'sepa_bic',
            'is_listed',
            'teams_are_listed',
            'members_can_post_to_club',
            'members_can_post_to_teams',
        ];

        foreach ($profileFields as $field) {
            $this->assertSame($storeRules[$field], $updateRules[$field], "Rule mismatch for {$field}.");
        }

        $this->assertArrayHasKey('is_official', $storeRules);
        $this->assertArrayNotHasKey('logo', $storeRules);
        $this->assertArrayHasKey('logo', $updateRules);
        $this->assertArrayNotHasKey('is_official', $updateRules);
    }
}
