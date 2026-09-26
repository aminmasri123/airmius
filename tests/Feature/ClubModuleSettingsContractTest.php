<?php

namespace Tests\Feature;

use App\Support\ClubModuleSettingsContract;
use Tests\TestCase;

class ClubModuleSettingsContractTest extends TestCase
{
    public function test_meta_exposes_club_module_settings_defaults_plan_gates_and_fallbacks(): void
    {
        $contract = $this->getJson('/api/v1/meta')
            ->assertOk()
            ->json('data.catalogs.club_module_settings');

        $this->assertSame(ClubModuleSettingsContract::VERSION, $contract['version']);
        $this->assertSame('local_contract_ready', $contract['decision']);
        $this->assertTrue($contract['defaults']['club_is_listed']);
        $this->assertTrue($contract['defaults']['teams_are_listed']);
        $this->assertFalse($contract['defaults']['contact_details_public']);
        $this->assertTrue($contract['defaults']['brand_colors_fallback_to_airmius_theme']);

        $this->assertSame('ClubResource.subscription_capabilities', $contract['governance']['api_payload']);
        $this->assertTrue($contract['governance']['write_actions_require_server_authorization']);
        $this->assertTrue($contract['governance']['explicit_denials_override_legacy_roles']);

        $modules = collect($contract['module_policy'])->keyBy('key');
        foreach (['members', 'events', 'training', 'files', 'sponsors', 'subscriptions'] as $key) {
            $this->assertTrue($modules[$key]['requires_permission_check'], $key);
            $this->assertSame('hide_or_read_only_when_unavailable', $modules[$key]['fallback_behavior']);
        }
        $this->assertTrue($modules['sponsors']['requires_plan_check']);
        $this->assertFalse($modules['blog']['requires_permission_check']);

        $this->assertSame('starter', $contract['feature_minimum_plans']['member_import']);
        $this->assertSame('club', $contract['feature_minimum_plans']['club_cockpit']);
        $this->assertSame('pro', $contract['feature_minimum_plans']['sepa_export']);
        $this->assertSame('elite', $contract['feature_minimum_plans']['api']);

        $this->assertSame(['de', 'en', 'fr', 'ar'], $contract['terminology']['locales']);
        $this->assertSame(['requested_locale', 'de', 'en', 'key'], $contract['terminology']['fallback_order']);
        $this->assertTrue($contract['terminology']['server_validates_required_german_label']);

        $this->assertContains('brand_primary_color', $contract['appearance']['palette_fields']);
        $this->assertSame('#RRGGBB', $contract['appearance']['color_format']);
        $this->assertTrue($contract['appearance']['private_document_templates_only_for_branding_editors']);
    }
}
