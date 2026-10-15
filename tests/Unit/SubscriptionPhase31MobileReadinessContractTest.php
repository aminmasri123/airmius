<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class SubscriptionPhase31MobileReadinessContractTest extends TestCase
{
    public function test_mobile_plan_cards_compare_actor_price_and_server_limits(): void
    {
        $screen = file_get_contents(dirname(__DIR__, 2).'/mobile/airmius_mobile/lib/screens/subscription_center_screen.dart');

        foreach ([
            "subscriptions.forClubs",
            "subscriptions.forPeople",
            "plan['member_limit']",
            "plan['team_limit']",
            "plan['storage_gb']",
            'localized_price',
        ] as $contract) {
            self::assertStringContainsString($contract, $screen);
        }
    }

    public function test_mobile_subscription_cards_explain_payment_grace_and_lifecycle_actions(): void
    {
        $screen = file_get_contents(dirname(__DIR__, 2).'/mobile/airmius_mobile/lib/screens/subscription_center_screen.dart');

        self::assertStringContainsString("'past_due'", $screen);
        self::assertStringContainsString("item['grace_period_ends_at']", $screen);
        self::assertStringContainsString('subscriptions.paymentIssue', $screen);
        self::assertStringContainsString('subscriptions.cancelAtPeriodEnd', $screen);
        self::assertStringContainsString('subscriptions.undoCancellation', $screen);
    }

    public function test_mobile_subscription_invoices_open_the_protected_pdf_flow(): void
    {
        $screen = file_get_contents(dirname(__DIR__, 2).'/mobile/airmius_mobile/lib/screens/subscription_center_screen.dart');
        $detail = file_get_contents(dirname(__DIR__, 2).'/mobile/airmius_mobile/lib/screens/billing_detail_screen.dart');
        $resource = file_get_contents(dirname(__DIR__, 2).'/app/Http/Resources/Api/V1/SubscriptionInvoiceResource.php');

        self::assertStringContainsString('BillingDetailScreen(', $screen);
        self::assertStringContainsString('AirmiusInvoice.fromJson(invoice)', $screen);
        self::assertStringContainsString('/api/v1/billing/invoices/${invoice.kind}/${invoice.id}/download', $detail);
        self::assertStringContainsString("'kind' => 'subscription_invoice'", $resource);
    }

    public function test_mobile_outfit_checkout_requires_and_displays_the_selected_size(): void
    {
        $screen = file_get_contents(dirname(__DIR__, 2).'/mobile/airmius_mobile/lib/screens/outfit_subscription_center_screen.dart');

        self::assertStringContainsString('DropdownButtonFormField<String>', $screen);
        self::assertStringContainsString("'selected_size': _selectedSize.isEmpty", $screen);
        self::assertStringContainsString("subscription['selected_size']", $screen);
        self::assertStringContainsString("subscription['payment_status']", $screen);
        self::assertStringContainsString("subscription['deliveries']", $screen);
    }
}
