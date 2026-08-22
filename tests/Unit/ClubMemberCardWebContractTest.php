<?php

namespace Tests\Unit;

use Tests\TestCase;

class ClubMemberCardWebContractTest extends TestCase
{
    public function test_club_profile_exposes_private_ajax_member_card_without_page_reload(): void
    {
        $source = file_get_contents(resource_path('js/Pages/Auth/Dashboard/Clubs/Profile.vue'));

        $this->assertStringContainsString("route('api.v1.clubs.member-card.show'", $source);
        $this->assertStringContainsString("route('api.v1.clubs.member-card.rotate'", $source);
        $this->assertStringContainsString('memberCard.token.qr_svg_data_uri', $source);
        $this->assertStringContainsString('viewer.is_member || viewer.can_manage', $source);
        $this->assertStringContainsString('aria-live="polite"', $source);
        $this->assertStringContainsString('role="alert"', $source);
        $this->assertStringNotContainsString('window.location.reload', $source);
    }
}
