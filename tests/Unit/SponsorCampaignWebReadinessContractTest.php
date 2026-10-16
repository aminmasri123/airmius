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

    public function test_draft_payment_flow_keeps_amount_and_requires_explicit_payment(): void
    {
        $root = dirname(__DIR__, 2);
        $controller = file_get_contents($root.'/app/Http/Controllers/CommerceCheckoutController.php');
        $page = file_get_contents($root.'/resources/js/Pages/Auth/Dashboard/Commerce/Index.vue');
        $routes = file_get_contents($root.'/routes/auth.php');

        self::assertStringContainsString("'status' => \$startPayment ? 'pending_payment' : 'draft'", $controller);
        self::assertStringContainsString('public function startOwnCampaignPayment', $controller);
        self::assertStringContainsString("(int) \$data['budget_cents'] !== (int) \$paymentOrder->amount_cents", $controller);
        self::assertStringContainsString('Als Entwurf speichern', $page);
        self::assertStringContainsString('Zahlung starten', $page);
        self::assertStringContainsString('value="paypal"', $page);
        self::assertStringContainsString('commerce.campaigns.payment', $routes);
    }

    public function test_ad_measurement_checks_consent_and_locks_budget_before_spend(): void
    {
        $controller = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/CommerceCheckoutController.php');

        self::assertStringContainsString('if (! $this->allowsAdMeasurement($request))', $controller);
        self::assertStringContainsString('AdCampaign::query()->lockForUpdate()->find($campaign->id)', $controller);
        self::assertStringContainsString('$this->campaignHasBudgetFor($campaign, $eventType, $costCents)', $controller);
    }

    public function test_web_and_api_admin_require_review_before_activation(): void
    {
        $root = dirname(__DIR__, 2);
        foreach ([
            '/app/Http/Controllers/AdminCommerceController.php',
            '/app/Http/Controllers/Api/V1/AdminCommerceController.php',
        ] as $path) {
            $controller = file_get_contents($root.$path);
            self::assertStringContainsString("in_array(\$campaign->status, ['pending_review', 'paused'], true)", $controller);
        }
    }

    public function test_workspace_exposes_partnership_terms_and_deliverables_to_the_authorized_sponsor(): void
    {
        $service = file_get_contents(dirname(__DIR__, 2).'/app/Services/SponsorWorkspaceService.php');
        $page = file_get_contents(dirname(__DIR__, 2).'/resources/js/Pages/Auth/Dashboard/SponsorWorkspace/Index.vue');

        self::assertStringContainsString("'sponsor.deliverables.responsible:id,name'", $service);
        self::assertStringContainsString("'individual_offer_terms' => \$partnership->individual_offer_terms", $service);
        self::assertStringContainsString("'fulfillment_evidence' => \$deliverable->fulfillment_evidence", $service);
        self::assertStringContainsString('partner.rights_package?.length', $page);
        self::assertStringContainsString('partner.deliverables?.length', $page);
        self::assertStringContainsString('deliverable.fulfillment_evidence', $page);
        self::assertStringContainsString("route('auth.sponsor-workspace.partnerships.store')", $page);
        self::assertStringContainsString('Details und Vereinbarung öffnen', $page);
    }

    public function test_partnership_records_are_separate_and_hide_foreign_sponsor_data(): void
    {
        $root = dirname(__DIR__, 2);
        $service = file_get_contents($root.'/app/Services/SponsorPartnershipService.php');
        $migration = file_get_contents($root.'/database/migrations/2026_10_16_000001_create_sponsor_partnerships_table.php');
        $routes = file_get_contents($root.'/routes/api.php');

        self::assertStringContainsString("Schema::create('sponsor_partnerships'", $migration);
        self::assertStringContainsString("->where('sponsor_id', \$sponsor->id)", $service);
        self::assertStringContainsString("->where('club_id', \$club->id)", $service);
        self::assertStringContainsString("abort_unless(\$this->organizations->can(\$user, \$partnership->sponsor, 'view'), 404)", $service);
        self::assertStringContainsString("'status' => SponsorPartnership::STATUS_PENDING", $service);
        self::assertStringContainsString("'/sponsor-workspace/partnerships/{partnership}'", $routes);
    }
}
