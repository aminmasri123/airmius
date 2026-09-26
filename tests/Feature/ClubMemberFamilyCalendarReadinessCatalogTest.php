<?php

namespace Tests\Feature;

use App\Support\ClubMemberFamilyCalendarReadinessCatalog;
use Tests\TestCase;

class ClubMemberFamilyCalendarReadinessCatalogTest extends TestCase
{
    public function test_meta_exposes_member_family_portal_team_and_calendar_contracts(): void
    {
        $catalog = $this->getJson('/api/v1/meta')
            ->assertOk()
            ->json('data.catalogs.club_member_family_calendar_readiness');

        $this->assertSame(ClubMemberFamilyCalendarReadinessCatalog::VERSION, $catalog['version']);
        $this->assertSame('local_contract_ready_external_gates_open', $catalog['decision']);

        $this->assertContains('production_deletion_disabled_until_go', $catalog['former_membership']['retention_deletion_gate']);
        $this->assertSame('pending', $catalog['former_membership']['external_completion']);

        $this->assertContains('sensitive_visibility', $catalog['family_youth']['relationships']);
        $this->assertContains('payments', $catalog['family_youth']['child_permissions']);
        $this->assertContains('server_side_enforcement', $catalog['family_youth']['consents']);
        $this->assertContains('adult_decision', $catalog['family_youth']['coming_of_age']);
        $this->assertContains('limited_governance_rights', $catalog['family_youth']['youth_structure']);

        $this->assertContains('mandatory_messages', $catalog['member_portal_card']['notification_visibility']);
        $this->assertContains('short_lived_signed_qr', $catalog['member_portal_card']['digital_card']);
        $this->assertContains('no_smartphone_path', $catalog['member_portal_card']['assisted_admin']);
        $this->assertContains('browser_real_device_gate', $catalog['member_portal_card']['regression_matrix']);

        $this->assertContains('atomic_rollback', $catalog['teams_groups']['season_rollover']);
        $this->assertContains('central_permissions', $catalog['teams_groups']['team_workspace']);

        $this->assertContains('warning_or_hard_block', $catalog['calendar_attendance']['conflict_checker']);
        $this->assertContains('delivery_status', $catalog['calendar_attendance']['change_notifications']);
        $this->assertContains('secure_ical_subscription', $catalog['calendar_attendance']['personal_calendar']);
    }
}
