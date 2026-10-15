<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class SponsorCampaignWebReadinessContractTest extends TestCase
{
    public function test_web_campaign_form_exposes_the_required_creation_fields(): void
    {
        $page = file_get_contents(dirname(__DIR__, 2).'/resources/js/Pages/Auth/Dashboard/Commerce/Index.vue');

        foreach ([
            'v-model="campaignForm.headline"',
            'v-model="campaignForm.target_url"',
            'v-model="campaignForm.primary_text"',
            'v-model="campaignForm.creative_image_url"',
            'v-model="campaignForm.daily_budget_cents"',
            'v-model="campaignForm.starts_at"',
            'v-model="campaignForm.ends_at"',
        ] as $field) {
            self::assertStringContainsString($field, $page);
            self::assertDoesNotMatchRegularExpression(
                '/'.$field.'.{0,160}class="hidden/s',
                $page,
                "{$field} must remain usable in the web campaign form.",
            );
        }

        self::assertStringContainsString('A/B-Test Varianten (optional)', $page);
        self::assertStringContainsString('Zielgruppe und Handlungsaufruf (optional)', $page);
    }

    public function test_campaign_urls_are_limited_to_http_and_https_at_every_write_boundary(): void
    {
        $checkout = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/CommerceCheckoutController.php');
        $admin = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/AdminCommerceController.php');
        $profile = file_get_contents(dirname(__DIR__, 2).'/app/Http/Requests/SponsorProfileRequest.php');

        self::assertStringNotContainsString("'target_url' => ['nullable', 'url',", $checkout);
        self::assertStringNotContainsString("'creatives.*.target_url' => ['nullable', 'url',", $checkout);
        self::assertStringNotContainsString("'target_url' => ['nullable', 'url',", $admin);
        self::assertGreaterThanOrEqual(3, substr_count($checkout, "'url:http,https'"));
        self::assertGreaterThanOrEqual(2, substr_count($admin, "'url:http,https'"));
        self::assertGreaterThanOrEqual(4, substr_count($profile, "'url:http,https'"));
    }

    public function test_sponsor_can_only_pause_or_complete_campaigns_from_valid_states(): void
    {
        $controller = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/CommerceCheckoutController.php');
        $page = file_get_contents(dirname(__DIR__, 2).'/resources/js/Pages/Auth/Dashboard/Commerce/Index.vue');

        self::assertStringContainsString("Rule::in(['draft', 'pending_review', 'active', 'paused', 'completed'])", $controller);
        self::assertStringContainsString("\$data['status'] === 'paused' && \$campaign->status !== 'active'", $controller);
        self::assertStringContainsString("\$data['status'] === 'completed' && ! in_array(\$campaign->status, ['active', 'paused'], true)", $controller);
        self::assertStringContainsString("updateOwnCampaignStatus(campaign, 'completed')", $page);
    }

    public function test_workspace_exposes_partnership_terms_and_deliverables_to_the_authorized_sponsor(): void
    {
        $service = file_get_contents(dirname(__DIR__, 2).'/app/Services/SponsorWorkspaceService.php');
        $page = file_get_contents(dirname(__DIR__, 2).'/resources/js/Pages/Auth/Dashboard/SponsorWorkspace/Index.vue');

        self::assertStringContainsString("'deliverables.responsible:id,name'", $service);
        self::assertStringContainsString("'individual_offer_terms' => \$sponsor->individual_offer_terms", $service);
        self::assertStringContainsString("'fulfillment_evidence' => \$deliverable->fulfillment_evidence", $service);
        self::assertStringContainsString('partner.rights_package?.length', $page);
        self::assertStringContainsString('partner.deliverables?.length', $page);
        self::assertStringContainsString('deliverable.fulfillment_evidence', $page);
    }
}
