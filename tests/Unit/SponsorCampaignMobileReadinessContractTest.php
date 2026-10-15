<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class SponsorCampaignMobileReadinessContractTest extends TestCase
{
    public function test_mobile_campaign_editor_exposes_period_validation_and_safe_completion(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/mobile/airmius_mobile/lib/screens/commerce_center_screen.dart');

        $this->assertStringContainsString("'starts_at': _campaignApiDate(_startsAt)", $source);
        $this->assertStringContainsString("'ends_at': _campaignApiDate(_endsAt)", $source);
        $this->assertStringContainsString('showDatePicker(', $source);
        $this->assertStringContainsString('_optionalHttpUrlValidator(context, value)', $source);
        $this->assertStringContainsString("'completed'", $source);
        $this->assertStringContainsString('Kampagne abschließen?', $source);
        $this->assertStringContainsString("status == 'active' || status == 'paused'", $source);
        $this->assertStringContainsString("_text(campaign['status']) == 'completed'", $source);
    }

    public function test_mobile_sponsor_profile_and_partnerships_expose_phase_29_details(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/mobile/airmius_mobile/lib/screens/sponsor_cockpit_screen.dart');

        $this->assertStringContainsString("partner['rights_package']", $source);
        $this->assertStringContainsString("partner['individual_offer_terms']", $source);
        $this->assertStringContainsString("partner['renewal_deadline']", $source);
        $this->assertStringContainsString("partner['deliverables']", $source);
        $this->assertStringContainsString("deliverable['responsible']", $source);
        $this->assertStringContainsString("deliverable['fulfillment_evidence']", $source);
        $this->assertStringContainsString("data['assets']", $source);
        $this->assertStringContainsString("data['agency_briefs']", $source);
        $this->assertStringContainsString("uri.scheme != 'http' && uri.scheme != 'https'", $source);
        $this->assertStringContainsString("'logo_light'", $source);
        $this->assertStringContainsString("'logo_dark'", $source);
    }

    public function test_mobile_deliverables_use_the_same_crud_and_permission_contract_as_web(): void
    {
        $root = dirname(__DIR__, 2);
        $screen = file_get_contents($root.'/mobile/airmius_mobile/lib/screens/sponsor_cockpit_screen.dart');
        $client = file_get_contents($root.'/mobile/airmius_mobile/lib/core/airmius_api_client.dart');
        $workspace = file_get_contents($root.'/app/Services/SponsorWorkspaceService.php');
        $controller = file_get_contents($root.'/app/Http/Controllers/Api/V1/SponsorDeliverableManagementController.php');

        foreach (['createSponsorDeliverable', 'updateSponsorDeliverable', 'deleteSponsorDeliverable'] as $method) {
            self::assertStringContainsString($method, $client);
            self::assertStringContainsString($method, $screen);
        }

        self::assertStringContainsString("partner['can_edit_deliverables'] == true", $screen);
        self::assertStringContainsString("partner['can_delete_deliverables'] == true", $screen);
        self::assertStringContainsString("'can_edit_deliverables'", $workspace);
        self::assertStringContainsString("'can_delete_deliverables'", $workspace);
        self::assertStringContainsString('ClubPermissions::SPONSORS_EDIT', $controller);
        self::assertStringContainsString('ClubPermissions::SPONSORS_DELETE', $controller);
        self::assertStringContainsString("'required_if:status,fulfilled'", $controller);
    }
}
