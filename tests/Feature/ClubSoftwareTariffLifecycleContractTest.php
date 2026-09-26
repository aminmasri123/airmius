<?php

namespace Tests\Feature;

use App\Support\ClubPermissions;
use App\Support\ClubSoftwareTariffLifecycleContract;
use Tests\TestCase;

class ClubSoftwareTariffLifecycleContractTest extends TestCase
{
    public function test_meta_exposes_club_software_tariff_lifecycle_contract(): void
    {
        $contract = $this->getJson('/api/v1/meta')
            ->assertOk()
            ->json('data.catalogs.club_software_tariff_lifecycle');

        $this->assertSame(ClubSoftwareTariffLifecycleContract::VERSION, $contract['version']);
        $this->assertSame('local_contract_ready', $contract['decision']);

        $this->assertSame('SubscriptionPlan', $contract['tariff_source']['model']);
        $this->assertSame('verein', $contract['tariff_source']['target_actor_for_clubs']);
        $this->assertContains('minimum_term_months', $contract['tariff_source']['contract_terms']);
        $this->assertContains('cancellation_notice_days', $contract['tariff_source']['contract_terms']);

        $this->assertSame('/api/v1/subscription-plans/{subscriptionPlan}/checkout', $contract['checkout']['endpoint']);
        $this->assertTrue($contract['checkout']['accepted_terms_required']);
        $this->assertSame(ClubPermissions::SUBSCRIPTIONS_EDIT, $contract['checkout']['club_checkout_permission']);
        $this->assertContains('bank_transfer', $contract['checkout']['providers']);
        $this->assertContains('stripe', $contract['checkout']['providers']);
        $this->assertContains('paypal', $contract['checkout']['providers']);
        $this->assertTrue($contract['checkout']['provider_cancel_url_is_signed']);
        $this->assertTrue($contract['checkout']['invoice_created_with_checkout']);

        $this->assertSame('SubscriptionCheckoutActivationService', $contract['activation']['source']);
        $this->assertContains('trialing', $contract['activation']['subscription_statuses']);
        $this->assertTrue($contract['activation']['trial_access_uses_trial_ends_at']);

        $this->assertSame('PlanFeatureService', $contract['entitlements']['source']);
        $this->assertSame('subscription_capabilities', $contract['entitlements']['club_resource_field']);
        $this->assertContains('past_due_before_grace_period_ends_at', $contract['entitlements']['granting_statuses']);
        $this->assertTrue($contract['entitlements']['free_plan_fallback_after_access_ends']);

        $this->assertSame('airmius:process-subscription-lifecycle', $contract['recurring_lifecycle']['command']);
        $this->assertTrue($contract['recurring_lifecycle']['generates_recurring_invoices']);
        $this->assertTrue($contract['recurring_lifecycle']['sets_past_due_with_grace_period']);
        $this->assertTrue($contract['recurring_lifecycle']['restricts_access_after_grace_period']);

        $this->assertSame('SubscriptionLifecycleService', $contract['cancellation']['source']);
        $this->assertContains('period_end', $contract['cancellation']['modes']);
        $this->assertTrue($contract['cancellation']['contractual_date_uses_period_notice_and_minimum_term']);
        $this->assertTrue($contract['cancellation']['paid_access_preserved_until_contractual_end']);

        $this->assertSame(ClubPermissions::SUBSCRIPTIONS_VIEW, $contract['security']['subscriptions_view_permission']);
        $this->assertSame(ClubPermissions::SUBSCRIPTIONS_EDIT, $contract['security']['subscriptions_edit_permission']);
        $this->assertTrue($contract['security']['foreign_club_checkout_rejected']);
    }
}
